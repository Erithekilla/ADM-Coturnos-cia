<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Endereco extends Model
{
    use HasFactory;

    protected $table = 'endereco';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['cep', 'rua', 'bairro', 'cidade', 'estado', 'numero', 'complemento', 'padrao', 'usuario_id'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'endereco_id', 'id');
    }
}

