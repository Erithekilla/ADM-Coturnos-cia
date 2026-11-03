<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use App\Support\Hashid;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de auditoria administrativa — append-only.
 *
 * Registros só podem ser inseridos. Qualquer tentativa de UPDATE ou DELETE
 * via Eloquent lança exceção, e a API não expõe rotas para isso.
 * Gravação centralizada em App\Services\AuditLogger (com sanitização de PII).
 */
class AuditLog extends Model
{
    use HasHashid {
        toArray as hashidToArray;
    }

    protected $table = 'audit_logs';

    /** created_at é gravado explicitamente pelo AuditLogger. */
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Registros de auditoria são imutáveis (append-only).');
        });

        static::deleting(function () {
            throw new \LogicException('Registros de auditoria não podem ser excluídos.');
        });
    }

    public function hashidForeignKeys(): array
    {
        return [
            'admin_user_id' => User::class,
        ];
    }

    /**
     * ID público do registro afetado. O namespace do Hashid é o nome da tabela,
     * então record_id sai igual ao ID que o próprio recurso expõe na API.
     */
    public function getRecordHashidAttribute(): ?string
    {
        $recordId = (int) ($this->attributes['record_id'] ?? 0);
        $table = (string) ($this->attributes['table_affected'] ?? '');

        return ($recordId > 0 && $table !== '') ? Hashid::encode($recordId, $table) : null;
    }

    public function getAutorAttribute() { return $this->attributes['actor_label'] ?? $this->attributes['autor'] ?? null; }
    public function getDetalhesAttribute() { return $this->attributes['description'] ?? $this->attributes['detalhes'] ?? null; }
    public function getAcaoAttribute() { return $this->attributes['action'] ?? $this->attributes['acao'] ?? null; }
    public function getTabelaAttribute() { return $this->attributes['table_affected'] ?? $this->attributes['tabela'] ?? null; }
    public function getRegistroIdAttribute() { return $this->attributes['record_id'] ?? $this->attributes['registro_id'] ?? null; }
    public function getIpAttribute() { return $this->attributes['ip_address'] ?? $this->attributes['ip'] ?? null; }

    public function toArray()
    {
        $attributes = $this->hashidToArray();

        if (array_key_exists('record_id', $attributes)) {
            $attributes['record_id'] = $this->record_hashid;
        }

        return $attributes;
    }
}
