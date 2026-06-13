<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StaffChatService
{
    public function syncChannels(User $user): void
    {
        $staff = ChatConversation::firstOrCreate(
            ['type' => 'staff', 'name' => 'Staff Chat'],
            ['created_by' => $user->id]
        );
        $this->syncEligibleParticipants($staff, ['helper', 'moderator', 'admin', 'management', 'owner', 'jury', 'partner_manager']);

        if (in_array($user->role->value, ['management', 'owner'], true)) {
            $management = ChatConversation::firstOrCreate(
                ['type' => 'management', 'name' => 'Management Chat'],
                ['created_by' => $user->id]
            );
            $this->syncEligibleParticipants($management, ['management', 'owner']);
        }
    }

    public function direct(User $creator, User $recipient): ChatConversation
    {
        $existing = ChatConversation::where('type', 'direct')
            ->whereHas('participants', fn ($query) => $query->whereKey($creator->id))
            ->whereHas('participants', fn ($query) => $query->whereKey($recipient->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($creator, $recipient): ChatConversation {
            $conversation = ChatConversation::create([
                'type' => 'direct',
                'created_by' => $creator->id,
            ]);
            $conversation->participants()->attach([$creator->id, $recipient->id], [
                'last_read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $conversation;
        });
    }

    private function syncEligibleParticipants(ChatConversation $conversation, array $roles): void
    {
        $ids = User::whereIn('role', $roles)->pluck('id');
        foreach ($ids as $id) {
            $conversation->participants()->syncWithoutDetaching([
                $id => ['created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }
}
