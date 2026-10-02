<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagensColecao extends Model
{
    use HasFactory;

    protected $table = 'imagens_colecao';
    protected $primaryKey = 'id_imagens_colecao';
    
    public $timestamps = false;

    protected $fillable = ['url_imagem', 'id_colecao'];

    public function colecao()
    {
        return $this->belongsTo(Colecao::class, 'id_colecao', 'id_colecao');
    }

}
