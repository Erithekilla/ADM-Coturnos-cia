<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TamanhoProduto extends Model
{
    use HasFactory;

    protected $table = 'tamanho_produtos';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['produtos_id', 'tamanho', 'quantidade'];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }
}

