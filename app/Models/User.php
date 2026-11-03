<?php

namespace App\Models;

use App\Casts\SafeEncrypted;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasHashid, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id';

    /**
     * Mass Assignment Protection: role, status, is_deleted and cpf_hash
     * are never mass-assignable directly from client requests.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'cpf',
        'phone',
        'birth_date',
        'email',
        'password',
        'google_id',
        'provider',
        // Backward-compatible input keys
        'nome',
        'sobrenome',
        'telefone',
        'data_nascimento',
        'senha',
        'data_criacao',
    ];

    /** Sensitive fields hidden from JSON serialization. */
    protected $hidden = [
        'password',
        'senha',
        'remember_token',
        'cpf_hash',
        'cpf',
        'google_id',
    ];

    protected function casts(): array
    {
        return [
            'cpf'        => SafeEncrypted::class,
            'phone'      => SafeEncrypted::class,
            'birth_date' => SafeEncrypted::class,
            'status'     => 'string',
            'role'       => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Synchronize backward-compatible attributes if set
            if (isset($user->attributes['nome']) && !isset($user->attributes['first_name'])) {
                $user->attributes['first_name'] = $user->attributes['nome'];
                unset($user->attributes['nome']);
            }
            if (isset($user->attributes['sobrenome']) && !isset($user->attributes['last_name'])) {
                $user->attributes['last_name'] = $user->attributes['sobrenome'];
                unset($user->attributes['sobrenome']);
            }
            if (isset($user->attributes['telefone']) && !isset($user->attributes['phone'])) {
                $user->attributes['phone'] = $user->attributes['telefone'];
                unset($user->attributes['telefone']);
            }
            if (isset($user->attributes['data_nascimento']) && !isset($user->attributes['birth_date'])) {
                $user->attributes['birth_date'] = $user->attributes['data_nascimento'];
                unset($user->attributes['data_nascimento']);
            }
            if (isset($user->attributes['senha']) && !isset($user->attributes['password'])) {
                $user->attributes['password'] = $user->attributes['senha'];
                unset($user->attributes['senha']);
            }
            if (isset($user->attributes['data_criacao']) && !isset($user->attributes['created_at'])) {
                $user->attributes['created_at'] = $user->attributes['data_criacao'];
                unset($user->attributes['data_criacao']);
            }

            if ($user->isDirty('cpf')) {
                $clean = self::cleanCpf($user->cpf);
                if ($clean !== $user->cpf) {
                    $user->cpf = $clean;
                }
                $user->cpf_hash = self::generateCpfHash($clean);
            }
        });

        static::updating(function (User $user) {
            if (!$user->isDirty('cpf')) {
                return;
            }

            $original = self::cleanCpf($user->getOriginal('cpf'));
            $new = self::cleanCpf($user->cpf);

            if ($original === null || $original === '') {
                return;
            }

            if ($original === $new) {
                $user->restoreRawAttribute('cpf');
                return;
            }

            throw new \InvalidArgumentException('Integrity violation: CPF cannot be altered after initial registration.');
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

        $clean = preg_replace('/\D/', '', $cpf);

        return $clean === '' ? null : $clean;
    }

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
        $isDeleted = strtoupper(trim((string) ($this->is_deleted ?? 'N')));

        return ($status === 'ativo' || $status === 'active') && $isDeleted !== 'S';
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getNomeAttribute() { return $this->first_name; }
    public function setNomeAttribute($value) { $this->attributes['first_name'] = $value; }

    public function getSobrenomeAttribute() { return $this->last_name; }
    public function setSobrenomeAttribute($value) { $this->attributes['last_name'] = $value; }

    public function getTelefoneAttribute() { return $this->phone; }
    public function setTelefoneAttribute($value) { $this->phone = $value; }

    public function getDataNascimentoAttribute() { return $this->birth_date; }
    public function setDataNascimentoAttribute($value) { $this->birth_date = $value; }

    public function getSenhaAttribute() { return $this->password; }
    public function setSenhaAttribute($value) { $this->attributes['password'] = $value; }

    public function getExcluidoAttribute() { return $this->is_deleted; }
    public function setExcluidoAttribute($value) { $this->attributes['is_deleted'] = $value; }

    public function getDataCriacaoAttribute() { return $this->created_at; }
    public function setDataCriacaoAttribute($value) { $this->attributes['created_at'] = $value; }

    // --- Relationships ---
    public function addresses()
    {
        return $this->hasMany(Address::class, 'user_id', 'id');
    }

    public function enderecos()
    {
        return $this->addresses();
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'user_id', 'id');
    }

    public function carrinhos()
    {
        return $this->carts();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }

    public function pedidos()
    {
        return $this->orders();
    }
}
