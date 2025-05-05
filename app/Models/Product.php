<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        'thumbnail_main_img',
        'sub_img',
        '3d_file',
        'customizable',
        'thumbnail_sub_img',
        'main_video',
        'file_data_sheet',
        'featured',
        'meta_data',
    ];

    protected $casts = [
        'meta_data' => 'array',
    ];

    public function attributes()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attributes', 'id_product', 'id_attribute_value')
            ->withPivot(['id', 'img', 'thumbnail_img'])
            ->with('attribute');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_categories', 'id_product', 'id_categorie');
    }

    public function materials()
    {
        return $this->belongsToMany(MaterialValue::class, 'product_materials', 'id_product', 'id_material')
            ->withPivot(['id', 'img', 'thumbnail_img'])
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

    public function parentAttributes3d()
    {
        return $this->hasMany(ProductParentAttribute::class, 'id_product');
    }

    public function attributeFiles()
    {
        return $this->hasMany(ProductAttributeValue::class, 'id_product');
    }

    public function materialFiles()
    {
        return $this->hasMany(ProductMaterialValue::class, 'id_product');
    }

}

