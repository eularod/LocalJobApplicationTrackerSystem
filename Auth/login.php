<?php
session_start();
require_once __DIR__ . "/../config/db.php";

$conn = get_conn();

if (isset($_SESSION['user_id'])) {
    header("Location: ../pages/dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $error = "Please enter your email and password.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT user_id, username, password FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            header("Location: ../pages/dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Job Tracker - Sign In</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:        #F4F6F9;
  --surface:   #FFFFFF;
  --panel-bg:  #EEF1F6;
  --blue:      #0B2E52;
  --blue-soft: #EFF4FF;
  --blue-mid:  #BFCFFF;
  --text:      #0F172A;
  --faint:     #94A3B8;
  --border-focus: #93B4FD;
  --error-bg:  #FFF1F1;
  --error-bdr: #FCA5A5;
  --error-txt: #B91C1C;
  --font:      'Sora', system-ui, sans-serif;
  --mono:      'DM Mono', monospace;
}

html, body {
  height: 100%;
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  -webkit-font-smoothing: antialiased;
}

/* Page layout */
.page {
  display: grid;
  grid-template-columns: 1fr 420px;
  min-height: 100vh;
}

/* Left panel */
.left {
  padding: 48px 56px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  position: relative;
  overflow: hidden;
}

/* Subtle grid background */
.left::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(var(--border) 1px, transparent 1px),
    linear-gradient(90deg, var(--border) 1px, transparent 1px);
  background-size: 48px 48px;
  opacity: 0.55;
  pointer-events: none;
}

/* Soft glow blobs */
.left::after {
  content: '';
  position: absolute;
  top: -80px;
  left: -80px;
  width: 420px;
  height: 420px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(37,99,235,0.08) 0%, transparent 70%);
  pointer-events: none;
}

.left > * { position: relative; z-index: 1; }

/* Brand */
.brand {
  display: flex;
  align-items: center;
  gap: 10px;
}
.brand-icon {
  width: 34px; height: 34px;
  border-radius: 9px;
  background: var(--blue);
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  box-shadow: 0 2px 8px rgba(37,99,235,0.30);
}
.brand-name {
  font-size: 0.88rem;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--text);
}

/* Headline */
.left-body { margin-top: 12px; }

.left-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--mono);
  font-size: 0.68rem;
  font-weight: 400;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--blue);
  background: var(--blue-soft);
  border: 1px solid var(--blue-mid);
  border-radius: 99px;
  padding: 4px 10px;
  margin-bottom: 20px;
}
.left-label::before {
  content: '';
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--blue);
}

.left-heading {
  font-size: clamp(2rem, 2.8vw, 2.75rem);
  font-weight: 600;
  line-height: 1.18;
  letter-spacing: -0.04em;
  color: var(--text);
  margin-bottom: 16px;
}
.left-heading em {
  font-style: normal;
  color: var(--blue);
}
.left-desc {
  font-size: 0.85rem;
  color: var(--muted);
  line-height: 1.75;
  font-weight: 300;
  max-width: 420px;
  margin-bottom: 44px;
}

/* Features */
.features {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  max-width: 480px;
}
.feat {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  transition: box-shadow .2s, border-color .2s;
}
.feat:hover {
  border-color: var(--blue-mid);
  box-shadow: 0 4px 16px rgba(37,99,235,0.08);
}
.feat-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  background: var(--blue-soft);
  display: flex; align-items: center; justify-content: center;
  color: var(--blue);
  margin-bottom: 10px;
}
.feat-title {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text);
  margin-bottom: 3px;
}
.feat-desc {
  font-size: 0.72rem;
  color: var(--faint);
  font-weight: 300;
  line-height: 1.5;
}

/* Footer stats */
.left-footer {
  display: flex;
  gap: 32px;
  padding-top: 28px;
  border-top: 1px solid var(--border);
  margin-top: 8px;
}
.stat {}
.stat-num {
  font-size: 1.15rem;
  font-weight: 600;
  letter-spacing: -0.03em;
  color: var(--text);
}
.stat-lbl {
  font-size: 0.68rem;
  color: var(--faint);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-top: 3px;
  font-family: var(--mono);
}

/* Right panel */
.right {
  background: var(--surface);
  border-left: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 52px 44px;
}

/* Form header */
.form-eyebrow {
  font-family: var(--mono);
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--faint);
  margin-bottom: 8px;
}
.form-title {
  font-size: 1.5rem;
  font-weight: 600;
  letter-spacing: -0.03em;
  color: var(--text);
  margin-bottom: 4px;
}
.form-sub {
  font-size: 0.82rem;
  color: var(--muted);
  font-weight: 300;
  margin-bottom: 32px;
  line-height: 1.6;
}

/* Divider line */
.form-divider {
  height: 1px;
  background: var(--border);
  margin-bottom: 28px;
}

/* Error */
.form-error {
  background: var(--error-bg);
  border: 1px solid var(--error-bdr);
  border-radius: 9px;
  padding: 10px 13px;
  font-size: 0.8rem;
  color: var(--error-txt);
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 400;
}

/* Fields */
.field { margin-bottom: 16px; }
.field label {
  display: block;
  font-size: 0.72rem;
  font-weight: 500;
  color: var(--muted);
  letter-spacing: 0.02em;
  margin-bottom: 7px;
}
.input-wrap {
  position: relative;
}
.input-wrap svg {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--faint);
  pointer-events: none;
  transition: color .15s;
}
.field input {
  width: 100%;
  background: var(--bg);
  border: 1.5px solid var(--border);
  border-radius: 9px;
  padding: 10px 12px 10px 38px;
  font-family: var(--font);
  font-size: 0.875rem;
  color: var(--text);
  outline: none;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.field input::placeholder { color: var(--faint); }
.field input:focus {
  border-color: var(--blue);
  background: var(--surface);
  box-shadow: 0 0 0 3.5px rgba(37,99,235,0.10);
}
.field input:focus + svg,
.input-wrap:focus-within svg {
  color: var(--blue);
}
/* Icon appears after input in DOM but positioned absolutely */
.field input:-webkit-autofill,
.field input:-webkit-autofill:focus {
  -webkit-box-shadow: 0 0 0 1000px #f8f9fb inset;
  -webkit-text-fill-color: var(--text);
  border-color: var(--blue);
}

/* Button */
.btn-login {
  width: 100%;
  padding: 11.5px;
  margin-top: 8px;
  background: var(--blue);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-family: var(--font);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  letter-spacing: -0.01em;
  transition: background .15s, box-shadow .15s, transform .1s;
  box-shadow: 0 2px 8px rgba(37,99,235,0.25);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
}
.btn-login:hover {
  background: #164F8E;
  box-shadow: 0 4px 14px rgba(37,99,235,0.35);
}
.btn-login:active {
  transform: translateY(1px);
  box-shadow: 0 1px 4px rgba(37,99,235,0.20);
}

/* Register */
.reg {
  text-align: center;
  font-size: 0.8rem;
  color: var(--faint);
  margin-top: 22px;
}
.reg a {
  color: var(--blue);
  text-decoration: none;
  font-weight: 500;
}
.reg a:hover { text-decoration: underline; }

/* Subtle trust badge */
.trust {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  margin-top: 28px;
  padding-top: 20px;
  border-top: 1px solid var(--border);
  font-size: 0.7rem;
  color: var(--faint);
  font-family: var(--mono);
}

/* Responsive */
@media (max-width: 820px) {
  .page { grid-template-columns: 1fr; }
  .left {
    padding: 36px 28px 32px;
    border-bottom: 1px solid var(--border);
    gap: 28px;
  }
  .left::before { background-size: 36px 36px; }
  .features { grid-template-columns: 1fr 1fr; }
  .right {
    padding: 36px 28px 48px;
    border-left: none;
  }
}
@media (max-width: 480px) {
  .features { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="page">

  <!-- Left -->
  <div class="left">

    <div class="brand">
      <div class="brand-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
      </div>
      <span class="brand-name">Job Tracker</span>
    </div>

    <div class="left-body">
      <h1 class="left-heading">Stay on top of<br><em>every</em> application.</h1>
      <p class="left-desc">Track your job applications, monitor your pipeline, and measure your progress — all in one clean, focused dashboard.</p>

      <div class="features">

        <div class="feat">
          <div class="feat-title">Application Log</div>
          <div class="feat-desc">Log every role with company, position, status, and date.</div>
        </div>

        <div class="feat">
          <div class="feat-title">KPI Dashboard</div>
          <div class="feat-desc">Interview rate, response rate, streaks, and monthly trends.</div>
        </div>

        <div class="feat">
          <div class="feat-title">Pipeline View</div>
          <div class="feat-desc">Visual breakdown of where every application stands.</div>
        </div>

        <div class="feat">
          <div class="feat-title">PDF Reports</div>
          <div class="feat-desc">Export a clean PDF summary of your full job search.</div>
        </div>

      </div>
    </div>

    <div class="left-footer">
      <div class="stat">
        <div class="stat-num">100%</div>
        <div class="stat-lbl">Free</div>
      </div>
      <div class="stat">
        <div class="stat-num">Live</div>
        <div class="stat-lbl">KPI tracking</div>
      </div>
      <div class="stat">
        <div class="stat-num">PDF</div>
        <div class="stat-lbl">Export ready</div>
      </div>
    </div>

  </div>

  <!-- Right -->
  <div class="right">

    <p class="form-eyebrow">Welcome back</p>
    <div class="form-title">Sign in</div>
    <div class="form-sub">Enter your credentials to access your job tracker dashboard.</div>

    <div class="form-divider"></div>

    <?php if ($error): ?>
    <div class="form-error">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="field">
        <label for="email">Email address</label>
        <div class="input-wrap">
          <input type="email" id="email" name="email" required placeholder="you@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--faint);"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" required placeholder="••••••••">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:var(--faint);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
      </div>
      <button type="submit" class="btn-login">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Sign In
      </button>
    </form>

    <p class="reg">No account yet? <a href="register.php">Create one for free</a></p>

    <div class="trust">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Passwords are hashed &amp; stored securely
    </div>

  </div>

</div>
</body>
</html>