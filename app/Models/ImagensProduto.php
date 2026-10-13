<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagensProduto extends Model
{
    use HasFactory;

    protected $table = 'imagens_produto';
    protected $primaryKey = 'id_imagens_produto';

    public $timestamps = false;

    protected $fillable = ['url_imagem', 'produtos_id'];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

