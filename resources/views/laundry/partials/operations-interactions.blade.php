<script>
(() => {
    const workspace = document.querySelector('[data-laundry-tabs]');
    if (!workspace) return;
    const tabs = [...workspace.querySelectorAll('[data-laundry-tab]')];
    const panels = [...workspace.querySelectorAll('[data-laundry-panel]')];
    const activate = (name, focus = false) => {
        tabs.forEach(tab => {
            const selected = tab.dataset.laundryTab === name;
            tab.classList.toggle('is-selected', selected);
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            if (selected && focus) tab.focus();
        });
        panels.forEach(panel => { panel.hidden = panel.dataset.laundryPanel !== name; });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab.dataset.laundryTab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            activate(tabs[next].dataset.laundryTab, true);
        });
    });
    const url = new URL(window.location.href);
    activate([...url.searchParams.keys()].some(key => key.startsWith('completed_')) || url.hash === '#completed-laundry-cases' ? 'completed' : 'active');
})();
</script>
