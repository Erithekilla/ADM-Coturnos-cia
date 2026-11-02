<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'pedido';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['valor_total', 'status', 'data_pedido', 'usuario_id', 'endereco_id'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'usuario_id' => Usuario::class,
            'endereco_id' => Endereco::class,
        ];
    }

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

