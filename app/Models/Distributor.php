<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'locality_id',
        'postal_code',
        'web_url',
        'phone',
        'whatsapp',
        'email',
        'instagram',
    ];

    public function locality()
    {
        return $this->belongsTo(Locality::class);
    }
}

