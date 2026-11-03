<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'order_items';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'size',
        'unit_price',
        // Backward-compatible
        'pedido_id',
        'produtos_id',
        'quantidade',
        'tamanho',
        'preco_unitario',
    ];

    public function hashidForeignKeys(): array
    {
        return [
            'order_id' => Order::class,
            'product_id' => Product::class,
            'pedido_id' => Order::class,
            'produtos_id' => Product::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $oi) {
            if (isset($oi->attributes['pedido_id']) && !isset($oi->attributes['order_id'])) {
                $oi->attributes['order_id'] = $oi->attributes['pedido_id'];
                unset($oi->attributes['pedido_id']);
            }
            if (isset($oi->attributes['produtos_id']) && !isset($oi->attributes['product_id'])) {
                $oi->attributes['product_id'] = $oi->attributes['produtos_id'];
                unset($oi->attributes['produtos_id']);
            }
            if (isset($oi->attributes['quantidade']) && !isset($oi->attributes['quantity'])) {
                $oi->attributes['quantity'] = $oi->attributes['quantidade'];
                unset($oi->attributes['quantidade']);
            }
            if (isset($oi->attributes['tamanho']) && !isset($oi->attributes['size'])) {
                $oi->attributes['size'] = $oi->attributes['tamanho'];
                unset($oi->attributes['tamanho']);
            }
            if (isset($oi->attributes['preco_unitario']) && !isset($oi->attributes['unit_price'])) {
                $oi->attributes['unit_price'] = $oi->attributes['preco_unitario'];
                unset($oi->attributes['preco_unitario']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getPedidoIdAttribute() { return $this->order_id; }
    public function setPedidoIdAttribute($v) { $this->attributes['order_id'] = $v; }

    public function getProdutosIdAttribute() { return $this->product_id; }
    public function setProdutosIdAttribute($v) { $this->attributes['product_id'] = $v; }

    public function getQuantidadeAttribute() { return $this->quantity; }
    public function setQuantidadeAttribute($v) { $this->attributes['quantity'] = $v; }

    public function getTamanhoAttribute() { return $this->size; }
    public function setTamanhoAttribute($v) { $this->attributes['size'] = $v; }

    public function getPrecoUnitarioAttribute() { return $this->unit_price; }
    public function setPrecoUnitarioAttribute($v) { $this->attributes['unit_price'] = $v; }

    // --- Relationships ---
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function pedido()
    {
        return $this->order();
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

