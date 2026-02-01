<?php
// Set the HTTP response code to 200 OK immediately
http_response_code(200);

require_once "db.php";

// 1. Get the raw JSON payload from the request
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// If the payload is not valid JSON, stop.
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Invalid JSON received in webhook.");
    exit();
}

// --- CORRECTED: Match these variable names to the actual payload from PaymentPoint ---
$transaction_ref = $data['transaction_id'] ?? null;
$account_number = $data['receiver']['account_number'] ?? null;
$amount_paid = $data['amount_paid'] ?? 0;
// ---

if (!$transaction_ref || !$account_number || $amount_paid <= 0) {
    // This is the error you were seeing. It will be fixed now.
    error_log("Incomplete webhook data received: " . $payload);
    exit();
}

// 2. SECURITY CHECK: Prevent Replay Attacks
$stmt = $conn->prepare("SELECT id FROM payment_webhook_log WHERE transaction_reference = ?");
$stmt->bind_param("s", $transaction_ref);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    // This transaction has already been processed. Stop.
    $stmt->close();
    exit();
}
$stmt->close();

// 3. FIND THE USER who owns the virtual account
$user_id = null;
$stmt = $conn->prepare("SELECT id FROM users WHERE palmpay_account_number = ? OR opay_account_number = ?");
$stmt->bind_param("ss", $account_number, $account_number);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    $user_id = $user['id'];
}
$stmt->close();

if (!$user_id) {
    // No user owns this account number. Log it and stop.
    $log_stmt = $conn->prepare("INSERT INTO payment_webhook_log (transaction_reference, account_number, amount, full_payload, status) VALUES (?, ?, ?, ?, 'unknown_account')");
    $log_stmt->bind_param("ssds", $transaction_ref, $account_number, $amount_paid, $payload);
    $log_stmt->execute();
    $log_stmt->close();
    exit();
}

// 4. PROCESS THE TRANSACTION
$conn->autocommit(FALSE); // Start transaction

// Query 1: Update the user's wallet balance
$update_wallet_sql = "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?";
$stmt1 = $conn->prepare($update_wallet_sql);
$stmt1->bind_param("di", $amount_paid, $user_id);

// Query 2: Log this for the user's transaction history
$description = "Wallet funding via bank transfer (" . substr($account_number, -4) . ")";
$log_transaction_sql = "INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'credit', ?, ?)";
$stmt2 = $conn->prepare($log_transaction_sql);
$stmt2->bind_param("ids", $user_id, $amount_paid, $description);

// Query 3: Log this for the admin's webhook history
$log_webhook_sql = "INSERT INTO payment_webhook_log (transaction_reference, account_number, amount, full_payload, status) VALUES (?, ?, ?, ?, 'processed')";
$stmt3 = $conn->prepare($log_webhook_sql);
$stmt3->bind_param("ssds", $transaction_ref, $account_number, $amount_paid, $payload);

// Execute all queries
if ($stmt1->execute() && $stmt2->execute() && $stmt3->execute()) {
    $conn->commit();
} else {
    $conn->rollback();
    error_log("Webhook transaction failed for user ID {$user_id}. Payload: " . $payload);
}

$stmt1->close();
$stmt2->close();
$stmt3->close();
$conn->close();

exit(); // End the script
?>
