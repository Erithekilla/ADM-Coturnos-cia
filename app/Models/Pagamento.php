<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pagamento extends Model
{
    use HasFactory;

    protected $table = 'pagamento';
    protected $primaryKey = 'id_compra';
    
    public $timestamps = false;

    protected $fillable = ['data_pagamento', 'pedido_id', 'metodo_pagamento', 'status', 'valor'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }

}
