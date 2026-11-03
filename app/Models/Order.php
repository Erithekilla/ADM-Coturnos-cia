<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'orders';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'total_amount',
        'status',
        'tracking_code',
        'order_date',
        'user_id',
        'address_id',
        // Backward-compatible
        'valor_total',
        'codigo_rastreio',
        'data_pedido',
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
            if (isset($o->attributes['valor_total']) && !isset($o->attributes['total_amount'])) {
                $o->attributes['total_amount'] = $o->attributes['valor_total'];
                unset($o->attributes['valor_total']);
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
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getValorTotalAttribute() { return $this->total_amount; }
    public function setValorTotalAttribute($v) { $this->attributes['total_amount'] = $v; }

    public function getCodigoRastreioAttribute() { return $this->tracking_code; }
    public function setCodigoRastreioAttribute($v) { $this->attributes['tracking_code'] = $v; }

    public function getDataPedidoAttribute() { return $this->order_date; }
    public function setDataPedidoAttribute($v) { $this->attributes['order_date'] = $v; }

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

    public function payments()
    {
        return $this->hasMany(Payment::class, 'order_id', 'id');
    }

    public function pagamentos()
    {
        return $this->payments();
    }

    public function history()
    {
        return $this->hasMany(OrderHistory::class, 'order_id', 'id');
    }

    public function historicos()
    {
        return $this->history();
    }
}
