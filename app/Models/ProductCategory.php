<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
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
        'status'
    ];

    public function parent()
    {
        return $this->belongsTo(ProductCategory::class, 'id_category');
    }
}
