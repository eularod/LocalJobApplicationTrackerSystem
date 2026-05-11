<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];
$modal_error   = "";
$modal_success = "";

// Handle ADD via modal
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

// Handle EDIT via modal
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

// Search & filter
$search = trim($_GET['search'] ?? '');
$filter = intval($_GET['status'] ?? 0);

$where = "WHERE a.user_id = $user_id";
if ($search !== '') {
    $safe_search = mysqli_real_escape_string($conn, $search);
    $where .= " AND (a.position LIKE '%$safe_search%' OR c.name LIKE '%$safe_search%')";
}
if ($filter > 0) {
    $where .= " AND a.status_id = $filter";
}

$applications = mysqli_query($conn, "
    SELECT a.application_id, a.position, a.applied_date, a.job_url,
           a.company_id, a.status_id,
           c.name AS company, c.location,
           s.label AS status
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    JOIN statuses  s ON a.status_id  = s.status_id
    $where
    ORDER BY a.applied_date DESC
");

// Dropdown data for modals
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
  <title>Applications - Job Tracker</title>
  <link rel="stylesheet" href="../css/style.css">
  <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<!-- ADD Modal -->
<div class="modal-overlay" id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3>Add Application</h3>
      <button class="modal-close" onclick="closeAddModal()">✕</button>
    </div>

    <?php if ($modal_error && ($_POST['modal_action'] ?? '') === 'add'): ?>
      <div class="alert alert-error"><?= htmlspecialchars($modal_error) ?></div>
    <?php endif; ?>
    <?php if ($modal_success && ($_POST['modal_action'] ?? '') === 'add'): ?>
      <div class="alert alert-success"><?= htmlspecialchars($modal_success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="modal_action" value="add">

      <div class="form-group">
        <label>Position / Job Title <span style="color:var(--red)">*</span></label>
        <input type="text" name="position" placeholder="e.g. Data Analyst" required>
      </div>

      <div class="form-group">
        <label>Company <span style="color:var(--red)">*</span></label>
        <select name="company_id">
          <option value="0">— Select existing or add new below —</option>
          <?php foreach ($companies_list as $c): ?>
          <option value="<?= $c['company_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-section-label">Or add a new company</div>
      <div>
        <div class="form-row">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="new_company" placeholder="New company name">
          </div>
          <div class="form-group">
            <label>Industry</label>
            <input type="text" name="industry" placeholder="e.g. Tech">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Website</label>
            <input type="text" name="website" placeholder="https://...">
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" placeholder="e.g. Remote">
          </div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Status <span style="color:var(--red)">*</span></label>
          <select name="status_id">
            <?php foreach ($statuses_list as $s): ?>
            <option value="<?= $s['status_id'] ?>"><?= htmlspecialchars($s['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date Applied <span style="color:var(--red)">*</span></label>
          <input type="date" name="applied_date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Job Posting URL</label>
        <input type="url" name="job_url" placeholder="https://...">
      </div>

      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto;margin-top:0;">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT Modal -->
<div class="modal-overlay" id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3>Edit Application</h3>
      <button class="modal-close" onclick="closeEditModal()">✕</button>
    </div>

    <?php if ($modal_error && ($_POST['modal_action'] ?? '') === 'edit'): ?>
      <div class="alert alert-error"><?= htmlspecialchars($modal_error) ?></div>
    <?php endif; ?>
    <?php if ($modal_success && ($_POST['modal_action'] ?? '') === 'edit'): ?>
      <div class="alert alert-success"><?= htmlspecialchars($modal_success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="modal_action" value="edit">
      <input type="hidden" name="application_id" id="edit-field-id">

      <div class="form-group">
        <label>Position / Job Title <span style="color:var(--red)">*</span></label>
        <input type="text" name="position" id="edit-field-position" placeholder="e.g. Data Analyst" required>
      </div>

      <div class="form-group">
        <label>Company <span style="color:var(--red)">*</span></label>
        <select name="company_id" id="edit-field-company">
          <option value="0">— Select a company —</option>
          <?php foreach ($companies_list as $c): ?>
          <option value="<?= $c['company_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Status <span style="color:var(--red)">*</span></label>
          <select name="status_id" id="edit-field-status">
            <?php foreach ($statuses_list as $s): ?>
            <option value="<?= $s['status_id'] ?>"><?= htmlspecialchars($s['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date Applied <span style="color:var(--red)">*</span></label>
          <input type="date" name="applied_date" id="edit-field-date">
        </div>
      </div>

      <div class="form-group">
        <label>Job Posting URL</label>
        <input type="url" name="job_url" id="edit-field-url" placeholder="https://...">
      </div>

      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto;margin-top:0;">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Main Content -->
<div class="container">

  <div class="page-header">
    <h1>All Applications</h1>
    <button class="btn btn-primary" onclick="openAddModal()">+ Add Application</button>
  </div>

  <?php if ($modal_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($modal_success) ?></div>
  <?php endif; ?>

  <!-- Search and Filter -->
  <form method="GET" action="" class="filter-bar">
    <input type="text" name="search" placeholder="Search position or company..."
           value="<?= htmlspecialchars($search) ?>">
    <select name="status">
      <option value="0">All Statuses</option>
      <?php foreach ($statuses_list as $s): ?>
      <option value="<?= $s['status_id'] ?>" <?= $filter == $s['status_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($s['label']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <a href="applications.php" class="btn btn-secondary" style="display:inline-flex;align-items:center;box-sizing:border-box;">Clear</a>
  </form>

  <?php if (mysqli_num_rows($applications) === 0): ?>
    <div class="empty-msg">No applications found. <a onclick="openAddModal()" style="cursor:pointer;">Add one →</a></div>
  <?php else: ?>
  <table class="data-table">
    <thead>
      <tr>
        <th>Position</th>
        <th>Company</th>
        <th>Location</th>
        <th>Date Applied</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = mysqli_fetch_assoc($applications)): ?>
      <tr>
        <td>
          <a href="view_application.php?id=<?= $row['application_id'] ?>" class="action-link" style="font-weight:600;">
            <?= htmlspecialchars($row['position']) ?>
          </a>
          <?php if ($row['job_url']): ?>
            <a href="<?= htmlspecialchars($row['job_url']) ?>" target="_blank"
               style="font-size:.78rem;color:var(--text-muted);margin-left:6px;">↗ Posting</a>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($row['company']) ?></td>
        <td class="cell-date"><?= htmlspecialchars($row['location'] ?? '—') ?></td>
        <td class="cell-date"><?= $row['applied_date'] ?></td>
        <td><span class="badge badge-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
        <td style="display:flex;gap:.4rem;align-items:center;">
          <button class="btn btn-sm btn-secondary"
            data-id="<?= $row['application_id'] ?>"
            data-company="<?= $row['company_id'] ?>"
            data-position="<?= htmlspecialchars($row['position'], ENT_QUOTES) ?>"
            data-status="<?= $row['status_id'] ?>"
            data-date="<?= $row['applied_date'] ?>"
            data-url="<?= htmlspecialchars($row['job_url'] ?? '', ENT_QUOTES) ?>"
            onclick="openEditModal(this)">Edit</button>
          <a href="delete_application.php?id=<?= $row['application_id'] ?>"
             class="btn btn-sm btn-secondary"
             style="color:var(--red);"
             onclick="return confirm('Delete this application?')">Delete</a>
        </td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <?php endif; ?>

</div>
<?php include "../includes/footer.php"; ?>

<script>
const addModal  = document.getElementById('addModal');
const editModal = document.getElementById('editModal');

// Re-open correct modal after POST (validation error or success)
<?php if (($modal_error || $modal_success) && ($_POST['modal_action'] ?? '') === 'add'): ?>
addModal.style.display = 'flex';
<?php endif; ?>

<?php if (($modal_error || $modal_success) && ($_POST['modal_action'] ?? '') === 'edit'): ?>
editModal.style.display = 'flex';
document.getElementById('edit-field-id').value       = '<?= htmlspecialchars($_POST['application_id'] ?? '') ?>';
document.getElementById('edit-field-position').value = '<?= htmlspecialchars($_POST['position'] ?? '') ?>';
document.getElementById('edit-field-company').value  = '<?= htmlspecialchars($_POST['company_id'] ?? '') ?>';
document.getElementById('edit-field-status').value   = '<?= htmlspecialchars($_POST['status_id'] ?? '') ?>';
document.getElementById('edit-field-date').value     = '<?= htmlspecialchars($_POST['applied_date'] ?? '') ?>';
document.getElementById('edit-field-url').value      = '<?= htmlspecialchars($_POST['job_url'] ?? '') ?>';
<?php endif; ?>

function openAddModal() {
  addModal.style.display = 'flex';
}
function closeAddModal() {
  addModal.style.display = 'none';
}

function openEditModal(btn) {
  document.getElementById('edit-field-id').value       = btn.getAttribute('data-id');
  document.getElementById('edit-field-position').value = btn.getAttribute('data-position');
  document.getElementById('edit-field-company').value  = btn.getAttribute('data-company');
  document.getElementById('edit-field-status').value   = btn.getAttribute('data-status');
  document.getElementById('edit-field-date').value     = btn.getAttribute('data-date');
  document.getElementById('edit-field-url').value      = btn.getAttribute('data-url');
  editModal.style.display = 'flex';
}
function closeEditModal() {
  editModal.style.display = 'none';
}

// Close on backdrop click
addModal.addEventListener('click',  function(e) { if (e.target === addModal)  closeAddModal(); });
editModal.addEventListener('click', function(e) { if (e.target === editModal) closeEditModal(); });

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeAddModal();
    closeEditModal();
  }
});
</script>

<?php include "../includes/footer.php"; ?>