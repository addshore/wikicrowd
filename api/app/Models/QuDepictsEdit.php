<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuDepictsEdit extends Model
{
    use HasFactory;

    protected $fillable = [
        'qu_depicts_id',
        'user_id',
        'revision_id',
    ];

    public function question()
    {
        return $this->belongsTo(QuDepicts::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
