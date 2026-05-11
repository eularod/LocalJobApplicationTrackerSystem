<?php
session_start();
require_once "../config/db.php";

$conn = get_conn();
$error   = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $email    = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm  = trim($_POST["confirm_password"]);

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check if username or email already exists
        $check = mysqli_prepare($conn, "SELECT user_id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($check, "ss", $username, $email);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) > 0) {
            $error = "Username or email is already taken.";
        } else {
            // Hash the password 
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hashed);

            if (mysqli_stmt_execute($stmt)) {
                $success = "Account created! You can now log in.";
            } else {
                $error = "Something went wrong. Please try again.";
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
    <title>Register - Job Tracker</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .btn-register {
            background-color: #0B2E52;
            color: #fff;
            border: none;
            width: 100%;
            padding: 0.65rem;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .btn-register:hover {
            background-color: #164F8E;
        }
        .auth-link a {
            color: #0B2E52;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        .auth-link a:hover {
            color: #164F8E;
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <h2>Create Account</h2>
        <p class="auth-sub">Job Application Tracker</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username <span style="color:red">*</span></label>
                <input type="text" id="username" name="username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email <span style="color:red">*</span></label>
                <input type="email" id="email" name="email" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password <span style="color:red">*</span></label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span style="color:red">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn-register">Register</button>
        </form>
        <p class="auth-link">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</div>
</body>
</html>