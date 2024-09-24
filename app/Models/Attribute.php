<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = ['id_attribute', 'name', 'status'];

    public function parent()
    {
        return $this->belongsTo(Attribute::class, 'id_attribute');
    }

    public function children()
    {
        return $this->hasMany(Attribute::class, 'id_attribute');
    }

    public function values()
    {
        return $this->hasMany(AttributeValue::class, 'id_attribute');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status', 'id');
    }
}