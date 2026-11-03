<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BannerImage extends Model
{
    use HasFactory;

    protected $table = 'banner_images';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'image_url',
        'banner_id',
        // Backward-compatible
        'url_imagem',
        'banner_idbanner',
    ];

    protected static function booted(): void
    {
        static::saving(function (BannerImage $bi) {
            if (isset($bi->attributes['url_imagem']) && !isset($bi->attributes['image_url'])) {
                $bi->attributes['image_url'] = $bi->attributes['url_imagem'];
                unset($bi->attributes['url_imagem']);
            }
            if (isset($bi->attributes['banner_idbanner']) && !isset($bi->attributes['banner_id'])) {
                $bi->attributes['banner_id'] = $bi->attributes['banner_idbanner'];
                unset($bi->attributes['banner_idbanner']);
            }
        });
    }

    public function getUrlImagemAttribute() { return $this->image_url; }
    public function setUrlImagemAttribute($v) { $this->attributes['image_url'] = $v; }

    public function getBannerIdbannerAttribute() { return $this->banner_id; }
    public function setBannerIdbannerAttribute($v) { $this->attributes['banner_id'] = $v; }

    public function banner()
    {
        return $this->belongsTo(Banner::class, 'banner_id', 'id');
    }
}

