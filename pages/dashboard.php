<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];
$modal_error   = "";
$modal_success = "";

// Handle add via modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_action']) && $_POST['modal_action'] === 'add') {
    $position     = trim($_POST['position']);
    $company_id   = intval($_POST['company_id']);
    $status_id    = intval($_POST['status_id']);
    $applied_date = trim($_POST['applied_date']);
    $job_url      = trim($_POST['job_url']);
    $new_company  = trim($_POST['new_company'] ?? '');
    $industry     = trim($_POST['industry'] ?? '');
    $website      = trim($_POST['website'] ?? '');
    $location     = trim($_POST['location'] ?? '');

    if (empty($position) || empty($applied_date) || $status_id <= 0) {
        $modal_error = "Position, date, and status are required.";
    } else {
        if ($new_company !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO companies (name, industry, website, location) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $new_company, $industry, $website, $location);
            mysqli_stmt_execute($stmt);
            $company_id = mysqli_insert_id($conn);
        }
        if ($company_id <= 0) {
            $modal_error = "Please select or enter a company.";
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO applications (user_id, company_id, status_id, position, applied_date, job_url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iiisss", $user_id, $company_id, $status_id, $position, $applied_date, $job_url);
            if (mysqli_stmt_execute($stmt)) {
                $modal_success = "Application added successfully!";
            } else {
                $modal_error = "Failed to save application. Please try again.";
            }
        }
    }
}

// Handle edit via modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_action']) && $_POST['modal_action'] === 'edit') {
    $app_id       = intval($_POST['application_id']);
    $position     = trim($_POST['position']);
    $company_id   = intval($_POST['company_id']);
    $status_id    = intval($_POST['status_id']);
    $applied_date = trim($_POST['applied_date']);
    $job_url      = trim($_POST['job_url']);

    if (empty($position) || empty($applied_date) || $status_id <= 0 || $company_id <= 0) {
        $modal_error = "Position, company, date, and status are required.";
    } else {
        $stmt = mysqli_prepare($conn, "
            UPDATE applications
            SET company_id=?, status_id=?, position=?, applied_date=?, job_url=?
            WHERE application_id=? AND user_id=?
        ");
        mysqli_stmt_bind_param($stmt, "iisssii", $company_id, $status_id, $position, $applied_date, $job_url, $app_id, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $modal_success = "Application updated successfully!";
        } else {
            $modal_error = "Failed to update. Please try again.";
        }
    }
}

// Queries
$total = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM applications WHERE user_id = $user_id"
))['total'];

$by_status = mysqli_query($conn, "
    SELECT s.label, s.status_id, COUNT(a.application_id) AS count
    FROM statuses s
    LEFT JOIN applications a ON s.status_id = a.status_id AND a.user_id = $user_id
    GROUP BY s.status_id, s.label
    ORDER BY s.sort_order
");

$recent = mysqli_query($conn, "
    SELECT a.application_id, a.position, a.applied_date,
           c.name AS company, s.label AS status, a.company_id, a.status_id, a.job_url
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    JOIN statuses  s ON a.status_id  = s.status_id
    WHERE a.user_id = $user_id
    ORDER BY a.applied_date DESC, a.application_id DESC
    LIMIT 5
");

// Dropdown data for modal
$companies_q = mysqli_query($conn, "SELECT * FROM companies ORDER BY name");
$statuses_q  = mysqli_query($conn, "SELECT * FROM statuses ORDER BY sort_order");
$companies_list = [];
$statuses_list  = [];
while ($r = mysqli_fetch_assoc($companies_q)) $companies_list[] = $r;
while ($r = mysqli_fetch_assoc($statuses_q))  $statuses_list[]  = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Job Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<!-- Add / Edit Application Modal -->
<div class="modal-overlay" id="appModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3 id="modal-title">Add Application</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <?php if ($modal_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($modal_error) ?></div>
    <?php endif; ?>
    <?php if ($modal_success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($modal_success) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="appForm">
      <input type="hidden" name="modal_action" id="modal-action" value="add">
      <input type="hidden" name="application_id" id="field-id" value="">

      <div class="form-group">
        <label>Position / Job Title <span style="color:var(--red)">*</span></label>
        <input type="text" name="position" id="field-position" placeholder="e.g. Data Analyst" required>
      </div>

      <div class="form-group">
        <label>Company <span style="color:var(--red)">*</span></label>
        <select name="company_id" id="field-company">
          <option value="0">— Select existing or add new below —</option>
          <?php foreach ($companies_list as $c): ?>
          <option value="<?= $c['company_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-section-label" id="new-company-toggle-label">
        Or add a new company
      </div>
      <div id="new-company-fields">
        <div class="form-row">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="new_company" id="field-new-company" placeholder="New company name">
          </div>
          <div class="form-group">
            <label>Industry</label>
            <input type="text" name="industry" id="field-industry" placeholder="e.g. Tech">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Website</label>
            <input type="text" name="website" id="field-website" placeholder="https://...">
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" id="field-location" placeholder="e.g. Remote">
          </div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Status <span style="color:var(--red)">*</span></label>
          <select name="status_id" id="field-status">
            <?php foreach ($statuses_list as $s): ?>
            <option value="<?= $s['status_id'] ?>"><?= htmlspecialchars($s['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date Applied <span style="color:var(--red)">*</span></label>
          <input type="date" name="applied_date" id="field-date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Job Posting URL</label>
        <input type="url" name="job_url" id="field-url" placeholder="https://...">
      </div>

      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto;margin-top:0;">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="container">

  <!-- Page header -->
  <div class="page-header">
    <h1>Dashboard</h1>
    <div style="display:flex;gap:.6rem;">
      <a href="reports.php" class="btn btn-secondary">View Reports</a>
      <button class="btn btn-primary" onclick="openAddModal()">+ Add Application</button>
    </div>
  </div>

  <!-- Stat cards -->
  <div class="stats-grid">
    <div class="stat-card stat-blue">
      <span class="stat-number"><?= $total ?></span>
      <span class="stat-label">Total Applications</span>
    </div>
    <?php
    $badge_colors = [
        'Applied'   => 'stat-blue',
        'Interview' => 'stat-amber',
        'Rejected'  => 'stat-red',
        'Withdrawn' => '',
    ];
    while ($row = mysqli_fetch_assoc($by_status)):
        $cls = $badge_colors[$row['label']] ?? 'stat-blue';
    ?>
    <div class="stat-card <?= $cls ?>">
      <span class="stat-number"><?= $row['count'] ?></span>
      <span class="stat-label"><?= htmlspecialchars($row['label']) ?></span>
    </div>
    <?php endwhile; ?>
  </div>

  <!-- Recent activity -->
  <div class="dash-card">
    <div class="dash-card-header">
      <h2>Recent Activity</h2>
      <a href="applications.php" class="dash-link">View all</a>
    </div>
    <?php if (mysqli_num_rows($recent) === 0): ?>
      <div class="empty-msg">No applications yet. <a onclick="openAddModal()" style="cursor:pointer;">Add your first one.</a></div>
    <?php else: ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Position</th>
          <th>Company</th>
          <th>Date Applied</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php while ($row = mysqli_fetch_assoc($recent)): ?>
        <tr>
          <td>
            <a href="view_application.php?id=<?= $row['application_id'] ?>" class="action-link" style="font-weight:600;">
              <?= htmlspecialchars($row['position']) ?>
            </a>
          </td>
          <td><?= htmlspecialchars($row['company']) ?></td>
          <td class="cell-date"><?= $row['applied_date'] ?></td>
          <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
          <td>
            <button class="btn btn-sm btn-secondary edit-app-btn"
              data-id="<?= $row['application_id'] ?>"
              data-company="<?= $row['company_id'] ?>"
              data-position="<?= htmlspecialchars($row['position'], ENT_QUOTES) ?>"
              data-status="<?= $row['status_id'] ?>"
              data-date="<?= $row['applied_date'] ?>"
              data-url="<?= htmlspecialchars($row['job_url'] ?? '', ENT_QUOTES) ?>">Edit</button>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

</div>

<script>
const modal      = document.getElementById('appModal');
const modalTitle = document.getElementById('modal-title');
const newCompanyFields = document.getElementById('new-company-fields');
const newCompanyLabel  = document.getElementById('new-company-toggle-label');

<?php if ($modal_error || $modal_success): ?>
modal.style.display = 'flex';
<?php if (isset($_POST['modal_action'])): ?>
document.getElementById('modal-action').value = '<?= htmlspecialchars($_POST['modal_action']) ?>';
<?php endif; ?>
<?php endif; ?>

function openAddModal() {
  modalTitle.textContent = 'Add Application';
  document.getElementById('modal-action').value     = 'add';
  document.getElementById('field-id').value          = '';
  document.getElementById('field-position').value    = '';
  document.getElementById('field-company').value     = '0';
  document.getElementById('field-new-company').value = '';
  document.getElementById('field-industry').value    = '';
  document.getElementById('field-website').value     = '';
  document.getElementById('field-location').value    = '';
  document.getElementById('field-status').value      = '<?= $statuses_list[0]['status_id'] ?? 1 ?>';
  document.getElementById('field-date').value        = '<?= date('Y-m-d') ?>';
  document.getElementById('field-url').value         = '';
  newCompanyFields.style.display = '';
  newCompanyLabel.style.display  = '';
  modal.style.display = 'flex';
}

function openEditModal(id, company_id, position, status_id, date, job_url) {
  modalTitle.textContent = 'Edit Application';
  document.getElementById('modal-action').value   = 'edit';
  document.getElementById('field-id').value        = id;
  document.getElementById('field-position').value  = position;
  document.getElementById('field-company').value   = company_id;
  document.getElementById('field-status').value    = status_id;
  document.getElementById('field-date').value      = date;
  document.getElementById('field-url').value       = job_url;
  newCompanyFields.style.display = 'none';
  newCompanyLabel.style.display  = 'none';
  modal.style.display = 'flex';
}

// Use event delegation to avoid inline onclick quoting issues
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.edit-app-btn');
  if (!btn) return;
  openEditModal(
    btn.dataset.id,
    btn.dataset.company,
    btn.dataset.position,
    btn.dataset.status,
    btn.dataset.date,
    btn.dataset.url
  );
});

function closeModal() {
  modal.style.display = 'none';
}

modal.addEventListener('click', function(e) {
  if (e.target === modal) closeModal();
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});
</script>

<?php include "../includes/footer.php"; ?>