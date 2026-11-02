<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class ItensCarrinho extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'itens_carrinho';
    protected $primaryKey = 'carrinho_id_carrinho';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['carrinho_id_carrinho', 'produtos_id', 'quantidade', 'tamanho'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'produtos_id' => Produto::class,
        ];
    }

    public function carrinho()
    {
        return $this->belongsTo(Carrinho::class, 'carrinho_id_carrinho', 'id_carrinho');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

