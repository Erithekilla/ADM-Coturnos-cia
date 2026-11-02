<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, HasHashid;

    protected $table = 'usuario';
    protected $primaryKey = 'id';

    public $timestamps = false;

    /** A senha fica na coluna `senha` (tabela legada). */
    protected $authPasswordName = 'senha';

    /** A tabela não possui remember_token (autenticação é stateless via Bearer). */
    protected $rememberTokenName = '';

    /**
     * Proteção de Mass Assignment: `role`, `status`, `excluido` e `cpf_hash`
     * NUNCA entram aqui. Só podem ser alterados com forceFill() em fluxos
     * administrativos controlados (ver UserPrivilegeService).
     */
    protected $fillable = [
        'nome',
        'sobrenome',
        'cpf',
        'telefone',
        'data_nascimento',
        'email',
        'data_criacao',
        'senha',
        'google_id',
        'provider',
    ];

    /** Campos blindados contra exposição em respostas JSON. */
    protected $hidden = [
        'senha',
        'password',
        'remember_token',
        'cpf_hash',
        'cpf',             // descriptografado apenas sob demanda, nunca em listagens em massa
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'cpf'             => SafeEncrypted::class,
            'telefone'        => SafeEncrypted::class,
            'data_nascimento' => SafeEncrypted::class,
            'status'          => 'string',
            'role'            => 'string',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Usuario $usuario) {
            if ($usuario->isDirty('cpf')) {
                $limpo = self::cleanCpf($usuario->cpf);
                if ($limpo !== $usuario->cpf) {
                    $usuario->cpf = $limpo;
                }
                $usuario->cpf_hash = self::generateCpfHash($limpo);
            }
        });

        // 4.4 — Imutabilidade do identificador fiscal.
        static::updating(function (Usuario $usuario) {
            if (!$usuario->isDirty('cpf')) {
                return;
            }

            $original = self::cleanCpf($usuario->getOriginal('cpf'));
            $novo = self::cleanCpf($usuario->cpf);

            if ($original === null || $original === '') {
                return; // primeiro preenchimento permitido
            }

            if ($original === $novo) {
                // Mesmo CPF re-cifrado com novo IV: mantém o valor original intacto.
                $usuario->restoreRawAttribute('cpf');
                return;
            }

            throw new \InvalidArgumentException('Violação de integridade: o CPF não pode ser alterado após o registro inicial.');
        });
    }

    protected function restoreRawAttribute(string $key): void
    {
        $this->attributes[$key] = $this->getRawOriginal($key);
    }

    public static function cleanCpf(?string $cpf): ?string
    {
        if ($cpf === null) {
            return null;
        }

        $limpo = preg_replace('/\D/', '', $cpf);

        return $limpo === '' ? null : $limpo;
    }

    /** 4.2 — Blind Index HMAC-SHA256 (determinístico, não reversível sem APP_KEY). */
    public static function generateCpfHash(?string $cpf): ?string
    {
        $clean = self::cleanCpf($cpf);

        if (empty($clean)) {
            return null;
        }

        return hash_hmac('sha256', $clean, (string) config('app.key'));
    }

    public static function findByCpf(?string $cpf): ?self
    {
        $hash = self::generateCpfHash($cpf);

        return $hash ? static::where('cpf_hash', $hash)->first() : null;
    }

    /** CPF mascarado para listagens: ***.456.789-** */
    public function maskedCpf(): string
    {
        $cpf = self::cleanCpf($this->cpf);

        if (!$cpf || strlen($cpf) !== 11) {
            return $cpf ? str_repeat('*', strlen($cpf)) : '';
        }

        return '***.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-**';
    }

    public function isAdmin(): bool
    {
        return strtolower((string) $this->role) === 'admin';
    }

    public function isActive(): bool
    {
        $status = strtolower((string) ($this->status ?? 'ativo'));
        $excluido = strtoupper(trim((string) ($this->excluido ?? 'N')));

        return $status === 'ativo' && $excluido !== 'S';
    }

    public function enderecos()
    {
        return $this->hasMany(Endereco::class, 'usuario_id', 'id');
    }

    public function carrinhos()
    {
        return $this->hasMany(Carrinho::class, 'usuario_id', 'id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'usuario_id', 'id');
    }
}
