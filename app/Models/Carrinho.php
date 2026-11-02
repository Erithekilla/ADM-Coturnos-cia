<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class Carrinho extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'carrinho';
    protected $primaryKey = 'id_carrinho';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'criado_em', 'atualizado_em'];

    /** IDs públicos ofuscados também nas chaves estrangeiras do JSON. */
    public function hashidForeignKeys(): array
    {
        return [
            'usuario_id' => Usuario::class,
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    public function itens()
    {
        return $this->hasMany(ItensCarrinho::class, 'carrinho_id_carrinho', 'id_carrinho');
    }
}

