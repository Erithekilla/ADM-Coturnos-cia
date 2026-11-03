<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'payments';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'order_id',
        'gateway',
        'preference_id',
        'gateway_payment_id',
        'method',
        'status',
        'amount',
        'installments',
        'card_last_four',
        'card_brand',
        'init_point',
        'qr_code_pix',
        'qr_code_base64',
        'response_payload',
        'paid_at',
        // Backward-compatible
        'payment_date',
        'payment_method',
        'data_pagamento',
        'pedido_id',
        'metodo_pagamento',
        'valor',
    ];

    protected function casts(): array
    {
        return [
            'response_payload' => 'encrypted:array',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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
            if (isset($p->attributes['pedido_id']) && !isset($p->attributes['order_id'])) {
                $p->attributes['order_id'] = $p->attributes['pedido_id'];
                unset($p->attributes['pedido_id']);
            }

            if (isset($p->attributes['valor']) && !isset($p->attributes['amount'])) {
                $p->attributes['amount'] = $p->attributes['valor'];
                unset($p->attributes['valor']);
            }

            $date = $p->attributes['paid_at'] ?? $p->attributes['payment_date'] ?? $p->attributes['data_pagamento'] ?? null;
            if ($date !== null) {
                $p->attributes['paid_at'] = $date;
                $p->attributes['payment_date'] = $date;
            }

            $method = $p->attributes['method'] ?? $p->attributes['payment_method'] ?? $p->attributes['metodo_pagamento'] ?? 'pix';
            $p->attributes['method'] = $method;
            $p->attributes['payment_method'] = $method;

            unset(
                $p->attributes['data_pagamento'],
                $p->attributes['metodo_pagamento']
            );
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getDataPagamentoAttribute() { return $this->paid_at ?: $this->payment_date; }
    public function setDataPagamentoAttribute($v) { $this->attributes['paid_at'] = $v; $this->attributes['payment_date'] = $v; }

    public function getPaymentDateAttribute() { return $this->paid_at ?: ($this->attributes['payment_date'] ?? null); }
    public function setPaymentDateAttribute($v) { $this->attributes['paid_at'] = $v; $this->attributes['payment_date'] = $v; }

    public function getPedidoIdAttribute() { return $this->order_id; }
    public function setPedidoIdAttribute($v) { $this->attributes['order_id'] = $v; }

    public function getMetodoPagamentoAttribute() { return $this->method ?: $this->payment_method; }
    public function setMetodoPagamentoAttribute($v) { $this->attributes['method'] = $v; $this->attributes['payment_method'] = $v; }

    public function getPaymentMethodAttribute() { return $this->method ?: ($this->attributes['payment_method'] ?? null); }
    public function setPaymentMethodAttribute($v) { $this->attributes['method'] = $v; $this->attributes['payment_method'] = $v; }

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
