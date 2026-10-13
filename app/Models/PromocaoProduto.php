<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromocaoProduto extends Model
{
    use HasFactory;

    protected $table = 'promocao_produtos';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['valor_promo', 'data_inicio', 'data_fim', 'ativo', 'precos_id_precos'];

    public function preco()
    {
        return $this->belongsTo(Preco::class, 'precos_id_precos', 'id_precos');
    }
}

