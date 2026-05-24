<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id     = (int)$_SESSION['user_id'];
    $title       = trim($_POST['title']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $due_date    = trim($_POST['due_date']    ?? '');
    $priority    = trim($_POST['priority']    ?? 'medium');

    if ($title) {
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, status) VALUES (?,?,?,?,?,'pending')");
        $stmt->bind_param("issss", $user_id, $title, $description, $due_date, $priority);
        $stmt->execute();
    }

    header("Location: index.php");
    exit;
}
?>
