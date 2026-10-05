document.addEventListener('DOMContentLoaded', () => {
    // Mobile sidebar toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openSidebar() {
        sidebar?.classList.add('open');
        sidebarOverlay?.classList.add('active');
    }

    function closeSidebar() {
        sidebar?.classList.remove('open');
        sidebarOverlay?.classList.remove('active');
    }

    menuToggle?.addEventListener('click', openSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', closeSidebar);
    });

    // Theme Toggle
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;
    const savedTheme = localStorage.getItem('theme') || 'light';
    body.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const current = body.getAttribute('data-theme');
            const next = current === 'light' ? 'dark' : 'light';
            body.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
            document.dispatchEvent(new CustomEvent('themechange', { detail: next }));
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggle) return;
        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.className = theme === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        }
    }

    // Modal handling
    window.openModal = (id) => {
        document.getElementById(id).classList.add('active');
    };

    window.closeModal = (id) => {
        document.getElementById(id).classList.remove('active');
    };

    // Close modal on outside click
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('active');
        });
    });

    // Auto-hide alerts
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Confirm deletes
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm(btn.dataset.confirm)) e.preventDefault();
        });
    });

    // Tab switching
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', () => {
            const parent = tab.closest('.tabs-container');
            parent.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            parent.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.target).classList.add('active');
        });
    });

    // Refresh only the message list, leaving an unfinished message form intact.
    const messageList = document.querySelector('.message-list');
    if (messageList) {
        let refreshing = false;
        setInterval(async () => {
            if (refreshing || document.hidden) return;
            refreshing = true;
            try {
                const response = await fetch(location.pathname, { cache: 'no-store' });
                if (!response.ok || new URL(response.url).pathname !== location.pathname) return;
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const replacement = page.querySelector('.message-list');
                if (replacement) {
                    messageList.replaceChildren(...replacement.childNodes);
                    messageList.querySelectorAll('[data-confirm]').forEach(button => {
                        button.addEventListener('click', event => { if (!confirm(button.dataset.confirm)) event.preventDefault(); });
                    });
                }
            } catch (_) { /* Keep the current messages on a temporary connection failure. */ }
            finally { refreshing = false; }
        }, 10000);
    }
});
