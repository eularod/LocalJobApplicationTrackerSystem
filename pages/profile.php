<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn    = get_conn();
$user_id = $_SESSION['user_id'];

// Fetch user record
$user_stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE user_id = ?");
mysqli_stmt_bind_param($user_stmt, "i", $user_id);
mysqli_stmt_execute($user_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($user_stmt));

// Quick stats
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)               AS total,
        SUM(status_id = 2)     AS interviews,
        SUM(status_id = 3)     AS rejected,
        COUNT(DISTINCT company_id) AS companies
    FROM applications WHERE user_id = $user_id
"));

// Applied jobs (most recent 5 for preview)
$applied = mysqli_query($conn, "
    SELECT a.application_id, a.position, a.applied_date,
           c.name AS company, s.label AS status
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    JOIN statuses  s ON a.status_id  = s.status_id
    WHERE a.user_id = $user_id
    ORDER BY a.applied_date DESC
    LIMIT 5
");

// Handle: Edit username
$name_error   = "";
$name_success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_name') {
    $new_username = trim($_POST['username'] ?? '');
    if (empty($new_username)) {
        $name_error = "Username cannot be empty.";
    } else {
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        mysqli_stmt_bind_param($check, "si", $new_username, $user_id);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        if (mysqli_stmt_num_rows($check) > 0) {
            $name_error = "That username is already taken.";
        } else {
            $upd = mysqli_prepare($conn, "UPDATE users SET username = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($upd, "si", $new_username, $user_id);
            if (mysqli_stmt_execute($upd)) {
                $user['username'] = $new_username;
                $_SESSION['username'] = $new_username;
                $name_success = "Username updated successfully.";
            } else {
                $name_error = "Failed to update username. Please try again.";
            }
        }
    }
}

// Handle: Change password
$pw_error   = "";
$pw_success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current  = $_POST['current_password'] ?? '';
    $new_pw   = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new_pw) || empty($confirm)) {
        $pw_error = "All password fields are required.";
    } elseif ($new_pw !== $confirm) {
        $pw_error = "New passwords do not match.";
    } elseif (strlen($new_pw) < 8) {
        $pw_error = "New password must be at least 8 characters.";
    } elseif (!password_verify($current, $user['password'])) {
        $pw_error = "Current password is incorrect.";
    } else {
        $hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $upd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
        mysqli_stmt_bind_param($upd, "si", $hashed, $user_id);
        if (mysqli_stmt_execute($upd)) {
            $pw_success = "Password changed successfully.";
        } else {
            $pw_error = "Failed to update password. Please try again.";
        }
    }
}

$username     = $user['username'] ?? 'User';
$initials     = strtoupper(substr($username, 0, 1));
$member_since = isset($user['created_at']) ? date('F Y', strtotime($user['created_at'])) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile - Job Tracker</title>
  <link rel="stylesheet" href="../css/style.css">
  <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<!-- Edit Username Modal -->
<div class="modal-overlay" id="editNameModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3>Edit Username</h3>
      <button class="modal-close" onclick="closeModal('editNameModal')">✕</button>
    </div>
    <?php if ($name_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($name_error) ?></div>
    <?php endif; ?>
    <?php if ($name_success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($name_success) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit_name">
      <div class="form-group">
        <label>Username <span style="color:var(--red)">*</span></label>
        <input type="text" name="username"
               value="<?= htmlspecialchars($username) ?>"
               placeholder="Enter new username" required>
      </div>
      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('editNameModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto;margin-top:0;">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="changePasswordModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:420px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3>Change Password</h3>
      <button class="modal-close" onclick="closeModal('changePasswordModal')">✕</button>
    </div>
    <?php if ($pw_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($pw_error) ?></div>
    <?php endif; ?>
    <?php if ($pw_success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($pw_success) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <input type="hidden" name="action" value="change_password">
      <div class="form-group">
        <label>Current Password <span style="color:var(--red)">*</span></label>
        <input type="password" name="current_password" placeholder="Enter current password" required>
      </div>
      <div class="form-group">
        <label>New Password <span style="color:var(--red)">*</span></label>
        <input type="password" name="new_password" placeholder="Min. 8 characters" required>
      </div>
      <div class="form-group">
        <label>Confirm New Password <span style="color:var(--red)">*</span></label>
        <input type="password" name="confirm_password" placeholder="Repeat new password" required>
      </div>
      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('changePasswordModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto;margin-top:0;">Update Password</button>
      </div>
    </form>
  </div>
</div>

<div class="container">

  <div class="page-header">
    <h1>Profile</h1>
    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
  </div>

  <div class="detail-grid" style="grid-template-columns:1fr 2fr;">

    <!-- Left: Avatar + identity card -->
    <div style="display:flex;flex-direction:column;gap:1rem;">

      <div class="dash-card" style="text-align:center;padding:2rem 1.5rem;">
        <!-- Avatar -->
        <div style="
          width:80px;height:80px;border-radius:var(--r-xl);
          background:var(--navy-mid);color:var(--blue-light);
          font-size:2rem;font-weight:600;
          display:flex;align-items:center;justify-content:center;
          margin:0 auto 1rem;border:2px solid rgba(47,127,214,.25);">
          <?= htmlspecialchars($initials) ?>
        </div>

        <div style="font-size:1.05rem;font-weight:600;color:var(--text-primary);letter-spacing:-0.02em;">
          <?= htmlspecialchars($username) ?>
        </div>
        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:8px;">
          Member since <?= $member_since ?>
        </div>

        <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:1.25rem;">
          <button class="btn btn-secondary" style="width:100%;" onclick="openModal('editNameModal')">
            Edit Username
          </button>
          <button class="btn btn-secondary" style="width:100%;" onclick="openModal('changePasswordModal')">
            Change Password
          </button>
          <a href="../auth/logout.php" class="btn btn-secondary" style="width:100%;color:var(--red);">
            Logout
          </a>
        </div>
      </div>

      <!-- Stats mini-card -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h2>Activity Summary</h2>
        </div>
        <div class="detail-list">
          <div class="detail-row">
            <span class="detail-label">Applications</span>
            <span class="detail-value" style="text-align:right;font-weight:600;"><?= $stats['total'] ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Interviews</span>
            <span class="detail-value" style="text-align:right;font-weight:600;color:var(--amber);"><?= $stats['interviews'] ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Rejected</span>
            <span class="detail-value" style="text-align:right;font-weight:600;color:var(--red);"><?= $stats['rejected'] ?></span>
          </div>
          <div class="detail-row">
            <span class="detail-label">Companies</span>
            <span class="detail-value" style="text-align:right;font-weight:600;"><?= $stats['companies'] ?></span>
          </div>
        </div>
      </div>

    </div>

    <!-- Right: Applied jobs -->
    <div class="dash-card">
      <div class="dash-card-header">
        <h2>Applied Jobs</h2>
        <a href="applications.php" class="dash-link">View all →</a>
      </div>

      <?php if (mysqli_num_rows($applied) === 0): ?>
        <div class="empty-msg">No applications yet. <a href="applications.php" style="color:var(--blue);font-weight:500;">Add your first one →</a></div>
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
          <?php while ($row = mysqli_fetch_assoc($applied)): ?>
          <tr>
            <td style="font-weight:600;">
              <?= htmlspecialchars($row['position']) ?>
            </td>
            <td><?= htmlspecialchars($row['company']) ?></td>
            <td class="cell-date"><?= $row['applied_date'] ?></td>
            <td>
              <span class="badge badge-<?= strtolower(str_replace(' ', '-', $row['status'])) ?>">
                <?= htmlspecialchars($row['status']) ?>
              </span>
            </td>
            <td>
              <a href="view_application.php?id=<?= $row['application_id'] ?>" class="action-link">View</a>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <?php if ($stats['total'] > 5): ?>
      <div style="padding:0.75rem 0 0;text-align:center;">
        <a href="applications.php" class="btn btn-secondary" style="width:100%;">
          View all <?= $stats['total'] ?> applications
        </a>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
function openModal(id) {
  document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}

// Close on backdrop click
['editNameModal', 'changePasswordModal'].forEach(function(id) {
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) closeModal(id);
  });
});

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeModal('editNameModal');
    closeModal('changePasswordModal');
  }
});

// Re-open modal on error OR success so the message is visible
<?php if ($name_error || $name_success): ?>
openModal('editNameModal');
<?php endif; ?>
<?php if ($pw_error || $pw_success): ?>
openModal('changePasswordModal');
<?php endif; ?>

// Auto-close modal after success
<?php if ($pw_success): ?>
setTimeout(function() { closeModal('changePasswordModal'); }, 3000);
<?php endif; ?>
<?php if ($name_success): ?>
setTimeout(function() { closeModal('editNameModal'); }, 3000);
<?php endif; ?>
</script>

<?php include "../includes/footer.php"; ?>