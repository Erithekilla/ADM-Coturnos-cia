<?php

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::dropIfExists('audit_logs');
    Schema::create('audit_logs', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('admin_user_id')->nullable();
        $t->string('action', 100)->nullable();
        $t->string('table_affected', 60)->nullable();
        $t->unsignedBigInteger('record_id')->nullable();
        $t->json('old_values')->nullable();
        $t->json('new_values')->nullable();
        $t->string('ip_address', 45)->nullable();
        $t->string('autor')->nullable();
        $t->string('acao', 100)->nullable();
        $t->string('tabela', 60)->nullable();
        $t->unsignedBigInteger('registro_id')->nullable();
        $t->string('ip', 45)->nullable();
        $t->text('detalhes')->nullable();
        $t->timestamp('created_at')->nullable();
    });
    AuditLogger::flushColumnCache();
});

function criarUsuario(string $role = 'cliente', string $status = 'ativo', array $extra = []): User
{
    $u = new User(array_merge([
        'first_name' => ucfirst($role),
        'last_name' => 'Teste',
        'email' => $role . '-' . uniqid() . '@teste.com',
        'password' => Hash::make('senha-forte-123'),
        'provider' => 'local',
    ], $extra));
    $u->forceFill(['role' => $role, 'status' => $status, 'is_deleted' => 'N'])->save();

    return $u;
}

function tokenDe(User $u, ?array $abilities = null): string
{
    return $u->createToken('teste', $abilities ?? ($u->isAdmin() ? ['admin'] : ['cliente']))->plainTextToken;
}

// ---------------------------------------------------------------------------
// 2.1 — As 4 barreiras do EnsureUserIsAdmin
// ---------------------------------------------------------------------------

test('barreira 1: sem token retorna 401', function () {
    $this->getJson('/api/v1/admin/logs')->assertStatus(401);
});

test('barreira 1: token inválido retorna 401', function () {
    $this->withToken('1|token-falso')->getJson('/api/v1/admin/logs')->assertStatus(401);
});

test('barreira 2: admin inativo retorna 403', function () {
    $admin = criarUsuario('admin', 'inativo');
    $this->withToken(tokenDe($admin))->getJson('/api/v1/admin/logs')->assertStatus(403);
});

test('barreira 3: cliente retorna 403', function () {
    $cliente = criarUsuario('cliente');
    $this->withToken(tokenDe($cliente))->getJson('/api/v1/admin/logs')->assertStatus(403);
});

test('barreira 4: admin com token sem ability admin retorna 403', function () {
    $admin = criarUsuario('admin');
    $this->withToken(tokenDe($admin, ['cliente']))->getJson('/api/v1/admin/logs')->assertStatus(403);
});

test('admin ativo com token admin passa', function () {
    $admin = criarUsuario('admin');
    $this->withToken(tokenDe($admin))->getJson('/api/v1/admin/logs')->assertOk();
});

// ---------------------------------------------------------------------------
// Login / logout
// ---------------------------------------------------------------------------

test('login de admin emite token com ability admin e logout o revoga', function () {
    $admin = criarUsuario('admin', extra: ['email' => 'chefe@teste.com']);

    $res = $this->postJson('/api/v1/auth/login', ['email' => 'chefe@teste.com', 'senha' => 'senha-forte-123'])
        ->assertOk()
        ->assertJsonPath('data.abilities', ['admin']);

    $token = $res->json('data.token');
    expect($res->json('data.usuario.id'))->toBe($admin->hashid);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('login com senha errada retorna 401 e é auditado', function () {
    criarUsuario('admin', extra: ['email' => 'chefe@teste.com']);

    $this->postJson('/api/v1/auth/login', ['email' => 'chefe@teste.com', 'senha' => 'errada'])->assertStatus(401);

    expect(AuditLog::where('action', 'AUTH_LOGIN_FAILED')->count())->toBe(1);
});

test('conta inativa não consegue logar', function () {
    criarUsuario('admin', 'inativo', ['email' => 'off@teste.com']);
    $this->postJson('/api/v1/auth/login', ['email' => 'off@teste.com', 'senha' => 'senha-forte-123'])->assertStatus(403);
});

// ---------------------------------------------------------------------------
// 3 — IDs ofuscados e anti-IDOR
// ---------------------------------------------------------------------------

test('listagem expõe IDs ofuscados, nunca inteiros', function () {
    $admin = criarUsuario('admin');

    $ids = $this->withToken(tokenDe($admin))->getJson('/api/v1/usuarios')->assertOk()->json('data.*.id');

    foreach ($ids as $id) {
        expect($id)->toBeString()->toMatch('/^[A-Za-z0-9_-]{22}$/');
    }
});

test('ID inteiro cru ou adulterado retorna 404', function () {
    $admin = criarUsuario('admin');
    $alvo = criarUsuario('cliente');
    $token = tokenDe($admin);

    $this->withToken($token)->getJson('/api/v1/usuarios/' . $alvo->id)->assertStatus(404);
    $this->withToken($token)->getJson('/api/v1/usuarios/' . strrev($alvo->hashid))->assertStatus(404);
    $this->withToken($token)->getJson('/api/v1/usuarios/' . $alvo->hashid)->assertOk();
});

test('ID público de um pedido não serve como ID de usuário', function () {
    $admin = criarUsuario('admin');
    $pedido = Order::create(['total_amount' => 10, 'status' => 'pendente', 'order_date' => now()]);

    // mesmo inteiro, namespace diferente
    $this->withToken(tokenDe($admin))->getJson('/api/v1/usuarios/' . $pedido->hashid)->assertStatus(404);
});

test('carrinho ignora usuario_id enviado pelo cliente (sem impersonação)', function () {
    $vitima = criarUsuario('cliente');

    $this->getJson('/api/v1/carrinho?usuario_id=' . $vitima->id)->assertStatus(401);
});

// ---------------------------------------------------------------------------
// 4 — Criptografia em repouso e Blind Index
// ---------------------------------------------------------------------------

test('CPF e telefone ficam cifrados no banco e o cpf_hash permite busca', function () {
    $admin = criarUsuario('admin');

    $this->withToken(tokenDe($admin))->postJson('/api/v1/usuarios', [
        'firstName' => 'Maria', 'lastName' => 'Silva', 'email' => 'maria@teste.com',
        'cpf' => '123.456.789-09', 'phone' => '(11) 98765-4321', 'type' => 'Cliente',
    ])->assertCreated()->assertJsonStructure(['senha_temporaria']);

    $raw = DB::table('users')->where('email', 'maria@teste.com')->first();
    expect($raw->cpf)->not->toContain('123')
        ->and($raw->phone)->not->toContain('98765')
        ->and($raw->cpf_hash)->toBe(User::generateCpfHash('12345678909'));

    $lista = $this->withToken(tokenDe($admin))->getJson('/api/v1/usuarios')->json('data');
    $maria = collect($lista)->firstWhere('email', 'maria@teste.com');
    expect($maria['cpf'])->toBe('***.456.789-**');

    $this->withToken(tokenDe($admin))->getJson('/api/v1/admin/usuarios/buscar-cpf?cpf=12345678909')
        ->assertOk()
        ->assertJsonPath('data.cpf', '123.456.789-09');

    expect(AuditLog::where('action', 'SENSITIVE_DATA_VIEW')->count())->toBe(1);
});

test('CPF não pode ser alterado depois de registrado', function () {
    $admin = criarUsuario('admin');
    $cliente = criarUsuario('cliente', extra: ['cpf' => '11122233344']);

    $this->withToken(tokenDe($admin))
        ->putJson('/api/v1/usuarios/' . $cliente->hashid, ['cpf' => '99988877766'])
        ->assertStatus(422);

    // valor mascarado da listagem é ignorado
    $this->withToken(tokenDe($admin))
        ->putJson('/api/v1/usuarios/' . $cliente->hashid, ['cpf' => '***.222.333-**', 'nome' => 'Novo'])
        ->assertOk();

    expect(User::find($cliente->id)->cpf)->toBe('11122233344');
});

test('editar usuário não redefine a senha', function () {
    $admin = criarUsuario('admin');
    $cliente = criarUsuario('cliente');
    $hashAntes = DB::table('users')->where('id', $cliente->id)->value('password');

    $this->withToken(tokenDe($admin))
        ->putJson('/api/v1/usuarios/' . $cliente->hashid, ['nome' => 'Outro', 'type' => 'Cliente'])
        ->assertOk();

    expect(DB::table('users')->where('id', $cliente->id)->value('password'))->toBe($hashAntes);
});

// ---------------------------------------------------------------------------
// 2.3 — Escalonamento de privilégios
// ---------------------------------------------------------------------------

test('role não é atribuível por mass assignment', function () {
    $u = new User(['first_name' => 'X', 'role' => 'admin', 'status' => 'ativo']);
    expect($u->role)->toBeNull();
});

test('admin não pode alterar o próprio papel', function () {
    $admin = criarUsuario('admin');
    criarUsuario('admin'); // existe outro admin

    $this->withToken(tokenDe($admin))
        ->patchJson('/api/v1/admin/usuarios/' . $admin->hashid . '/toggle-role')
        ->assertStatus(403);
});

test('admin pode despromover outro admin, mas o que sobrou não pode se despromover', function () {
    $ator = criarUsuario('admin');
    $alvo = criarUsuario('admin');

    $this->withToken(tokenDe($ator))
        ->patchJson('/api/v1/admin/usuarios/' . $alvo->hashid . '/toggle-role')
        ->assertOk();

    expect(User::find($alvo->id)->role)->toBe('cliente');

    // o ator agora é o único admin ativo e não consegue se auto-despromover
    $this->withToken(tokenDe($ator))
        ->patchJson('/api/v1/admin/usuarios/' . $ator->hashid . '/toggle-role')
        ->assertStatus(403);

    expect(User::find($ator->id)->role)->toBe('admin');
});

test('despromoção revoga os tokens do alvo', function () {
    $ator = criarUsuario('admin');
    $alvo = criarUsuario('admin');
    $tokenAlvo = tokenDe($alvo);

    $this->withToken(tokenDe($ator))
        ->patchJson('/api/v1/admin/usuarios/' . $alvo->hashid . '/toggle-role')
        ->assertOk();

    expect($alvo->tokens()->count())->toBe(0);

    $this->app['auth']->forgetGuards();
    $this->withToken($tokenAlvo)->getJson('/api/v1/admin/logs')->assertStatus(401);
});

// ---------------------------------------------------------------------------
// 2.4 — Auditoria imutável e sanitizada
// ---------------------------------------------------------------------------

test('auditoria grava old/new sanitizados e é append-only', function () {
    $ator = criarUsuario('admin');
    $alvo = criarUsuario('cliente');

    $this->withToken(tokenDe($ator))
        ->patchJson('/api/v1/admin/usuarios/' . $alvo->hashid . '/toggle-status')
        ->assertOk();

    $log = AuditLog::where('action', 'TOGGLE_USER_STATUS')->firstOrFail();
    expect($log->admin_user_id)->toBe($ator->id)
        ->and($log->old_values)->toBe(['status' => 'ativo'])
        ->and($log->new_values)->toBe(['status' => 'inativo']);

    expect(fn () => $log->update(['action' => 'X']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);

    $sanitizado = AuditLogger::sanitize(['senha' => 'x', 'cpf' => '123', 'email' => 'joao@x.com']);
    expect($sanitizado)->toBe(['senha' => '***', 'cpf' => '***', 'email' => 'j***@x.com']);
});

test('não existe rota de escrita para audit_logs', function () {
    $admin = criarUsuario('admin');
    $token = tokenDe($admin);

    $this->withToken($token)->deleteJson('/api/v1/admin/logs')->assertStatus(405);
    $this->withToken($token)->putJson('/api/v1/admin/logs')->assertStatus(405);
});
