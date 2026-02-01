<?php
// === PHP LOGIC STARTS HERE ===

session_start();
require_once "db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- [FIXED] FETCH THE PRICE DYNAMICALLY FROM THE DATABASE ---
$service_cost = 500.00; // A default price in case the DB query fails
$stmt_price = $conn->prepare("SELECT price FROM services WHERE service_key = 'nin_retrieval' LIMIT 1");
if ($stmt_price) {
    $stmt_price->execute();
    $result_price = $stmt_price->get_result();
    if ($row_price = $result_price->fetch_assoc()) {
        $service_cost = $row_price['price'];
    }
    $stmt_price->close();
}
// --- END OF FIX ---


// Handle form submission via AJAX
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json'); 
    $userId = $_SESSION["id"];
    $trackingId = trim($_POST['trackingId'] ?? '');

    if (empty($trackingId)) {
        echo json_encode(['status' => 'error', 'message' => 'Tracking ID cannot be empty.']);
        exit;
    }

    $conn->autocommit(FALSE);
    $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $userId);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    $currentBalance = $result->fetch_assoc()['wallet_balance'];
    $stmt_check->close();

    if ($currentBalance < $service_cost) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance to perform this operation.']);
        exit;
    }

    $newBalance = $currentBalance - $service_cost;
    $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt_wallet->bind_param("di", $newBalance, $userId);
    
    $description = "Payment for NIN Retrieval (ID: " . htmlspecialchars($trackingId) . ")";
    $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt_trans->bind_param("ids", $userId, $service_cost, $description);

    $stmt_req = $conn->prepare("INSERT INTO nin_requests (user_id, tracking_id, cost) VALUES (?, ?, ?)");
    $stmt_req->bind_param("isd", $userId, $trackingId, $service_cost);
    
    if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) { 
        $conn->commit(); 
        echo json_encode(['status' => 'success', 'message' => 'Your request has been submitted successfully!']);
    } else { 
        $conn->rollback(); 
        echo json_encode(['status' => 'error', 'message' => 'A database error occurred. Please try again.']); 
    }

    $stmt_wallet->close();
    $stmt_trans->close();
    $stmt_req->close();
    $conn->close();
    exit;
}


// Fetch request history
$requests = [];
$sql = "SELECT tracking_id, status, result_nin, result_document_path, request_date FROM nin_requests WHERE user_id = ? ORDER BY request_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NIN Retrieval via Tracking ID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #4f46e5; --primary-hover: #4338ca; --secondary-color: #64748b;
            --background-color: #f1f5f9; --card-bg: #ffffff; --text-color: #334155;
            --heading-color: #1e293b; --border-color: #e2e8f0;
            --pending-bg: #fef9c3; --pending-text: #854d0e;
            --processing-bg: #cff4fc; --processing-text: #055160;
            --failed-bg: #fee2e2; --failed-text: #991b1b;
            --completed-bg: #dcfce7; --completed-text: #166534;
            --success-bg: var(--completed-bg); --success-text: var(--completed-text);
            --error-bg: var(--failed-bg); --error-text: var(--failed-text);
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; color: var(--text-color); }
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 1rem; }
        .card { background-color: var(--card-bg); border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; border: 1px solid var(--border-color);}
        .page-header { text-align: center; margin-bottom: 2rem; margin-top: 1rem;}
        .page-header h1 { font-size: 2rem; color: var(--heading-color); margin: 0; }
        .page-header p { color: var(--secondary-color); margin-top: 0.25rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; transition: background-color 0.2s; }
        .btn-primary { background-color: var(--primary-color); color: white; width: 100%; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-primary:disabled { background-color: #9ca3af; cursor: not-allowed; }
        .btn-secondary { background-color: var(--card-bg); color: var(--secondary-color); border: 1px solid var(--border-color); font-size: 0.9rem; padding: 0.5rem 1rem;}
        .btn-secondary:hover { background-color: #f8fafc; }
        .btn-download {
            background-color: var(--completed-text); color: white; font-size: 0.8rem;
            padding: 0.4rem 0.8rem; white-space: nowrap;
        }
        .card-header, .card-body { padding: 1.5rem; }
        .card-header { border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin:0; font-size: 1.25rem; color: var(--heading-color); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.85rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
        .price-display { text-align: center; font-size: 1.25rem; font-weight: 600; margin: 1rem 0; padding: 1rem; background-color: #f8fafc; border-radius: 8px; color: var(--primary-color); }
        .terms-group { display: flex; align-items: center; gap: 0.75rem; }
        .terms-group input { width: 1.1em; height: 1.1em; }
        .result { margin-top: 1.5rem; text-align: center; padding: 1rem; border-radius: 8px; display: none; }
        .result.success { background-color: var(--success-bg); color: var(--success-text); }
        .result.error { background-color: var(--error-bg); color: var(--error-text); }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
        th { font-weight: 600; color: var(--heading-color); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
        .status-pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .status-processing { background-color: var(--processing-bg); color: var(--processing-text); }
        .status-failed { background-color: var(--failed-bg); color: var(--failed-text); }
        .status-completed { background-color: var(--completed-bg); color: var(--completed-text); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>NIN Retrieval Service</h1>
            <p>Retrieve your National Identification Number (NIN) using your tracking ID.</p>
            <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <div class="card">
            <div class="card-header"><h3>New Retrieval Request</h3></div>
            <div class="card-body">
                <form id="ninForm" method="POST">
                    <div class="form-group">
                        <label for="trackingId">NIN Tracking ID</label>
                        <input type="text" id="trackingId" name="trackingId" placeholder="Enter the tracking ID from your slip" required>
                    </div>
                    <div class="price-display">
                        <span>Service Cost:</span>
                        <span>₦<?php echo number_format($service_cost, 2); ?></span>
                    </div>
                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms" style="margin-bottom: 0;">I confirm the tracking ID is correct and agree to the ₦<?php echo number_format($service_cost, 0); ?> charge.</label>
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;" disabled>
                        <i class="fas fa-search"></i> Submit Request
                    </button>
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Your Retrieval History</h3></div>
            <div class="card-body table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th>Tracking ID</th><th>Status</th><th>Retrieved NIN</th><th>Document</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem;">You have not made any retrieval requests yet.</td></tr>
                        <?php else: foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo date("d M Y, g:ia", strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['tracking_id']); ?></td>
                                <td><span class="status-badge <?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($request['result_nin'] ?? '---'); ?></strong></td>
                                <td>
                                    <?php if ($request['status'] === 'Completed' && !empty($request['result_document_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['result_document_path']); ?>" class="btn btn-download" target="_blank" download>
                                            <i class="fas fa-download"></i> Download PDF
                                        </a>
                                    <?php else: ?>
                                        ---
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        // The Javascript part of this file remains the same
        document.addEventListener('DOMContentLoaded', () => {
            const ninForm = document.getElementById('ninForm');
            const trackingIdInput = document.getElementById('trackingId');
            const termsCheckbox = document.getElementById('terms');
            const submitButton = document.querySelector('#ninForm button[type="submit"]');
            const resultBox = document.getElementById('resultBox');

            function checkFormValidity() {
                const isIdEntered = trackingIdInput.value.trim() !== '';
                const areTermsChecked = termsCheckbox.checked;
                submitButton.disabled = !(isIdEntered && areTermsChecked);
            }

            trackingIdInput.addEventListener('input', checkFormValidity);
            termsCheckbox.addEventListener('change', checkFormValidity);
            
            ninForm.addEventListener('submit', function(event) {
                event.preventDefault();
                submitButton.disabled = true;
                resultBox.style.display = 'block';
                resultBox.className = 'result';
                resultBox.textContent = 'Submitting your request...';
                
                const formData = new FormData(ninForm);
                fetch('nin-tracking.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    resultBox.textContent = data.message;
                    resultBox.className = 'result ' + data.status;
                    if (data.status === 'success') {
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                         submitButton.disabled = false;
                    }
                })
                .catch(error => { 
                    resultBox.textContent = 'An unexpected error occurred. Please check your connection.';
                    resultBox.className = 'result error';
                    submitButton.disabled = false;
                });
            });
        });
    </script>
</body>
</html>
