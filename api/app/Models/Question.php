<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_group_id',
        'unique_id', // Unique within the group
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function group()
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public function answer()
    {
        return $this->hasMany(Answer::class, 'question_id');
    }

    public function edit()
    {
        return $this->hasMany(Edit::class, 'question_id');
    }
}
