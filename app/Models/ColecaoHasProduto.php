<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class ColecaoHasProduto extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'colecao_has_produtos';
    protected $primaryKey = 'id_colecao';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['id_colecao', 'produtos_id'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'produtos_id' => Produto::class,
        ];
    }

    public function colecao()
    {
        return $this->belongsTo(Colecao::class, 'id_colecao', 'id_colecao');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

