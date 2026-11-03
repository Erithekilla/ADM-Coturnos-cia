<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'product_images';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'image_url',
        'public_id',
        'product_id',
        // Backward-compatible
        'url_imagem',
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
        static::saving(function (ProductImage $i) {
            if (isset($i->attributes['url_imagem']) && !isset($i->attributes['image_url'])) {
                $i->attributes['image_url'] = $i->attributes['url_imagem'];
                unset($i->attributes['url_imagem']);
            }
            if (isset($i->attributes['produtos_id']) && !isset($i->attributes['product_id'])) {
                $i->attributes['product_id'] = $i->attributes['produtos_id'];
                unset($i->attributes['produtos_id']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getUrlImagemAttribute() { return $this->image_url; }
    public function setUrlImagemAttribute($v) { $this->attributes['image_url'] = $v; }

    public function getProdutosIdAttribute() { return $this->product_id; }
    public function setProdutosIdAttribute($v) { $this->attributes['product_id'] = $v; }

    // --- Relationships ---
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function produto()
    {
        return $this->product();
    }
}
