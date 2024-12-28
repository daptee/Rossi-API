<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    use HasFactory;

    // Definir la tabla asociada
    protected $table = 'components';

    // Definir los campos que se pueden asignar masivamente
    protected $fillable = [
        'id_component',
        'name',
        'img',
        'description',
        'status',
        'id_category'
    ];

    public function status()
    {
        return $this->belongsTo(Status::class, 'status', 'id');
    }

    public function components()
    {
        return $this->hasMany(Component::class, 'id_component');
    }
}
