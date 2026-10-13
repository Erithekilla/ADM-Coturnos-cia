<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuario';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['nome', 'sobrenome', 'cpf', 'data_nascimento', 'email', 'data_criacao', 'senha', 'google_id', 'provider', 'role', 'status', 'excluido'];
    protected $hidden = ['senha'];

    public function enderecos()
    {
        return $this->hasMany(Endereco::class, 'usuario_id', 'id');
    }

    public function carrinhos()
    {
        return $this->hasMany(Carrinho::class, 'usuario_id', 'id');
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class, 'usuario_id', 'id');
    }
}

