<?php

// app/Models/ProductGallery.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGallery extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_product', // Asegúrate de que se llame 'id_product'
        'file',
        'thumbnail_file'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }
}
