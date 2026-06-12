@php
    $paths = [
        'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-7h6v7"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'community' => '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="10" r="2.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M14 15a5 5 0 0 1 7 4.5"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'nomination' => '<path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="m3 12 9 5 9-5"/><path d="m3 16 9 5 9-5"/>',
        'vote' => '<path d="m4 12 5 5L20 6"/>',
        'result' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 16v-4m4 4V8m4 8v-6"/>',
        'award' => '<circle cx="12" cy="8" r="5"/><path d="m8.5 12-2 9 5.5-3 5.5 3-2-9"/>',
        'book' => '<path d="M4 4h11a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3V4Z"/><path d="M7 17h11"/><path d="M8 8h6"/>',
        'exam' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'certificate' => '<rect x="4" y="3" width="16" height="14" rx="2"/><path d="m9 21 3-4 3 4"/><path d="M8 8h8m-8 4h5"/>',
        'badge' => '<path d="m12 2 3 3 4-.5.5 4 3 3-3 3-.5 4-4-.5-3 3-3-3-4 .5-.5-4-3-3 3-3 .5-4 4 .5 3-3Z"/><path d="m9 12 2 2 4-4"/>',
        'task' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="m8 10 2 2 4-4m-6 8h8"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6V3h4v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'spark' => '<path d="m12 3 1.6 5.4L19 10l-5.4 1.6L12 17l-1.6-5.4L5 10l5.4-1.6L12 3Z"/><path d="m19 16 .7 2.3L22 19l-2.3.7L19 22l-.7-2.3L16 19l2.3-.7L19 16Z"/>',
        'bolt' => '<path d="m13 2-9 12h8l-1 8 9-12h-8l1-8Z"/>',
        'ranking' => '<path d="M4 20V10m6 10V4m6 16v-7m4 7H2"/>',
        'activity' => '<path d="M3 12h4l2-7 4 14 2-7h6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>',
        'logout' => '<path d="M10 17l5-5-5-5m5 5H3"/><path d="M14 3h6v18h-6"/>',
    ];
    $markup = $paths[$name] ?? $paths['activity'];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $markup !!}</svg>
