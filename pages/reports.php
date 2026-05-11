<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];

// Summary totals (Aggregates)
$summary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                                        AS total,
        SUM(status_id = 1)                              AS applied,
        SUM(status_id = 2)                              AS interview,
        SUM(status_id = 3)                              AS rejected,
        SUM(status_id = 4)                              AS withdrawn,
        MIN(applied_date)                               AS first_date,
        MAX(applied_date)                               AS latest_date,
        COUNT(DISTINCT company_id)                      AS unique_companies
    FROM applications
    WHERE user_id = $user_id
"));

$avg_per_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT ROUND(AVG(monthly_total), 1) AS avg_applications
    FROM (
        SELECT COUNT(*) AS monthly_total
        FROM applications
        WHERE user_id = $user_id
        GROUP BY DATE_FORMAT(applied_date, '%Y-%m')
    ) AS monthly_summary
"));

// This month summary
$this_month = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total,
           SUM(status_id = 2) AS interviews,
           SUM(status_id = 3) AS rejections
    FROM applications
    WHERE user_id = $user_id
    AND DATE_FORMAT(applied_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
"));

// Top companies
$top_companies_q = mysqli_query($conn, "
    SELECT c.name, COUNT(a.application_id) AS count
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    WHERE a.user_id = $user_id
    GROUP BY c.company_id, c.name
    ORDER BY count DESC LIMIT 5
");
$max_count    = 1;
$company_rows = [];
while ($r = mysqli_fetch_assoc($top_companies_q)) {
    $company_rows[] = $r;
    if ($r['count'] > $max_count) $max_count = $r['count'];
}

// Monthly breakdown — full history (CTE + GROUP BY)
$monthly_full = mysqli_query($conn, "
    WITH monthly AS (
        SELECT
            DATE_FORMAT(applied_date, '%Y-%m')  AS month_key,
            DATE_FORMAT(applied_date, '%M %Y')  AS month_label,
            COUNT(*)                            AS total,
            SUM(status_id = 2)                  AS interviews,
            SUM(status_id = 3)                  AS rejections
        FROM applications
        WHERE user_id = $user_id
        GROUP BY month_key, month_label
    )
    SELECT *, ROUND((interviews / total) * 100, 1) AS interview_rate
    FROM monthly
    ORDER BY month_key DESC
");

$chart_labels = [];
$chart_data   = [];
$monthly_rows = [];
while ($row = mysqli_fetch_assoc($monthly_full)) {
    $monthly_rows[] = $row;
    array_unshift($chart_labels, $row['month_label']);
    array_unshift($chart_data,   (int)$row['total']);
}

// Applications per company (JOIN + GROUP BY)
$by_company = mysqli_query($conn, "
    SELECT
        c.name,
        c.industry,
        c.location,
        COUNT(a.application_id)     AS total,
        SUM(a.status_id = 2)        AS interviews,
        SUM(a.status_id = 3)        AS rejections,
        MAX(a.applied_date)         AS last_applied
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    WHERE a.user_id = $user_id
    GROUP BY c.company_id, c.name, c.industry, c.location
    ORDER BY total DESC
");

// Status progression with percentage (Subquery)
$progression = mysqli_query($conn, "
    SELECT s.label, COUNT(a.application_id) AS count,
           ROUND(COUNT(a.application_id) /
               (SELECT COUNT(*) FROM applications WHERE user_id = $user_id) * 100, 1
           ) AS percentage
    FROM statuses s
    LEFT JOIN applications a ON s.status_id = a.status_id AND a.user_id = $user_id
    GROUP BY s.status_id, s.label
    ORDER BY s.sort_order
");

$top_company = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT c.name, COUNT(*) AS total
    FROM companies c
    WHERE c.company_id = (
        SELECT company_id FROM applications
        WHERE user_id = $user_id
        GROUP BY company_id
        ORDER BY COUNT(*) DESC
        LIMIT 1
    )
    GROUP BY c.company_id, c.name
"));

$latest_app = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT a.position, c.name AS company, a.applied_date
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    WHERE a.application_id = (
        SELECT application_id FROM applications
        WHERE user_id = $user_id
        ORDER BY applied_date DESC, application_id DESC
        LIMIT 1
    )
"));

// Most active weeks (CTE)
$weekly = mysqli_query($conn, "
    WITH weekly_counts AS (
        SELECT
            YEARWEEK(applied_date, 1)               AS week_key,
            DATE_FORMAT(
                STR_TO_DATE(
                    CONCAT(YEARWEEK(applied_date,1), ' Monday'),
                    '%X%V %W'
                ), '%b %d, %Y'
            )                                       AS week_start,
            COUNT(*)                                AS total
        FROM applications
        WHERE user_id = $user_id
        GROUP BY week_key, week_start
    )
    SELECT * FROM weekly_counts
    ORDER BY week_key DESC
    LIMIT 8
");

$week_rows = [];
$week_max  = 1;
while ($r = mysqli_fetch_assoc($weekly)) {
    $week_rows[] = $r;
    if ($r['total'] > $week_max) $week_max = $r['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Job Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="container">

    <div class="page-header">
        <h1>Reports</h1>
        <div style="display:flex;gap:.6rem;">
            <a href="print_report.php" class="btn btn-secondary" target="_blank">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Report
            </a>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card stat-blue">
            <span class="stat-number"><?= $summary['total'] ?></span>
            <span class="stat-label">Total Applications</span>
        </div>
        <div class="stat-card stat-amber">
            <span class="stat-number"><?= $summary['interview'] ?></span>
            <span class="stat-label">Interviews</span>
        </div>
        <div class="stat-card stat-red">
            <span class="stat-number"><?= $summary['rejected'] ?></span>
            <span class="stat-label">Rejected</span>
        </div>
        <div class="stat-card stat-teal">
            <span class="stat-number"><?= $summary['unique_companies'] ?></span>
            <span class="stat-label">Companies</span>
        </div>
        <div class="stat-card">
            <span class="stat-number"><?= $summary['first_date'] ?? '—' ?></span>
            <span class="stat-label">First Application</span>
        </div>
        <div class="stat-card stat-blue">
            <span class="stat-number"><?= $summary['latest_date'] ?? '—' ?></span>
            <span class="stat-label">Latest Application</span>
        </div>
        <div class="stat-card stat-purple">
            <span class="stat-number"><?= htmlspecialchars($top_company['name'] ?? '—') ?></span>
            <span class="stat-label">Most Applied Company</span>
        </div>
        <div class="stat-card stat-teal">
            <span class="stat-number"><?= htmlspecialchars($latest_app['position'] ?? '—') ?></span>
            <span class="stat-label">Latest Position</span>
        </div>
        <div class="stat-card stat-blue">
            <span class="stat-number"><?= $avg_per_month['avg_applications'] ?></span>
            <span class="stat-label">Average per Month</span>
        </div>
    </div>

    <!-- This Month + Top Companies -->
    <div class="dash-grid" style="margin-bottom:1.5rem;">

        <div class="dash-card">
            <div class="dash-card-header">
                <h2>This Month</h2>
                <span style="font-size:0.78rem;color:var(--text-muted);">Quick summary</span>
            </div>
            <div class="summary-list">
                <div class="summary-row">
                    <span class="summary-label">Applied this month</span>
                    <span class="summary-value"><?= $this_month['total'] ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Interviews this month</span>
                    <span class="summary-value caution"><?= $this_month['interviews'] ?></span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Rejections this month</span>
                    <span class="summary-value danger"><?= $this_month['rejections'] ?></span>
                </div>
            </div>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <h2>Top Companies</h2>
                <span style="font-size:0.78rem;color:var(--text-muted);">Most applied to</span>
            </div>
            <?php if (empty($company_rows)): ?>
                <p class="empty-msg">No data yet.</p>
            <?php else: ?>
            <div class="bar-list">
                <?php foreach ($company_rows as $r): ?>
                <div class="bar-item">
                    <div class="bar-label"><?= htmlspecialchars($r['name']) ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= round(($r['count']/$max_count)*100) ?>%"></div>
                    </div>
                    <div class="bar-count"><?= $r['count'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Applications per Month chart -->
    <div class="dash-card" style="margin-bottom:1.5rem;">
        <div class="dash-card-header">
            <h2>Applications per Month</h2>
        </div>
        <?php if (empty($chart_labels)): ?>
            <p class="empty-msg">No data yet.</p>
        <?php else: ?>
        <div style="position:relative; height:260px; width:100%;">
            <canvas id="monthlyChart"></canvas>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status breakdown + Weekly activity -->
    <div class="dash-grid" style="margin-bottom:1.5rem;">

        <div class="dash-card">
            <div class="dash-card-header">
                <h2>Status Breakdown</h2>
            </div>
            <table class="data-table">
                <thead>
                    <tr><th>Status</th><th>Count</th><th>% of Total</th></tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($progression)): ?>
                <tr>
                    <td><span class="badge badge-<?= strtolower(str_replace(' ','-',$row['label'])) ?>">
                        <?= htmlspecialchars($row['label']) ?></span></td>
                    <td><?= $row['count'] ?></td>
                    <td>
                        <div class="inline-bar-wrap">
                            <div class="inline-bar" style="width:<?= min($row['percentage'],100) ?>%"></div>
                            <span><?= $row['percentage'] ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="dash-card">
            <div class="dash-card-header">
                <h2>Weekly Activity</h2>
            </div>
            <?php if (empty($week_rows)): ?>
                <p class="empty-msg">No data yet.</p>
            <?php else: ?>
            <div class="bar-list">
                <?php foreach ($week_rows as $r): ?>
                <div class="bar-item">
                    <div class="bar-label" style="min-width:110px"><?= htmlspecialchars($r['week_start']) ?></div>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= round(($r['total']/$week_max)*100) ?>%"></div>
                    </div>
                    <div class="bar-count"><?= $r['total'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Monthly breakdown table -->
    <div class="dash-card" style="margin-bottom:1.5rem;">
        <div class="dash-card-header">
            <h2>Monthly Breakdown</h2>
        </div>
        <?php if (empty($monthly_rows)): ?>
            <p class="empty-msg">No data yet.</p>
        <?php else: ?>
        <table class="data-table">
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
            <?php foreach ($monthly_rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['month_label']) ?></td>
                <td><?= $row['total'] ?></td>
                <td><?= $row['interviews'] ?></td>
                <td><?= $row['rejections'] ?></td>
                <td>
                    <div class="inline-bar-wrap">
                        <div class="inline-bar" style="width:<?= min($row['interview_rate'],100) ?>%;background:var(--amber)"></div>
                        <span><?= $row['interview_rate'] ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- By company table -->
    <div class="dash-card">
        <div class="dash-card-header">
            <h2>Applications by Company</h2>
        </div>
        <?php if (mysqli_num_rows($by_company) === 0): ?>
            <p class="empty-msg">No data yet.</p>
        <?php else: ?>
        <table class="data-table">
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
            <?php while ($row = mysqli_fetch_assoc($by_company)): ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                <td><?= htmlspecialchars($row['industry'] ?? '—') ?></td>
                <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
                <td><?= $row['total'] ?></td>
                <td><?= $row['interviews'] ?></td>
                <td><?= $row['rejections'] ?></td>
                <td class="cell-date"><?= $row['last_applied'] ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- container -->

<?php if (!empty($chart_labels)): ?>
<script>
const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
            label: 'Applications',
            data: <?= json_encode($chart_data) ?>,
            backgroundColor: 'rgba(47,127,214,0.75)',
            borderColor: '#2F7FD6',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 },
                grid: { color: '#eef2f6' }
            },
            x: { grid: { display: false } }
        }
    }
});
</script>
<?php endif; ?>

<?php include "../includes/footer.php"; ?>