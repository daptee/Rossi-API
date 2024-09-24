<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    protected $fillable = ['id_material', 'name', 'status'];

    public function parent()
    {
        return $this->belongsTo(Material::class, 'id_material');
    }

    public function children()
    {
        return $this->hasMany(Material::class, 'id_material');
    }

    public function values()
    {
        return $this->hasMany(MaterialValue::class, 'id_material');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status', 'id');
    }
}
