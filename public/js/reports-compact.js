(() => {
    'use strict';
    if (window.__spmuCompactReports) return;
    window.__spmuCompactReports = true;
    let opened = null;
    const choices = new WeakMap(), lookups = new WeakMap(), lookupTimers = new WeakMap();
    const formFor = node => node?.form || node?.closest('form[data-report-compact-builder]')
        || document.getElementById(node?.closest('[data-report-more-panel]')?.dataset.formId || '');
    const field = (form, name) => form.elements.namedItem(name);
    const pickers = form => [...document.querySelectorAll('[data-report-picker]')].filter(picker => formFor(picker.querySelector('input')) === form);
    const pickerFor = (form, key) => pickers(form).find(picker => picker.dataset.reportPicker === key);
    const data = picker => {
        if (!choices.has(picker)) {
            try { choices.set(picker, JSON.parse(picker.querySelector('[data-picker-choices]').textContent)); }
            catch { choices.set(picker, []); }
        }
        return choices.get(picker);
    };
    const showChoices = picker => {
        const input = picker.querySelector('[data-picker-input]'), list = picker.querySelector('[data-picker-list]');
        const query = input.value.trim().toLocaleLowerCase();
        list.replaceChildren();
        if (!query) return;
        data(picker).filter(option => option.label.toLocaleLowerCase().includes(query)).slice(0, 30).forEach(option => {
            const node = document.createElement('option'); node.value = option.label; list.append(node);
        });
    };
    const clearPicker = picker => {
        if (!picker) return;
        clearTimeout(lookupTimers.get(picker));
        lookups.get(picker)?.abort();
        picker.querySelector('[data-picker-value]').value = '';
        const input = picker.querySelector('[data-picker-input]'); input.value = ''; input.setCustomValidity('');
        picker.querySelector('[data-picker-list]').replaceChildren();
        picker.querySelector('[data-picker-error]').hidden = true;
    };
    const refreshUnits = form => {
        const picker = pickerFor(form, 'unit');
        if (!picker) return;
        const groups = JSON.parse(form.querySelector('[data-report-unit-options]').textContent);
        const division = field(form, 'division')?.value || '';
        const units = division ? (groups[division] || []) : [...new Set(Object.values(groups).flat())];
        choices.set(picker, units.map(label => ({value: label, label})));
        if (field(form, 'unit')?.value && !units.includes(field(form, 'unit').value)) clearPicker(picker);
        showChoices(picker);
    };
    const syncPeriod = form => {
        const custom = field(form, 'academic_period').value === 'custom';
        if (form.dataset.summary === '1') {
            form.querySelectorAll('[data-report-summary-month]').forEach(container => {
                container.hidden = custom;
                container.querySelectorAll('input,select').forEach(input => { input.disabled = custom; });
            });
            const year = Number(field(form, 'summary_year').value), month = Number(field(form, 'summary_month').value);
            if (!custom && year >= 1900 && year <= 9999 && month >= 1 && month <= 12) {
                field(form, 'start_date').value = `${year}-${String(month).padStart(2, '0')}-01`;
                field(form, 'end_date').value = new Date(Date.UTC(year, month, 0)).toISOString().slice(0,10);
            }
        }
        const range = form.querySelector('[data-report-custom-range]');
        range.hidden = !custom; range.querySelectorAll('input').forEach(input => { input.disabled = !custom; });
        const start = field(form, 'start_date'), end = field(form, 'end_date');
        end.min = start.value;
        end.setCustomValidity(custom && start.value && end.value && end.value < start.value ? 'To must be on or after From.' : '');
    };
    const countFilters = form => {
        const panel = form.querySelector('[data-report-more-panel]') || (opened?.form === form ? opened.panel : null);
        const count = panel ? [...panel.querySelectorAll('[name]')].filter(input => input.value !== '').length : 0;
        const badge = form.querySelector('[data-report-more-count]');
        if (badge) badge.textContent = count ? ` (${count})` : '';
    };
    const position = () => {
        if (!opened) return;
        const {panel, trigger} = opened, rect = trigger.getBoundingClientRect();
        const width = panel.getBoundingClientRect().width;
        panel.style.left = `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`;
        const height = panel.getBoundingClientRect().height;
        panel.style.top = `${Math.max(8, Math.min(rect.bottom + 8, window.innerHeight - height - 8))}px`;
    };
    const close = (commit = false, focus = true) => {
        if (!opened) return;
        const state = opened; opened = null;
        if (!commit) {
            state.saved.forEach(([node, value]) => { node.value = value; node.setCustomValidity?.(''); });
            state.panel.querySelectorAll('[data-report-picker]').forEach(picker => {
                clearTimeout(lookupTimers.get(picker));
                lookups.get(picker)?.abort(); picker.querySelector('[data-picker-error]').hidden = true;
            });
        }
        state.panel.hidden = true;
        state.form.append(state.panel);
        state.trigger.setAttribute('aria-expanded', 'false');
        refreshUnits(state.form); countFilters(state.form);
        if (focus && state.trigger.isConnected) state.trigger.focus();
    };
    const open = trigger => {
        if (opened?.trigger === trigger) { close(); return; }
        close();
        const form = formFor(trigger), panel = form.querySelector('[data-report-more-panel]');
        panel.querySelectorAll('input,select,button').forEach(control => control.setAttribute('form', form.id));
        const saved = [...panel.querySelectorAll('input,select')].map(node => [node, node.value]);
        document.body.append(panel); panel.hidden = false; trigger.setAttribute('aria-expanded', 'true');
        opened = {form, panel, trigger, saved}; position();
        (panel.querySelector('select,input:not([type=hidden])') || panel.querySelector('button')).focus();
    };
    const searchBorrowers = async picker => {
        const form = formFor(picker.querySelector('input')), input = picker.querySelector('[data-picker-input]');
        const query = input.value.trim();
        lookups.get(picker)?.abort();
        if (!query) return;
        const controller = new AbortController(); lookups.set(picker, controller);
        const url = new URL(form.action, window.location.href);
        new FormData(form).forEach((value,key) => { if (typeof value === 'string' && value !== '') url.searchParams.set(key,value); });
        url.searchParams.delete('search'); url.searchParams.delete('borrower');
        url.searchParams.set('borrower_options','1'); url.searchParams.set('q',query);
        try {
            const response = await fetch(url, {signal:controller.signal, credentials:'same-origin', headers:{Accept:'application/json'}});
            if (!response.ok) throw new Error('Lookup failed');
            const result = await response.json();
            if (controller.signal.aborted || input.value.trim() !== query) return;
            choices.set(picker, result.options.map(option => ({value:String(option.value), label:option.email ? `${option.name} — ${option.email}` : option.name})));
            picker.querySelector('[data-picker-error]').hidden = true; showChoices(picker);
        } catch (error) {
            if (error.name === 'AbortError') return;
            picker.querySelector('[data-picker-error]').hidden = false;
        }
    };
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-report-more-trigger]');
        if (trigger) { open(trigger); return; }
        if (event.target.closest('[data-report-more-cancel]')) { close(); return; }
        if (event.target.closest('[data-report-more-clear]')) {
            if (!opened) return;
            opened.panel.querySelectorAll('input,select').forEach(input => { input.value = ''; input.setCustomValidity?.(''); });
            opened.panel.querySelectorAll('[data-report-picker]').forEach(clearPicker);
            refreshUnits(opened.form); return;
        }
        if (event.target.closest('[data-report-more-apply]')) {
            if (opened) { syncPeriod(opened.form); opened.form.requestSubmit(); }
            return;
        }
        if (opened && !opened.panel.contains(event.target)) {
            const submit = event.target.closest('button[type=submit]');
            if (!submit || submit.form !== opened.form) close(false, false);
        }
    });
    document.addEventListener('input', event => {
        const picker = event.target.closest('[data-report-picker]');
        if (!picker || !event.target.matches('[data-picker-input]')) return;
        const input = event.target, value = picker.querySelector('[data-picker-value]');
        const match = data(picker).find(option => option.label.toLocaleLowerCase() === input.value.trim().toLocaleLowerCase());
        value.value = match?.value || '';
        input.setCustomValidity(input.value.trim() && !match ? 'Select a matching result, or clear this field.' : '');
        showChoices(picker);
        if (picker.dataset.reportPicker === 'borrower') {
            clearTimeout(lookupTimers.get(picker)); lookups.get(picker)?.abort();
            if (!match && input.value.trim()) lookupTimers.set(picker, setTimeout(() => searchBorrowers(picker), 180));
        }
        if (picker.dataset.reportPicker === 'unit') clearPicker(pickerFor(formFor(input), 'borrower'));
    });
    document.addEventListener('change', event => {
        const form = formFor(event.target);
        if (!form?.matches('[data-report-compact-builder]')) return;
        if (event.target.matches('[data-report-type]')) {
            close(false, false);
            const url = new URL(form.action, window.location.href); url.searchParams.set('report', event.target.value);
            const selectedPeriod = field(form,'academic_period').value;
            const period = event.target.value === 'summary' && selectedPeriod !== 'custom' ? 'month' : selectedPeriod;
            url.searchParams.set('academic_period', period);
            if (period === 'custom') ['start_date','end_date'].forEach(key => url.searchParams.set(key,field(form,key).value));
            if (form.closest('.reports-detail-page')) {
                form.dispatchEvent(new CustomEvent('report:type-change',{bubbles:true,detail:{url:url.href}}));
            } else window.location.assign(url.href);
            return;
        }
        if (event.target.name === 'division') { refreshUnits(form); clearPicker(pickerFor(form,'borrower')); }
        if (['academic_period','start_date','end_date','summary_month','summary_year'].includes(event.target.name)) syncPeriod(form);
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-report-compact-builder]')) return;
        event.preventDefault(); syncPeriod(form);
        if (!form.reportValidity()) return;
        if (opened?.form === form) close(true, false);
        countFilters(form);
        if (form.closest('.reports-detail-page')) {
            form.dispatchEvent(new CustomEvent('report:refresh',{bubbles:true}));
        } else HTMLFormElement.prototype.submit.call(form);
    });
    document.addEventListener('keydown', event => {
        if (!opened) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key === 'Tab') {
            const nodes = [...opened.panel.querySelectorAll('button,input:not([type=hidden]),select')].filter(node => !node.disabled && !node.hidden);
            const first = nodes[0], last = nodes[nodes.length-1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    document.addEventListener('report:loaded', event => {
        const form = event.target.querySelector('[data-report-compact-builder]');
        if (!form) return;
        const url = new URL(event.detail.url, window.location.href);
        const size = field(form, 'per_page');
        if (size) size.value = url.searchParams.get('per_page') || '10';
        const search = field(form, 'search');
        if (search) search.value = url.searchParams.get('search') || '';
    });
    window.addEventListener('resize', position); window.addEventListener('scroll', position, true);
})();
