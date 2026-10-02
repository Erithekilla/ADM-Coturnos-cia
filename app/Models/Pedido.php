<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory;

    protected $table = 'pedido';
    protected $primaryKey = 'id';
    
    public $timestamps = false;

    protected $fillable = ['valor_total', 'status', 'data_pedido', 'usuario_id', 'endereco_id'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    public function endereco()
    {
        return $this->belongsTo(Endereco::class, 'endereco_id', 'id');
    }

    public function itens()
    {
        return $this->hasMany(ItensPedido::class, 'pedido_id', 'id');
    }

    public function pagamentos()
    {
        return $this->hasMany(Pagamento::class, 'pedido_id', 'id');
    }

    public function historicos()
    {
        return $this->hasMany(HistoricoPedido::class, 'pedido_id', 'id');
    }

}
