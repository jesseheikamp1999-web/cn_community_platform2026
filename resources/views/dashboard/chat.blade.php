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
                    <span class="chat-secure">Intern CN</span>
                </header>

                <div class="chat-messages"
                     data-chat-messages
                     data-conversation-id="{{ $selected->id }}"
                     data-fetch-url="{{ route('chat.api.messages') }}"
                     data-send-url="{{ route('chat.api.send') }}"
                     data-typing-url="{{ route('chat.api.typing') }}"
                     data-read-url="{{ route('chat.api.read') }}"
                     data-presence-url="{{ route('chat.api.presence') }}"
                     data-presence-user-id="{{ $other?->id }}"
                     data-update-template="{{ route('chat.api.messages.update', ['message' => '__ID__']) }}"
                     data-delete-template="{{ route('chat.api.messages.delete', ['message' => '__ID__']) }}">
                    @forelse($selected->messages as $message)
                        <article class="{{ $message->sender_id === auth()->id() ? 'mine' : '' }}" data-message-id="{{ $message->id }}">
                            @if($message->sender_id !== auth()->id())
                                <div class="chat-message-avatar">@include('components.user-avatar', ['user' => $message->sender])</div>
                            @endif
                            <div>
                                <span>{{ $message->sender_id === auth()->id() ? 'Jij' : $message->sender->name }}</span>
                                <p class="{{ $message->deleted_at ? 'deleted' : '' }}">{{ $message->deleted_at ? 'Dit bericht is verwijderd.' : $message->body }}</p>
                                @foreach($message->attachments as $attachment)
                                    <a class="chat-image" href="{{ \Illuminate\Support\Facades\Storage::disk($attachment->disk)->url($attachment->path) }}" target="_blank" rel="noopener"><img src="{{ \Illuminate\Support\Facades\Storage::disk($attachment->disk)->url($attachment->path) }}" alt="{{ $attachment->original_name }}"></a>
                                @endforeach
                                <time>
                                    {{ $message->created_at->format('H:i') }}
                                    @if($message->edited_at)
                                        &middot; bewerkt
                                    @endif
                                    @if($message->sender_id === auth()->id() && $message->readers->where('id', '!=', auth()->id())->isNotEmpty())
                                        &middot; gelezen
                                    @endif
                                </time>
                                @if($message->sender_id === auth()->id() && !$message->deleted_at)
                                    <nav class="chat-message-actions"><button type="button" data-edit-message>Bewerk</button><button type="button" data-delete-message>Verwijder</button></nav>
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
                    <button class="chat-emoji" type="button" title="Emoji toevoegen" data-chat-emoji>&#9786;</button>
                    <label class="chat-upload" title="Afbeelding toevoegen"><input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" data-chat-image>&#128247;</label>
                    <textarea name="body" rows="1" maxlength="4000" placeholder="Typ een bericht..." data-chat-input></textarea>
                    <button type="submit" aria-label="Bericht versturen">&#10148;</button>
                </form>
            @else
                <div class="chat-no-room"><strong>Staff Messenger</strong><p>Start een gesprek met een collega.</p></div>
            @endif
        </section>
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
    const scrollBottom = () => messages.scrollTop = messages.scrollHeight;
    const escapeHtml = (text) => {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    const messageHtml = (message) => {
        const attachments = (message.attachments || []).map((attachment) => `<a class="chat-image" href="${attachment.url}" target="_blank" rel="noopener"><img src="${attachment.url}" alt="${escapeHtml(attachment.name)}"></a>`).join('');
        const actions = message.mine && !message.deleted ? '<nav class="chat-message-actions"><button type="button" data-edit-message>Bewerk</button><button type="button" data-delete-message>Verwijder</button></nav>' : '';
        return `${message.mine ? '' : `<div class="chat-message-avatar">${message.avatar ? `<img class="user-avatar-image" src="${message.avatar}" alt="">` : `<span class="user-avatar-fallback">${escapeHtml(message.initials)}</span>`}</div>`}<div><span>${message.mine ? 'Jij' : escapeHtml(message.sender)}</span><p class="${message.deleted ? 'deleted' : ''}">${message.deleted ? 'Dit bericht is verwijderd.' : escapeHtml(message.body)}</p>${attachments}<time>${escapeHtml(message.time)}${message.edited ? ' · bewerkt' : ''}${message.read ? ' · gelezen' : ''}</time>${actions}</div>`;
    };
    const append = (message, prepend = false) => {
        if (messages.querySelector(`[data-message-id="${message.id}"]`)) return;
        messages.querySelector('[data-chat-empty]')?.remove();
        const article = document.createElement('article');
        article.className = message.mine ? 'mine' : '';
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
        const image = form.querySelector('[data-chat-image]');
        if (!input.value.trim() && !image.files.length) return;
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: new FormData(form)
        });
        if (response.ok) {
            append((await response.json()).message);
            input.value = '';
            image.value = '';
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
    form.querySelector('[data-chat-input]').addEventListener('input', () => {
        window.clearTimeout(typingTimer);
        typingTimer = window.setTimeout(() => fetch(messages.dataset.typingUrl, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            body: JSON.stringify({conversation_id: conversationId})
        }), 250);
    });
    messages.addEventListener('click', async (event) => {
        const article = event.target.closest('[data-message-id]');
        if (!article) return;
        const id = article.dataset.messageId;
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
                article.querySelectorAll('.chat-image').forEach((image) => image.remove());
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
