<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = ['id_attribute', 'value', 'color'];

    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'id_attribute');
    }

    public function files()
    {
        return $this->hasMany(
            ProductAttributeValue::class,
            'id_product_atribute_value',
            'id'
        );
    }
}