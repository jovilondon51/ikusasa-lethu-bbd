// Keep password controls independent of theme storage and other page scripts.
(() => {
    function setVisible(button, input, visible) {
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide password' : 'Show password';
        button.setAttribute('aria-pressed', String(visible));
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-password-toggle]');
        if (!button) return;
        const input = document.getElementById(button.dataset.passwordToggle);
        if (!input || input.tagName !== 'INPUT') return;
        event.preventDefault();
        setVisible(button, input, input.type === 'password');
    });

    document.addEventListener('submit', event => {
        event.target.querySelectorAll('[data-password-toggle]').forEach(button => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (input && input.form === event.target) setVisible(button, input, false);
        });
    }, true);
})();
