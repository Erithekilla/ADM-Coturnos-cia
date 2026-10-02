<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrinho extends Model
{
    use HasFactory;

    protected $table = 'carrinho';
    protected $primaryKey = 'id_carrinho';
    
    public $timestamps = false;

    protected $fillable = ['usuario_id', 'criado_em', 'atualizado_em'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'id');
    }

    public function itens()
    {
        return $this->hasMany(ItensCarrinho::class, 'carrinho_id_carrinho', 'id_carrinho');
    }

}
