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

    public function answer()
    {
        return $this->hasMany(QuDepictsAnswer::class, 'qu_depicts_id');
    }

    public function edit()
    {
        return $this->hasMany(QuDepictsEdit::class, 'qu_depicts_id');
    }
}
