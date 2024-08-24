<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStatus extends Model
{
    use HasFactory;

    protected $table = 'product_status';

    protected $fillable = ['status_name'];

    // Definimos la relación inversa
    public function products()
    {
        return $this->hasMany(Product::class, 'status', 'id');
    }
}
