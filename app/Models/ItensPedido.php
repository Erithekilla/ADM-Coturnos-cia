<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class ItensPedido extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'itens_pedidos';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['quantidade', 'tamanho', 'preco_unitario', 'produtos_id', 'pedido_id'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'produtos_id' => Produto::class,
            'pedido_id' => Pedido::class,
        ];
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

