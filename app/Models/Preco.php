<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Model;

class Preco extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'precos';
    protected $primaryKey = 'id_precos';

    public $timestamps = false;

    protected $fillable = ['preco', 'alteracao', 'produtos_id'];

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

    public function promocoes()
    {
        return $this->hasMany(PromocaoProduto::class, 'precos_id_precos', 'id_precos');
    }
}

