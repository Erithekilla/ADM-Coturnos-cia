<?php

namespace App\Models;

use App\Models\Concerns\HasHashidForeignKeys;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory, HasHashidForeignKeys;

    protected $table = 'carts';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'created_at',
        'updated_at',
        // Backward-compatible
        'usuario_id',
        'criado_em',
        'atualizado_em',
    ];

    public function hashidForeignKeys(): array
    {
        return [
            'user_id' => User::class,
            'usuario_id' => User::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Cart $c) {
            if (isset($c->attributes['usuario_id']) && !isset($c->attributes['user_id'])) {
                $c->attributes['user_id'] = $c->attributes['usuario_id'];
                unset($c->attributes['usuario_id']);
            }
            if (isset($c->attributes['criado_em']) && !isset($c->attributes['created_at'])) {
                $c->attributes['created_at'] = $c->attributes['criado_em'];
                unset($c->attributes['criado_em']);
            }
            if (isset($c->attributes['atualizado_em']) && !isset($c->attributes['updated_at'])) {
                $c->attributes['updated_at'] = $c->attributes['atualizado_em'];
                unset($c->attributes['atualizado_em']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getIdCarrinhoAttribute() { return $this->id; }
    public function setIdCarrinhoAttribute($v) { $this->attributes['id'] = $v; }

    public function getUsuarioIdAttribute() { return $this->user_id; }
    public function setUsuarioIdAttribute($v) { $this->attributes['user_id'] = $v; }

    public function getCriadoEmAttribute() { return $this->created_at; }
    public function setCriadoEmAttribute($v) { $this->attributes['created_at'] = $v; }

    public function getAtualizadoEmAttribute() { return $this->updated_at; }
    public function setAtualizadoEmAttribute($v) { $this->attributes['updated_at'] = $v; }

    // --- Relationships ---
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function usuario()
    {
        return $this->user();
    }

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id', 'id');
    }

    public function itens()
    {
        return $this->items();
    }
}
