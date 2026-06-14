<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\User;
use App\Services\StaffChatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StaffChatController extends Controller
{
    public function index(Request $request, StaffChatService $chat): View
    {
        $this->authorizeStaff($request->user());
        if (!$this->schemaReady()) {
            return view('dashboard.chat-setup');
        }

        $chat->syncChannels($request->user());

        $conversations = $this->conversations($request->user());
        $selected = $request->integer('gesprek')
            ? $conversations->firstWhere('id', $request->integer('gesprek'))
            : $conversations->first();
        if ($selected) {
            $this->markRead($selected, $request->user());
            $selected->unread_count = 0;
            $selected->load([
                'messages' => fn ($query) => $query->with($this->messageRelations())->latest()->limit(30),
                'participants.staffProfile',
            ]);
            $selected->setRelation('messages', $selected->messages->sortBy('created_at')->values());
            $selected->can_manage = $this->canModerate($selected, $request->user());
            $selected->is_muted = (bool) $selected->participants
                ->firstWhere('id', $request->user()->id)?->pivot?->is_muted;
            $selected->pinned_messages = $selected->messages()
                ->whereNotNull('pinned_at')
                ->with(['sender'])
                ->latest('pinned_at')
                ->limit(10)
                ->get();
            $selected->media = $selected->messages()
                ->whereHas('attachments')
                ->with(['attachments', 'sender'])
                ->latest()
                ->limit(30)
                ->get()
                ->flatMap->attachments;
        }

        $staffMembers = User::whereNot('role', 'member')
            ->where('id', '!=', $request->user()->id)
            ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'management' THEN 2 WHEN 'admin' THEN 3 WHEN 'moderator' THEN 4 WHEN 'helper' THEN 5 ELSE 6 END")
            ->orderBy('name')
            ->get();

        return view('dashboard.chat', compact('conversations', 'selected', 'staffMembers'));
    }

    public function install(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role->value === 'owner', 403);

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'chat_installation' => 'De chatdatabase kon niet automatisch worden bijgewerkt. Controleer de schrijfrechten en databasegebruiker in Plesk.',
            ]);
        }

        if (!$this->schemaReady()) {
            return back()->withErrors([
                'chat_installation' => 'De migratie is uitgevoerd, maar niet alle chattabellen zijn aangemaakt.',
            ]);
        }

        return redirect()->route('mijncn.chat')->with('status', 'Staff Messenger is geïnstalleerd.');
    }

    public function conversationsApi(Request $request, StaffChatService $chat): JsonResponse
    {
        $this->authorizeStaff($request->user());
        $chat->syncChannels($request->user());
        $this->touchPresence($request->user());

        return response()->json([
            'conversations' => $this->conversations($request->user())->map(fn ($conversation) => [
                'id' => $conversation->id,
                'name' => $conversation->display_name,
                'type' => $conversation->type,
                'unread_count' => $conversation->unread_count,
                'last_message' => $conversation->messages->first()
                    ? ($conversation->messages->first()->body ?: 'Afbeelding')
                    : null,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ]),
        ]);
    }

    public function start(Request $request, StaffChatService $chat): RedirectResponse
    {
        $this->authorizeStaff($request->user());
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $recipient = User::findOrFail($data['user_id']);
        abort_if($recipient->role->value === 'member' || $recipient->is($request->user()), 422);

        $conversation = $chat->direct($request->user(), $recipient);

        return redirect()->route('mijncn.chat', ['gesprek' => $conversation->id]);
    }

    public function createGroup(Request $request): RedirectResponse
    {
        $this->authorizeStaff($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'user_ids' => ['required', 'array', 'min:1', 'max:30'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', '!=', 'member'))],
        ]);
        $ids = collect($data['user_ids'])->push($request->user()->id)->unique()->values();
        $conversation = DB::transaction(function () use ($request, $data, $ids): ChatConversation {
            $conversation = ChatConversation::create([
                'name' => $data['name'],
                'type' => 'staff',
                'created_by' => $request->user()->id,
            ]);
            foreach ($ids as $id) {
                $conversation->participants()->attach($id, [
                    'is_admin' => $id === $request->user()->id,
                    'last_read_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $conversation;
        });

        return redirect()->route('mijncn.chat', ['gesprek' => $conversation->id]);
    }

    public function store(Request $request, ChatConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeParticipant($conversation, $request->user());
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt', 'max:10240'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
            'reply_to_id' => ['nullable', 'integer', 'exists:chat_messages,id'],
            'is_announcement' => ['nullable', 'boolean'],
            'requires_ack' => ['nullable', 'boolean'],
        ]);
        abort_if(
            blank(trim((string) ($data['body'] ?? '')))
            && !$request->hasFile('attachment')
            && !$request->hasFile('image'),
            422,
            'Een bericht of bestand is verplicht.'
        );
        if (!empty($data['reply_to_id'])) {
            abort_unless(
                $conversation->messages()->whereKey($data['reply_to_id'])->exists(),
                422
            );
        }
        $announcement = (bool) ($data['is_announcement'] ?? false);
        abort_if($announcement && !$this->canModerate($conversation, $request->user()), 403);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'reply_to_id' => $data['reply_to_id'] ?? null,
            'body' => trim((string) ($data['body'] ?? '')),
            'is_announcement' => $announcement,
            'requires_ack' => $announcement && (bool) ($data['requires_ack'] ?? false),
        ]);
        $file = $request->file('attachment') ?: $request->file('image');
        if ($file) {
            $path = $file->store('chat/'.now()->format('Y/m'), 'public');
            $message->attachments()->create([
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
        $conversation->update(['last_message_at' => $message->created_at]);
        $this->createMentionNotifications($message, $conversation, $request->user());
        $this->markRead($conversation, $request->user());
        $this->touchPresence($request->user());

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->messageData($message->load($this->messageRelations()), $request->user())], 201);
        }

        return redirect()->route('mijncn.chat', ['gesprek' => $conversation->id]);
    }

    public function sendApi(Request $request): RedirectResponse|JsonResponse
    {
        return $this->store(
            $request,
            ChatConversation::findOrFail($request->integer('conversation_id'))
        );
    }

    public function messagesApi(Request $request): JsonResponse
    {
        return $this->messages(
            $request,
            ChatConversation::findOrFail($request->integer('conversation_id'))
        );
    }

    public function messages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($conversation, $request->user());
        $after = max(0, $request->integer('after'));
        $before = max(0, $request->integer('before'));
        $query = $conversation->messages()->with($this->messageRelations());
        if ($before > 0) {
            $messages = $query->where('id', '<', $before)->latest('id')->limit(30)->get()->reverse()->values();
        } else {
            $messages = $query->where('id', '>', $after)->orderBy('id')->limit(100)->get();
        }
        $this->markRead($conversation, $request->user());
        $this->touchPresence($request->user());
        $typing = DB::table('chat_typing_statuses')
            ->join('users', 'users.id', '=', 'chat_typing_statuses.user_id')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', '!=', $request->user()->id)
            ->where('expires_at', '>', now())
            ->pluck('users.name');

        return response()->json([
            'messages' => $messages->map(fn ($message) => $this->messageData($message, $request->user())),
            'typing' => $typing,
            'has_more' => $before > 0 && $messages->count() === 30,
        ]);
    }

    public function typing(Request $request): JsonResponse
    {
        $conversation = ChatConversation::findOrFail($request->integer('conversation_id'));
        $this->authorizeParticipant($conversation, $request->user());
        DB::table('chat_typing_statuses')->updateOrInsert(
            ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id],
            ['expires_at' => now()->addSeconds(4), 'created_at' => now(), 'updated_at' => now()]
        );
        $this->touchPresence($request->user());

        return response()->json(['ok' => true]);
    }

    public function read(Request $request): JsonResponse
    {
        $conversation = ChatConversation::findOrFail($request->integer('conversation_id'));
        $this->authorizeParticipant($conversation, $request->user());
        $this->markRead($conversation, $request->user());

        return response()->json(['ok' => true]);
    }

    public function presence(Request $request): JsonResponse
    {
        $this->authorizeStaff($request->user());
        $this->touchPresence($request->user());
        $ids = collect(explode(',', (string) $request->query('user_ids')))
            ->filter(fn ($id) => ctype_digit($id))
            ->map(fn ($id) => (int) $id)
            ->take(50);

        return response()->json([
            'users' => DB::table('chat_user_presences')
                ->whereIn('user_id', $ids)
                ->get()
                ->map(fn ($presence) => [
                    'user_id' => $presence->user_id,
                    'online' => $presence->is_online
                        && now()->diffInSeconds(Carbon::parse($presence->last_seen_at)) <= 30,
                    'last_seen_at' => $presence->last_seen_at,
                ]),
        ]);
    }

    public function updateMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeParticipant($message->conversation, $request->user());
        abort_unless($message->sender_id === $request->user()->id && !$message->deleted_at, 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => trim($data['body']), 'edited_at' => now()]);

        return response()->json(['message' => $this->messageData($message->load($this->messageRelations()), $request->user())]);
    }

    public function deleteMessage(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeParticipant($message->conversation, $request->user());
        abort_unless($message->sender_id === $request->user()->id, 403);
        foreach ($message->attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
        $message->attachments()->delete();
        $message->update(['body' => '', 'deleted_at' => now()]);

        return response()->json(['ok' => true, 'message_id' => $message->id]);
    }

    public function search(Request $request): JsonResponse
    {
        $conversation = ChatConversation::findOrFail($request->integer('conversation_id'));
        $this->authorizeParticipant($conversation, $request->user());
        $query = trim((string) $request->query('q'));
        abort_if(mb_strlen($query) < 2, 422);

        return response()->json([
            'messages' => $conversation->messages()
                ->with($this->messageRelations())
                ->whereNull('deleted_at')
                ->where('body', 'like', '%'.$query.'%')
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn ($message) => $this->messageData($message, $request->user())),
        ]);
    }

    public function react(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeParticipant($message->conversation, $request->user());
        $data = $request->validate(['emoji' => ['required', Rule::in(['👍', '❤️', '😂', '🎉', '👀', '✅'])]]);
        $existing = $message->reactions()
            ->where('user_id', $request->user()->id)
            ->where('emoji', $data['emoji'])
            ->first();
        $existing
            ? $existing->delete()
            : $message->reactions()->create(['user_id' => $request->user()->id, 'emoji' => $data['emoji']]);

        return response()->json([
            'reactions' => $this->reactionData($message->fresh()->load('reactions'), $request->user()),
        ]);
    }

    public function pin(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeParticipant($message->conversation, $request->user());
        abort_unless($this->canModerate($message->conversation, $request->user()), 403);
        $pinned = !$message->pinned_at;
        $message->update([
            'pinned_at' => $pinned ? now() : null,
            'pinned_by' => $pinned ? $request->user()->id : null,
        ]);

        return response()->json(['pinned' => $pinned]);
    }

    public function acknowledge(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeParticipant($message->conversation, $request->user());
        abort_unless($message->requires_ack, 422);
        DB::table('chat_message_acknowledgements')->updateOrInsert(
            ['message_id' => $message->id, 'user_id' => $request->user()->id],
            ['acknowledged_at' => now()]
        );

        return response()->json(['ok' => true]);
    }

    public function mute(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->authorizeParticipant($conversation, $request->user());
        $muted = !(bool) $conversation->participants()
            ->whereKey($request->user()->id)
            ->firstOrFail()
            ->pivot
            ->is_muted;
        $conversation->participants()->updateExistingPivot($request->user()->id, ['is_muted' => $muted]);

        return back()->with('success', $muted ? 'Meldingen zijn gedempt.' : 'Meldingen staan weer aan.');
    }

    public function updateGroup(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->authorizeParticipant($conversation, $request->user());
        abort_if($conversation->type === 'direct', 422);
        abort_unless($this->canModerate($conversation, $request->user()), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'retention_days' => ['nullable', 'integer', Rule::in([7, 30, 90, 180, 365])],
            'user_ids' => ['required', 'array', 'min:2', 'max:50'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', '!=', 'member'))],
            'admin_ids' => ['nullable', 'array'],
            'admin_ids.*' => ['integer'],
        ]);
        $updates = [
            'name' => $data['name'],
            'retention_days' => $data['retention_days'] ?? null,
        ];
        if ($request->hasFile('avatar')) {
            if ($conversation->avatar_path) {
                Storage::disk('public')->delete($conversation->avatar_path);
            }
            $updates['avatar_path'] = $request->file('avatar')->store('chat/groups', 'public');
        }
        $conversation->update($updates);
        $admins = collect($data['admin_ids'] ?? [])->push($request->user()->id)->unique();
        $members = collect($data['user_ids'])->push($request->user()->id)->unique();
        $sync = $members->mapWithKeys(fn ($id) => [
            $id => ['is_admin' => $admins->contains((int) $id)],
        ])->all();
        $conversation->participants()->sync($sync);

        return redirect()->route('mijncn.chat', ['gesprek' => $conversation->id])
            ->with('success', 'Groepsinstellingen bijgewerkt.');
    }

    public function archive(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $this->authorizeParticipant($conversation, $request->user());
        abort_unless($this->canModerate($conversation, $request->user()), 403);
        $conversation->update(['archived_at' => $conversation->archived_at ? null : now()]);

        return redirect()->route('mijncn.chat')->with('success', 'Gesprek gearchiveerd.');
    }

    public function createTask(Request $request, ChatMessage $message): JsonResponse
    {
        $this->authorizeParticipant($message->conversation, $request->user());
        abort_if($message->deleted_at, 422);
        $boardId = DB::table('boards')->orderBy('id')->value('id');
        if (!$boardId) {
            $boardId = DB::table('boards')->insertGetId([
                'name' => 'Staff Taken',
                'slug' => 'staff-taken',
                'description' => 'Taken vanuit Staff Messenger',
                'is_private' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $task = Task::create([
            'board_id' => $boardId,
            'creator_id' => $request->user()->id,
            'title' => Str::limit($message->body ?: 'Opvolgen van chatbijlage', 120, ''),
            'description' => 'Aangemaakt vanuit Staff Messenger, gesprek #'.$message->conversation_id.', bericht #'.$message->id,
            'status' => 'open',
            'priority' => 'normal',
            'type' => 'community',
            'is_public' => false,
        ]);
        $message->update(['task_id' => $task->id]);

        return response()->json(['task_id' => $task->id, 'title' => $task->title], 201);
    }

    private function conversations(User $user)
    {
        return ChatConversation::whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->whereNull('archived_at')
            ->with([
                'participants',
                'messages' => fn ($query) => $query->with(['sender', 'attachments'])->latest()->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->orderBy('type')
            ->get()
            ->map(function (ChatConversation $conversation) use ($user): ChatConversation {
                $conversation->display_name = $conversation->type === 'direct'
                    ? ($conversation->participants->firstWhere('id', '!=', $user->id)?->name ?? 'Privégesprek')
                    : $conversation->name;
                $conversation->unread_count = $conversation->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('created_at', '>', $conversation->participants->firstWhere('id', $user->id)?->pivot?->last_read_at ?? '1970-01-01')
                    ->count();

                return $conversation;
            });
    }

    private function markRead(ChatConversation $conversation, User $user): void
    {
        $latestMessageId = $conversation->messages()->max('id');
        $conversation->participants()->updateExistingPivot($user->id, [
            'last_read_at' => now(),
            'updated_at' => now(),
        ]);
        if ($latestMessageId) {
            DB::table('chat_message_reads')->updateOrInsert(
                ['message_id' => $latestMessageId, 'user_id' => $user->id],
                ['read_at' => now()]
            );
        }
    }

    private function authorizeParticipant(ChatConversation $conversation, User $user): void
    {
        $this->authorizeStaff($user);
        abort_unless($conversation->participants()->whereKey($user->id)->exists(), 403);
    }

    private function authorizeStaff(User $user): void
    {
        abort_if($user->role->value === 'member', 403);
    }

    private function messageData($message, User $viewer): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'deleted' => (bool) $message->deleted_at,
            'edited' => (bool) $message->edited_at,
            'mine' => $message->sender_id === $viewer->id,
            'sender' => $message->sender->name,
            'avatar' => $message->sender->discord_avatar_url,
            'initials' => strtoupper(substr($message->sender->name, 0, 2)),
            'time' => $message->created_at->format('H:i'),
            'date' => $message->created_at->translatedFormat('d M Y'),
            'reply' => $message->replyTo ? [
                'id' => $message->replyTo->id,
                'sender' => $message->replyTo->sender?->name,
                'body' => Str::limit($message->replyTo->body ?: 'Bijlage', 100),
            ] : null,
            'pinned' => (bool) $message->pinned_at,
            'announcement' => (bool) $message->is_announcement,
            'requires_ack' => (bool) $message->requires_ack,
            'acknowledged' => $message->acknowledgements->contains('id', $viewer->id),
            'task' => $message->task ? ['id' => $message->task->id, 'title' => $message->task->title] : null,
            'reactions' => $this->reactionData($message, $viewer),
            'read' => $message->sender_id === $viewer->id
                && $message->readers->where('id', '!=', $viewer->id)->isNotEmpty(),
            'attachments' => $message->attachments->map(fn ($attachment) => [
                'url' => Storage::disk($attachment->disk)->url($attachment->path),
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'is_image' => str_starts_with($attachment->mime_type, 'image/'),
            ])->values(),
        ];
    }

    private function reactionData(ChatMessage $message, User $viewer): array
    {
        return $message->reactions
            ->groupBy('emoji')
            ->map(fn ($reactions, $emoji) => [
                'emoji' => $emoji,
                'count' => $reactions->count(),
                'mine' => $reactions->contains('user_id', $viewer->id),
            ])
            ->values()
            ->all();
    }

    private function messageRelations(): array
    {
        return [
            'sender',
            'attachments',
            'readers',
            'reactions',
            'acknowledgements',
            'task',
            'replyTo.sender',
        ];
    }

    private function canModerate(ChatConversation $conversation, User $user): bool
    {
        if (in_array($user->role->value, ['owner', 'management'], true)) {
            return true;
        }

        return (bool) $conversation->participants()
            ->whereKey($user->id)
            ->first()?->pivot?->is_admin;
    }

    private function createMentionNotifications(ChatMessage $message, ChatConversation $conversation, User $sender): void
    {
        preg_match_all('/@([A-Za-z0-9_.-]+)/u', $message->body, $matches);
        $handles = collect($matches[1] ?? [])->map(fn ($handle) => mb_strtolower($handle));
        $recipients = $conversation->participants()
            ->where('users.id', '!=', $sender->id)
            ->get()
            ->filter(function (User $user) use ($handles, $message): bool {
                return $message->is_announcement
                    || $handles->contains(mb_strtolower((string) $user->discord_username))
                    || $handles->contains(mb_strtolower(str_replace(' ', '.', $user->name)));
            });
        foreach ($recipients as $recipient) {
            if ($recipient->pivot->is_muted && !$message->is_announcement) {
                continue;
            }
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => $message->is_announcement ? 'chat_announcement' : 'chat_mention',
                'notifiable_type' => User::class,
                'notifiable_id' => $recipient->id,
                'data' => json_encode([
                    'title' => $message->is_announcement ? 'Nieuwe staffmededeling' : $sender->name.' noemde je',
                    'message' => Str::limit($message->body, 150),
                    'url' => route('mijncn.chat', ['gesprek' => $conversation->id]),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function touchPresence(User $user): void
    {
        DB::table('chat_user_presences')->updateOrInsert(
            ['user_id' => $user->id],
            ['last_seen_at' => now(), 'is_online' => true, 'created_at' => now(), 'updated_at' => now()]
        );
        $user->update(['last_seen_at' => now()]);
    }

    private function schemaReady(): bool
    {
        return collect([
            'chat_conversations',
            'chat_participants',
            'chat_messages',
            'chat_message_reads',
            'chat_typing_statuses',
            'chat_user_presences',
            'chat_message_attachments',
            'chat_message_reactions',
            'chat_message_acknowledgements',
        ])->every(fn (string $table): bool => Schema::hasTable($table))
            && Schema::hasColumns('chat_conversations', ['avatar_path', 'retention_days', 'archived_at'])
            && Schema::hasColumns('chat_messages', ['reply_to_id', 'task_id', 'pinned_at']);
    }
}
