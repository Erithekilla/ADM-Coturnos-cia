<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

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
    public const ROLES = ['admin', 'cliente'];

    public function changeRole(Usuario $actor, Usuario $target, string $newRole): Usuario
    {
        $newRole = strtolower(trim($newRole));
        if (!in_array($newRole, self::ROLES, true)) {
            throw new PrivilegeException("Perfil inválido: '{$newRole}'. Use 'admin' ou 'cliente'.", 422);
        }

        $currentRole = strtolower((string) $target->role);
        if ($currentRole === $newRole) {
            return $target;
        }

        $this->guardSelf($actor, $target, 'alterar o próprio perfil de acesso');

        if ($currentRole === 'admin' && $target->isActive()) {
            $this->guardLastAdmin($target, 'despromover');
        }

        return DB::transaction(function () use ($actor, $target, $currentRole, $newRole) {
            $target->forceFill(['role' => $newRole])->save();

            // Tokens antigos carregam abilities do papel anterior: força novo login.
            $target->tokens()->delete();

            AuditLogger::record('TOGGLE_USER_ROLE', 'usuario', $target->getKey(), ['role' => $currentRole], ['role' => $newRole], actor: $actor);

            return $target;
        });
    }

    public function changeStatus(Usuario $actor, Usuario $target, string $newStatus): Usuario
    {
        $newStatus = strtolower(trim($newStatus)) === 'inativo' ? 'inativo' : 'ativo';
        $currentStatus = strtolower((string) ($target->status ?: 'ativo'));

        if ($currentStatus === $newStatus) {
            return $target;
        }

        $this->guardSelf($actor, $target, 'alterar o status da própria conta');

        if ($newStatus === 'inativo' && $target->isAdmin() && $target->isActive()) {
            $this->guardLastAdmin($target, 'desativar');
        }

        return DB::transaction(function () use ($actor, $target, $currentStatus, $newStatus) {
            $target->forceFill(['status' => $newStatus])->save();

            if ($newStatus === 'inativo') {
                $target->tokens()->delete();
            }

            AuditLogger::record('TOGGLE_USER_STATUS', 'usuario', $target->getKey(), ['status' => $currentStatus], ['status' => $newStatus], actor: $actor);

            return $target;
        });
    }

    public function softDelete(Usuario $actor, Usuario $target): void
    {
        $this->guardSelf($actor, $target, 'excluir a própria conta');

        if ($target->isAdmin() && $target->isActive()) {
            $this->guardLastAdmin($target, 'excluir');
        }

        DB::transaction(function () use ($actor, $target) {
            $old = ['status' => $target->status, 'excluido' => $target->excluido];

            $target->forceFill(['excluido' => 'S', 'status' => 'inativo'])->save();
            $target->tokens()->delete();

            foreach ($target->carrinhos as $carrinho) {
                DB::table('itens_carrinho')->where('carrinho_id_carrinho', $carrinho->id_carrinho)->delete();
            }

            AuditLogger::record('USER_DELETE', 'usuario', $target->getKey(), $old, ['status' => 'inativo', 'excluido' => 'S'], actor: $actor);
        });
    }

    private function guardSelf(Usuario $actor, Usuario $target, string $acao): void
    {
        if ((int) $actor->getKey() === (int) $target->getKey()) {
            throw new PrivilegeException("Por segurança, você não pode {$acao}. Peça a outro administrador.", 403);
        }
    }

    private function guardLastAdmin(Usuario $target, string $acao): void
    {
        $outrosAdminsAtivos = Usuario::query()
            ->whereRaw('LOWER(role) = ?', ['admin'])
            ->where(function ($q) {
                $q->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['ativo']);
            })
            ->where(function ($q) {
                $q->whereNull('excluido')->orWhereRaw("TRIM(UPPER(COALESCE(excluido, 'N'))) != 'S'");
            })
            ->where('id', '!=', $target->getKey())
            ->count();

        if ($outrosAdminsAtivos === 0) {
            throw new PrivilegeException("Não é possível {$acao} o último administrador ativo do sistema.", 422);
        }
    }
}
