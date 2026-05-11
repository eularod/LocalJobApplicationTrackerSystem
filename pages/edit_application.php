<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];
$error   = "";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: applications.php");
    exit();
}

// Fetch the application
$stmt = mysqli_prepare($conn, "
    SELECT a.*, c.name AS company_name, c.industry, c.website, c.location
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    WHERE a.application_id = ? AND a.user_id = ?
");
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$app    = mysqli_fetch_assoc($result);

if (!$app) {
    header("Location: applications.php");
    exit();
}

$companies = mysqli_query($conn, "SELECT * FROM companies ORDER BY name");
$statuses  = mysqli_query($conn, "SELECT * FROM statuses ORDER BY sort_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position     = trim($_POST['position']);
    $company_id   = intval($_POST['company_id']);
    $status_id    = intval($_POST['status_id']);
    $applied_date = trim($_POST['applied_date']);
    $job_url      = trim($_POST['job_url']);

    if (empty($position) || empty($applied_date) || $status_id <= 0 || $company_id <= 0) {
        $error = "Position, company, date, and status are required.";
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE applications
            SET company_id = ?, status_id = ?, position = ?, applied_date = ?, job_url = ?
            WHERE application_id = ? AND user_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "iisssii", $company_id, $status_id, $position, $applied_date, $job_url, $id, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            header("Location: applications.php?updated=1");
            exit();
        } else {
            $error = "Failed to update. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Application - Job Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="container">
    <div class="page-header">
        <h1>Edit Application</h1>
        <a href="applications.php" class="btn btn-secondary">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="">

            <div class="form-group">
                <label for="position">Position / Job Title <span style="color:red"> *</span></label>
                <input type="text" id="position" name="position" required
                       value="<?= htmlspecialchars($_POST['position'] ?? $app['position']) ?>">
            </div>

            <div class="form-group">
                <label for="company_id">Company <span style="color:red"> *</span></label>
                <select id="company_id" name="company_id" required>
                    <?php while ($c = mysqli_fetch_assoc($companies)): ?>
                    <option value="<?= $c['company_id'] ?>"
                        <?= ($c['company_id'] == ($POST['company_id'] ?? $app['company_id'])) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status_id">Status <span style="color:red"> *</span></label>
                    <select id="status_id" name="status_id" required>
                        <?php while ($s = mysqli_fetch_assoc($statuses)): ?>
                        <option value="<?= $s['status_id'] ?>"
                            <?= ($s['status_id'] == ($_POST['status_id'] ?? $app['status_id'])) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['label']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="applied_date">Date Applied <span style="color:red"> *</span></label>
                    <input type="date" id="applied_date" name="applied_date" required
                           value="<?= htmlspecialchars($_POST['applied_date'] ?? $app['applied_date']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="job_url">Job Posting URL</label>
                <input type="text" id="job_url" name="job_url"
                       value="<?= htmlspecialchars($_POST['job_url'] ?? $app['job_url']) ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update Application</button>
                <a href="applications.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div><!-- /container -->

<?php include "../includes/footer.php"; ?>