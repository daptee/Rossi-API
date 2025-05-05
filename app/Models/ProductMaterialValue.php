<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMaterialValue extends Model
{
    protected $table = 'product_material_value';

    protected $fillable = [
        'id_product_material_value',
        'id_product',
        'img',
        'thumbnail_img',
    ];
}
