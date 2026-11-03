<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'order_history';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'status',
        'order_id',
        'status_date',
        'notes',
        'extra_info',
        // Backward-compatible
        'pedido_id',
        'data_status',
        'observacao',
        'historico_pedidocol',
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
        static::saving(function (OrderHistory $oh) {
            if (isset($oh->attributes['pedido_id']) && !isset($oh->attributes['order_id'])) {
                $oh->attributes['order_id'] = $oh->attributes['pedido_id'];
                unset($oh->attributes['pedido_id']);
            }
            if (isset($oh->attributes['data_status']) && !isset($oh->attributes['status_date'])) {
                $oh->attributes['status_date'] = $oh->attributes['data_status'];
                unset($oh->attributes['data_status']);
            }
            if (isset($oh->attributes['observacao']) && !isset($oh->attributes['notes'])) {
                $oh->attributes['notes'] = $oh->attributes['observacao'];
                unset($oh->attributes['observacao']);
            }
            if (isset($oh->attributes['historico_pedidocol']) && !isset($oh->attributes['extra_info'])) {
                $oh->attributes['extra_info'] = $oh->attributes['historico_pedidocol'];
                unset($oh->attributes['historico_pedidocol']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getPedidoIdAttribute() { return $this->order_id; }
    public function setPedidoIdAttribute($v) { $this->attributes['order_id'] = $v; }

    public function getDataStatusAttribute() { return $this->status_date; }
    public function setDataStatusAttribute($v) { $this->attributes['status_date'] = $v; }

    public function getObservacaoAttribute() { return $this->notes; }
    public function setObservacaoAttribute($v) { $this->attributes['notes'] = $v; }

    public function getHistoricoPedidocolAttribute() { return $this->extra_info; }
    public function setHistoricoPedidocolAttribute($v) { $this->attributes['extra_info'] = $v; }

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

