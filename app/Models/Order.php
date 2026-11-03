<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'orders';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'order_number',
        'subtotal',
        'shipping_fee',
        'discount_fee',
        'total_amount',
        'shipping_method',
        'tracking_code',
        'status',
        'user_id',
        'address_id',
        'shipping_zip_code',
        'shipping_street',
        'shipping_number',
        'shipping_complement',
        'shipping_neighborhood',
        'shipping_city',
        'shipping_state',
        // Backward-compatible
        'valor_total',
        'codigo_rastreio',
        'data_pedido',
        'order_date',
        'usuario_id',
        'endereco_id',
    ];

    public function hashidForeignKeys(): array
    {
        return [
            'user_id' => User::class,
            'address_id' => Address::class,
            'usuario_id' => User::class,
            'endereco_id' => Address::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Order $o) {
            if (empty($o->attributes['order_number'])) {
                $o->attributes['order_number'] = 'CC-' . strtoupper(Str::random(10));
            }

            if (isset($o->attributes['valor_total']) && !isset($o->attributes['total_amount'])) {
                $o->attributes['total_amount'] = $o->attributes['valor_total'];
                unset($o->attributes['valor_total']);
            }

            $total = (float) ($o->attributes['total_amount'] ?? 0);
            if (!isset($o->attributes['subtotal'])) {
                $o->attributes['subtotal'] = $total;
            }
            if (!isset($o->attributes['shipping_fee'])) {
                $o->attributes['shipping_fee'] = 0;
            }
            if (!isset($o->attributes['discount_fee'])) {
                $o->attributes['discount_fee'] = 0;
            }

            if (isset($o->attributes['codigo_rastreio']) && !isset($o->attributes['tracking_code'])) {
                $o->attributes['tracking_code'] = $o->attributes['codigo_rastreio'];
                unset($o->attributes['codigo_rastreio']);
            }

            if (isset($o->attributes['data_pedido']) && !isset($o->attributes['order_date'])) {
                $o->attributes['order_date'] = $o->attributes['data_pedido'];
                unset($o->attributes['data_pedido']);
            }

            if (isset($o->attributes['usuario_id']) && !isset($o->attributes['user_id'])) {
                $o->attributes['user_id'] = $o->attributes['usuario_id'];
                unset($o->attributes['usuario_id']);
            }

            if (isset($o->attributes['endereco_id']) && !isset($o->attributes['address_id'])) {
                $o->attributes['address_id'] = $o->attributes['endereco_id'];
                unset($o->attributes['endereco_id']);
            }

            // Status normalization (portuguese to english)
            if (isset($o->attributes['status'])) {
                $statusMap = [
                    'pendente' => 'pending',
                    'pago' => 'paid',
                    'em_separacao' => 'preparing',
                    'enviado' => 'shipped',
                    'entregue' => 'delivered',
                    'cancelado' => 'cancelled',
                ];
                $s = strtolower(trim((string)$o->attributes['status']));
                $o->attributes['status'] = $statusMap[$s] ?? $s;
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getValorTotalAttribute() { return $this->total_amount; }
    public function setValorTotalAttribute($v) { $this->attributes['total_amount'] = $v; }

    public function getCodigoRastreioAttribute() { return $this->tracking_code; }
    public function setCodigoRastreioAttribute($v) { $this->attributes['tracking_code'] = $v; }

    public function getDataPedidoAttribute() { return $this->created_at ?: ($this->attributes['order_date'] ?? now()); }
    public function setDataPedidoAttribute($v) { $this->attributes['created_at'] = $v; }

    public function getOrderDateAttribute() { return $this->created_at ?: ($this->attributes['order_date'] ?? now()); }
    public function setOrderDateAttribute($v) { $this->attributes['created_at'] = $v; }

    public function getUsuarioIdAttribute() { return $this->user_id; }
    public function setUsuarioIdAttribute($v) { $this->attributes['user_id'] = $v; }

    public function getEnderecoIdAttribute() { return $this->address_id; }
    public function setEnderecoIdAttribute($v) { $this->attributes['address_id'] = $v; }

    // --- Relationships ---
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function usuario()
    {
        return $this->user();
    }

    public function address()
    {
        return $this->belongsTo(Address::class, 'address_id', 'id');
    }

    public function endereco()
    {
        return $this->address();
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }

    public function itens()
    {
        return $this->items();
    }

    public function history()
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'id')->orderBy('status_date', 'asc');
    }

    public function historico()
    {
        return $this->history();
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'order_id', 'id');
    }

    public function pagamento()
    {
        return $this->payment();
    }
}
