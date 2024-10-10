<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebContentAbout extends Model
{
    protected $table = 'web_content_about';
    
    use HasFactory;

    protected $fillable = ['id_user', 'data'];

    protected $casts = [
        'data' => 'array',
    ];
}
