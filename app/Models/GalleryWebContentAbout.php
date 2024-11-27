<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GalleryWebContentAbout extends Model
{
    use HasFactory;

    // Nombre de la tabla
    protected $table = 'gallery_web_content_about';

    // Clave primaria
    protected $primaryKey = 'id';

    // Campos que se pueden asignar de forma masiva
    protected $fillable = [
        'id_web_content_about', // Asegúrate de incluir esta columna en la base de datos
        'file',
    ];

    // Si no necesitas timestamps personalizados, puedes deshabilitarlos (opcional)
    public $timestamps = true;

    // Relación con WebContentAbout
    public function webContentAbout()
    {
        return $this->belongsTo(WebContentAbout::class, 'id_web_content_about');
    }
}
