<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

class Pagamento extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'pagamento';
    protected $primaryKey = 'id_compra';

    public $timestamps = false;

    protected $fillable = ['data_pagamento', 'pedido_id', 'metodo_pagamento', 'status', 'valor'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'pedido_id' => Pedido::class,
        ];
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }
}

