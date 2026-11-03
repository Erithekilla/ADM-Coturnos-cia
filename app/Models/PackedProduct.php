<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PackedProduct extends Model
{
    use HasFactory;

    protected $table = 'packed_products';

    protected $fillable = [
        'weight',
        'height',
        'width',
        'length',
        // Backward-compatible
        'peso',
        'altura',
        'largura',
        'comprimento',
    ];

    protected $attributes = [
        'weight' => 1.50,
        'height' => 14.00,
        'width' => 22.00,
        'length' => 35.00,
    ];

    protected $casts = [
        'weight' => 'float',
        'height' => 'float',
        'width' => 'float',
        'length' => 'float',
    ];

    public function getPesoAttribute() { return $this->weight; }
    public function setPesoAttribute($v) { $this->attributes['weight'] = $v; }

    public function getAlturaAttribute() { return $this->height; }
    public function setAlturaAttribute($v) { $this->attributes['height'] = $v; }

    public function getLarguraAttribute() { return $this->width; }
    public function setLarguraAttribute($v) { $this->attributes['width'] = $v; }

    public function getComprimentoAttribute() { return $this->length; }
    public function setComprimentoAttribute($v) { $this->attributes['length'] = $v; }
}
