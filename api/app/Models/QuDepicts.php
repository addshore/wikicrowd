<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuDepicts extends Model
{
    use HasFactory;

    protected $fillable = [
        'mediainfo_id',
        'depicts_id',
        'img_url',
    ];
}
