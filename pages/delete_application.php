<?php
session_start();
require_once "../includes/auth_check.php";
require_once "../config/db.php";

$conn = get_conn();
$user_id = $_SESSION['user_id'];
$id      = intval($_GET['id'] ?? 0);

if ($id > 0) {
    // The AND user_id check ensures users can only delete their own records
    $stmt = mysqli_prepare($conn, "DELETE FROM applications WHERE application_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $user_id);
    mysqli_stmt_execute($stmt);
}

header("Location: applications.php?deleted=1");
exit();
?>