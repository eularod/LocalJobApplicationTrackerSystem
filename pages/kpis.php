<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn    = get_conn();
$user_id = $_SESSION['user_id'];

// 1. Core totals needed to calculate rates
$totals = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                                AS total,
        SUM(status_id = 2)                      AS interviews,
        SUM(status_id = 3)                      AS rejected,
        SUM(status_id = 4)                      AS withdrawn,
        COUNT(DISTINCT company_id)              AS unique_companies,
        MIN(applied_date)                       AS first_date,
        MAX(applied_date)                       AS latest_date,
        DATEDIFF(MAX(applied_date), MIN(applied_date)) AS active_days
    FROM applications
    WHERE user_id = $user_id
"));

$total       = $totals['total']       ?: 0;
$interviews  = $totals['interviews']  ?: 0;
$rejected    = $totals['rejected']    ?: 0;
$withdrawn   = $totals['withdrawn']   ?: 0;
$companies   = $totals['unique_companies'] ?: 0;
$active_days = $totals['active_days'] ?: 0;

// 2. Derived rate KPIs
$interview_rate   = $total > 0 ? round(($interviews / $total) * 100, 1) : 0;
$rejection_rate   = $total > 0 ? round(($rejected   / $total) * 100, 1) : 0;
$withdrawal_rate  = $total > 0 ? round(($withdrawn  / $total) * 100, 1) : 0;
$active_pipeline  = $total - $rejected - $withdrawn;
$active_rate      = $total > 0 ? round(($active_pipeline / $total) * 100, 1) : 0;
$apps_per_company = $companies > 0 ? round($total / $companies, 1) : 0;
$apps_per_week    = $active_days > 0 ? round($total / ($active_days / 7), 1) : $total;

// Overall search health score (0–100)
$health = 0;
if ($total >= 10)          $health += 20;
elseif ($total >= 5)       $health += 10;
if ($interview_rate >= 20) $health += 30;
elseif ($interview_rate >= 10) $health += 15;
if ($apps_per_week >= 5)   $health += 20;
elseif ($apps_per_week >= 2)   $health += 10;
if ($rejection_rate <= 30) $health += 15;
elseif ($rejection_rate <= 50) $health += 8;
if ($active_rate >= 50)    $health += 15;
elseif ($active_rate >= 25)    $health += 8;
$health = min($health, 100);

$health_label = $health >= 75 ? 'Excellent' : ($health >= 50 ? 'Good' : ($health >= 25 ? 'Fair' : 'Needs Work'));
$health_color = $health >= 75 ? 'var(--teal)' : ($health >= 50 ? 'var(--blue)' : ($health >= 25 ? 'var(--amber)' : 'var(--red)'));

// 3. Avg days between applications
$avg_gap = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT ROUND(AVG(gap_days), 1) AS avg_gap
    FROM (
        SELECT DATEDIFF(
            applied_date,
            LAG(applied_date) OVER (ORDER BY applied_date)
        ) AS gap_days
        FROM applications
        WHERE user_id = $user_id
    ) AS gaps
    WHERE gap_days IS NOT NULL
"));
$avg_gap_days = $avg_gap['avg_gap'] ?? '—';

// 4. Best performing month by interview rate
$best_month = mysqli_fetch_assoc(mysqli_query($conn, "
    WITH monthly AS (
        SELECT
            DATE_FORMAT(applied_date, '%M %Y') AS month_label,
            COUNT(*)                           AS total,
            SUM(status_id = 2)                 AS interviews
        FROM applications
        WHERE user_id = $user_id
        GROUP BY DATE_FORMAT(applied_date, '%Y-%m'), month_label
    )
    SELECT *,
           ROUND((interviews / total) * 100, 1) AS rate
    FROM monthly
    WHERE total > 0
    ORDER BY rate DESC
    LIMIT 1
"));

// 5. Slowest month
$slowest_month = mysqli_fetch_assoc(mysqli_query($conn, "
    WITH monthly AS (
        SELECT
            DATE_FORMAT(applied_date, '%M %Y') AS month_label,
            COUNT(*)                           AS total
        FROM applications
        WHERE user_id = $user_id
        GROUP BY DATE_FORMAT(applied_date, '%Y-%m'), month_label
    )
    SELECT * FROM monthly
    ORDER BY total ASC
    LIMIT 1
"));

// 6. Response rate — apps that got any response (interview or rejection)
$responded = $interviews + $rejected;
$response_rate = $total > 0 ? round(($responded / $total) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPIs - Job Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="container">

    <div class="page-header">
        <div>
            <h1>Key Performance Indicators</h1>
        </div>
        <a href="reports.php" class="btn btn-secondary">View Raw Reports</a>
    </div>

    <?php if ($total === 0): ?>
        <p class="empty-msg">
            No applications yet.
            <a href="add_application.php">Add your first one</a> to see your KPIs.
        </p>
    <?php else: ?>

    <!-- Health Score -->
    <div class="dash-card" style="margin-bottom:1.4rem;">
        <div class="dash-card-header">
            <h2>Overall Search Health Score</h2>
            <span class="dash-sub">Composite of all KPIs below</span>
        </div>
        <div class="health-wrap">
            <div class="health-score" style="color:<?= $health_color ?>">
                <?= $health ?><span class="health-max">/100</span>
            </div>
            <div class="health-right">
                <div class="health-label" style="color:<?= $health_color ?>"><?= $health_label ?></div>
                <div class="health-bar-track">
                    <div class="health-bar-fill"
                         style="width:<?= $health ?>%;background:<?= $health_color ?>">
                    </div>
                </div>
                <div class="health-hint">
                    <?php if ($health < 50): ?>
                        Increase your application pace and refine your resume to improve your rate.
                    <?php elseif ($health < 75): ?>
                        You're on the right track — focus on increasing your interview conversion.
                    <?php else: ?>
                        Excellent job search discipline. Keep up the momentum.
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rate KPI cards -->
    <div class="kpi-grid" style="margin-bottom:1.4rem;">

        <div class="kpi-card kpi-amber">
            <div class="kpi-top">
                <span class="kpi-trend <?= $interview_rate >= 20 ? 'up' : ($interview_rate >= 10 ? 'neutral' : 'down') ?>">
                    <?= $interview_rate >= 20 ? 'Strong' : ($interview_rate >= 10 ? 'Average' : 'Low') ?>
                </span>
            </div>
            <div class="kpi-value"><?= $interview_rate ?>%</div>
            <div class="kpi-label">Interview Rate</div>
            <div class="kpi-sub"><?= $interviews ?> interviews from <?= $total ?> applications</div>
        </div>

        <div class="kpi-card kpi-red">
            <div class="kpi-top">
                <span class="kpi-trend <?= $rejection_rate <= 30 ? 'up' : ($rejection_rate <= 50 ? 'neutral' : 'down') ?>">
                    <?= $rejection_rate <= 30 ? 'Low' : ($rejection_rate <= 50 ? 'Medium' : 'High') ?>
                </span>
            </div>
            <div class="kpi-value"><?= $rejection_rate ?>%</div>
            <div class="kpi-label">Rejection Rate</div>
            <div class="kpi-sub"><?= $rejected ?> rejections from <?= $total ?> applications</div>
        </div>

        <div class="kpi-card kpi-blue">
            <div class="kpi-top">
                <span class="kpi-trend <?= $response_rate >= 50 ? 'up' : ($response_rate >= 25 ? 'neutral' : 'down') ?>">
                    <?= $response_rate >= 50 ? 'Good' : ($response_rate >= 25 ? 'Fair' : 'Low') ?>
                </span>
            </div>
            <div class="kpi-value"><?= $response_rate ?>%</div>
            <div class="kpi-label">Response Rate</div>
            <div class="kpi-sub"><?= $responded ?> responses (interviews + rejections)</div>
        </div>

        <div class="kpi-card kpi-teal">
            <div class="kpi-top">
                <span class="kpi-trend <?= $active_rate >= 50 ? 'up' : ($active_rate >= 25 ? 'neutral' : 'down') ?>">
                    <?= $active_pipeline ?> active
                </span>
            </div>
            <div class="kpi-value"><?= $active_rate ?>%</div>
            <div class="kpi-label">Active Pipeline Rate</div>
            <div class="kpi-sub">Applications still in play</div>
        </div>

        <div class="kpi-card kpi-purple">
            <div class="kpi-top">
                <span class="kpi-trend <?= $apps_per_week >= 5 ? 'up' : ($apps_per_week >= 2 ? 'neutral' : 'down') ?>">
                    <?= $apps_per_week >= 5 ? 'High' : ($apps_per_week >= 2 ? 'Steady' : 'Slow') ?>
                </span>
            </div>
            <div class="kpi-value"><?= $apps_per_week ?></div>
            <div class="kpi-label">Applications per Week</div>
            <div class="kpi-sub">Over <?= $active_days ?> active days</div>
        </div>

        <div class="kpi-card kpi-neutral">
            <div class="kpi-top">
                <span class="kpi-trend neutral">cadence</span>
            </div>
            <div class="kpi-value"><?= $avg_gap_days ?></div>
            <div class="kpi-label">Avg Days Between Apps</div>
            <div class="kpi-sub">Your application frequency</div>
        </div>

    </div>

    <!-- Insights -->
    <div class="dash-grid" style="margin-bottom:1.4rem;">

        <div class="dash-card">
            <div class="dash-card-header">
                <h2>Performance Insights</h2>
                <span class="dash-sub">What your data is telling you</span>
            </div>
            <div class="insight-list">

                <?php if ($best_month): ?>
                <div class="insight-row">
                    <div>
                        <div class="insight-title">Best Month by Interview Rate</div>
                        <div class="insight-desc">
                            <strong><?= htmlspecialchars($best_month['month_label']) ?></strong> —
                            <?= $best_month['interviews'] ?> interviews from
                            <?= $best_month['total'] ?> applications
                            (<?= $best_month['rate'] ?>% rate)
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($slowest_month): ?>
                <div class="insight-row">
                    <div>
                        <div class="insight-title">Slowest Month</div>
                        <div class="insight-desc">
                            <strong><?= htmlspecialchars($slowest_month['month_label']) ?></strong> —
                            only <?= $slowest_month['total'] ?> application<?= $slowest_month['total'] != 1 ? 's' : '' ?> that month
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="insight-row">
                    <div>
                        <div class="insight-title">Company Spread</div>
                        <div class="insight-desc">
                            <?= $apps_per_company ?> applications per company on average across <?= $companies ?> companies
                            <?= $apps_per_company > 2
                                ? '— consider spreading across more companies'
                                : '— good diversity across companies' ?>
                        </div>
                    </div>
                </div>

                <div class="insight-row">
                    <div>
                        <div class="insight-title">Interview Conversion</div>
                        <div class="insight-desc">
                            <?= $interview_rate >= 20
                                ? 'Your resume and applications are performing well above average.'
                                : ($interview_rate >= 10
                                    ? 'Average conversion. Try tailoring your resume per application.'
                                    : 'Low conversion rate. Consider revising your resume or targeting more suitable roles.') ?>
                        </div>
                    </div>
                </div>

                <div class="insight-row">
                    <div>
                        <div class="insight-title">Application Pace</div>
                        <div class="insight-desc">
                            <?= $apps_per_week >= 5
                                ? "Strong pace of {$apps_per_week} apps/week. Maintain quality over quantity."
                                : ($apps_per_week >= 2
                                    ? "Steady pace of {$apps_per_week} apps/week. Consider increasing slightly."
                                    : "Low pace of {$apps_per_week} apps/week. Aim for at least 3–5 per week.") ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- KPI Benchmark table -->
        <div class="dash-card">
            <div class="dash-card-header">
                <h2>KPI Benchmarks</h2>
                <span class="dash-sub">Your rates vs industry targets</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Yours</th>
                        <th>Target</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Interview Rate</td>
                        <td><strong><?= $interview_rate ?>%</strong></td>
                        <td>≥ 10%</td>
                        <td><span class="badge <?= $interview_rate >= 10 ? 'badge-offer' : 'badge-rejected' ?>">
                            <?= $interview_rate >= 10 ? 'Met' : 'Below' ?></span></td>
                    </tr>
                    <tr>
                        <td>Response Rate</td>
                        <td><strong><?= $response_rate ?>%</strong></td>
                        <td>≥ 25%</td>
                        <td><span class="badge <?= $response_rate >= 25 ? 'badge-offer' : 'badge-rejected' ?>">
                            <?= $response_rate >= 25 ? 'Met' : 'Below' ?></span></td>
                    </tr>
                    <tr>
                        <td>Rejection Rate</td>
                        <td><strong><?= $rejection_rate ?>%</strong></td>
                        <td>≤ 50%</td>
                        <td><span class="badge <?= $rejection_rate <= 50 ? 'badge-offer' : 'badge-rejected' ?>">
                            <?= $rejection_rate <= 50 ? 'Met' : 'High' ?></span></td>
                    </tr>
                    <tr>
                        <td>Active Pipeline</td>
                        <td><strong><?= $active_rate ?>%</strong></td>
                        <td>≥ 25%</td>
                        <td><span class="badge <?= $active_rate >= 25 ? 'badge-offer' : 'badge-rejected' ?>">
                            <?= $active_rate >= 25 ? 'Met' : 'Below' ?></span></td>
                    </tr>
                    <tr>
                        <td>Apps per Week</td>
                        <td><strong><?= $apps_per_week ?></strong></td>
                        <td>≥ 3/week</td>
                        <td><span class="badge <?= $apps_per_week >= 3 ? 'badge-offer' : 'badge-interview' ?>">
                            <?= $apps_per_week >= 3 ? 'Met' : 'Below' ?></span></td>
                    </tr>
                    <tr>
                        <td>Search Health</td>
                        <td><strong><?= $health ?>/100</strong></td>
                        <td>≥ 50</td>
                        <td><span class="badge <?= $health >= 50 ? 'badge-offer' : 'badge-rejected' ?>">
                            <?= $health >= 50 ? 'Met' : 'Below' ?></span></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    <?php endif; ?>

</div><!-- /container -->

<?php include "../includes/footer.php"; ?>