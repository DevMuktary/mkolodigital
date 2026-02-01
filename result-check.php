<?php
// === PHP LOGIC (Unchanged) ===

session_start();
require_once "db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- [FINAL PHP LOGIC] ---
$services = []; 
$sql_prices = "SELECT service_key, price FROM services";

if ($result_prices = $conn->query($sql_prices)) {
    while ($row = $result_prices->fetch_assoc()) {
        $parts = explode('_', $row['service_key'], 2);
        if (count($parts) === 2) {
            $examType = strtoupper($parts[0]);
            $serviceKey = $parts[1];
            if ($serviceKey === 'result') {
                $services[$examType]['Result'] = $row['price'];
            } else if ($serviceKey === 'pin') {
                $services[$examType]['PIN'] = $row['price'];
            }
        }
    }
}
// --- [END FINAL PHP LOGIC] ---


// === HANDLE AJAX FORM SUBMISSION (Unchanged) ===
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $userId = $_SESSION["id"];
    // Read from the new hidden inputs
    $examType = $_POST['examType'] ?? ''; 
    $serviceType = $_POST['serviceType'] ?? '';
    
    $cost = $services[$examType][$serviceType] ?? 0;

    if ($cost == 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid selection. Please try again.']); exit;
    }

    $conn->autocommit(FALSE);

    $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $userId);
    $stmt_check->execute();
    $user = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($user['wallet_balance'] < $cost) {
        echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance.']); $conn->rollback(); exit;
    }

    $newBalance = $user['wallet_balance'] - $cost;
    $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt_wallet->bind_param("di", $newBalance, $userId);

    $description = "Payment for " . $examType . " " . $serviceType;
    $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt_trans->bind_param("ids", $userId, $cost, $description);

    $details = "Exam Number: " . ($_POST['examNumber'] ?? 'N/A') . "\n" . "Exam Year: " . ($_POST['examYear'] ?? 'N/A');
    $stmt_req = $conn->prepare("INSERT INTO result_requests (user_id, exam_type, service_type, details, cost) VALUES (?, ?, ?, ?, ?)");
    $stmt_req->bind_param("isssd", $userId, $examType, $serviceType, $details, $cost);

    if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Your request was submitted successfully!']);
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error. Could not save your request.']);
    }

    $stmt_wallet->close(); 
    $stmt_trans->close();
    $stmt_req->close();
    $conn->close(); 
    exit;
}

// === FETCH DATA FOR PAGE DISPLAY (Unchanged) ===
$requests = [];
$sql = "SELECT exam_type, service_type, details, status, result_info, result_file_path FROM result_requests WHERE user_id = ? ORDER BY request_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
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
    <title>Exam Result Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd; --primary-hover: #0b5ed7; --secondary-color: #64748b;
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
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; }
        .btn-primary { background-color: var(--primary-color); color: white; width: 100%; }
        .btn-primary:disabled { background-color: #9ca3af; }
        .btn-secondary { background-color: var(--card-bg); color: var(--secondary-color); border: 1px solid var(--border-color); font-size: 0.9rem; padding: 0.5rem 1rem;}
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0; font-size: 1.25rem; color: var(--heading-color); }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .price-display { text-align: center; font-size: 1.5rem; font-weight: 700; margin: 2rem 0; padding: 1rem; background-color: #f8fafc; border-radius: 8px; color: var(--primary-color); }
        .result { margin-top: 1rem; text-align: center; padding: 1rem; border-radius: 8px; }
        .result.success { background-color: var(--success-bg); color: var(--success-text); }
        .result.error { background-color: var(--rejected-bg); color: var(--rejected-text); }
        
        /* New Button Stepper Styles */
        .step { margin-bottom: 1.5rem; }
        .step-label { display: block; font-weight: 500; margin-bottom: 0.75rem; color: var(--heading-color); }
        .btn-group { display: flex; flex-wrap: wrap; gap: 0.75rem; }
        .btn-option {
            flex: 1 1 120px; /* Grow, shrink, base width */
            background-color: var(--card-bg);
            color: var(--primary-color);
            border: 2px solid var(--border-color);
            padding: 1rem;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.2s ease;
        }
        .btn-option:hover {
            border-color: var(--primary-hover);
            background-color: #f4f8ff;
        }
        .btn-option.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .step-section {
            /* JS will hide/show these */
        }
        #step-3-details, #step-2-service {
            display: none; /* Hide steps 2 and 3 initially */
        }
        .selection-summary {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: #f8fafc;
            border-radius: 8px;
            font-weight: 500;
        }
        .selection-summary div {
            flex: 1;
        }
        .selection-summary .label {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }
        .selection-summary .value {
            color: var(--heading-color);
            font-size: 1.1rem;
        }
        .btn-reset {
            margin-top: 1rem;
            width: auto;
            font-size: 0.9rem;
        }
        
        /* Table Styles (Unchanged) */
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: pre-wrap; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .status-badge.pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .status-badge.completed { background-color: var(--success-bg); color: var(--success-text); }
        .status-badge.rejected { background-color: var(--rejected-bg); color: var(--rejected-text); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-header-title">
                <h1>Exam Result Services</h1>
                <p>Purchase result checker PINs or request a result check.</p>
                <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>Place New Request</h3></div>
            <div class="card-body">
                <form id="resultForm" method="POST">
                
                    <div id="step-1-exam" class="step-section">
                        <div class="step">
                            <span class="step-label">1. Select Exam Type</span>
                            <div class="btn-group">
                                <button type="button" class="btn btn-option" data-exam="WAEC">WAEC</button>
                                <button type="button" class="btn btn-option" data-exam="NECO">NECO</button>
                                <button type="button" class="btn btn-option" data-exam="NABTEB">NABTEB</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="step-2-service" class="step-section">
                        <div class="step">
                            <span class="step-label">2. Select Service Type</span>
                            <div class="btn-group">
                                <button type="button" class="btn btn-option" data-service="Result">Result Check</button>
                                <button type="button" class="btn btn-option" data-service="PIN">PIN Purchase</button>
                            </div>
                        </div>
                    </div>
                    
                    <div id="step-3-details" class="step-section">
                        <div class="selection-summary">
                            <div>
                                <span class="label">Exam</span>
                                <span class="value" id="summary-exam">--</span>
                            </div>
                            <div>
                                <span class="label">Service</span>
                                <span class="value" id="summary-service">--</span>
                            </div>
                        </div>
                    
                        <div class="form-group">
                            <label for="examNumber">3. Candidate / Exam Number</label>
                            <input type="text" id="examNumber" name="examNumber" required>
                        </div>
                        <div class="form-group">
                            <label for="examYear">4. Exam Year</label>
                            <input type="number" id="examYear" name="examYear" required placeholder="e.g., 2024" min="1980" max="2025">
                        </div>
                        <div class="price-display">Cost: <span id="displayPrice">₦0.00</span></div>
                        <button type="submit" class="btn btn-primary" disabled><i class="fas fa-paper-plane"></i> Submit Request</button>
                        <button type="button" id="reset-btn" class="btn btn-secondary btn-reset"><i class="fas fa-undo"></i> Start Over</button>
                    </div>
                    
                    <input type="hidden" id="examTypeInput" name="examType">
                    <input type="hidden" id="serviceTypeInput" name="serviceType">
                    
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Your Request History</h3></div>
            <div class="card-body table-wrapper">
                <table>
                    <thead><tr><th>Service</th><th>Details</th><th>Status</th><th>Download/PIN</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="4" style="text-align:center; padding: 2rem;">No requests yet.</td></tr>
                        <?php else: foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['exam_type'] . ' ' . $request['service_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['details']); ?></td>
                                <td><span class="status-badge pending"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                <td>
                                    <?php if (!empty($request['result_file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['result_file_path']); ?>" class="btn btn-secondary" download><i class="fas fa-download"></i> Download</a>
                                    <?php elseif (!empty($request['result_info'])): ?>
                                        <strong><?php echo htmlspecialchars($request['result_info']); ?></strong>
                                    <?php else: ?>
                                        <span>Processing...</span>
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
            const services = <?php echo json_encode($services); ?>;
            
            // Get all elements
            const step1 = document.getElementById('step-1-exam');
            const step2 = document.getElementById('step-2-service');
            const step3 = document.getElementById('step-3-details');
            
            const examTypeInput = document.getElementById('examTypeInput');
            const serviceTypeInput = document.getElementById('serviceTypeInput');
            
            const displayPrice = document.getElementById('displayPrice');
            const submitButton = document.querySelector('#resultForm button[type="submit"]');
            
            const summaryExam = document.getElementById('summary-exam');
            const summaryService = document.getElementById('summary-service');
            
            const resultForm = document.getElementById('resultForm');
            const resultBox = document.getElementById('resultBox');
            const resetButton = document.getElementById('reset-btn');

            // --- Main Function to Update Price ---
            function updatePrice() {
                const exam = examTypeInput.value;
                const service = serviceTypeInput.value;
                let cost = 0;

                console.log(`Updating price. Exam: '${exam}', Service: '${service}'`);

                if (exam && service && services[exam] && services[exam][service]) {
                    cost = parseFloat(services[exam][service]);
                    console.log("Found cost:", cost);
                } else {
                    console.log("Cost not found. Setting to 0.");
                }
                
                // This will work now.
                displayPrice.textContent = `₦${cost.toLocaleString()}`;
                submitButton.disabled = (cost === 0);
            }

            // --- Event Listeners for Buttons ---
            
            // Step 1: Exam Buttons
            step1.querySelectorAll('.btn-option').forEach(button => {
                button.addEventListener('click', () => {
                    const exam = button.dataset.exam;
                    examTypeInput.value = exam;
                    summaryExam.textContent = exam;
                    
                    // Highlight selected button
                    step1.querySelectorAll('.btn-option').forEach(btn => btn.classList.remove('selected'));
                    button.classList.add('selected');

                    // Move to next step
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                    step3.style.display = 'none';
                });
            });
            
            // Step 2: Service Buttons
            step2.querySelectorAll('.btn-option').forEach(button => {
                button.addEventListener('click', () => {
                    const service = button.dataset.service;
                    serviceTypeInput.value = service;
                    summaryService.textContent = service;

                    // Highlight selected button
                    step2.querySelectorAll('.btn-option').forEach(btn => btn.classList.remove('selected'));
                    button.classList.add('selected');

                    // Move to final step
                    step1.style.display = 'none';
                    step2.style.display = 'none';
                    step3.style.display = 'block';
                    
                    // Final call to update price
                    updatePrice();
                });
            });
            
            // Reset Button
            resetButton.addEventListener('click', () => {
                // Reset UI
                step1.style.display = 'block';
                step2.style.display = 'none';
                step3.style.display = 'none';
                
                // Clear inputs
                examTypeInput.value = '';
                serviceTypeInput.value = '';
                document.getElementById('examNumber').value = '';
                document.getElementById('examYear').value = '';
                
                // Clear summaries
                summaryExam.textContent = '--';
                summaryService.textContent = '--';
                
                // Clear selected states
                document.querySelectorAll('.btn-option').forEach(btn => btn.classList.remove('selected'));
                
                // Reset price
                updatePrice();
            });

            // --- Form Submit Listener (Unchanged) ---
            resultForm.addEventListener('submit', function(event) {
                event.preventDefault();
                submitButton.disabled = true;
                resultBox.className = 'result';
                resultBox.textContent = 'Submitting...';
                
                const formData = new FormData(resultForm);
                
                fetch('result-check.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    resultBox.textContent = data.message;
                    resultBox.className = 'result ' + (data.status === 'success' ? 'success' : 'error');
                    if (data.status === 'success') {
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        submitButton.disabled = false; // Re-enable on error
                    }
                })
                .catch(error => { 
                    console.error(error); 
                    resultBox.textContent = 'An unexpected error occurred.';
                    submitButton.disabled = false; // Re-enable on error
                });
            });
        });
    </script>
</body>
</html>
