<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id = (int)$_SESSION['user_id'];
$subject = trim($_POST['subject'] ?? '');
$hours   = floatval($_POST['hours'] ?? 0);
$date    = trim($_POST['session_date'] ?? date('Y-m-d'));
$notes   = trim($_POST['notes'] ?? '');

if ($subject && $hours > 0) {
    $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, subject, hours_studied, session_date, notes) VALUES (?,?,?,?,?)");
    $stmt->bind_param("isdss", $user_id, $subject, $hours, $date, $notes);
    $stmt->execute();
}
header("Location: index.php#home");
exit;
?>
