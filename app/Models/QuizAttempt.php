<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizAttempt extends Model
{
    protected $fillable = ['user_id', 'course_id', 'module_id', 'lesson_id', 'type', 'score', 'passed', 'time_spent_seconds', 'tab_switches', 'started_at', 'submitted_at'];
    protected function casts(): array { return ['passed' => 'boolean', 'started_at' => 'datetime', 'submitted_at' => 'datetime']; }
    public function answers(): HasMany { return $this->hasMany(QuizAttemptAnswer::class, 'attempt_id'); }
}
