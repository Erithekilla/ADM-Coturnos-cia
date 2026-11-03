<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'addresses';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'zip_code',
        'street',
        'neighborhood',
        'city',
        'state',
        'number',
        'complement',
        'is_default',
        'user_id',
        // Backward-compatible keys
        'cep',
        'rua',
        'bairro',
        'cidade',
        'estado',
        'numero',
        'complemento',
        'padrao',
        'usuario_id',
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
        static::saving(function (Address $a) {
            if (isset($a->attributes['cep']) && !isset($a->attributes['zip_code'])) {
                $a->attributes['zip_code'] = $a->attributes['cep'];
                unset($a->attributes['cep']);
            }
            if (isset($a->attributes['rua']) && !isset($a->attributes['street'])) {
                $a->attributes['street'] = $a->attributes['rua'];
                unset($a->attributes['rua']);
            }
            if (isset($a->attributes['bairro']) && !isset($a->attributes['neighborhood'])) {
                $a->attributes['neighborhood'] = $a->attributes['bairro'];
                unset($a->attributes['bairro']);
            }
            if (isset($a->attributes['cidade']) && !isset($a->attributes['city'])) {
                $a->attributes['city'] = $a->attributes['cidade'];
                unset($a->attributes['cidade']);
            }
            if (isset($a->attributes['estado']) && !isset($a->attributes['state'])) {
                $a->attributes['state'] = $a->attributes['estado'];
                unset($a->attributes['estado']);
            }
            if (isset($a->attributes['numero']) && !isset($a->attributes['number'])) {
                $a->attributes['number'] = $a->attributes['numero'];
                unset($a->attributes['numero']);
            }
            if (isset($a->attributes['complemento']) && !isset($a->attributes['complement'])) {
                $a->attributes['complement'] = $a->attributes['complemento'];
                unset($a->attributes['complemento']);
            }
            if (isset($a->attributes['padrao']) && !isset($a->attributes['is_default'])) {
                $a->attributes['is_default'] = $a->attributes['padrao'];
                unset($a->attributes['padrao']);
            }
            if (isset($a->attributes['usuario_id']) && !isset($a->attributes['user_id'])) {
                $a->attributes['user_id'] = $a->attributes['usuario_id'];
                unset($a->attributes['usuario_id']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getCepAttribute() { return $this->zip_code; }
    public function setCepAttribute($v) { $this->attributes['zip_code'] = $v; }

    public function getRuaAttribute() { return $this->street; }
    public function setRuaAttribute($v) { $this->attributes['street'] = $v; }

    public function getBairroAttribute() { return $this->neighborhood; }
    public function setBairroAttribute($v) { $this->attributes['neighborhood'] = $v; }

    public function getCidadeAttribute() { return $this->city; }
    public function setCidadeAttribute($v) { $this->attributes['city'] = $v; }

    public function getEstadoAttribute() { return $this->state; }
    public function setEstadoAttribute($v) { $this->attributes['state'] = $v; }

    public function getNumeroAttribute() { return $this->number; }
    public function setNumeroAttribute($v) { $this->attributes['number'] = $v; }

    public function getComplementoAttribute() { return $this->complement; }
    public function setComplementoAttribute($v) { $this->attributes['complement'] = $v; }

    public function getPadraoAttribute() { return $this->is_default; }
    public function setPadraoAttribute($v) { $this->attributes['is_default'] = $v; }

    public function getUsuarioIdAttribute() { return $this->user_id; }
    public function setUsuarioIdAttribute($v) { $this->attributes['user_id'] = $v; }

    // --- Relationships ---
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function usuario()
    {
        return $this->user();
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'address_id', 'id');
    }

    public function pedidos()
    {
        return $this->orders();
    }
}
