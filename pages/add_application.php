<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];
$error   = "";
$success = "";

// Load dropdowns
$companies = mysqli_query($conn, "SELECT * FROM companies ORDER BY name");
$statuses  = mysqli_query($conn, "SELECT * FROM statuses ORDER BY sort_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $position     = trim($_POST['position']);
    $company_id   = intval($_POST['company_id']);
    $status_id    = intval($_POST['status_id']);
    $applied_date = trim($_POST['applied_date']);
    $job_url      = trim($_POST['job_url']);

    // Handle new company entry
    $new_company  = trim($_POST['new_company'] ?? '');
    $industry     = trim($_POST['industry'] ?? '');
    $website      = trim($_POST['website'] ?? '');
    $location     = trim($_POST['location'] ?? '');

    if (empty($position) || empty($applied_date) || empty($status_id)) {
        $error = "Position, date, and status are required.";
    } else {
        // If user typed a new company, insert it first
        if ($new_company !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO companies (name, industry, website, location) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $new_company, $industry, $website, $location);
            mysqli_stmt_execute($stmt);
            $company_id = mysqli_insert_id($conn);
        }

        if ($company_id <= 0) {
            $error = "Please select or enter a company.";
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO applications (user_id, company_id, status_id, position, applied_date, job_url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iiisss", $user_id, $company_id, $status_id, $position, $applied_date, $job_url);

            if (mysqli_stmt_execute($stmt)) {
                header("Location: applications.php?added=1");
                exit();
            } else {
                $error = "Failed to save application. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Application - Job Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<div class="container">
    <div class="page-header">
        <h1>Add Application</h1>
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
                       value="<?= htmlspecialchars($_POST['position'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="company_id">Company (existing) <span style="color:red"> *</span></label>
                <select id="company_id" name="company_id" required>
                    <option value="0">— Select existing or add new below —</option>
                    <?php while ($c = mysqli_fetch_assoc($companies)): ?>
                    <option value="<?= $c['company_id'] ?>"
                        <?= (isset($_POST['company_id']) && $_POST['company_id'] == $c['company_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-section-label">Or add a new company</div>
            <div class="form-row">
                <div class="form-group">
                    <label for="new_company">Company Name</label>
                    <input type="text" id="new_company" name="new_company"
                           value="<?= htmlspecialchars($_POST['new_company'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="industry">Industry</label>
                    <input type="text" id="industry" name="industry"
                           value="<?= htmlspecialchars($_POST['industry'] ?? '') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="website">Website</label>
                    <input type="text" id="website" name="website"
                           value="<?= htmlspecialchars($_POST['website'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location"
                           value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="status_id">Status <span style="color:red"> *</span></label>
                    <select id="status_id" name="status_id" required>
                        <?php while ($s = mysqli_fetch_assoc($statuses)): ?>
                        <option value="<?= $s['status_id'] ?>"
                            <?= (isset($_POST['status_id']) && $_POST['status_id'] == $s['status_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['label']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="applied_date">Date Applied <span style="color:red"> *</span></label>
                    <input type="date" id="applied_date" name="applied_date" required
                           value="<?= htmlspecialchars($_POST['applied_date'] ?? date('Y-m-d')) ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="job_url">Job Posting URL</label>
                <input type="text" id="job_url" name="job_url"
                       value="<?= htmlspecialchars($_POST['job_url'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Application</button>
                <a href="applications.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div><!-- /container -->

<?php include "../includes/footer.php"; ?>