<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// ProductParentAttribute.php
class ProductParentAttribute extends Model
{
    protected $table = 'product_parent_attribute';

    protected $fillable = [
        'id_product',
        'id_attribute',
        '3d_file',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'id_product');
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'id_attribute');
    }
}
