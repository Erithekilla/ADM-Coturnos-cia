<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Nunca use senha fixa no código: defina ADMIN_SEED_PASSWORD no .env
        // ou deixe vazio para gerar uma senha aleatória exibida no terminal.
        $senha = (string) env('ADMIN_SEED_PASSWORD', '');
        $gerada = false;
        if (strlen($senha) < 8) {
            $senha = Str::password(16, symbols: false);
            $gerada = true;
        }

        $admin = Usuario::firstOrNew(['email' => 'admin@coturnos.com.br']);
        $novo = !$admin->exists;

        $admin->fill([
            'nome' => 'Administrador',
            'sobrenome' => 'Coturnos & Cia',
            'provider' => 'local',
        ]);

        if ($novo) {
            $admin->fill(['data_criacao' => now()]);
        }

        if ($novo || env('ADMIN_SEED_PASSWORD')) {
            $admin->senha = Hash::make($senha);
        }

        // Campos de privilégio fora do $fillable: atribuição explícita.
        $admin->forceFill(['role' => 'admin', 'status' => 'ativo', 'excluido' => 'N'])->save();

        if ($novo && $gerada && $this->command) {
            $this->command->warn("Admin criado: admin@coturnos.com.br / senha: {$senha}");
            $this->command->warn('Guarde esta senha agora: ela não será exibida novamente.');
        }

        if (class_exists(User::class) && \Illuminate\Support\Facades\Schema::hasTable('users')
            && \Illuminate\Support\Facades\Schema::hasColumn('users', 'name')) {
            $user = User::firstOrNew(['email' => 'admin@coturnos.com.br']);
            if (!$user->exists) {
                $user->fill(['name' => 'Administrador Coturnos & Cia', 'password' => Hash::make($senha)])->save();
            }
        }
    }
}
