<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'parent',
        'layout',
    ];

    public function parentGroup()
    {
        return $this->belongsTo(QuestionGroup::class, 'parent');
    }

    public function subGroups()
    {
        return $this->hasMany(QuestionGroup::class, 'parent');
    }

    public function question()
    {
        return $this->hasMany(Question::class, 'question_group_id');
    }
}
