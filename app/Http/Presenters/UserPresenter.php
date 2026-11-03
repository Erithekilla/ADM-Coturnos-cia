<?php

namespace App\Http\Presenters;

use App\Models\User;

class UserPresenter
{
    public static function listItem(User $u): array
    {
        $address = $u->relationLoaded('addresses') ? $u->addresses->first() : ($u->relationLoaded('enderecos') ? $u->enderecos->first() : null);
        $isAtivo = strtolower((string) ($u->status ?? 'ativo')) !== 'inativo';

        return [
            'id' => $u->hashid,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'firstName' => $u->first_name,
            'lastName' => $u->last_name,
            'nome' => $u->first_name,
            'sobrenome' => $u->last_name,
            'email' => $u->email,
            'cpf' => $u->maskedCpf(),
            'hasCpf' => !empty($u->getRawOriginal('cpf')),
            'phone' => self::formatPhone($u->phone),
            'zip_code' => $address->zip_code ?? '',
            'cep' => $address->zip_code ?? '',
            'street' => $address->street ?? '',
            'sreet' => $address->street ?? '',
            'number' => $address->number ?? '',
            'sreet_number' => $address->number ?? '',
            'neighborhood' => $address->neighborhood ?? '',
            'block' => $address->neighborhood ?? '',
            'city' => $address->city ?? '',
            'state' => $address->state ?? '',
            'type' => $u->isAdmin() ? 'Administrador' : 'Cliente',
            'role' => $u->role,
            'provider' => ucfirst($u->provider ?? 'Local'),
            'status' => $isAtivo ? 'ativo' : 'inativo',
            'statusLabel' => $isAtivo ? 'Ativo' : 'Inativo',
            'created_at' => $u->created_at ? $u->created_at->format('Y-m-d H:i:s') : '',
            'createdAt' => $u->created_at ? $u->created_at->format('d/m/Y') : '',
        ];
    }

    public static function detail(User $u): array
    {
        return array_merge(self::listItem($u), [
            'cpf' => self::formatCpf($u->cpf),
            'phone' => self::formatPhone($u->phone),
            'birth_date' => $u->birth_date ? substr((string) $u->birth_date, 0, 10) : null,
        ]);
    }

    public static function formatCpf(?string $cpf): string
    {
        $d = User::cleanCpf($cpf) ?? '';

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

