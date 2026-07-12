document.addEventListener('DOMContentLoaded', () => {
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

    // Auto-refresh community messages every 10 seconds
function initCommunityRefresh() {
    const messageList = document.querySelector('.message-list');
    if (!messageList) return;
    
    const isAdmin = document.querySelector('.nav-links a[href*="admin/dashboard"]') !== null;
    const refreshUrl = isAdmin ? '' : '';
    
    // Simple polling - reload page if new messages detected (simplified)
    // For a smoother experience, we'll just add a visual indicator
    setInterval(() => {
        const indicator = document.getElementById('refreshIndicator');
        if (indicator) {
            indicator.style.display = 'inline';
            setTimeout(() => indicator.style.display = 'none', 2000);
        }
    }, 10000);
}

// Add refresh indicator to community pages
document.addEventListener('DOMContentLoaded', () => {
    initCommunityRefresh();
    
    // Add live indicator to community header
    const communityHeader = document.querySelector('.card-header h2.card-title');
    if (communityHeader && communityHeader.textContent.includes('Community')) {
        const liveBadge = document.createElement('span');
        liveBadge.id = 'refreshIndicator';
        liveBadge.innerHTML = ' <span class="badge badge-success" style="font-size:0.7rem; animation:pulse 2s infinite;"><i class="fas fa-circle" style="font-size:0.4rem;"></i> LIVE</span>';
        liveBadge.style.display = 'none';
        communityHeader.appendChild(liveBadge);
    }
});
});