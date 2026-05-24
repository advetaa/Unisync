<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id  = (int)$_SESSION['user_id'];
$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email']     ?? '');
$course   = trim($_POST['course']    ?? '');
$semester = intval($_POST['semester'] ?? 1);

if ($fullName && $email) {
    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, course=?, semester=? WHERE user_id=?");
    $stmt->bind_param("sssii", $fullName, $email, $course, $semester, $user_id);
    if ($stmt->execute()) {
        $_SESSION['user_name'] = $fullName;
    }
}
header("Location: index.php#account");
exit;
?>
