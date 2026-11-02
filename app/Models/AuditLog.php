<?php

namespace App\Models;

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
}
