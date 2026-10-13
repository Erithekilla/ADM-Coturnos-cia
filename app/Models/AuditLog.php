<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';

    protected $fillable = [
        'autor',
        'acao',
        'tabela',
        'registro_id',
        'ip',
        'detalhes',
        'admin_user_id',
        'action',
        'table_affected',
        'record_id',
        'old_values',
        'new_values',
        'ip_address',
    ];
}

