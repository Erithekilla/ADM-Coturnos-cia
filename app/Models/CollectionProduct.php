<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionProduct extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'collection_products';
    protected $primaryKey = 'collection_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'collection_id',
        'product_id',
        // Backward-compatible
        'id_colecao',
        'produtos_id',
    ];

    public function hashidForeignKeys(): array
    {
        return [
            'product_id' => Product::class,
            'produtos_id' => Product::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CollectionProduct $cp) {
            if (isset($cp->attributes['id_colecao']) && !isset($cp->attributes['collection_id'])) {
                $cp->attributes['collection_id'] = $cp->attributes['id_colecao'];
                unset($cp->attributes['id_colecao']);
            }
            if (isset($cp->attributes['produtos_id']) && !isset($cp->attributes['product_id'])) {
                $cp->attributes['product_id'] = $cp->attributes['produtos_id'];
                unset($cp->attributes['produtos_id']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getIdColecaoAttribute() { return $this->collection_id; }
    public function setIdColecaoAttribute($v) { $this->attributes['collection_id'] = $v; }

    public function getProdutosIdAttribute() { return $this->product_id; }
    public function setProdutosIdAttribute($v) { $this->attributes['product_id'] = $v; }

    // --- Relationships ---
    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id', 'id');
    }

    public function colecao()
    {
        return $this->collection();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function produto()
    {
        return $this->product();
    }
}
