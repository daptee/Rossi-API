<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'id_category',
        'category',
        'img',
        'sub_img',
        'video',
        'icon',
        'color',
        'status',
        'grid',
        'meta_data'
    ];

    protected $casts = [
        'grid' => 'array',
        'meta_data' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'id_category')->with('categories', 'status');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status', 'id');
    }

    public function products()
    {
        return $this->hasMany(ProductCategory::class, 'id_categorie');
    }

}
