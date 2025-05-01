<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model
{
    protected $table = 'product_attribute_value';

    protected $fillable = [
        'id_product_atribute_value',
        'id_product',
        'img',
        'thumbnail_img',
    ];
}
