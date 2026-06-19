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

    document.querySelectorAll('[data-absence-planner]').forEach(form => {
        const startInput = form.querySelector('[data-absence-start]');
        const endInput = form.querySelector('[data-absence-end]');
        const summary = form.querySelector('[data-absence-summary]');
        const rangeText = form.querySelector('[data-absence-range]');
        const title = form.querySelector('[data-week-title]');
        const grid = form.querySelector('.absence-grid');
        let weekStart = startOfWeek(new Date());
        let selecting = false;
        let anchor = null;

        const cells = () => [...form.querySelectorAll('[data-absence-cell]')];
        const pad = value => String(value).padStart(2, '0');
        const dateValue = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        const inputValue = date => `${dateValue(date)}T${pad(date.getHours())}:00`;
        const pretty = date => date.toLocaleString('nl-NL', {weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'});

        function startOfWeek(date) {
            const next = new Date(date);
            const day = (next.getDay() + 6) % 7;
            next.setDate(next.getDate() - day);
            next.setHours(0, 0, 0, 0);
            return next;
        }

        function weekNumber(date) {
            const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
            const dayNumber = target.getUTCDay() || 7;
            target.setUTCDate(target.getUTCDate() + 4 - dayNumber);
            const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
            return Math.ceil((((target - yearStart) / 86400000) + 1) / 7);
        }

        function refreshWeek() {
            title.textContent = `${weekStart.getFullYear()} - week ${weekNumber(weekStart)}`;
            [...form.querySelectorAll('.absence-day-label')].forEach((label, dayIndex) => {
                const date = new Date(weekStart);
                date.setDate(weekStart.getDate() + dayIndex);
                label.querySelector('b').textContent = date.toLocaleDateString('nl-NL', {weekday: 'short'});
                label.querySelector('span').textContent = date.toLocaleDateString('nl-NL', {day: '2-digit', month: '2-digit'});
            });
            cells().forEach((cell, index) => {
                const day = Math.floor(index / 24);
                const hour = index % 24;
                const date = new Date(weekStart);
                date.setDate(weekStart.getDate() + day);
                cell.dataset.date = dateValue(date);
                cell.dataset.hour = pad(hour);
                cell.classList.remove('selected');
            });
            anchor = null;
            updateSelection();
        }

        function indexOf(cell) {
            return cells().indexOf(cell);
        }

        function selectBetween(from, to) {
            const all = cells();
            const min = Math.min(from, to);
            const max = Math.max(from, to);
            all.forEach((cell, index) => cell.classList.toggle('selected', index >= min && index <= max));
            updateSelection();
        }

        function updateSelection() {
            const selected = cells().filter(cell => cell.classList.contains('selected'));
            if (selected.length === 0) {
                summary.textContent = 'Selecteer in het rooster wanneer je niet beschikbaar bent.';
                rangeText.textContent = 'Nog geen periode geselecteerd';
                return;
            }
            const first = selected[0];
            const last = selected[selected.length - 1];
            const start = new Date(`${first.dataset.date}T${first.dataset.hour}:00:00`);
            const end = new Date(`${last.dataset.date}T${last.dataset.hour}:00:00`);
            end.setHours(end.getHours() + 1);
            startInput.value = inputValue(start);
            endInput.value = inputValue(end);
            summary.textContent = `Afwezig van ${pretty(start)} tot ${pretty(end)}.`;
            rangeText.textContent = `${pretty(start)} tot ${pretty(end)}`;
        }

        form.querySelectorAll('[data-week-shift]').forEach(button => {
            button.addEventListener('click', () => {
                weekStart.setDate(weekStart.getDate() + Number(button.dataset.weekShift));
                refreshWeek();
            });
        });

        grid?.addEventListener('mousedown', event => {
            const cell = event.target.closest('[data-absence-cell]');
            if (!cell) return;
            selecting = true;
            anchor = indexOf(cell);
            selectBetween(anchor, anchor);
        });
        grid?.addEventListener('mouseover', event => {
            if (!selecting || anchor === null) return;
            const cell = event.target.closest('[data-absence-cell]');
            if (cell) selectBetween(anchor, indexOf(cell));
        });
        document.addEventListener('mouseup', () => selecting = false);
        form.querySelector('[data-absence-clear]')?.addEventListener('click', () => {
            cells().forEach(cell => cell.classList.remove('selected'));
            anchor = null;
            updateSelection();
        });

        refreshWeek();
    });
});
