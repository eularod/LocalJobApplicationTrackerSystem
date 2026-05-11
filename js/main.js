document.addEventListener('DOMContentLoaded', function () {
    const sidebar     = document.getElementById('sidebar');
    const toggle      = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');
    const STORE_KEY   = 'sidebar_collapsed';
    const COLLAPSED_W = '60px';
    const EXPANDED_W  = '220px';

    if (!sidebar || !toggle) return;

    // Restore saved state on page load
    if (localStorage.getItem(STORE_KEY) === 'true') {
        sidebar.classList.add('collapsed');
        if (mainContent) mainContent.style.marginLeft = COLLAPSED_W;
    }

    toggle.addEventListener('click', function () {
        const isCollapsed = sidebar.classList.toggle('collapsed');
        localStorage.setItem(STORE_KEY, isCollapsed);
        if (mainContent) {
            mainContent.style.marginLeft = isCollapsed ? COLLAPSED_W : EXPANDED_W;
        }
    });
});