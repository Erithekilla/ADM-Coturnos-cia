<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $table = 'banner';
    protected $primaryKey = 'idbanner';

    public $timestamps = false;

    protected $fillable = ['ativo'];

    public function imagens()
    {
        return $this->hasMany(ImagensBanner::class, 'banner_idbanner', 'idbanner');
    }
}

