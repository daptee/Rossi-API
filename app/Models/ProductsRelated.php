<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ProductParentAttribute.php
class ProductsRelated extends Model
{
    protected $table = 'products_related';
    protected $fillable = ['id_product', 'id_product_related'];

    public function related()
    {
        return $this->belongsTo(Product::class, 'id_product_related');
    }
}

