<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2.3 — Prevenção contra escalonamento de privilégios.
 *
 * Toda mudança de `role`, `status` ou exclusão lógica passa por aqui:
 * - o administrador não altera o próprio papel/status nem se exclui
 *   (a ação precisa ser feita por OUTRO administrador autenticado);
 * - o último administrador ativo nunca pode ser despromovido, desativado ou excluído;
 * - ao perder privilégio ou acesso, todos os tokens do alvo são revogados na hora.
 */
class UserPrivilegeService
{
    public const ROLES = ['admin', 'cliente', 'customer'];

    public function changeRole(User $actor, User $target, string $newRole): User
    {
        $newRole = strtolower(trim($newRole));
        if (!in_array($newRole, self::ROLES, true)) {
            throw new PrivilegeException("Perfil inválido: '{$newRole}'. Use 'admin' ou 'cliente'.", 422);
        }

        $currentRole = strtolower((string) $target->role);
        $normalizedNewRole = ($newRole === 'cliente') ? 'customer' : $newRole;
        $normalizedCurrentRole = ($currentRole === 'cliente') ? 'customer' : $currentRole;

        if ($normalizedCurrentRole === $normalizedNewRole) {
            return $target;
        }

        $this->guardSelf($actor, $target, 'alterar o próprio perfil de acesso');

        if ($normalizedCurrentRole === 'admin' && $target->isActive()) {
            $this->guardLastAdmin($target, 'despromover');
        }

        return DB::transaction(function () use ($actor, $target, $currentRole, $normalizedNewRole) {
            $target->forceFill(['role' => $normalizedNewRole])->save();

            // Tokens antigos carregam abilities do papel anterior: força novo login.
            $target->tokens()->delete();

            AuditLogger::record('TOGGLE_USER_ROLE', 'users', $target->getKey(), ['role' => $currentRole], ['role' => $normalizedNewRole], actor: $actor);

            return $target;
        });
    }

    public function changeStatus(User $actor, User $target, string $newStatus): User
    {
        $newStatus = (strtolower(trim($newStatus)) === 'inativo') ? 'inativo' : 'ativo';
        $currentStatus = $target->isActive() ? 'ativo' : 'inativo';

        if ($currentStatus === $newStatus) {
            return $target;
        }

        $this->guardSelf($actor, $target, 'alterar o status da própria conta');

        if ($newStatus === 'inativo' && $target->isAdmin() && $target->isActive()) {
            $this->guardLastAdmin($target, 'desativar');
        }

        return DB::transaction(function () use ($actor, $target, $currentStatus, $newStatus) {
            $fields = [];
            if (Schema::hasColumn('users', 'active')) {
                $fields['active'] = ($newStatus === 'ativo');
            }
            if (Schema::hasColumn('users', 'status')) {
                $fields['status'] = $newStatus;
            }
            $target->forceFill($fields)->save();

            if ($newStatus === 'inativo') {
                $target->tokens()->delete();
            }

            AuditLogger::record('TOGGLE_USER_STATUS', 'users', $target->getKey(), ['status' => $currentStatus], ['status' => $newStatus], actor: $actor);

            return $target;
        });
    }

    public function softDelete(User $actor, User $target): void
    {
        $this->guardSelf($actor, $target, 'excluir a própria conta');

        if ($target->isAdmin() && $target->isActive()) {
            $this->guardLastAdmin($target, 'excluir');
        }

        DB::transaction(function () use ($actor, $target) {
            $old = ['status' => $target->status, 'is_deleted' => $target->is_deleted];

            $fields = [];
            if (Schema::hasColumn('users', 'active')) {
                $fields['active'] = false;
            }
            if (Schema::hasColumn('users', 'status')) {
                $fields['status'] = 'inativo';
            }
            if (Schema::hasColumn('users', 'is_deleted')) {
                $fields['is_deleted'] = 'S';
            }
            $target->forceFill($fields)->save();
            $target->delete(); // SoftDeletes
            $target->tokens()->delete();

            $cartItemsTable = Schema::hasTable('cart_items') ? 'cart_items' : 'itens_carrinho';
            $cartCol = Schema::hasColumn($cartItemsTable, 'cart_id') ? 'cart_id' : 'carrinho_id_carrinho';

            foreach ($target->carts as $cart) {
                DB::table($cartItemsTable)->where($cartCol, $cart->getKey())->delete();
            }

            AuditLogger::record('USER_DELETE', 'users', $target->getKey(), $old, ['status' => 'inativo', 'is_deleted' => 'S'], actor: $actor);
        });
    }

    private function guardSelf(User $actor, User $target, string $acao): void
    {
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            throw new PrivilegeException("Por segurança, você não pode {$acao}. Peça a outro administrador.", 403);
        }
    }

    private function guardLastAdmin(User $target, string $acao): void
    {
        $outrosAdminsAtivos = User::query()
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->where(function ($q) {
                if (Schema::hasColumn('users', 'active')) {
                    $q->where('active', 1);
                } else {
                    $q->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['ativo']);
                }
            })
            ->where(function ($q) {
                if (Schema::hasColumn('users', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
                if (Schema::hasColumn('users', 'is_deleted')) {
                    $q->whereRaw("TRIM(UPPER(COALESCE(is_deleted, 'N'))) != 'S'");
                }
            })
            ->where('id', '!=', $target->getKey())
            ->count();

        if ($outrosAdminsAtivos === 0) {
            throw new PrivilegeException("Não é possível {$acao} o último administrador ativo do sistema.", 422);
        }
    }
}
