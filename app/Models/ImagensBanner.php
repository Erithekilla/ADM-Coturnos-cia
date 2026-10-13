<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImagensBanner extends Model
{
    use HasFactory;

    protected $table = 'imagens_banner';
    protected $primaryKey = 'id_imagens_banner';

    public $timestamps = false;

    protected $fillable = ['url_imagem', 'banner_idbanner'];

    public function banner()
    {
        return $this->belongsTo(Banner::class, 'banner_idbanner', 'idbanner');
    }
}

