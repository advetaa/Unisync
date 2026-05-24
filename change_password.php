<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id  = (int)$_SESSION['user_id'];
$oldPass  = $_POST['old_password']     ?? '';
$newPass  = $_POST['new_password']     ?? '';
$confPass = $_POST['confirm_password'] ?? '';

$user = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch_assoc();

if ($newPass !== $confPass) {
    header("Location: index.php#settings&msg=Passwords+do+not+match"); exit;
}
if (!password_verify($oldPass, $user['password'])) {
    header("Location: index.php#settings&msg=Incorrect+current+password"); exit;
}

$hash = password_hash($newPass, PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
$stmt->bind_param("si", $hash, $user_id);
$stmt->execute();

header("Location: index.php#settings");
exit;
?>
