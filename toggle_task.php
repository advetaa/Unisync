<?php
include 'db.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    if (!empty($_GET['ajax'])) { echo json_encode(['ok'=>false]); exit; }
    header("Location: login.php"); exit;
}

$id     = intval($_GET['id']     ?? 0);
$status = $_GET['status']        ?? 'pending';
$userId = (int)$_SESSION['user_id'];

$newStatus = ($status === 'completed') ? 'pending' : 'completed';

$stmt = $conn->prepare("UPDATE tasks SET status=? WHERE task_id=? AND user_id=?");
$stmt->bind_param("sii", $newStatus, $id, $userId);
$ok = $stmt->execute();

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'new_status' => $newStatus]);
    exit;
}

header("Location: index.php#planner");
exit;
?>
