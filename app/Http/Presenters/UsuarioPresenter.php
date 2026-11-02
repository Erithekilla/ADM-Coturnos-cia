<?php

namespace App\Http\Presenters;

use App\Models\Usuario;

/**
 * Formato de usuário consumido pelo painel administrativo.
 * - listItem(): para listagens em massa — CPF sempre mascarado.
 * - detail():   para a ficha individual — CPF/telefone/nascimento descriptografados
 *               (o acesso é registrado na auditoria pelo controller).
 */
class UsuarioPresenter
{
    public static function listItem(Usuario $u): array
    {
        $end = $u->relationLoaded('enderecos') ? $u->enderecos->first() : null;
        $isAtivo = strtolower((string) ($u->status ?? 'ativo')) !== 'inativo';

        return [
            'id' => $u->hashid,
            'nome' => $u->nome,
            'sobrenome' => $u->sobrenome,
            'firstName' => $u->nome,
            'lastName' => $u->sobrenome,
            'email' => $u->email,
            'cpf' => $u->maskedCpf(),
            'hasCpf' => !empty($u->getRawOriginal('cpf')),
            'phone' => self::formatPhone($u->telefone),
            'cep' => $end->cep ?? '',
            'sreet' => $end->rua ?? '',
            'sreet_number' => $end->numero ?? '',
            'block' => $end->bairro ?? '',
            'city' => $end->cidade ?? '',
            'state' => $end->estado ?? '',
            'type' => $u->isAdmin() ? 'Administrador' : 'Cliente',
            'role' => $u->role,
            'provider' => ucfirst($u->provider ?? 'Local'),
            'status' => $isAtivo ? 'ativo' : 'inativo',
            'statusLabel' => $isAtivo ? 'Ativo' : 'Inativo',
            'createdAt' => $u->data_criacao ? date('d/m/Y', strtotime($u->data_criacao)) : '',
        ];
    }

    public static function detail(Usuario $u): array
    {
        return array_merge(self::listItem($u), [
            'cpf' => self::formatCpf($u->cpf),
            'phone' => self::formatPhone($u->telefone),
            'birth_date' => $u->data_nascimento ? substr((string) $u->data_nascimento, 0, 10) : null,
        ]);
    }

    public static function formatCpf(?string $cpf): string
    {
        $d = Usuario::cleanCpf($cpf) ?? '';

        return strlen($d) === 11
            ? substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2)
            : $d;
    }

    public static function formatPhone(?string $phone): string
    {
        $d = preg_replace('/\D/', '', (string) $phone);

        return match (strlen($d)) {
            11 => '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7),
            10 => '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6),
            default => $d,
        };
    }
}
