<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebContentAbout extends Model
{
    use HasFactory;

    protected $table = 'web_content_about';

    protected $fillable = ['id_user', 'data', 'video_giro', 'video_showroom'];

    protected $casts = [
        'data' => 'array',
    ];

    // Relación con la galería
    public function gallery()
    {
        return $this->hasMany(GalleryWebContentAbout::class, 'id_web_content_about');
    }
}