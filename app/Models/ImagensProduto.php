<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class ImagensProduto extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'imagens_produto';
    protected $primaryKey = 'id_imagens_produto';

    public $timestamps = false;

    protected $fillable = ['url_imagem', 'produtos_id'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'produtos_id' => Produto::class,
        ];
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

