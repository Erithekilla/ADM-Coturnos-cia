<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSize extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'product_sizes';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'size',
        'quantity',
        // Backward-compatible
        'produtos_id',
        'tamanho',
        'quantidade',
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
        static::saving(function (ProductSize $s) {
            if (isset($s->attributes['produtos_id']) && !isset($s->attributes['product_id'])) {
                $s->attributes['product_id'] = $s->attributes['produtos_id'];
                unset($s->attributes['produtos_id']);
            }
            if (isset($s->attributes['tamanho']) && !isset($s->attributes['size'])) {
                $s->attributes['size'] = $s->attributes['tamanho'];
                unset($s->attributes['tamanho']);
            }
            if (isset($s->attributes['quantidade']) && !isset($s->attributes['quantity'])) {
                $s->attributes['quantity'] = $s->attributes['quantidade'];
                unset($s->attributes['quantidade']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getProdutosIdAttribute() { return $this->product_id; }
    public function setProdutosIdAttribute($v) { $this->attributes['product_id'] = $v; }

    public function getTamanhoAttribute() { return $this->size; }
    public function setTamanhoAttribute($v) { $this->attributes['size'] = $v; }

    public function getQuantidadeAttribute() { return $this->quantity; }
    public function setQuantidadeAttribute($v) { $this->attributes['quantity'] = $v; }

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
