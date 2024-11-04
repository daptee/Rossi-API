<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductsCategories extends Model
{
    use HasFactory;

    protected $table = 'products_categories';

    protected $fillable = [
        'id_category',
        'category',
        'img',
        'video',
        'icon',
        'color',
        'status',
        'grid'
    ];

    protected $casts = [
        'grid' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(ProductsCategories::class, 'id_category');
    }

    public function categories()
    {
        return $this->hasMany(ProductsCategories::class, 'id_category')->with('categories', 'status');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status', 'id');
    }
}
