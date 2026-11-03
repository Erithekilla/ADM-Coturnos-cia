<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionImage extends Model
{
    use HasFactory;

    protected $table = 'collection_images';
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'image_url',
        'collection_id',
        // Backward-compatible
        'url_imagem',
        'id_colecao',
    ];

    protected static function booted(): void
    {
        static::saving(function (CollectionImage $ci) {
            if (isset($ci->attributes['url_imagem']) && !isset($ci->attributes['image_url'])) {
                $ci->attributes['image_url'] = $ci->attributes['url_imagem'];
                unset($ci->attributes['url_imagem']);
            }
            if (isset($ci->attributes['id_colecao']) && !isset($ci->attributes['collection_id'])) {
                $ci->attributes['collection_id'] = $ci->attributes['id_colecao'];
                unset($ci->attributes['id_colecao']);
            }
        });
    }

    // --- Backward Compatible Attribute Accessors ---
    public function getUrlImagemAttribute() { return $this->image_url; }
    public function setUrlImagemAttribute($v) { $this->attributes['image_url'] = $v; }

    public function getIdColecaoAttribute() { return $this->collection_id; }
    public function setIdColecaoAttribute($v) { $this->attributes['collection_id'] = $v; }

    // --- Relationships ---
    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id', 'id');
    }

    public function colecao()
    {
        return $this->collection();
    }
}
