<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItensCarrinho extends Model
{
    use HasFactory;

    protected $table = 'itens_carrinho';
    protected $primaryKey = 'carrinho_id_carrinho';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['carrinho_id_carrinho', 'produtos_id', 'quantidade', 'tamanho'];

    public function carrinho()
    {
        return $this->belongsTo(Carrinho::class, 'carrinho_id_carrinho', 'id_carrinho');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

