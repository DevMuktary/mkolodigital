<?php
// === PHP LOGIC STARTS HERE ===

session_start();
require_once "db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- [FIXED] FETCH PRICES DYNAMICALLY FROM THE DATABASE ---
$prices = []; // Start with an empty array
$sql_prices = "SELECT service_name, price FROM services WHERE category = 'BVN Retrieval' ORDER BY price ASC";
if ($result_prices = $conn->query($sql_prices)) {
    while ($row = $result_prices->fetch_assoc()) {
        // This page uses a 'name' => price structure, so we build the array that way
        $prices[$row['service_name']] = $row['price'];
    }
}
// If the query fails or returns no results, the dropdown will be empty.
// Ensure you have run the SQL queries from Step 1.
// --- END OF FIX ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $userId = $_SESSION["id"];
    // UPDATED: Now saving Full Name instead of NIN
    $identifier_value = "Identifier: " . $_POST['identifier'] . "\nFull Name: " . $_POST['fullName'];
    $request_id_to_edit = $_POST['request_id_to_edit'] ?? null;

    if (!empty($request_id_to_edit)) {
        // --- UPDATE A REJECTED REQUEST (NO CHARGE) ---
        $sql = "UPDATE bvn_retrievals SET identifier_value = ?, status = 'Pending', rejection_reason = NULL WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $identifier_value, $request_id_to_edit, $userId);
        if ($stmt->execute()) { echo json_encode(['status' => 'success', 'message' => 'Your request has been re-submitted!']); } 
        else { echo json_encode(['status' => 'error', 'message' => 'Failed to re-submit request.']); }
        $stmt->close();
    } else {
        // --- CREATE A NEW REQUEST (WITH CHARGE AND TRANSACTION LOG) ---
        $retrievalType = $_POST['retrievalType'] ?? '';
        // The cost is now calculated using the dynamically fetched $prices array
        $cost = $prices[$retrievalType] ?? 0;
        if ($cost == 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid Retrieval Type selected.']); exit; }

        $conn->autocommit(FALSE);
        $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt_check->bind_param("i", $userId);
        $stmt_check->execute();
        $user = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if ($user['wallet_balance'] < $cost) { echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance.']); $conn->rollback(); exit; }

        $newBalance = $user['wallet_balance'] - $cost;
        $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt_wallet->bind_param("di", $newBalance, $userId);
        
        $description = "Payment for BVN Retrieval";
        $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
        $stmt_trans->bind_param("ids", $userId, $cost, $description);

        $sql_req = "INSERT INTO bvn_retrievals (user_id, retrieval_method, identifier_value, retrieval_type, cost) VALUES (?, ?, ?, ?, ?)";
        $stmt_req = $conn->prepare($sql_req);
        $stmt_req->bind_param("isssd", $userId, $_POST['verifyType'], $identifier_value, $retrievalType, $cost);

        if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) {
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Request submitted successfully!']);
        } else {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Database error. Could not save request.']);
        }
    }
    $conn->close(); exit;
}

// --- LOGIC TO FETCH A REJECTED REQUEST FOR EDITING ---
$edit_data = null;
$edit_identifier = '';
$edit_fullName = '';
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $userId = $_SESSION['id'];
    $sql = "SELECT * FROM bvn_retrievals WHERE id = ? AND user_id = ? AND status = 'Rejected'";
    if($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $edit_id, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $edit_data = $result->fetch_assoc();
            $lines = explode("\n", $edit_data['identifier_value']);
            if (isset($lines[0])) { $edit_identifier = trim(str_replace('Identifier:', '', $lines[0])); }
            if (isset($lines[1])) { $edit_fullName = trim(str_replace('Full Name:', '', $lines[1])); }
        }
        $stmt->close();
    }
}


// Fetch user's request history for the table
$requests = [];
$sql = "SELECT id, identifier_value, status, bvn_result, rejection_reason, result_file_path, request_date FROM bvn_retrievals WHERE user_id = ? ORDER BY request_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $requests[] = $row; }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BVN Retrieval</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd; --secondary-color: #64748b;
            --background-color: #f1f5f9; --card-bg: #ffffff; --text-color: #334155;
            --heading-color: #1e293b; --border-color: #e2e8f0;
            --pending-bg: #fef9c3; --pending-text: #854d0e;
            --rejected-bg: #fee2e2; --rejected-text: #991b1b;
            --success-bg: #dcfce7; --success-text: #166534;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 1rem; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .page-header { text-align: center; margin-bottom: 2rem; margin-top: 1rem;}
        .page-header-title h1 { font-size: 2rem; color: var(--heading-color); margin: 0; }
        .page-header-title p { color: var(--secondary-color); margin-top: 0.25rem; }
        .back-link { display: inline-block; margin-top: 1rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; text-decoration: none; }
        .btn-primary { background-color: var(--primary-color); color: white; width: 100%; }
        .btn-primary:disabled { background-color: #9ca3af; }
        .btn-secondary { background-color: var(--card-bg); color: var(--secondary-color); border: 1px solid var(--border-color); font-size: 0.9rem; padding: 0.5rem 1rem;}
        .card-header, .card-body { padding: 1.5rem; }
        .card-header { border-bottom: 1px solid var(--border-color); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .price-display { text-align: center; font-size: 1.5rem; font-weight: 700; margin: 2rem 0; padding: 1rem; background-color: #f8fafc; border-radius: 8px; color: var(--primary-color); }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .status-badge.pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .status-badge.rejected { background-color: var(--rejected-bg); color: var(--rejected-text); }
        .status-badge.completed { background-color: var(--success-bg); color: var(--success-text); }
        .rejection-reason { font-size: 0.9rem; color: var(--rejected-text); margin-top: 0.5rem; padding: 0.75rem; background-color: #fff1f2; border-left: 4px solid var(--rejected-text); border-radius: 6px;}
        .result { margin-top: 1rem; text-align: center; padding: 1rem; border-radius: 8px; }
        .result.success { background-color: var(--success-bg); color: var(--success-text); }
        .result.error { background-color: var(--rejected-bg); color: var(--rejected-text); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-header-title">
                <h1>BVN Retrieval</h1>
                <p>Submit and track your BVN retrieval requests.</p>
                <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3><?php echo $edit_data ? 'Edit & Resubmit Your Request' : 'New Retrieval Request'; ?></h3></div>
            <div class="card-body">
                <form id="bvnForm" method="POST">
                    <input type="hidden" name="request_id_to_edit" value="<?php echo htmlspecialchars($edit_data['id'] ?? ''); ?>">
                    <div class="form-group"><label>1. Retrieve With</label><select id="verifyType" name="verifyType" required><option value="">-- Select Method --</option><option value="phone" <?php if(isset($edit_data) && $edit_data['retrieval_method'] == 'phone') echo 'selected'; ?>>Phone Number</option></div>
                    <div class="form-group"><label for="identifier" id="identifierLabel">2. Enter Identifier</label><input type="text" id="identifier" name="identifier" placeholder="Enter phone or account number" required value="<?php echo htmlspecialchars($edit_identifier); ?>"></div>
                    <div class="form-group"><label for="fullName">3. Enter Your Full Name</label><input type="text" id="fullName" name="fullName" placeholder="Enter full name on bank account" required value="<?php echo htmlspecialchars($edit_fullName); ?>"></div>
                    <div class="form-group"><label for="retrievalType">4. Select Package</label><select id="retrievalType" name="retrievalType" required><option value="">-- Select Package --</option><?php foreach ($prices as $name => $price): ?><option value="<?php echo $name; ?>" <?php if(isset($edit_data) && $edit_data['retrieval_type'] == $name) echo 'selected'; ?>><?php echo $name; ?></option><?php endforeach; ?></select></div>
                    <div class="price-display" style="<?php if($edit_data) echo 'display:none;'; ?>"><span>Request Cost:</span><span id="displayPrice">₦0.00</span></div>
                    <button type="submit" class="btn btn-primary" disabled><i class="fas fa-paper-plane"></i> <?php echo $edit_data ? 'Resubmit Request' : 'Submit Now'; ?></button>
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Your Retrieval History</h3></div>
            <div class="card-body table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th>Details</th><th>Status & Reason</th><th>Result</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 2rem;">No retrieval requests yet.</td></tr>
                        <?php else: foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo date("d M Y", strtotime($request['request_date'])); ?></td>
                                <td style="white-space: pre-wrap;"><?php echo htmlspecialchars($request['identifier_value']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                                    <?php if ($request['status'] === 'Rejected'): ?>
                                        <div class="rejection-reason"><strong>Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?></div>
                                       
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($request['result_file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['result_file_path']); ?>" class="btn btn-secondary" download><i class="fas fa-download"></i> Download File</a>
                                    <?php elseif (!empty($request['bvn_result'])): ?>
                                        <strong><?php echo htmlspecialchars($request['bvn_result']); ?></strong>
                                    <?php else: ?>
                                        <span>...</span>
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
        document.addEventListener('DOMContentLoaded', () => {
            const verifyTypeSelect = document.getElementById('verifyType');
            const identifierLabel = document.getElementById('identifierLabel');
            const identifierInput = document.getElementById('identifier');
            const retrievalTypeSelect = document.getElementById('retrievalType');
            const displayPriceEl = document.getElementById('displayPrice');
            const submitButton = document.querySelector('#bvnForm button[type="submit"]');
            const bvnForm = document.getElementById('bvnForm');
            const resultBox = document.getElementById('resultBox');
            const prices = <?php echo json_encode($prices); ?>;
            const isEditMode = <?php echo json_encode($edit_data !== null); ?>;

            function updateLabel() {
                if (verifyTypeSelect.value === 'phone') {
                    identifierLabel.textContent = '2. Enter Phone Number';
                    identifierInput.placeholder = 'Enter your phone number';
                } else if (verifyTypeSelect.value === 'account') {
                    identifierLabel.textContent = '2. Enter Bank Account Number';
                    identifierInput.placeholder = 'Enter your NUBAN account number';
                }
            }

            function updatePrice() {
                const selectedType = retrievalTypeSelect.value;
                const cost = prices[selectedType] || 0;
                displayPriceEl.textContent = `₦${cost.toLocaleString()}`;
                if (!isEditMode) {
                    submitButton.disabled = (cost === 0);
                }
            }

            verifyTypeSelect.addEventListener('change', updateLabel);
            retrievalTypeSelect.addEventListener('change', updatePrice);

            bvnForm.addEventListener('submit', function(event) {
                event.preventDefault();
                resultBox.className = 'result'; resultBox.textContent = 'Submitting...';
                const formData = new FormData(bvnForm);
                fetch('bvn-retrieval.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    resultBox.textContent = data.message;
                    resultBox.className = 'result ' + data.status;
                    if (data.status === 'success') {
                        setTimeout(() => { window.location.href = 'bvn-retrieval.php'; }, 2000);
                    }
                })
                .catch(error => { resultBox.textContent = 'An unexpected error occurred.'; });
            });
            
            if (isEditMode) {
                updateLabel();
                updatePrice();
                submitButton.disabled = false;
            }
        });
    </script>
</body>
</html>
