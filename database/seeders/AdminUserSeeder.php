<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        Usuario::updateOrCreate(
            ['email' => 'admin@coturnos.com.br'],
            [
                'nome' => 'Administrador',
                'sobrenome' => 'Coturnos & Cia',
                'cpf' => '000.000.000-00',
                'senha' => Hash::make('admin123'),
                'provider' => 'local',
                'role' => 'admin',
                'status' => 'ativo',
                'excluido' => 'N',
                'data_criacao' => now(),
            ]
        );

        if (class_exists(User::class)) {
            User::updateOrCreate(
                ['email' => 'admin@coturnos.com.br'],
                [
                    'name' => 'Administrador Coturnos & Cia',
                    'password' => Hash::make('admin123'),
                ]
            );
        }
    }
}

