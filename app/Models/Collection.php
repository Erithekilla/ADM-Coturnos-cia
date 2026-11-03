<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $table = 'collections';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        // Backward-compatible
        'nome_colecao',
        'descricao_colecao',
        'data_inicio',
        'data_fim',
    ];

    protected static function booted(): void
    {
        static::saving(function (Collection $c) {
            if (isset($c->attributes['nome_colecao']) && !isset($c->attributes['name'])) {
                $c->attributes['name'] = $c->attributes['nome_colecao'];
                unset($c->attributes['nome_colecao']);
            }
            if (isset($c->attributes['descricao_colecao']) && !isset($c->attributes['description'])) {
                $c->attributes['description'] = $c->attributes['descricao_colecao'];
                unset($c->attributes['descricao_colecao']);
            }
            if (isset($c->attributes['data_inicio']) && !isset($c->attributes['start_date'])) {
                $c->attributes['start_date'] = $c->attributes['data_inicio'];
                unset($c->attributes['data_inicio']);
            }
            if (isset($c->attributes['data_fim']) && !isset($c->attributes['end_date'])) {
                $c->attributes['end_date'] = $c->attributes['data_fim'];
                unset($c->attributes['data_fim']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getNomeColecaoAttribute() { return $this->name; }
    public function setNomeColecaoAttribute($v) { $this->attributes['name'] = $v; }

    public function getDescricaoColecaoAttribute() { return $this->description; }
    public function setDescricaoColecaoAttribute($v) { $this->attributes['description'] = $v; }

    public function getDataInicioAttribute() { return $this->start_date; }
    public function setDataInicioAttribute($v) { $this->attributes['start_date'] = $v; }

    public function getDataFimAttribute() { return $this->end_date; }
    public function setDataFimAttribute($v) { $this->attributes['end_date'] = $v; }

    // --- Relationships ---
    public function products()
    {
        return $this->belongsToMany(Product::class, 'collection_products', 'collection_id', 'product_id');
    }

    public function produtos()
    {
        return $this->products();
    }

    public function images()
    {
        return $this->hasMany(CollectionImage::class, 'collection_id', 'id');
    }

    public function imagens()
    {
        return $this->images();
    }
}

