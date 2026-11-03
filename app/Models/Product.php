<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'products';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'product_type',
        'model',
        'brand',
        'description',
        'active',
        'color',
        'status',
        'is_deleted',
        // Backward-compatible keys
        'tipo_do_produto',
        'modelo',
        'marca',
        'descricao',
        'ativo',
        'cor',
        'excluido',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if (isset($p->attributes['tipo_do_produto']) && !isset($p->attributes['product_type'])) {
                $p->attributes['product_type'] = $p->attributes['tipo_do_produto'];
                unset($p->attributes['tipo_do_produto']);
            }
            if (isset($p->attributes['modelo']) && !isset($p->attributes['model'])) {
                $p->attributes['model'] = $p->attributes['modelo'];
                unset($p->attributes['modelo']);
            }
            if (isset($p->attributes['marca']) && !isset($p->attributes['brand'])) {
                $p->attributes['brand'] = $p->attributes['marca'];
                unset($p->attributes['marca']);
            }
            if (isset($p->attributes['descricao']) && !isset($p->attributes['description'])) {
                $p->attributes['description'] = $p->attributes['descricao'];
                unset($p->attributes['descricao']);
            }
            if (isset($p->attributes['ativo']) && !isset($p->attributes['active'])) {
                $p->attributes['active'] = $p->attributes['ativo'];
                unset($p->attributes['ativo']);
            }
            if (isset($p->attributes['cor']) && !isset($p->attributes['color'])) {
                $p->attributes['color'] = $p->attributes['cor'];
                unset($p->attributes['cor']);
            }
            if (isset($p->attributes['excluido']) && !isset($p->attributes['is_deleted'])) {
                $p->attributes['is_deleted'] = $p->attributes['excluido'];
                unset($p->attributes['excluido']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getTipoDoProdutoAttribute() { return $this->product_type; }
    public function setTipoDoProdutoAttribute($v) { $this->attributes['product_type'] = $v; }

    public function getModeloAttribute() { return $this->model; }
    public function setModeloAttribute($v) { $this->attributes['model'] = $v; }

    public function getMarcaAttribute() { return $this->brand; }
    public function setMarcaAttribute($v) { $this->attributes['brand'] = $v; }

    public function getDescricaoAttribute() { return $this->description; }
    public function setDescricaoAttribute($v) { $this->attributes['description'] = $v; }

    public function getAtivoAttribute() { return $this->active; }
    public function setAtivoAttribute($v) { $this->attributes['active'] = $v; }

    public function getCorAttribute() { return $this->color; }
    public function setCorAttribute($v) { $this->attributes['color'] = $v; }

    public function getExcluidoAttribute() { return $this->is_deleted; }
    public function setExcluidoAttribute($v) { $this->attributes['is_deleted'] = $v; }

    // --- Relationships ---
    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id', 'id');
    }

    public function imagens()
    {
        return $this->images();
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'product_id', 'id');
    }

    public function precos()
    {
        return $this->prices();
    }

    public function sizes()
    {
        return $this->hasMany(ProductSize::class, 'product_id', 'id');
    }

    public function tamanhos()
    {
        return $this->sizes();
    }

    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_products', 'product_id', 'collection_id');
    }

    public function colecoes()
    {
        return $this->collections();
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'product_id', 'id');
    }

    public function itensCarrinho()
    {
        return $this->cartItems();
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id', 'id');
    }

    public function itensPedidos()
    {
        return $this->orderItems();
    }
}

