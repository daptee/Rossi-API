<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriesStatus extends Model
{
    use HasFactory;

    protected $table = 'categories_status';

    protected $fillable = [
        'status_name',
    ];

    public $timestamps = false;

    public function productsCategories()
    {
        return $this->hasMany(ProductsCategories::class, 'status', 'id');
    }
}
