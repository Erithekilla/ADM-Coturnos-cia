<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'product_prices';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'price',
        'effective_date',
        'product_id',
        // Backward-compatible
        'preco',
        'alteracao',
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
        static::saving(function (ProductPrice $p) {
            if (isset($p->attributes['preco']) && !isset($p->attributes['price'])) {
                $p->attributes['price'] = $p->attributes['preco'];
                unset($p->attributes['preco']);
            }
            if (isset($p->attributes['alteracao']) && !isset($p->attributes['effective_date'])) {
                $p->attributes['effective_date'] = $p->attributes['alteracao'];
                unset($p->attributes['alteracao']);
            }
            if (isset($p->attributes['produtos_id']) && !isset($p->attributes['product_id'])) {
                $p->attributes['product_id'] = $p->attributes['produtos_id'];
                unset($p->attributes['produtos_id']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getPrecoAttribute() { return $this->price; }
    public function setPrecoAttribute($v) { $this->attributes['price'] = $v; }

    public function getAlteracaoAttribute() { return $this->effective_date; }
    public function setAlteracaoAttribute($v) { $this->attributes['effective_date'] = $v; }

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

    public function promotions()
    {
        return $this->hasMany(ProductPromotion::class, 'price_id', 'id');
    }

    public function promocoes()
    {
        return $this->promotions();
    }
}

