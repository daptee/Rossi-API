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
        'description_bold',
        'description_italic',
        'description_underline',
        'status',
        'main_img',
        'main_video',
        'file_data_sheet',
        'featured'
    ];

    public function attributes()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attributes', 'id_product', 'id_attribute_value')
            ->withPivot('img')
            ->with('attribute');
    }

    public function categories()
    {
        return $this->belongsToMany(ProductsCategories::class, 'product_categories', 'id_product', 'id_categorie');
    }

    public function materials()
    {
        return $this->belongsToMany(MaterialValue::class, 'product_materials', 'id_product', 'id_material')
            ->withPivot('img')
            ->with('material'); // Esto carga la relación con el modelo Material
    }

    public function gallery()
    {
        return $this->hasMany(ProductGallery::class, 'id_product');
    }

    public function components()
    {
        return $this->belongsToMany(Component::class, 'product_components', 'id_product', 'id_component');
    }
}

