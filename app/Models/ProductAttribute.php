<?php

// app/Models/ProductAttribute.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_product',
        'id_attribute_value',
        'img',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function attribute()
    {
        return $this->belongsTo(AttributeValue::class, 'id_attribute');
    }
}
