<?php

// app/Models/ProductAttribute.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product3DModel extends Model
{
    use HasFactory;

    protected $table = 'product_3d_models';

    protected $fillable = [
        'id_product',
        'name',
        'glb_file_path',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }
}