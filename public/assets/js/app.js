document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    toggle?.addEventListener('click', () => nav?.classList.toggle('open'));
    document.querySelector('[data-sidebar-toggle]')?.addEventListener('click', () => {
        document.body.classList.toggle('sidebar-open');
    });

    const profileToggle = document.querySelector('[data-profile-toggle]');
    const profileMenu = document.querySelector('[data-profile-menu]');
    profileToggle?.addEventListener('click', event => {
        event.stopPropagation();
        const open = profileMenu?.classList.toggle('open') ?? false;
        profileToggle.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', event => {
        if (!profileMenu?.classList.contains('open') || event.target.closest('.topbar-profile-menu')) return;
        profileMenu.classList.remove('open');
        profileToggle?.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !profileMenu?.classList.contains('open')) return;
        profileMenu.classList.remove('open');
        profileToggle?.setAttribute('aria-expanded', 'false');
        profileToggle?.focus();
    });

    document.querySelectorAll('[data-task]').forEach(card => {
        card.draggable = true;
        card.addEventListener('dragstart', () => card.classList.add('dragging'));
        card.addEventListener('dragend', () => card.classList.remove('dragging'));
    });

    document.querySelectorAll('[data-column]').forEach(column => {
        column.addEventListener('dragover', event => {
            event.preventDefault();
            const card = document.querySelector('.dragging');
            if (card) column.querySelector('.task-list')?.appendChild(card);
        });
        column.addEventListener('drop', async event => {
            event.preventDefault();
            const card = document.querySelector('.dragging');
            if (!card?.dataset.updateUrl) return;
            await fetch(card.dataset.updateUrl, {
                method: 'PATCH',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                body: JSON.stringify({status: column.dataset.column, position: column.querySelectorAll('[data-task]').length - 1})
            });
        });
    });
});
