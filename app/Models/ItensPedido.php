<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItensPedido extends Model
{
    use HasFactory;

    protected $table = 'itens_pedidos';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['quantidade', 'tamanho', 'preco_unitario', 'produtos_id', 'pedido_id'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

