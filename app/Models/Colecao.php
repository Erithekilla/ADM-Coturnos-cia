<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Colecao extends Model
{
    use HasFactory;

    protected $table = 'colecao';
    protected $primaryKey = 'id_colecao';

    public $timestamps = false;

    protected $fillable = ['nome_colecao', 'descricao_colecao', 'data_inicio', 'data_fim'];

    public function produtos()
    {
        return $this->belongsToMany(Produto::class, 'colecao_has_produtos', 'id_colecao', 'produtos_id');
    }

    public function imagens()
    {
        return $this->hasMany(ImagensColecao::class, 'id_colecao', 'id_colecao');
    }
}

