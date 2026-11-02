<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class HistoricoPedido extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'historico_pedido';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['status', 'pedido_id', 'data_status', 'observacao', 'historico_pedidocol'];

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

