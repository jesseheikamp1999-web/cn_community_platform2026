<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizAttemptAnswer extends Model
{
    protected $fillable = ['attempt_id', 'question_id', 'selected_answer', 'correct_answer', 'is_correct'];
    protected function casts(): array { return ['is_correct' => 'boolean']; }
}
