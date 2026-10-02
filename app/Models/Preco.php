<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Preco extends Model
{
    use HasFactory;

    protected $table = 'precos';
    protected $primaryKey = 'id_precos';
    
    public $timestamps = false;

    protected $fillable = ['preco', 'alteracao', 'produtos_id'];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produtos_id', 'id');
    }

    public function promocoes()
    {
        return $this->hasMany(PromocaoProduto::class, 'precos_id_precos', 'id_precos');
    }

}
