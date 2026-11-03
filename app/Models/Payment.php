<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'payments';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'payment_date',
        'order_id',
        'payment_method',
        'status',
        'amount',
        // Backward-compatible
        'data_pagamento',
        'pedido_id',
        'metodo_pagamento',
        'valor',
    ];

    public function hashidForeignKeys(): array
    {
        return [
            'order_id' => Order::class,
            'pedido_id' => Order::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Payment $p) {
            if (isset($p->attributes['data_pagamento']) && !isset($p->attributes['payment_date'])) {
                $p->attributes['payment_date'] = $p->attributes['data_pagamento'];
                unset($p->attributes['data_pagamento']);
            }
            if (isset($p->attributes['pedido_id']) && !isset($p->attributes['order_id'])) {
                $p->attributes['order_id'] = $p->attributes['pedido_id'];
                unset($p->attributes['pedido_id']);
            }
            if (isset($p->attributes['metodo_pagamento']) && !isset($p->attributes['payment_method'])) {
                $p->attributes['payment_method'] = $p->attributes['metodo_pagamento'];
                unset($p->attributes['metodo_pagamento']);
            }
            if (isset($p->attributes['valor']) && !isset($p->attributes['amount'])) {
                $p->attributes['amount'] = $p->attributes['valor'];
                unset($p->attributes['valor']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getDataPagamentoAttribute() { return $this->payment_date; }
    public function setDataPagamentoAttribute($v) { $this->attributes['payment_date'] = $v; }

    public function getPedidoIdAttribute() { return $this->order_id; }
    public function setPedidoIdAttribute($v) { $this->attributes['order_id'] = $v; }

    public function getMetodoPagamentoAttribute() { return $this->payment_method; }
    public function setMetodoPagamentoAttribute($v) { $this->attributes['payment_method'] = $v; }

    public function getValorAttribute() { return $this->amount; }
    public function setValorAttribute($v) { $this->attributes['amount'] = $v; }

    // --- Relationships ---
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    public function pedido()
    {
        return $this->order();
    }
}

