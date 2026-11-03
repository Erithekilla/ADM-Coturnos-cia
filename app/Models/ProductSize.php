<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProductSize extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'product_sizes';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'product_id',
        'size',
        'stock',
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

            $qty = $s->attributes['stock'] ?? $s->attributes['quantity'] ?? $s->attributes['quantidade'] ?? null;
            if ($qty !== null) {
                if (Schema::hasColumn('product_sizes', 'stock')) {
                    $s->attributes['stock'] = (int) $qty;
                }
                if (Schema::hasColumn('product_sizes', 'quantity')) {
                    $s->attributes['quantity'] = (int) $qty;
                }
                unset($s->attributes['quantidade']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getProdutosIdAttribute() { return $this->product_id; }
    public function setProdutosIdAttribute($v) { $this->attributes['product_id'] = $v; }

    public function getTamanhoAttribute() { return $this->size; }
    public function setTamanhoAttribute($v) { $this->attributes['size'] = $v; }

    public function getQuantidadeAttribute() { return $this->attributes['quantity'] ?? $this->attributes['stock'] ?? 0; }
    public function setQuantidadeAttribute($v) {
        $this->attributes['quantity'] = (int) $v;
        $this->attributes['stock'] = (int) $v;
    }

    public function getStockAttribute() { return $this->attributes['stock'] ?? $this->attributes['quantity'] ?? 0; }
    public function setStockAttribute($v) {
        $this->attributes['stock'] = (int) $v;
        $this->attributes['quantity'] = (int) $v;
    }

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
