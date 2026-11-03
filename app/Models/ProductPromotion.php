<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPromotion extends Model
{
    use HasFactory;

    protected $table = 'product_promotions';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'promotional_price',
        'start_date',
        'end_date',
        'active',
        'price_id',
        // Backward-compatible
        'valor_promo',
        'data_inicio',
        'data_fim',
        'ativo',
        'precos_id_precos',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProductPromotion $p) {
            if (isset($p->attributes['valor_promo']) && !isset($p->attributes['promotional_price'])) {
                $p->attributes['promotional_price'] = $p->attributes['valor_promo'];
                unset($p->attributes['valor_promo']);
            }
            if (isset($p->attributes['data_inicio']) && !isset($p->attributes['start_date'])) {
                $p->attributes['start_date'] = $p->attributes['data_inicio'];
                unset($p->attributes['data_inicio']);
            }
            if (isset($p->attributes['data_fim']) && !isset($p->attributes['end_date'])) {
                $p->attributes['end_date'] = $p->attributes['data_fim'];
                unset($p->attributes['data_fim']);
            }
            if (isset($p->attributes['ativo']) && !isset($p->attributes['active'])) {
                $p->attributes['active'] = $p->attributes['ativo'];
                unset($p->attributes['ativo']);
            }
            if (isset($p->attributes['precos_id_precos']) && !isset($p->attributes['price_id'])) {
                $p->attributes['price_id'] = $p->attributes['precos_id_precos'];
                unset($p->attributes['precos_id_precos']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getValorPromoAttribute() { return $this->promotional_price; }
    public function setValorPromoAttribute($v) { $this->attributes['promotional_price'] = $v; }

    public function getDataInicioAttribute() { return $this->start_date; }
    public function setDataInicioAttribute($v) { $this->attributes['start_date'] = $v; }

    public function getDataFimAttribute() { return $this->end_date; }
    public function setDataFimAttribute($v) { $this->attributes['end_date'] = $v; }

    public function getAtivoAttribute() { return $this->active; }
    public function setAtivoAttribute($v) { $this->attributes['active'] = $v; }

    public function getPrecosIdPrecosAttribute() { return $this->price_id; }
    public function setPrecosIdPrecosAttribute($v) { $this->attributes['price_id'] = $v; }

    // --- Relationships ---
    public function price()
    {
        return $this->belongsTo(ProductPrice::class, 'price_id', 'id');
    }

    public function preco()
    {
        return $this->price();
    }
}

