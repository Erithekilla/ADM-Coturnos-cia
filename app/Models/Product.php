<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasHashid, SoftDeletes;

    protected $table = 'products';
    protected $primaryKey = 'id';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'product_type',
        'brand',
        'model',
        'color',
        'description',
        'base_price',
        'promotional_price',
        'weight',
        'height',
        'width',
        'length',
        'active',
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
        'preco',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'base_price' => 'decimal:2',
            'promotional_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'height' => 'decimal:2',
            'width' => 'decimal:2',
            'length' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            $name = $p->attributes['name'] ?? $p->attributes['model'] ?? $p->attributes['modelo'] ?? 'Novo Produto';
            $p->attributes['name'] = substr((string)$name, 0, 120);
            $p->attributes['model'] = substr((string)$name, 0, 45);

            if (empty($p->attributes['slug'])) {
                $p->attributes['slug'] = Str::slug($name) . '-' . substr(uniqid(), -6);
            }

            if (!isset($p->attributes['product_type']) || empty($p->attributes['product_type'])) {
                $p->attributes['product_type'] = $p->attributes['tipo_do_produto'] ?? 'Coturno';
            }
            if (!isset($p->attributes['brand']) || empty($p->attributes['brand'])) {
                $p->attributes['brand'] = $p->attributes['marca'] ?? 'Coturnos & Cia';
            }
            if (!isset($p->attributes['description']) || empty($p->attributes['description'])) {
                $p->attributes['description'] = $p->attributes['descricao'] ?? 'Calçado de alta resistência';
            }
            if (!isset($p->attributes['color']) || empty($p->attributes['color'])) {
                $p->attributes['color'] = $p->attributes['cor'] ?? 'Preto';
            }

            $price = $p->attributes['base_price'] ?? $p->attributes['price'] ?? $p->attributes['preco'] ?? 0;
            $p->attributes['base_price'] = (float) $price;

            $activeVal = $p->attributes['active'] ?? $p->attributes['ativo'] ?? $p->attributes['status'] ?? true;
            $isActive = is_bool($activeVal) ? $activeVal : (strtolower((string)$activeVal) !== 'inativo');
            $p->attributes['active'] = $isActive;
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'status')) {
                $p->attributes['status'] = $isActive ? 'ativo' : 'inativo';
            } else {
                unset($p->attributes['status']);
            }
            if (!\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_deleted')) {
                unset($p->attributes['is_deleted']);
            }

            unset(
                $p->attributes['tipo_do_produto'],
                $p->attributes['modelo'],
                $p->attributes['marca'],
                $p->attributes['descricao'],
                $p->attributes['ativo'],
                $p->attributes['cor'],
                $p->attributes['excluido'],
                $p->attributes['preco'],
                $p->attributes['price']
            );
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

    public function getAtivoAttribute() { return $this->active ? 'ativo' : 'inativo'; }
    public function setAtivoAttribute($v) { $this->attributes['active'] = is_bool($v) ? $v : (strtolower((string)$v) !== 'inativo'); }

    public function getCorAttribute() { return $this->color; }
    public function setCorAttribute($v) { $this->attributes['color'] = $v; }

    public function getExcluidoAttribute() { return $this->trashed() ? 'S' : 'N'; }
    public function setExcluidoAttribute($v) { $this->attributes['is_deleted'] = $v; }

    public function getPriceAttribute() { return (float) ($this->base_price ?: ($this->prices->sortByDesc('id')->first()?->price ?? 0)); }

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
        $pivot = \Illuminate\Support\Facades\Schema::hasTable('collection_product') ? 'collection_product' : 'collection_products';
        return $this->belongsToMany(Collection::class, $pivot, 'product_id', 'collection_id');
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
