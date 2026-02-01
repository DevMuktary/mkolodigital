<?php
session_start();
header('Content-Type: application/json');

// Security check: Only logged-in admins can make changes.
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit;
}
require_once "../db.php";

// [REMOVED] The check for is_beta_tester is removed. All logged-in admins can make changes.

// Handle the incoming request
$action = $_POST['action'] ?? '';

if ($action === 'update_plan') {
    $plan_id = filter_var($_POST['plan_id'] ?? 0, FILTER_VALIDATE_INT);
    $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : null;

    if (!$plan_id || !$status) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data provided.']);
        exit;
    }

    if (isset($_POST['price'])) {
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid price format.']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE vtu_products SET selling_price = ?, status = ? WHERE id = ?");
        $stmt->bind_param("dsi", $price, $status, $plan_id);
    } else {
        $stmt = $conn->prepare("UPDATE vtu_products SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $plan_id);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
    }
    $stmt->close();
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action.']);
?>
