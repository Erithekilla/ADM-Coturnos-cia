<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Gera o usuário administrador padrão do sistema.
     */
    public function run(): void
    {
        // 1. Cadastra/Atualiza na tabela 'usuario' (utilizada pela API e Front-end Admin)
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

        // 2. Garante compatibilidade caso o auth nativo do Laravel utilize o model User
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
