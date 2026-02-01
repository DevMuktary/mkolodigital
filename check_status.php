<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

$transaction_id = $_GET['tx_id'] ?? null;
$user_id = $_SESSION['id'] ?? null;

if (!$transaction_id || !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// [SECURITY] Fetch status ONLY if the transaction belongs to the logged-in user
$stmt = $conn->prepare("SELECT status FROM vtu_transactions WHERE transaction_id = ? AND user_id = ?");
$stmt->bind_param("si", $transaction_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $transaction = $result->fetch_assoc();
    echo json_encode(['status' => $transaction['status']]);
} else {
    echo json_encode(['status' => 'not_found']);
}

$stmt->close();
exit;
?>
