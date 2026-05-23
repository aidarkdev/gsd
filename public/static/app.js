(() => {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-app-sidebar]');
    const shell = toggle?.closest('.app-shell');

    if (!toggle || !sidebar || !shell) {
        return;
    }

    const setOpen = (isOpen) => {
        shell.classList.toggle('is-sidebar-open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => {
        setOpen(!shell.classList.contains('is-sidebar-open'));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();
