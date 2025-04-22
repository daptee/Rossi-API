<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebContentHomeFile extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'web_content_home_files';

    /**
     * Los atributos que se pueden asignar de manera masiva.
     *
     * @var array
     */
    protected $fillable = [
        'id_web_content_home',
        'name',
        'type',
        'path',
        'thumbnail_path'
    ];

    /**
     * Relación con el modelo WebContentHome.
     * Cada archivo pertenece a un contenido web.
     */
    public function webContentHome()
    {
        return $this->belongsTo(WebContentHome::class, 'id_web_content_home');
    }
}
