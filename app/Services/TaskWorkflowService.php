<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskWorkflowService
{
    public function canClaim(Task $task, User $user): bool
    {
        if ($task->claimed_by || in_array($task->status, ['completed', 'rejected'], true)) {
            return false;
        }

        if ($user->hasPermission('tasks.manage') || in_array($user->role, [UserRole::Owner, UserRole::Management], true)) {
            return true;
        }

        if (!$task->required_role) {
            return $user->role !== UserRole::Member;
        }

        $hierarchy = ['member' => 0, 'helper' => 1, 'moderator' => 2, 'jury' => 2, 'admin' => 3, 'partner_manager' => 3, 'management' => 4, 'owner' => 5];

        return ($hierarchy[$user->role->value] ?? 0) >= ($hierarchy[$task->required_role] ?? 99);
    }

    public function claim(Task $task, User $user): Task
    {
        if (!$this->canClaim($task, $user)) {
            throw ValidationException::withMessages(['task' => 'Deze taak kan niet door jou worden geclaimd.']);
        }

        return DB::transaction(function () use ($task, $user) {
            $task = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if (!$this->canClaim($task, $user)) {
                throw ValidationException::withMessages(['task' => 'Deze taak is inmiddels door iemand anders geclaimd.']);
            }
            $task->update(['claimed_by' => $user->id, 'status' => 'in_progress']);
            $this->log($task, $user, 'claimed', null, $user->name);

            return $task->refresh();
        });
    }

    public function complete(Task $task, User $user, ?string $note = null): Task
    {
        $canComplete = $task->claimed_by === $user->id
            || $task->assignees()->whereKey($user->id)->exists()
            || $user->hasPermission('tasks.manage');

        if (!$canComplete) {
            throw ValidationException::withMessages(['task' => 'Je mag deze taak niet voltooien.']);
        }

        return DB::transaction(function () use ($task, $user, $note) {
            $oldStatus = $task->status;
            $task->update([
                'status' => 'completed',
                'progress' => 100,
                'completed_by' => $user->id,
                'completed_at' => now(),
            ]);

            if ($note) {
                DB::table('task_comments')->insert([
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'body' => $note,
                    'is_internal' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->log($task, $user, 'completed', $oldStatus, $note);

            return $task->refresh();
        });
    }

    private function log(Task $task, User $user, string $action, ?string $oldValue, ?string $newValue): void
    {
        DB::table('task_logs')->insert([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
