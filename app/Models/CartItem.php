<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'cart_items';
    protected $primaryKey = 'cart_id';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'size',
        // Backward-compatible
        'carrinho_id_carrinho',
        'produtos_id',
        'quantidade',
        'tamanho',
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
        static::saving(function (CartItem $ci) {
            if (isset($ci->attributes['carrinho_id_carrinho']) && !isset($ci->attributes['cart_id'])) {
                $ci->attributes['cart_id'] = $ci->attributes['carrinho_id_carrinho'];
                unset($ci->attributes['carrinho_id_carrinho']);
            }
            if (isset($ci->attributes['produtos_id']) && !isset($ci->attributes['product_id'])) {
                $ci->attributes['product_id'] = $ci->attributes['produtos_id'];
                unset($ci->attributes['produtos_id']);
            }
            if (isset($ci->attributes['quantidade']) && !isset($ci->attributes['quantity'])) {
                $ci->attributes['quantity'] = $ci->attributes['quantidade'];
                unset($ci->attributes['quantidade']);
            }
            if (isset($ci->attributes['tamanho']) && !isset($ci->attributes['size'])) {
                $ci->attributes['size'] = $ci->attributes['tamanho'];
                unset($ci->attributes['tamanho']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getCarrinhoIdCarrinhoAttribute() { return $this->cart_id; }
    public function setCarrinhoIdCarrinhoAttribute($v) { $this->attributes['cart_id'] = $v; }

    public function getProdutosIdAttribute() { return $this->product_id; }
    public function setProdutosIdAttribute($v) { $this->attributes['product_id'] = $v; }

    public function getQuantidadeAttribute() { return $this->quantity; }
    public function setQuantidadeAttribute($v) { $this->attributes['quantity'] = $v; }

    public function getTamanhoAttribute() { return $this->size; }
    public function setTamanhoAttribute($v) { $this->attributes['size'] = $v; }

    // --- Relationships ---
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }

    public function carrinho()
    {
        return $this->cart();
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
