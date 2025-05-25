<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkippedQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'unique_id',
    ];
}
