<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'produtos';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['tipo_do_produto', 'modelo', 'marca', 'descricao', 'ativo', 'cor', 'status', 'excluido'];

    public function imagens()
    {
        return $this->hasMany(ImagensProduto::class, 'produtos_id', 'id');
    }

    public function precos()
    {
        return $this->hasMany(Preco::class, 'produtos_id', 'id');
    }

    public function tamanhos()
    {
        return $this->hasMany(TamanhoProduto::class, 'produtos_id', 'id');
    }

    public function colecoes()
    {
        return $this->belongsToMany(Colecao::class, 'colecao_has_produtos', 'produtos_id', 'id_colecao');
    }

    public function itensCarrinho()
    {
        return $this->hasMany(ItensCarrinho::class, 'produtos_id', 'id');
    }

    public function itensPedidos()
    {
        return $this->hasMany(ItensPedido::class, 'produtos_id', 'id');
    }
}

