<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'slug',
        'description',
        'status',
        'main_img',
        'main_video',
        'file_data_sheet',
        'featured'
    ];

    public function attributes()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attributes', 'id_product', 'id_attribute_value')
                    ->withPivot('img'); // Si quieres traer también la columna 'img' de la tabla pivot
    }

    public function categories()
    {
        return $this->belongsToMany(ProductsCategories::class, 'product_categories', 'id_product', 'id_categorie');
    }

    public function materials()
    {
        return $this->belongsToMany(MaterialValue::class, 'product_materials', 'id_product', 'id_material');
    }

    public function gallery()
    {
        return $this->hasMany(ProductGallery::class, 'id_product');
    }
}

