<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn    = get_conn();
$user_id = $_SESSION['user_id'];

// Summary
$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                            AS total,
        SUM(status_id = 1)                  AS applied,
        SUM(status_id = 2)                  AS interview,
        SUM(status_id = 3)                  AS rejected,
        SUM(status_id = 4)                  AS withdrawn,
        MIN(applied_date)                   AS first_date,
        MAX(applied_date)                   AS latest_date,
        COUNT(DISTINCT company_id)          AS unique_companies
    FROM applications WHERE user_id = $user_id
"));

$interview_rate = $summary['total'] > 0
    ? round(($summary['interview'] / $summary['total']) * 100, 1) : 0;

$avg_per_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT ROUND(AVG(monthly_total), 1) AS avg
    FROM (
        SELECT COUNT(*) AS monthly_total
        FROM applications WHERE user_id = $user_id
        GROUP BY DATE_FORMAT(applied_date, '%Y-%m')
    ) t
"));

// Status progression
$progression = mysqli_query($conn, "
    SELECT s.label, COUNT(a.application_id) AS count,
           ROUND(COUNT(a.application_id) /
               NULLIF((SELECT COUNT(*) FROM applications WHERE user_id = $user_id), 0) * 100, 1
           ) AS percentage
    FROM statuses s
    LEFT JOIN applications a ON s.status_id = a.status_id AND a.user_id = $user_id
    GROUP BY s.status_id, s.label
    ORDER BY s.sort_order
");
$status_rows = [];
while ($r = mysqli_fetch_assoc($progression)) $status_rows[] = $r;

// Monthly breakdown
$monthly = mysqli_query($conn, "
    SELECT
        DATE_FORMAT(applied_date, '%M %Y')  AS month_label,
        COUNT(*)                            AS total,
        SUM(status_id = 2)                  AS interviews,
        SUM(status_id = 3)                  AS rejections,
        ROUND(SUM(status_id = 2) / COUNT(*) * 100, 1) AS interview_rate
    FROM applications WHERE user_id = $user_id
    GROUP BY DATE_FORMAT(applied_date, '%Y-%m'), month_label
    ORDER BY DATE_FORMAT(applied_date, '%Y-%m') DESC
");
$monthly_rows = [];
while ($r = mysqli_fetch_assoc($monthly)) $monthly_rows[] = $r;

// Top companies
$top_companies = mysqli_query($conn, "
    SELECT c.name, c.industry, c.location,
           COUNT(a.application_id) AS total,
           SUM(a.status_id = 2)    AS interviews,
           SUM(a.status_id = 3)    AS rejections,
           MAX(a.applied_date)     AS last_applied
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    WHERE a.user_id = $user_id
    GROUP BY c.company_id, c.name, c.industry, c.location
    ORDER BY total DESC
    LIMIT 10
");
$company_rows = [];
while ($r = mysqli_fetch_assoc($top_companies)) $company_rows[] = $r;

// All applications
$all_apps = mysqli_query($conn, "
    SELECT a.position, c.name AS company, c.industry, c.location,
           s.label AS status, a.applied_date, a.job_url
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    JOIN statuses  s ON a.status_id  = s.status_id
    WHERE a.user_id = $user_id
    ORDER BY a.applied_date DESC, a.application_id DESC
");
$all_rows = [];
while ($r = mysqli_fetch_assoc($all_apps)) $all_rows[] = $r;

$generated_at = date('F j, Y \a\t g:i A');
$username     = htmlspecialchars($_SESSION['username'] ?? 'User');

$status_colors = [
    'Applied'      => '#2F7FD6',
    'Interview'    => '#B36B10',
    'Offer'        => '#0F9270',
    'Rejected'     => '#D43F3F',
    'Withdrawn'    => '#8FA1B3',
    'Phone Screen' => '#4C3DB5',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Search Report - <?= $username ?></title>
<script src="../js/main.js" defer></script>
<style>

@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap');

/* Reset */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* Variables */
:root {
    --navy:       #0B2E52;
    --navy-deep:  #071E35;
    --blue:       #2F7FD6;
    --blue-light: #EBF3FD;
    --teal:       #0F9270;
    --teal-light: #E4F5EF;
    --amber:      #B36B10;
    --amber-light:#FDF3E1;
    --red:        #D43F3F;
    --red-light:  #FDEDED;
    --purple:     #4C3DB5;
    --purple-light:#EEECFC;
    --text:       #111827;
    --text-sub:   #4B5A6E;
    --text-muted: #8FA1B3;
    --border:     #E5EAF2;
    --bg:         #F5F7FA;
    --font:       'DM Sans', sans-serif;
}

/* Base */
body {
    font-family: var(--font);
    font-size: 13px;
    color: var(--text);
    background: var(--bg);
    -webkit-font-smoothing: antialiased;
}

/* Screen bar */
.screen-bar {
    background: var(--navy-deep);
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    position: sticky;
    top: 0;
    z-index: 99;
}
.screen-bar-logo {
    color: #fff;
    font-weight: 600;
    font-size: 0.9rem;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}
.screen-bar-actions { display: flex; gap: 8px; }

.btn-print, .btn-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 16px;
    border-radius: 7px;
    font-family: var(--font);
    font-size: 0.82rem;
    font-weight: 500;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: background 0.15s;
}
.btn-print { background: var(--blue); color: #fff; }
.btn-print:hover { background: #1a6abf; }
.btn-back {
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.75);
    border: 0.5px solid rgba(255,255,255,0.12);
}
.btn-back:hover { background: rgba(255,255,255,0.14); color: #fff; }

/* Page */
.page {
    max-width: 860px;
    margin: 32px auto;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 4px 24px rgba(11,46,82,0.10);
    overflow: hidden;
}

/* Report Header */
.report-header {
    background: linear-gradient(135deg, var(--navy-deep) 0%, #0f3a6e 100%);
    padding: 36px 40px 32px;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.report-header::before {
    content: '';
    position: absolute;
    top: -40px; right: -40px;
    width: 220px; height: 220px;
    border-radius: 50%;
    background: rgba(47,127,214,0.12);
}
.report-header::after {
    content: '';
    position: absolute;
    bottom: -60px; left: 30%;
    width: 160px; height: 160px;
    border-radius: 50%;
    background: rgba(15,146,112,0.08);
}
.report-header-inner { position: relative; z-index: 1; }

.report-brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.5);
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-bottom: 16px;
}
.report-brand svg { opacity: 0.6; }

.report-title {
    font-size: 1.75rem;
    font-weight: 600;
    letter-spacing: -0.035em;
    margin-bottom: 6px;
}
.report-sub {
    color: rgba(255,255,255,0.55);
    font-size: 0.85rem;
    font-weight: 300;
}

.report-meta {
    margin-top: 24px;
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}
.report-meta-item { display: flex; flex-direction: column; gap: 2px; }
.report-meta-label {
    font-size: 0.67rem;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 500;
}
.report-meta-value {
    font-size: 0.88rem;
    color: rgba(255,255,255,0.85);
    font-weight: 500;
}

/* Body */
.report-body { padding: 32px 40px; }

/* Section */
.section { margin-bottom: 2rem; }
.section-title {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 8px;
}
.section-title svg { color: var(--blue); opacity: 0.7; }

/* KPI Grid */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin-bottom: 1.5rem;
}
.kpi-card {
    background: var(--bg);
    border-radius: 10px;
    padding: 14px 16px;
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}
.kpi-card-accent {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    border-radius: 10px 10px 0 0;
}
.kpi-value {
    font-size: 1.7rem;
    font-weight: 600;
    letter-spacing: -0.04em;
    color: var(--text);
    line-height: 1;
}
.kpi-label {
    font-size: 0.72rem;
    color: var(--text-sub);
    margin-top: 5px;
}

/* Pipeline */
.pipeline-bar {
    display: flex;
    height: 10px;
    border-radius: 99px;
    overflow: hidden;
    gap: 2px;
    margin-bottom: 10px;
}
.pipeline-seg { height: 100%; min-width: 4px; border-radius: 2px; }
.pipeline-legend { display: flex; flex-wrap: wrap; gap: 6px 16px; }
.pipeline-legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.73rem;
    color: var(--text-sub);
}
.pipeline-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

/* Tables */
.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.82rem;
}
.report-table th {
    text-align: left;
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.07em;
    padding: 7px 12px;
    background: var(--bg);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
.report-table td {
    padding: 9px 12px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}
.report-table tr:last-child td { border-bottom: none; }
.report-table tbody tr:hover td { background: #fafbfd; }

/* Badge */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 99px;
    font-size: 0.68rem;
    font-weight: 500;
}
.badge::before {
    content: '';
    width: 5px; height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}
.badge-applied       { background: #EBF3FD; color: #0B3F73; }
.badge-applied::before { background: #2F7FD6; }
.badge-interview     { background: #FDF3E1; color: #5E3408; }
.badge-interview::before { background: #B36B10; }
.badge-rejected      { background: #FDEDED; color: #6E1E1E; }
.badge-rejected::before { background: #D43F3F; }
.badge-withdrawn     { background: #F3F4F6; color: #4B5A6E; }
.badge-withdrawn::before { background: #8FA1B3; }

/* Inline bar */
.ibar-wrap { display: flex; align-items: center; gap: 6px; }
.ibar { height: 5px; border-radius: 3px; min-width: 3px; background: var(--blue); }
.ibar-val { font-size: 0.72rem; color: var(--text-muted); white-space: nowrap; }

/* Two-col */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Insight cards */
.insight-card {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px 16px;
}
.insight-card h4 {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-sub);
    margin-bottom: 8px;
}
.insight-value {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--text);
    letter-spacing: -0.03em;
}
.insight-sub { font-size: 0.72rem; color: var(--text-muted); margin-top: 3px; }

/* Row number */
.row-num { font-size: 0.72rem; color: var(--text-muted); }

/* Footer */
.report-footer {
    border-top: 1px solid var(--border);
    padding: 16px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.72rem;
    color: var(--text-muted);
    background: var(--bg);
}

/* Page break helpers */
.page-break { page-break-before: always; }
.no-break   { page-break-inside: avoid; }

/* Print Styles */
@media print {
    @page { size: A4; margin: 16mm 14mm; }

    body {
        background: #fff !important;
        font-size: 11px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .screen-bar { display: none !important; }

    .page {
        max-width: 100%;
        margin: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .report-header {
        padding: 24px 28px 20px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .report-body { padding: 20px 28px; }
    .report-footer { padding: 12px 28px; }

    .kpi-grid { grid-template-columns: repeat(4, 1fr); gap: 8px; }
    .kpi-card { padding: 10px 12px; }
    .kpi-value { font-size: 1.4rem; }

    .two-col { grid-template-columns: 1fr 1fr; }

    .report-table { font-size: 0.76rem; }
    .report-table th { padding: 5px 10px; font-size: 0.6rem; }
    .report-table td { padding: 6px 10px; }

    a { text-decoration: none !important; color: inherit !important; }

    .no-break { page-break-inside: avoid; }
    .section  { page-break-inside: avoid; }
}
</style>
</head>
<body>

<!-- Screen-only top bar -->
<div class="screen-bar">
    <a href="dashboard.php" class="screen-bar-logo">
        Job Tracker
    </a>
    <div class="screen-bar-actions">
        <a href="reports.php" class="btn-back">
            Back to Reports
        </a>
        <button class="btn-print" onclick="window.print()">
            Print / Save PDF
        </button>
    </div>
</div>

<!-- Report Page -->
<div class="page">

    <!-- Header -->
    <div class="report-header">
        <div class="report-header-inner">
            <div class="report-brand">
                Job Tracker · Official Report
            </div>
            <div class="report-title">Job Search Report</div>
            <div class="report-sub">Comprehensive overview of your application activity</div>
            <div class="report-meta">
                <div class="report-meta-item">
                    <span class="report-meta-label">Prepared for</span>
                    <span class="report-meta-value"><?= $username ?></span>
                </div>
                <div class="report-meta-item">
                    <span class="report-meta-label">Generated</span>
                    <span class="report-meta-value"><?= $generated_at ?></span>
                </div>
                <?php if ($summary['first_date']): ?>
                <div class="report-meta-item">
                    <span class="report-meta-label">Period</span>
                    <span class="report-meta-value"><?= $summary['first_date'] ?> — <?= $summary['latest_date'] ?></span>
                </div>
                <?php endif; ?>
                <div class="report-meta-item">
                    <span class="report-meta-label">Total Applications</span>
                    <span class="report-meta-value"><?= $summary['total'] ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Body -->
    <div class="report-body">

        <!-- KPI Summary -->
        <div class="section no-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Key Performance Indicators
            </div>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#2F7FD6;"></div>
                    <div class="kpi-value"><?= $summary['total'] ?></div>
                    <div class="kpi-label">Total Applications</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#B36B10;"></div>
                    <div class="kpi-value"><?= $summary['interview'] ?></div>
                    <div class="kpi-label">Interviews</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#0F9270;"></div>
                    <div class="kpi-value"><?= $interview_rate ?>%</div>
                    <div class="kpi-label">Interview Rate</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#4C3DB5;"></div>
                    <div class="kpi-value"><?= $summary['unique_companies'] ?></div>
                    <div class="kpi-label">Companies Reached</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#D43F3F;"></div>
                    <div class="kpi-value"><?= $summary['rejected'] ?></div>
                    <div class="kpi-label">Rejections</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#8FA1B3;"></div>
                    <div class="kpi-value"><?= $summary['withdrawn'] ?></div>
                    <div class="kpi-label">Withdrawn</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#0891b2;"></div>
                    <div class="kpi-value"><?= $avg_per_month['avg'] ?? 0 ?></div>
                    <div class="kpi-label">Avg / Month</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-accent" style="background:#0B2E52;"></div>
                    <div class="kpi-value"><?= $summary['applied'] ?></div>
                    <div class="kpi-label">Still Pending</div>
                </div>
            </div>
        </div>

        <!-- Pipeline -->
        <?php if ($summary['total'] > 0): ?>
        <div class="section no-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                Application Pipeline
            </div>
            <div class="pipeline-bar">
                <?php foreach ($status_rows as $r):
                    $col = $status_colors[$r['label']] ?? '#8FA1B3';
                    if ($r['count'] == 0) continue;
                    $pct = $summary['total'] > 0 ? round(($r['count'] / $summary['total']) * 100, 1) : 0;
                ?>
                <div class="pipeline-seg" style="width:<?= $pct ?>%; background:<?= $col ?>;"></div>
                <?php endforeach; ?>
            </div>
            <div class="pipeline-legend">
                <?php foreach ($status_rows as $r):
                    $col = $status_colors[$r['label']] ?? '#8FA1B3';
                    $pct = $summary['total'] > 0 ? round(($r['count'] / $summary['total']) * 100, 1) : 0;
                ?>
                <div class="pipeline-legend-item">
                    <div class="pipeline-dot" style="background:<?= $col ?>;"></div>
                    <?= htmlspecialchars($r['label']) ?>
                    <span style="color:#8FA1B3"><?= $r['count'] ?> (<?= $pct ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Insights -->
        <div class="section no-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Quick Insights
            </div>
            <div class="two-col">
                <div class="insight-card">
                    <h4>First Application</h4>
                    <div class="insight-value"><?= $summary['first_date'] ?? '—' ?></div>
                    <div class="insight-sub">When you started tracking</div>
                </div>
                <div class="insight-card">
                    <h4>Latest Application</h4>
                    <div class="insight-value"><?= $summary['latest_date'] ?? '—' ?></div>
                    <div class="insight-sub">Most recent entry</div>
                </div>
                <div class="insight-card">
                    <h4>Average Applications / Month</h4>
                    <div class="insight-value"><?= $avg_per_month['avg'] ?? 0 ?></div>
                    <div class="insight-sub">Across all active months</div>
                </div>
                <div class="insight-card">
                    <h4>Interview Conversion Rate</h4>
                    <div class="insight-value"><?= $interview_rate ?>%</div>
                    <div class="insight-sub"><?= $summary['interview'] ?> interviews from <?= $summary['total'] ?> applications</div>
                </div>
            </div>
        </div>

        <!-- Status Breakdown -->
        <?php if (!empty($status_rows)): ?>
        <div class="section no-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                Status Breakdown
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>% of Total</th>
                        <th style="width:40%">Distribution</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($status_rows as $r): ?>
                <tr>
                    <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$r['label'])) ?>"><?= htmlspecialchars($r['label']) ?></span></td>
                    <td><?= $r['count'] ?></td>
                    <td><?= $r['percentage'] ?>%</td>
                    <td>
                        <div class="ibar-wrap">
                            <div class="ibar" style="width:<?= min($r['percentage'],100) ?>%; background:<?= $status_colors[$r['label']] ?? '#2F7FD6' ?>;"></div>
                            <span class="ibar-val"><?= $r['percentage'] ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Monthly Breakdown -->
        <?php if (!empty($monthly_rows)): ?>
        <div class="section no-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Monthly Breakdown
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Total</th>
                        <th>Interviews</th>
                        <th>Rejections</th>
                        <th>Interview Rate</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($monthly_rows as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['month_label']) ?></td>
                    <td><?= $r['total'] ?></td>
                    <td><?= $r['interviews'] ?></td>
                    <td><?= $r['rejections'] ?></td>
                    <td>
                        <div class="ibar-wrap">
                            <div class="ibar" style="width:<?= min($r['interview_rate'],100) ?>%; background:#B36B10;"></div>
                            <span class="ibar-val"><?= $r['interview_rate'] ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Applications by Company -->
        <?php if (!empty($company_rows)): ?>
        <div class="section no-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
                Applications by Company
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Industry</th>
                        <th>Location</th>
                        <th>Total</th>
                        <th>Interviews</th>
                        <th>Rejections</th>
                        <th>Last Applied</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($company_rows as $r): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                    <td><?= htmlspecialchars($r['industry'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($r['location'] ?? '—') ?></td>
                    <td><?= $r['total'] ?></td>
                    <td><?= $r['interviews'] ?></td>
                    <td><?= $r['rejections'] ?></td>
                    <td><?= $r['last_applied'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- All Applications -->
        <?php if (!empty($all_rows)): ?>
        <div class="section page-break">
            <div class="section-title">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                All Applications (<?= count($all_rows) ?>)
            </div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Position</th>
                        <th>Company</th>
                        <th>Industry</th>
                        <th>Status</th>
                        <th>Date Applied</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_rows as $i => $r): ?>
                <tr>
                    <td class="row-num"><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars($r['position']) ?></strong></td>
                    <td><?= htmlspecialchars($r['company']) ?></td>
                    <td><?= htmlspecialchars($r['industry'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$r['status'])) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td><?= $r['applied_date'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /report-body -->

    <!-- Footer -->
    <div class="report-footer">
        <span>Job Tracker · <?= $generated_at ?></span>
        <span>Prepared for <?= $username ?> · <?= $summary['total'] ?> total applications</span>
    </div>

</div><!-- /page -->

<?php include "../includes/footer.php"; ?>