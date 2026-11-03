<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create
                            {email=admin@coturnos.com.br : E-mail do administrador}
                            {--password=admin123 : Senha do administrador}
                            {--name=Administrador : Primeiro nome}
                            {--lastname=Coturnos & Cia : Sobrenome}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cria ou atualiza um usuário administrador com senha usando Laravel Eloquent e Hash';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $password = (string) $this->option('password');
        $firstName = (string) $this->option('name');
        $lastName = (string) $this->option('lastname');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("E-mail inválido: {$email}");
            return self::FAILURE;
        }

        if (strlen($password) < 6) {
            $this->error("A senha deve ter pelo menos 6 caracteres.");
            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $isNew = !$user->exists;

        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->password = Hash::make($password);
        $user->provider = 'local';

        if ($isNew) {
            $user->created_at = now();
        }

        $user->forceFill([
            'role' => 'admin',
            'status' => 'ativo',
            'is_deleted' => 'N',
        ])->save();

        $action = $isNew ? 'criado' : 'atualizado';

        $this->info("Administrador {$action} com sucesso!");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['ID (Público / Hashid)', $user->hashid],
                ['Nome', "{$user->first_name} {$user->last_name}"],
                ['E-mail', $user->email],
                ['Senha', $password],
                ['Papel (Role)', $user->role],
                ['Status', $user->status],
            ]
        );

        return self::SUCCESS;
    }
}
