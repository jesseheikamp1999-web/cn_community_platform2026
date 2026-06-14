@extends('layouts.dashboard')
@section('title', 'Staff Messenger - MijnCN')

@section('content')
<main class="chat-shell">
    <section class="chat-app" data-chat-app data-conversations-url="{{ route('chat.api.conversations') }}">
        <aside class="chat-sidebar">
            <header>
                <div><span>MIJNCN</span><h1>Messenger</h1></div>
                <div class="chat-header-actions"><button type="button" data-theme-toggle aria-label="Thema wisselen">&#9680;</button><button type="button" data-new-chat aria-label="Nieuw gesprek">+</button></div>
            </header>

            <div class="chat-search"><span>&#8981;</span><input type="search" placeholder="Zoek gesprek..." data-chat-search></div>

            <div class="chat-new-panel" data-new-chat-panel hidden>
                <strong>Nieuw privégesprek</strong>
                <form method="post" action="{{ route('mijncn.chat.start') }}">
                    @csrf
                    <select name="user_id" required>
                        <option value="">Kies een stafflid</option>
                        @foreach($staffMembers as $staffMember)
                            <option value="{{ $staffMember->id }}">{{ $staffMember->name }} · {{ $staffMember->role->label() }}</option>
                        @endforeach
                    </select>
                    <button class="button button-primary button-small">Start gesprek</button>
                </form>
                <details class="chat-group-create">
                    <summary>Groepsgesprek maken</summary>
                    <form method="post" action="{{ route('mijncn.chat.groups.store') }}">
                        @csrf
                        <input name="name" maxlength="80" required placeholder="Naam van de groep">
                        <select name="user_ids[]" multiple required size="5">
                            @foreach($staffMembers as $staffMember)
                                <option value="{{ $staffMember->id }}">{{ $staffMember->name }}</option>
                            @endforeach
                        </select>
                        <button class="button button-secondary button-small">Groep maken</button>
                    </form>
                </details>
            </div>

            <nav class="chat-conversations">
                @foreach($conversations as $conversation)
                    @php($other = $conversation->type === 'direct' ? $conversation->participants->firstWhere('id', '!=', auth()->id()) : null)
                    @php($lastMessage = $conversation->messages->first())
                    <a href="{{ route('mijncn.chat', ['gesprek' => $conversation->id]) }}" class="{{ $selected?->id === $conversation->id ? 'active' : '' }}" data-chat-name="{{ strtolower($conversation->display_name) }}" data-conversation-list-id="{{ $conversation->id }}">
                        <div class="chat-list-avatar {{ $conversation->type }}">
                            @if($other)
                                @include('components.user-avatar', ['user' => $other])
                            @elseif($conversation->avatar_path)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($conversation->avatar_path) }}" alt="">
                            @else
                                <span>{{ $conversation->type === 'management' ? 'MG' : 'CN' }}</span>
                            @endif
                        </div>
                        <div><strong>{{ $conversation->display_name }}</strong><p data-conversation-preview>{{ $lastMessage ? \Illuminate\Support\Str::limit(($lastMessage->sender_id === auth()->id() ? 'Jij: ' : '').($lastMessage->body ?: 'Afbeelding'), 42) : 'Nog geen berichten' }}</p></div>
                        <time data-conversation-time>{{ $lastMessage?->created_at?->format('H:i') }}</time>
                        <b data-conversation-unread
                           @if(!$conversation->unread_count)
                               hidden
                           @endif>{{ $conversation->unread_count }}</b>
                    </a>
                @endforeach
            </nav>
        </aside>

        <section class="chat-room">
            @if($selected)
                @php($other = $selected->type === 'direct' ? $selected->participants->firstWhere('id', '!=', auth()->id()) : null)
                <header class="chat-room-header">
                    <a class="chat-mobile-back" href="{{ route('mijncn.chat') }}" aria-label="Terug naar gesprekken">&larr;</a>
                    <div class="chat-list-avatar {{ $selected->type }}">
                        @if($other)
                            @include('components.user-avatar', ['user' => $other])
                        @elseif($selected->avatar_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($selected->avatar_path) }}" alt="">
                        @else
                            <span>{{ $selected->type === 'management' ? 'MG' : 'CN' }}</span>
                        @endif
                    </div>
                    <div>
                        <h2>{{ $selected->display_name }}</h2>
                        <p>
                            @if($other)
                                <span class="chat-presence-dot" data-presence-dot></span>
                                <span data-presence-label>{{ $other->role->label() }}</span>
                            @else
                                {{ $selected->participants->count().' deelnemers' }}
                            @endif
                        </p>
                    </div>
                    <div class="chat-room-tools">
                        <button type="button" data-search-toggle aria-label="Zoeken">&#8981;</button>
                        <button type="button" data-details-toggle aria-label="Gespreksinformatie">&#9432;</button>
                        <span class="chat-secure">Intern CN</span>
                    </div>
                </header>

                <div class="chat-pinned-bar" @if($selected->pinned_messages->isEmpty()) hidden @endif data-pinned-bar>
                    <strong>Vastgezet</strong>
                    <span>{{ $selected->pinned_messages->first()?->body ?: 'Bijlage' }}</span>
                </div>

                <div class="chat-messages"
                     data-chat-messages
                     data-conversation-id="{{ $selected->id }}"
                     data-can-manage="{{ $selected->can_manage ? '1' : '0' }}"
                     data-fetch-url="{{ route('chat.api.messages') }}"
                     data-send-url="{{ route('chat.api.send') }}"
                     data-typing-url="{{ route('chat.api.typing') }}"
                     data-read-url="{{ route('chat.api.read') }}"
                     data-presence-url="{{ route('chat.api.presence') }}"
                     data-presence-user-id="{{ $other?->id }}"
                     data-update-template="{{ route('chat.api.messages.update', ['message' => '__ID__']) }}"
                     data-delete-template="{{ route('chat.api.messages.delete', ['message' => '__ID__']) }}"
                     data-react-template="{{ route('chat.api.messages.react', ['message' => '__ID__']) }}"
                     data-pin-template="{{ route('chat.api.messages.pin', ['message' => '__ID__']) }}"
                     data-ack-template="{{ route('chat.api.messages.acknowledge', ['message' => '__ID__']) }}"
                     data-task-template="{{ route('chat.api.messages.task', ['message' => '__ID__']) }}"
                     data-search-url="{{ route('chat.api.search') }}">
                    @forelse($selected->messages as $message)
                        <article class="{{ $message->sender_id === auth()->id() ? 'mine' : '' }} {{ $message->is_announcement ? 'announcement' : '' }}" data-message-id="{{ $message->id }}">
                            @if($message->sender_id !== auth()->id())
                                <div class="chat-message-avatar">@include('components.user-avatar', ['user' => $message->sender])</div>
                            @endif
                            <div>
                                <span>{{ $message->sender_id === auth()->id() ? 'Jij' : $message->sender->name }}</span>
                                @if($message->replyTo)
                                    <button class="chat-reply-preview" type="button" data-jump-message="{{ $message->replyTo->id }}">
                                        <strong>{{ $message->replyTo->sender?->name }}</strong>
                                        {{ \Illuminate\Support\Str::limit($message->replyTo->body ?: 'Bijlage', 90) }}
                                    </button>
                                @endif
                                @if($message->is_announcement)
                                    <b class="chat-announcement-label">Staffmededeling</b>
                                @endif
                                <p class="{{ $message->deleted_at ? 'deleted' : '' }}">{{ $message->deleted_at ? 'Dit bericht is verwijderd.' : $message->body }}</p>
                                @foreach($message->attachments as $attachment)
                                    @php($attachmentUrl = \Illuminate\Support\Facades\Storage::disk($attachment->disk)->url($attachment->path))
                                    @if(str_starts_with($attachment->mime_type, 'image/'))
                                        <button class="chat-image" type="button" data-lightbox="{{ $attachmentUrl }}"><img loading="lazy" src="{{ $attachmentUrl }}" alt="{{ $attachment->original_name }}"></button>
                                    @else
                                        <a class="chat-file" href="{{ $attachmentUrl }}" download><b>{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</b><span>{{ $attachment->original_name }}<small>{{ number_format($attachment->size / 1024, 0) }} KB</small></span></a>
                                    @endif
                                @endforeach
                                @if($message->task)
                                    <a class="chat-task-link" href="{{ route('mijncn.module', 'tasks') }}">Taak #{{ $message->task->id }}: {{ $message->task->title }}</a>
                                @endif
                                <div class="chat-reactions" data-reactions>
                                    @foreach($message->reactions->groupBy('emoji') as $emoji => $reactions)
                                        <button type="button" class="{{ $reactions->contains('user_id', auth()->id()) ? 'mine' : '' }}" data-quick-react="{{ $emoji }}">{{ $emoji }} {{ $reactions->count() }}</button>
                                    @endforeach
                                </div>
                                <time>
                                    {{ $message->created_at->format('H:i') }}
                                    @if($message->edited_at)
                                        &middot; bewerkt
                                    @endif
                                    @if($message->sender_id === auth()->id() && $message->readers->where('id', '!=', auth()->id())->isNotEmpty())
                                        &middot; gelezen
                                    @endif
                                </time>
                                @if(!$message->deleted_at)
                                    <nav class="chat-message-actions">
                                        <button type="button" data-reply-message>Antwoord</button>
                                        <button type="button" data-reaction-menu>Reageer</button>
                                        @if($selected->can_manage)<button type="button" data-pin-message>{{ $message->pinned_at ? 'Losmaken' : 'Vastzetten' }}</button>@endif
                                        <button type="button" data-task-message>Maak taak</button>
                                        @if($message->sender_id === auth()->id())
                                            <button type="button" data-edit-message>Bewerk</button>
                                            <button type="button" data-delete-message>Verwijder</button>
                                        @endif
                                    </nav>
                                    <div class="chat-reaction-menu" data-reaction-picker hidden>
                                        @foreach(['👍', '❤️', '😂', '🎉', '👀', '✅'] as $emoji)
                                            <button type="button" data-quick-react="{{ $emoji }}">{{ $emoji }}</button>
                                        @endforeach
                                    </div>
                                    @if($message->requires_ack && !$message->acknowledgements->contains('id', auth()->id()))
                                        <button class="chat-ack-button" type="button" data-ack-message>Gelezen en begrepen</button>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="chat-empty" data-chat-empty><strong>Begin het gesprek</strong><p>Stuur het eerste bericht naar {{ $selected->display_name }}.</p></div>
                    @endforelse
                </div>
                <div class="chat-typing" data-typing-indicator hidden><i></i><i></i><i></i><span></span></div>

                <form class="chat-composer" method="post" action="{{ route('chat.api.send') }}" enctype="multipart/form-data" data-chat-form>
                    @csrf
                    <input type="hidden" name="conversation_id" value="{{ $selected->id }}">
                    <input type="hidden" name="reply_to_id" value="" data-reply-id>
                    <div class="chat-reply-compose" data-reply-compose hidden><span></span><button type="button" data-cancel-reply>&times;</button></div>
                    <button class="chat-emoji" type="button" title="Emoji toevoegen" data-chat-emoji>&#9786;</button>
                    <label class="chat-upload" title="Bestand toevoegen"><input type="file" name="attachment" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt" data-chat-file>&#128206;</label>
                    <textarea name="body" rows="1" maxlength="4000" placeholder="Typ een bericht..." data-chat-input></textarea>
                    <button type="submit" aria-label="Bericht versturen">&#10148;</button>
                    @if($selected->can_manage)
                        <label class="chat-announcement-option"><input type="checkbox" name="is_announcement" value="1"> Mededeling</label>
                        <label class="chat-announcement-option"><input type="checkbox" name="requires_ack" value="1"> Bevestiging verplicht</label>
                    @endif
                </form>
            @else
                <div class="chat-no-room"><strong>Staff Messenger</strong><p>Start een gesprek met een collega.</p></div>
            @endif
        </section>

        @if($selected)
            <aside class="chat-details" data-chat-details hidden>
                <header><strong>Gespreksinformatie</strong><button type="button" data-details-close>&times;</button></header>
                <section>
                    <h3>Deelnemers ({{ $selected->participants->count() }})</h3>
                    <div class="chat-member-list">
                        @foreach($selected->participants as $participant)
                            <article>
                                @include('components.user-avatar', ['user' => $participant])
                                <div><strong>{{ $participant->name }}</strong><span>{{ $participant->role->label() }}@if($participant->pivot->is_admin) &middot; beheerder @endif</span></div>
                                <b class="{{ $participant->isCurrentlyAbsent() ? 'absent' : '' }}">{{ $participant->isCurrentlyAbsent() ? 'Afwezig' : 'Beschikbaar' }}</b>
                            </article>
                        @endforeach
                    </div>
                </section>
                <section>
                    <h3>Bestanden en afbeeldingen</h3>
                    <div class="chat-media-grid">
                        @forelse($selected->media as $attachment)
                            @php($mediaUrl = \Illuminate\Support\Facades\Storage::disk($attachment->disk)->url($attachment->path))
                            @if(str_starts_with($attachment->mime_type, 'image/'))
                                <button type="button" data-lightbox="{{ $mediaUrl }}"><img loading="lazy" src="{{ $mediaUrl }}" alt=""></button>
                            @else
                                <a href="{{ $mediaUrl }}" download>{{ strtoupper(pathinfo($attachment->original_name, PATHINFO_EXTENSION)) }}</a>
                            @endif
                        @empty
                            <p>Nog geen gedeelde bestanden.</p>
                        @endforelse
                    </div>
                </section>
                <section class="chat-detail-actions">
                    <form method="post" action="{{ route('mijncn.chat.mute', $selected) }}">@csrf<button class="button button-secondary button-small">{{ $selected->is_muted ? 'Meldingen aanzetten' : 'Meldingen dempen' }}</button></form>
                </section>
                @if($selected->type !== 'direct' && $selected->can_manage)
                    <details class="chat-group-settings">
                        <summary>Groep beheren</summary>
                        <form method="post" action="{{ route('mijncn.chat.groups.update', $selected) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <label>Groepsnaam<input name="name" value="{{ $selected->name }}" required maxlength="80"></label>
                            <label>Groepsafbeelding<input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label>
                            <label>Bewaartermijn<select name="retention_days"><option value="">Onbeperkt</option>@foreach([7,30,90,180,365] as $days)<option value="{{ $days }}" @selected($selected->retention_days === $days)>{{ $days }} dagen</option>@endforeach</select></label>
                            <fieldset><legend>Deelnemers</legend>
                                @foreach(collect([auth()->user()])->merge($staffMembers)->unique('id') as $staffMember)
                                    <label><input type="checkbox" name="user_ids[]" value="{{ $staffMember->id }}" @checked($selected->participants->contains('id', $staffMember->id))> {{ $staffMember->name }}</label>
                                @endforeach
                            </fieldset>
                            <fieldset><legend>Groepsbeheerders</legend>
                                @foreach($selected->participants as $participant)
                                    <label><input type="checkbox" name="admin_ids[]" value="{{ $participant->id }}" @checked($participant->pivot->is_admin)> {{ $participant->name }}</label>
                                @endforeach
                            </fieldset>
                            <button class="button button-primary button-small">Wijzigingen opslaan</button>
                        </form>
                    </details>
                    <form method="post" action="{{ route('mijncn.chat.archive', $selected) }}">@csrf<button class="chat-danger-action">Gesprek archiveren</button></form>
                @endif
            </aside>

            <aside class="chat-search-panel" data-chat-search-panel hidden>
                <header><input type="search" placeholder="Zoek in dit gesprek..." data-message-search><button type="button" data-search-close>&times;</button></header>
                <div data-search-results><p>Voer minimaal twee tekens in.</p></div>
            </aside>

            <div class="chat-lightbox" data-chat-lightbox hidden><button type="button" data-lightbox-close>&times;</button><img src="" alt=""></div>
        @endif
    </section>
</main>
@endsection

@push('scripts')
<script>
(() => {
    const root = document.documentElement;
    const storedTheme = localStorage.getItem('cn-chat-theme');
    if (storedTheme) document.querySelector('.chat-app')?.classList.toggle('dark', storedTheme === 'dark');
    document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
        const app = document.querySelector('.chat-app');
        app.classList.toggle('dark');
        localStorage.setItem('cn-chat-theme', app.classList.contains('dark') ? 'dark' : 'light');
    });
    const panel = document.querySelector('[data-new-chat-panel]');
    document.querySelector('[data-new-chat]')?.addEventListener('click', () => panel.hidden = !panel.hidden);
    document.querySelector('[data-chat-search]')?.addEventListener('input', (event) => {
        const query = event.target.value.toLowerCase();
        document.querySelectorAll('[data-chat-name]').forEach((item) => item.hidden = !item.dataset.chatName.includes(query));
    });

    const messages = document.querySelector('[data-chat-messages]');
    const form = document.querySelector('[data-chat-form]');
    const chatApp = document.querySelector('[data-chat-app]');
    if (!messages || !form) return;
    const conversationId = messages.dataset.conversationId;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const typingIndicator = document.querySelector('[data-typing-indicator]');
    const presenceUserId = messages.dataset.presenceUserId;
    let loadingOlder = false;
    let hasMore = true;
    let typingTimer;
    const draftKey = `cn-chat-draft-${conversationId}`;
    const scrollBottom = () => messages.scrollTop = messages.scrollHeight;
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    const messageHtml = (message) => {
        const attachments = (message.attachments || []).map((attachment) => attachment.is_image
            ? `<button class="chat-image" type="button" data-lightbox="${attachment.url}"><img loading="lazy" src="${attachment.url}" alt="${escapeHtml(attachment.name)}"></button>`
            : `<a class="chat-file" href="${attachment.url}" download><b>${escapeHtml(attachment.name.split('.').pop().toUpperCase())}</b><span>${escapeHtml(attachment.name)}<small>${Math.round(attachment.size / 1024)} KB</small></span></a>`
        ).join('');
        const reply = message.reply ? `<button class="chat-reply-preview" type="button" data-jump-message="${message.reply.id}"><strong>${escapeHtml(message.reply.sender || '')}</strong>${escapeHtml(message.reply.body)}</button>` : '';
        const reactions = `<div class="chat-reactions" data-reactions>${(message.reactions || []).map((reaction) => `<button type="button" class="${reaction.mine ? 'mine' : ''}" data-quick-react="${reaction.emoji}">${reaction.emoji} ${reaction.count}</button>`).join('')}</div>`;
        const task = message.task ? `<a class="chat-task-link" href="{{ route('mijncn.module', 'tasks') }}">Taak #${message.task.id}: ${escapeHtml(message.task.title)}</a>` : '';
        const manage = messages.dataset.canManage === '1' ? `<button type="button" data-pin-message>${message.pinned ? 'Losmaken' : 'Vastzetten'}</button>` : '';
        const ownerActions = message.mine ? '<button type="button" data-edit-message>Bewerk</button><button type="button" data-delete-message>Verwijder</button>' : '';
        const actions = message.deleted ? '' : `<nav class="chat-message-actions"><button type="button" data-reply-message>Antwoord</button><button type="button" data-reaction-menu>Reageer</button>${manage}<button type="button" data-task-message>Maak taak</button>${ownerActions}</nav><div class="chat-reaction-menu" data-reaction-picker hidden>${['👍','❤️','😂','🎉','👀','✅'].map((emoji) => `<button type="button" data-quick-react="${emoji}">${emoji}</button>`).join('')}</div>`;
        const ack = message.requires_ack && !message.acknowledged ? '<button class="chat-ack-button" type="button" data-ack-message>Gelezen en begrepen</button>' : '';
        return `${message.mine ? '' : `<div class="chat-message-avatar">${message.avatar ? `<img class="user-avatar-image" src="${message.avatar}" alt="">` : `<span class="user-avatar-fallback">${escapeHtml(message.initials)}</span>`}</div>`}<div><span>${message.mine ? 'Jij' : escapeHtml(message.sender)}</span>${reply}${message.announcement ? '<b class="chat-announcement-label">Staffmededeling</b>' : ''}<p class="${message.deleted ? 'deleted' : ''}">${message.deleted ? 'Dit bericht is verwijderd.' : escapeHtml(message.body)}</p>${attachments}${task}${reactions}<time>${escapeHtml(message.time)}${message.edited ? ' · bewerkt' : ''}${message.read ? ' · gelezen' : ''}</time>${actions}${ack}</div>`;
    };
    const append = (message, prepend = false) => {
        if (messages.querySelector(`[data-message-id="${message.id}"]`)) return;
        messages.querySelector('[data-chat-empty]')?.remove();
        const article = document.createElement('article');
        article.className = `${message.mine ? 'mine' : ''} ${message.announcement ? 'announcement' : ''}`;
        article.dataset.messageId = message.id;
        article.innerHTML = messageHtml(message);
        if (prepend) messages.prepend(article); else messages.appendChild(article);
        if (!prepend) scrollBottom();
    };
    const latestId = () => Number(messages.lastElementChild?.dataset.messageId || 0);
    const oldestId = () => Number(messages.firstElementChild?.dataset.messageId || 0);
    const poll = async () => {
        try {
            const response = await fetch(`${messages.dataset.fetchUrl}?conversation_id=${conversationId}&after=${latestId()}`, {headers: {'Accept': 'application/json'}});
            if (response.ok) {
                const data = await response.json();
                data.messages.forEach((message) => append(message));
                if (data.typing.length) {
                    typingIndicator.hidden = false;
                    typingIndicator.querySelector('span').textContent = `${data.typing.join(', ')} typt...`;
                } else typingIndicator.hidden = true;
            }
        } catch (_) {}
        window.setTimeout(poll, document.hidden ? 5000 : 1000);
    };
    const pollPresence = async () => {
        if (!presenceUserId) return;
        try {
            const response = await fetch(`${messages.dataset.presenceUrl}?user_ids=${presenceUserId}`, {headers: {'Accept': 'application/json'}});
            if (response.ok) {
                const user = (await response.json()).users[0];
                const online = Boolean(user?.online);
                document.querySelector('[data-presence-dot]')?.classList.toggle('online', online);
                const label = document.querySelector('[data-presence-label]');
                if (label) label.textContent = online ? 'Online' : 'Offline';
            }
        } catch (_) {}
        window.setTimeout(pollPresence, 10000);
    };
    const pollConversations = async () => {
        try {
            const response = await fetch(chatApp.dataset.conversationsUrl, {headers: {'Accept': 'application/json'}});
            if (response.ok) {
                const data = await response.json();
                data.conversations.forEach((conversation) => {
                    const item = document.querySelector(`[data-conversation-list-id="${conversation.id}"]`);
                    if (!item) return;
                    item.querySelector('[data-conversation-preview]').textContent = conversation.last_message || 'Nog geen berichten';
                    const unread = item.querySelector('[data-conversation-unread]');
                    unread.textContent = conversation.unread_count;
                    unread.hidden = !conversation.unread_count;
                    if (conversation.last_message_at) {
                        item.querySelector('[data-conversation-time]').textContent = new Date(conversation.last_message_at)
                            .toLocaleTimeString('nl-NL', {hour: '2-digit', minute: '2-digit'});
                    }
                });
            }
        } catch (_) {}
        window.setTimeout(pollConversations, document.hidden ? 10000 : 5000);
    };
    messages.addEventListener('scroll', async () => {
        if (messages.scrollTop > 50 || loadingOlder || !hasMore || !oldestId()) return;
        loadingOlder = true;
        const oldHeight = messages.scrollHeight;
        try {
            const response = await fetch(`${messages.dataset.fetchUrl}?conversation_id=${conversationId}&before=${oldestId()}`, {headers: {'Accept': 'application/json'}});
            if (response.ok) {
                const data = await response.json();
                data.messages.reverse().forEach((message) => append(message, true));
                hasMore = data.has_more;
                messages.scrollTop = messages.scrollHeight - oldHeight;
            }
        } finally { loadingOlder = false; }
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.querySelector('[data-chat-input]');
        const file = form.querySelector('[data-chat-file]');
        if (!input.value.trim() && !file.files.length) return;
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: new FormData(form)
        });
        if (response.ok) {
            append((await response.json()).message);
            input.value = '';
            file.value = '';
            form.querySelector('[data-reply-id]').value = '';
            form.querySelector('[data-reply-compose]').hidden = true;
            form.querySelectorAll('.chat-announcement-option input').forEach((checkbox) => checkbox.checked = false);
            localStorage.removeItem(draftKey);
            input.focus();
        }
    });
    form.querySelector('[data-chat-input]').addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    document.querySelector('[data-chat-emoji]')?.addEventListener('click', () => {
        const input = form.querySelector('[data-chat-input]');
        const start = input.selectionStart;
        input.value = input.value.slice(0, start) + '🙂' + input.value.slice(input.selectionEnd);
        input.focus();
        input.selectionStart = input.selectionEnd = start + 2;
    });
    const chatInput = form.querySelector('[data-chat-input]');
    chatInput.value = localStorage.getItem(draftKey) || '';
    chatInput.addEventListener('input', () => {
        localStorage.setItem(draftKey, chatInput.value);
        window.clearTimeout(typingTimer);
        typingTimer = window.setTimeout(() => fetch(messages.dataset.typingUrl, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify({conversation_id: conversationId})
        }), 250);
    });

    const details = document.querySelector('[data-chat-details]');
    const searchPanel = document.querySelector('[data-chat-search-panel]');
    document.querySelector('[data-details-toggle]')?.addEventListener('click', () => details.hidden = !details.hidden);
    document.querySelector('[data-details-close]')?.addEventListener('click', () => details.hidden = true);
    document.querySelector('[data-search-toggle]')?.addEventListener('click', () => {
        searchPanel.hidden = !searchPanel.hidden;
        searchPanel.querySelector('input')?.focus();
    });
    document.querySelector('[data-search-close]')?.addEventListener('click', () => searchPanel.hidden = true);

    let searchTimer;
    document.querySelector('[data-message-search]')?.addEventListener('input', (event) => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(async () => {
            const results = document.querySelector('[data-search-results]');
            const query = event.target.value.trim();
            if (query.length < 2) {
                results.innerHTML = '<p>Voer minimaal twee tekens in.</p>';
                return;
            }
            const response = await fetch(`${messages.dataset.searchUrl}?conversation_id=${conversationId}&q=${encodeURIComponent(query)}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            const data = await response.json();
            results.innerHTML = data.messages.length
                ? data.messages.map((message) => `<button type="button" data-jump-message="${message.id}"><strong>${escapeHtml(message.sender)}</strong><span>${escapeHtml(message.body || 'Bijlage')}</span><small>${escapeHtml(message.date)} ${escapeHtml(message.time)}</small></button>`).join('')
                : '<p>Geen berichten gevonden.</p>';
        }, 350);
    });

    document.querySelector('[data-cancel-reply]')?.addEventListener('click', () => {
        form.querySelector('[data-reply-id]').value = '';
        form.querySelector('[data-reply-compose]').hidden = true;
    });

    document.addEventListener('click', (event) => {
        const lightboxTarget = event.target.closest('[data-lightbox]');
        if (lightboxTarget) {
            const lightbox = document.querySelector('[data-chat-lightbox]');
            lightbox.querySelector('img').src = lightboxTarget.dataset.lightbox;
            lightbox.hidden = false;
        }
        if (event.target.closest('[data-lightbox-close]') || event.target.matches('[data-chat-lightbox]')) {
            document.querySelector('[data-chat-lightbox]').hidden = true;
        }
        const jump = event.target.closest('[data-jump-message]');
        if (jump) {
            const target = messages.querySelector(`[data-message-id="${jump.dataset.jumpMessage}"]`);
            if (target) {
                target.scrollIntoView({behavior: 'smooth', block: 'center'});
                target.classList.add('highlight');
                window.setTimeout(() => target.classList.remove('highlight'), 1800);
            }
        }
    });

    messages.addEventListener('click', async (event) => {
        const article = event.target.closest('[data-message-id]');
        if (!article) return;
        const id = article.dataset.messageId;
        if (event.target.matches('[data-reply-message]')) {
            form.querySelector('[data-reply-id]').value = id;
            const preview = form.querySelector('[data-reply-compose]');
            preview.querySelector('span').textContent = `Antwoord op: ${article.querySelector('p')?.textContent || 'Bijlage'}`;
            preview.hidden = false;
            chatInput.focus();
        }
        if (event.target.matches('[data-reaction-menu]')) {
            const picker = article.querySelector('[data-reaction-picker]');
            picker.hidden = !picker.hidden;
        }
        if (event.target.matches('[data-quick-react]')) {
            const response = await fetch(messages.dataset.reactTemplate.replace('__ID__', id), {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({emoji: event.target.dataset.quickReact})
            });
            if (response.ok) {
                const reactions = (await response.json()).reactions;
                article.querySelector('[data-reactions]').innerHTML = reactions.map((reaction) => `<button type="button" class="${reaction.mine ? 'mine' : ''}" data-quick-react="${reaction.emoji}">${reaction.emoji} ${reaction.count}</button>`).join('');
                article.querySelector('[data-reaction-picker]').hidden = true;
            }
        }
        if (event.target.matches('[data-pin-message]')) {
            const response = await fetch(messages.dataset.pinTemplate.replace('__ID__', id), {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
            });
            if (response.ok) {
                const pinned = (await response.json()).pinned;
                event.target.textContent = pinned ? 'Losmaken' : 'Vastzetten';
                const bar = document.querySelector('[data-pinned-bar]');
                bar.hidden = !pinned;
                if (pinned) bar.querySelector('span').textContent = article.querySelector('p')?.textContent || 'Bijlage';
            }
        }
        if (event.target.matches('[data-task-message]')) {
            const response = await fetch(messages.dataset.taskTemplate.replace('__ID__', id), {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
            });
            if (response.ok) {
                const task = await response.json();
                event.target.textContent = `Taak #${task.task_id} gemaakt`;
                event.target.disabled = true;
            }
        }
        if (event.target.matches('[data-ack-message]')) {
            const response = await fetch(messages.dataset.ackTemplate.replace('__ID__', id), {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
            });
            if (response.ok) event.target.remove();
        }
        if (event.target.matches('[data-edit-message]')) {
            const current = article.querySelector('p').textContent;
            const body = prompt('Bericht bewerken', current);
            if (!body?.trim()) return;
            const response = await fetch(messages.dataset.updateTemplate.replace('__ID__', id), {
                method: 'PATCH',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                body: JSON.stringify({body})
            });
            if (response.ok) article.innerHTML = messageHtml((await response.json()).message);
        }
        if (event.target.matches('[data-delete-message]') && confirm('Dit bericht verwijderen?')) {
            const response = await fetch(messages.dataset.deleteTemplate.replace('__ID__', id), {
                method: 'DELETE',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf}
            });
            if (response.ok) {
                article.querySelector('p').textContent = 'Dit bericht is verwijderd.';
                article.querySelector('p').classList.add('deleted');
                article.querySelector('.chat-message-actions')?.remove();
                article.querySelectorAll('.chat-image, .chat-file').forEach((attachment) => attachment.remove());
            }
        }
    });
    scrollBottom();
    window.setTimeout(poll, 1000);
    window.setTimeout(pollPresence, 250);
    window.setTimeout(pollConversations, 2000);
})();
</script>
@endpush
