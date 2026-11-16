(() => {
    'use strict';

    // This is the searchable Actor control for the authorized Audit Trail.
    const initialize = () => document.querySelectorAll('[data-audit-actor-picker]').forEach(picker => {
        if (picker.dataset.actorReady === '1') return;
        picker.dataset.actorReady = '1';
        const input = picker.querySelector('[data-audit-actor-search]');
        const value = picker.querySelector('input[name="actor"]');
        const clear = picker.querySelector('[data-audit-actor-clear]');
        const panel = picker.querySelector('[data-audit-actor-panel]');
        const list = panel.querySelector('[data-audit-actor-results]');
        const status = panel.querySelector('[data-audit-actor-status]');
        const originalLabel = input.value;
        let choices = [], opened = false, active = -1, visible = [], available = true;
        try {
            choices = JSON.parse(picker.querySelector('[data-audit-actor-options]').textContent);
            if (!Array.isArray(choices) || choices.some(option => typeof option.value !== 'string'
                || typeof option.label !== 'string' || typeof option.detail !== 'string')) throw new Error('Invalid actor options');
        } catch {
            available = false;
            input.disabled = true;
            picker.querySelector('[data-audit-actor-error]').hidden = false;
        }

        const label = () => {
            if (value.value === 'ALL') return 'All actors';
            if (value.value === 'SYSTEM') return 'System';
            const choice = choices.find(option => option.value === value.value);
            return choice ? choice.label + (choice.detail ? ' — ' + choice.detail : '') : originalLabel;
        };
        const highlight = index => {
            active = index;
            [...list.children].forEach((option, position) => option.setAttribute('aria-selected', String(position === index)));
            const selected = list.children[index];
            if (selected) {
                input.setAttribute('aria-activedescendant', selected.id);
                selected.scrollIntoView?.({block: 'nearest'});
            } else input.removeAttribute('aria-activedescendant');
        };
        const position = () => {
            if (!opened) return;
            const rect = input.getBoundingClientRect();
            const width = Math.min(Math.max(rect.width, 280), window.innerWidth - 16);
            panel.style.width = width + 'px';
            panel.style.left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)) + 'px';
            const height = panel.getBoundingClientRect().height;
            const below = window.innerHeight - rect.bottom - 8;
            const top = below < height && rect.top > below ? rect.top - height - 6 : rect.bottom + 6;
            panel.style.top = Math.max(8, Math.min(top, window.innerHeight - height - 8)) + 'px';
        };
        const close = () => {
            opened = false;
            panel.hidden = true;
            picker.append(panel);
            input.setAttribute('aria-expanded', 'false');
            input.value = label();
            clear.hidden = value.value === 'ALL';
            highlight(-1);
        };
        const choose = selected => {
            if (!visible.some(option => option.value === selected) && selected !== 'ALL') return;
            const changed = value.value !== selected;
            value.value = selected;
            close();
            input.focus();
            close();
            if (changed) value.dispatchEvent(new Event('change', {bubbles: true}));
        };
        const render = () => {
            const query = input.value.trim().toLocaleLowerCase();
            const matches = query ? choices.filter(option => (option.label + ' ' + option.detail).toLocaleLowerCase().includes(query)) : [];
            const system = picker.dataset.systemActor === '1' && (!query || 'system'.includes(query));
            visible = [{value:'ALL', label:'All actors', detail:''},
                ...(system ? [{value:'SYSTEM', label:'System', detail:''}] : []), ...matches.slice(0, 8)];
            list.replaceChildren();
            visible.forEach((choice, index) => {
                const option = document.createElement('div');
                option.id = 'audit-actor-option-' + index;
                option.className = 'ictu-audit-actor-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.dataset.actorValue = choice.value;
                const name = document.createElement('span'); name.textContent = choice.label; option.append(name);
                if (choice.detail) {
                    const detail = document.createElement('small'); detail.textContent = choice.detail; option.append(detail);
                }
                list.append(option);
            });
            const count = matches.length + (system ? 1 : 0);
            status.textContent = !query ? 'Type a name to search.' : count === 0 ? 'No matching actors.'
                : count + ' matching actor' + (count === 1 ? '' : 's') + (matches.length > 8 ? ' — showing the first 8 names. Narrow your search.' : '');
            highlight(-1);
            position();
        };
        const open = (reset = true) => {
            if (!available) return;
            if (!opened) {
                if (reset) input.value = '';
                document.body.append(panel);
                opened = true;
                panel.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            }
            render();
        };

        input.addEventListener('focus', () => open());
        input.addEventListener('click', () => { if (!opened) open(); });
        input.addEventListener('input', () => { if (!opened) open(false); else render(); });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); close(); return; }
            if (event.key === 'Tab') { close(); return; }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End', 'Enter'].includes(event.key)) {
                if (event.key === 'Enter' && !opened) return;
                if (!opened && !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
                event.preventDefault();
                if (!opened) open();
                if (event.key === 'Enter') { if (active >= 0) choose(visible[active].value); return; }
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? visible.length - 1
                    : active < 0 ? (event.key === 'ArrowUp' ? visible.length - 1 : input.value.trim() && visible.length > 1 ? 1 : 0)
                    : event.key === 'ArrowDown' ? (active + 1) % visible.length : (active - 1 + visible.length) % visible.length;
                highlight(next);
            }
        });
        input.addEventListener('blur', () => { if (opened) close(); });
        panel.addEventListener('mousedown', event => event.preventDefault());
        panel.addEventListener('click', event => {
            const option = event.target.closest('[data-actor-value]');
            if (option) choose(option.dataset.actorValue);
        });
        clear.addEventListener('click', () => choose('ALL'));
        document.addEventListener('pointerdown', event => {
            if (opened && !picker.contains(event.target) && !panel.contains(event.target)) close();
        });
        window.addEventListener('resize', position);
        window.addEventListener('scroll', position, true);
        window.addEventListener('pageshow', close);
        close();
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
