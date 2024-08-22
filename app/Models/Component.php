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
        'name',
        'img',
    ];
}
