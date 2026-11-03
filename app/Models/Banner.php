<?php

namespace App\Models;

use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory, HasHashid;

    protected $table = 'banners';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = ['active', 'ativo'];

    protected static function booted(): void
    {
        static::saving(function (Banner $b) {
            if (isset($b->attributes['ativo']) && !isset($b->attributes['active'])) {
                $b->attributes['active'] = $b->attributes['ativo'];
                unset($b->attributes['ativo']);
            }
        });
    }

    public function getAtivoAttribute() { return $this->active; }
    public function setAtivoAttribute($v) { $this->attributes['active'] = $v; }

    public function images()
    {
        return $this->hasMany(BannerImage::class, 'banner_id', 'id');
    }

    public function imagens()
    {
        return $this->images();
    }
}
