<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $senha = (string) env('ADMIN_SEED_PASSWORD', '');
        $gerada = false;
        if (strlen($senha) < 8) {
            $senha = Str::password(16, symbols: false);
            $gerada = true;
        }

        $admin = User::firstOrNew(['email' => 'admin@coturnos.com.br']);
        $novo = !$admin->exists;

        $admin->fill([
            'first_name' => 'Administrador',
            'last_name' => 'Coturnos & Cia',
            'provider' => 'local',
        ]);

        if ($novo) {
            $admin->created_at = now();
        }

        if ($novo || env('ADMIN_SEED_PASSWORD')) {
            $admin->password = Hash::make($senha);
        }

        $admin->forceFill([
            'role' => 'admin',
            'status' => 'ativo',
            'is_deleted' => 'N',
        ])->save();

        if ($novo && $gerada && $this->command) {
            $this->command->warn("Admin criado: admin@coturnos.com.br / senha: {$senha}");
            $this->command->warn('Guarde esta senha agora: ela não será exibida novamente.');
        }
    }
}
