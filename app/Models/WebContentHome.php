<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebContentHome extends Model
{
    protected $table = 'web_content_home';
    
    use HasFactory;

    protected $fillable = ['date', 'id_user', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}
