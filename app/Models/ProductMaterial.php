<?php

// app/Models/ProductMaterial.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_product',
        'id_material',
        'img',
        'thumbnail_img',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'id_material');
    }
}
