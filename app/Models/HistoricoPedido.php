<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricoPedido extends Model
{
    use HasFactory;

    protected $table = 'historico_pedido';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['status', 'pedido_id', 'data_status', 'observacao', 'historico_pedidocol'];

    public function pedido()
    {
        return $this->belongsTo(Pedido::class, 'pedido_id', 'id');
    }
}

