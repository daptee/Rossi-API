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
        'number',
        'locality_id',        
        'locality',
        'position',
        'postal_code',
        'web_url',
        'phone',
        'whatsapp',
        'email',
        'instagram',
        'facebook',
        'status'
    ];

    protected $casts = [
        'position' => 'array',
    ];

    public function locality()
    {
        return $this->belongsTo(Locality::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status', 'id');
    }
}

