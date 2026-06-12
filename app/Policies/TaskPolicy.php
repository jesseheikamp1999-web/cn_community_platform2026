<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function update(User $user, Task $task): bool
    {
        return $task->creator_id === $user->id
            || $task->assignees()->whereKey($user->id)->exists()
            || $user->hasPermission('tasks.manage');
    }
}
