<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];
$id      = intval($_GET['id'] ?? 0);

if ($id <= 0) { header("Location: applications.php"); exit(); }

// Fetch application
$stmt = mysqli_prepare($conn, "
    SELECT a.*, c.name AS company, c.industry, c.website, c.location,
           s.label AS status, a.company_id, a.status_id
    FROM applications a
    JOIN companies c ON a.company_id = c.company_id
    JOIN statuses  s ON a.status_id  = s.status_id
    WHERE a.application_id = ? AND a.user_id = ?
");
mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
mysqli_stmt_execute($stmt);
$app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$app) { header("Location: applications.php"); exit(); }

// Handle edit via modal
$modal_error   = "";
$modal_success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_action']) && $_POST['modal_action'] === 'edit') {
    $position     = trim($_POST['position']);
    $company_id   = intval($_POST['company_id']);
    $status_id    = intval($_POST['status_id']);
    $applied_date = trim($_POST['applied_date']);
    $job_url      = trim($_POST['job_url']);

    if (empty($position) || empty($applied_date) || $status_id <= 0 || $company_id <= 0) {
        $modal_error = "Position, company, date, and status are required.";
    } else {
        $upd = mysqli_prepare($conn, "
            UPDATE applications
            SET company_id=?, status_id=?, position=?, applied_date=?, job_url=?
            WHERE application_id=? AND user_id=?
        ");
        mysqli_stmt_bind_param($upd, "iisssii", $company_id, $status_id, $position, $applied_date, $job_url, $id, $user_id);
        if (mysqli_stmt_execute($upd)) {
            // Re-fetch updated app data
            $stmt2 = mysqli_prepare($conn, "
                SELECT a.*, c.name AS company, c.industry, c.website, c.location,
                       s.label AS status, a.company_id, a.status_id
                FROM applications a
                JOIN companies c ON a.company_id = c.company_id
                JOIN statuses  s ON a.status_id  = s.status_id
                WHERE a.application_id = ? AND a.user_id = ?
            ");
            mysqli_stmt_bind_param($stmt2, "ii", $id, $user_id);
            mysqli_stmt_execute($stmt2);
            $app = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
            $modal_success = "Application updated successfully!";
        } else {
            $modal_error = "Failed to update. Please try again.";
        }
    }
}

// Handle note actions
$note_error   = "";
$note_success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $content = trim($_POST['content']);
    if (empty($content)) {
        $note_error = "Note cannot be empty.";
    } else {
        $ns = mysqli_prepare($conn, "INSERT INTO notes (application_id, user_id, content) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($ns, "iis", $id, $user_id, $content);
        if (mysqli_stmt_execute($ns)) {
            $note_success = "Note added.";
        } else {
            $note_error = "Failed to save note.";
        }
    }
}

// Handle edit note
$edit_note_error   = "";
$edit_note_success = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_note'])) {
    $note_id = intval($_POST['note_id']);
    $content = trim($_POST['edit_content']);
    if (empty($content)) {
        $edit_note_error = "Note cannot be empty.";
    } else {
        $en = mysqli_prepare($conn, "UPDATE notes SET content=? WHERE note_id=? AND user_id=? AND application_id=?");
        mysqli_stmt_bind_param($en, "siii", $content, $note_id, $user_id, $id);
        if (mysqli_stmt_execute($en)) {
            $edit_note_success = "Note updated.";
        } else {
            $edit_note_error = "Failed to update note.";
        }
    }
}

if (isset($_GET['delete_note'])) {
    $note_id = intval($_GET['delete_note']);
    $ds = mysqli_prepare($conn, "DELETE FROM notes WHERE note_id = ? AND user_id = ? AND application_id = ?");
    mysqli_stmt_bind_param($ds, "iii", $note_id, $user_id, $id);
    mysqli_stmt_execute($ds);
    header("Location: view_application.php?id=$id&deleted=1");
    exit();
}

$notes = mysqli_query($conn, "
    SELECT note_id, content, created_at FROM notes
    WHERE application_id = $id AND user_id = $user_id
    ORDER BY created_at DESC
");
$note_count = mysqli_num_rows($notes);

// Dropdown data for edit modal
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
  <title><?= htmlspecialchars($app['position']) ?> - Job Tracker</title>
  <link rel="stylesheet" href="../css/style.css">
  <script src="../js/main.js" defer></script>
</head>
<body>
<?php include "../includes/header.php"; ?>

<!-- Edit Application Modal -->
<div class="modal-overlay" id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3>Edit Application</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>

    <?php if ($modal_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($modal_error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="modal_action" value="edit">
      <input type="hidden" name="application_id" value="<?= $id ?>">

      <div class="form-group">
        <label>Position / Job Title <span style="color:var(--red)">*</span></label>
        <input type="text" name="position" id="field-position"
               value="<?= htmlspecialchars($_POST['position'] ?? $app['position']) ?>" required>
      </div>

      <div class="form-group">
        <label>Company <span style="color:var(--red)">*</span></label>
        <select name="company_id" id="field-company">
          <?php foreach ($companies_list as $c): ?>
          <option value="<?= $c['company_id'] ?>"
            <?= ($c['company_id'] == ($_POST['company_id'] ?? $app['company_id'])) ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label>Status <span style="color:var(--red)">*</span></label>
          <select name="status_id">
            <?php foreach ($statuses_list as $s): ?>
            <option value="<?= $s['status_id'] ?>"
              <?= ($s['status_id'] == ($_POST['status_id'] ?? $app['status_id'])) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['label']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Date Applied <span style="color:var(--red)">*</span></label>
          <input type="date" name="applied_date"
                 value="<?= htmlspecialchars($_POST['applied_date'] ?? $app['applied_date']) ?>" required>
        </div>
      </div>

      <div class="form-group">
        <label>Job Posting URL</label>
        <input type="url" name="job_url"
               value="<?= htmlspecialchars($_POST['job_url'] ?? $app['job_url']) ?>">
      </div>

      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" style="width:auto;margin-top:0;">Update</button>
      </div>
    </form>
  </div>
</div>

<!--Edit Note Modal-->
<div class="modal-overlay" id="editNoteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;align-items:center;justify-content:center;">
  <div class="modal" style="background:#fff;border-radius:10px;padding:1.5rem;width:100%;max-width:520px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,.18);">
    <div class="modal-header">
      <h3>Edit Note</h3>
      <button class="modal-close" onclick="closeEditNoteModal()">✕</button>
    </div>

    <?php if ($edit_note_error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($edit_note_error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="note_id" id="edit-note-id" value="">
      <div class="form-group">
        <textarea name="edit_content" id="edit-note-content" rows="4" required></textarea>
      </div>
      <div class="form-actions" style="justify-content:flex-end;">
        <button type="button" class="btn btn-secondary" onclick="closeEditNoteModal()">Cancel</button>
        <button type="submit" name="edit_note" class="btn btn-primary" style="width:auto;margin-top:0;">Update Note</button>
      </div>
    </form>
  </div>
</div>

<div class="container">

  <!-- Page header -->
  <div class="page-header">
    <div>
      <h1><?= htmlspecialchars($app['position']) ?></h1>
      <p class="page-subtitle">
        <?= htmlspecialchars($app['company']) ?>
        <?php if ($app['location']): ?> · <?= htmlspecialchars($app['location']) ?><?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:.6rem;">
      <button class="btn btn-secondary" onclick="openEditModal()">Edit</button>
      <a href="applications.php" class="btn btn-secondary">Back</a>
    </div>
  </div>

  <?php if ($modal_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($modal_success) ?></div>
  <?php endif; ?>
  <?php if ($edit_note_success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($edit_note_success) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-error">Note deleted.</div>
  <?php endif; ?>

  <div class="detail-grid">

    <!-- Left: Application details -->
    <div class="dash-card">
      <div class="dash-card-header">
        <h2>Application Details</h2>
        <span class="badge badge-<?= strtolower(str_replace(' ', '-', $app['status'])) ?>">
          <?= htmlspecialchars($app['status']) ?>
        </span>
      </div>
      <div class="detail-list">
        <div class="detail-row">
          <span class="detail-label">Position</span>
          <span class="detail-value"><?= htmlspecialchars($app['position']) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Company</span>
          <span class="detail-value"><?= htmlspecialchars($app['company']) ?></span>
        </div>
        <?php if ($app['industry']): ?>
        <div class="detail-row">
          <span class="detail-label">Industry</span>
          <span class="detail-value"><?= htmlspecialchars($app['industry']) ?></span>
        </div>
        <?php endif; ?>
        <?php if ($app['location']): ?>
        <div class="detail-row">
          <span class="detail-label">Location</span>
          <span class="detail-value"><?= htmlspecialchars($app['location']) ?></span>
        </div>
        <?php endif; ?>
        <div class="detail-row">
          <span class="detail-label">Date Applied</span>
          <span class="detail-value"><?= $app['applied_date'] ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Last Updated</span>
          <span class="detail-value"><?= $app['updated_at'] ?></span>
        </div>
        <?php if ($app['job_url']): ?>
        <div class="detail-row">
          <span class="detail-label">Job Posting</span>
          <span class="detail-value">
            <a href="<?= htmlspecialchars($app['job_url']) ?>" target="_blank" class="detail-link">View Posting ↗</a>
          </span>
        </div>
        <?php endif; ?>
        <?php if ($app['website']): ?>
        <div class="detail-row">
          <span class="detail-label">Company Site</span>
          <span class="detail-value">
            <a href="<?= htmlspecialchars($app['website']) ?>" target="_blank" class="detail-link">
              <?= htmlspecialchars($app['website']) ?>
            </a>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Notes -->
    <div>
      <div class="dash-card" style="margin-bottom:1rem;">
        <div class="dash-card-header">
          <h2>Add a Note</h2>
          <span style="font-size:0.78rem;color:var(--text-muted);"><?= $note_count ?> note<?= $note_count !== 1 ? 's' : '' ?></span>
        </div>

        <?php if ($note_error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($note_error) ?></div>
        <?php endif; ?>
        <?php if ($note_success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($note_success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="form-group">
            <textarea name="content" rows="4"
              placeholder="e.g. Interview scheduled for Monday, asked about salary range..."
              required></textarea>
          </div>
          <button type="submit" name="add_note" class="btn btn-primary" style="width:100%;margin-top:0;">
            Save Note
          </button>
        </form>
      </div>

      <div class="notes-list">
        <?php if ($note_count === 0): ?>
          <div class="empty-msg">No notes yet. Add your first note above.</div>
        <?php else: ?>
          <?php while ($note = mysqli_fetch_assoc($notes)): ?>
          <div class="note-card">
            <div class="note-meta">
              <span class="note-date"><?= date('M d, Y · g:i A', strtotime($note['created_at'])) ?></span>
              <div style="display:flex;gap:.75rem;">
                <a href="#"
                   class="note-delete edit-note-btn"
                   data-id="<?= $note['note_id'] ?>"
                   data-content="<?= htmlspecialchars($note['content'], ENT_QUOTES) ?>">Edit</a>
                <a href="view_application.php?id=<?= $id ?>&delete_note=<?= $note['note_id'] ?>"
                   class="note-delete"
                   onclick="return confirm('Delete this note?')">Delete</a>
              </div>
            </div>
            <p class="note-content"><?= nl2br(htmlspecialchars($note['content'])) ?></p>
          </div>
          <?php endwhile; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
// Application edit modal
const modal = document.getElementById('editModal');

<?php if ($modal_error): ?>
modal.style.display = 'flex';
<?php endif; ?>

function openEditModal() {
  modal.style.display = 'flex';
}
function closeModal() {
  modal.style.display = 'none';
}
modal.addEventListener('click', function(e) {
  if (e.target === modal) closeModal();
});

// Note edit modal
const editNoteModal = document.getElementById('editNoteModal');

<?php if ($edit_note_error): ?>
editNoteModal.style.display = 'flex';
<?php endif; ?>

function openEditNoteModal(noteId, content) {
  document.getElementById('edit-note-id').value      = noteId;
  document.getElementById('edit-note-content').value = content;
  editNoteModal.style.display = 'flex';
}
function closeEditNoteModal() {
  editNoteModal.style.display = 'none';
}
editNoteModal.addEventListener('click', function(e) {
  if (e.target === editNoteModal) closeEditNoteModal();
});

// Event delegation for note edit — avoids inline onclick quoting issues
document.addEventListener('click', function(e) {
  const btn = e.target.closest('.edit-note-btn');
  if (!btn) return;
  e.preventDefault();
  openEditNoteModal(btn.dataset.id, btn.dataset.content);
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') { closeModal(); closeEditNoteModal(); }
});
</script>

<?php include "../includes/footer.php"; ?>