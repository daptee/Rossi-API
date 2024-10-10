<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaterialValue extends Model
{
    use HasFactory;

    protected $fillable = ['id_material', 'value', 'img', 'color'];

    public function material()
    {
        return $this->belongsTo(Material::class, 'id_material');
    }
}
