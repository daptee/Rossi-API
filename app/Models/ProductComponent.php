<?php

// app/Models/ProductComponent.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductComponent extends Model
{
    use HasFactory;

    protected $fillable = [
        'id_product',
        'id_component',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function component()
    {
        return $this->belongsTo(Component::class, 'id_component');
    }
}