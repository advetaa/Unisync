<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id  = (int)$_SESSION['user_id'];
$title    = trim($_POST['title']    ?? '');
$amount   = floatval($_POST['amount'] ?? 0);
$category = trim($_POST['category'] ?? 'Other');
$date     = trim($_POST['date']     ?? '');

if ($title && $amount > 0 && $date) {
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, title, amount, category, expense_date) VALUES (?,?,?,?,?)");
    $stmt->bind_param("isdss", $user_id, $title, $amount, $category, $date);
    $stmt->execute();
}

header("Location: index.php");
exit;
?>
