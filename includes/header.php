<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>

<div class="layout">
<aside class="sidebar" id="sidebar">

    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle sidebar">
        <svg class="icon-collapse" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        <svg class="icon-expand" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </button>

    <div class="sidebar-logo">
        <div class="brand-icon">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2"/>
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
        </div>
        <span class="logo-text">Job Tracker</span>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php"
           class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
           data-tooltip="Dashboard">
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="applications.php"
           class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'applications.php' ? 'active' : '' ?>"
           data-tooltip="Applications">
            <span class="nav-label">Applications</span>
        </a>
        <a href="reports.php"
           class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>"
           data-tooltip="Reports">
            <span class="nav-label">Reports</span>
        </a>
        <a href="kpis.php"
           class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'kpis.php' ? 'active' : '' ?>"
           data-tooltip="KPIs">
            <span class="nav-label">KPIs</span>
        </a>
        <a href="profile.php"
           class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>"
           data-tooltip="Profile">
            <span class="nav-label">Profile</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <span class="user-role">Job Seeker</span>
            </div>
        </div>
        <a href="../auth/logout.php" class="logout-btn">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span class="nav-label">Logout</span>
        </a>
    </div>

</aside>
<div class="main-content" id="mainContent">