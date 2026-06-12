<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionBank extends Model
{
    protected $table = 'question_bank';
    protected $fillable = ['course_id', 'module_id', 'lesson_id', 'type', 'question', 'options', 'correct_answer', 'explanation', 'difficulty', 'is_active', 'question_hash'];
    protected function casts(): array { return ['options' => 'array', 'is_active' => 'boolean']; }
}
