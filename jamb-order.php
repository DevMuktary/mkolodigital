<?php
// === PHP LOGIC STARTS HERE ===

session_start();
require_once "db.php";

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- [FIXED] FETCH PRICES DYNAMICALLY FROM THE DATABASE ---

// First, define the default structure with the required fields for each service.
// This acts as a fallback and defines the form logic.
$jamb_services = [
    'profile_code' => ['name' => 'Jamb Old Profile Code Retrieval', 'price' => 2000, 'fields' => ['reg_number', 'phone']],
    'result_slip' => ['name' => 'Jamb Original Result Slip', 'price' => 3000, 'fields' => ['reg_number', 'profile_code']],
    'reg_slip' => ['name' => 'Jamb 2025 Registration Slip', 'price' => 2500, 'fields' => ['reg_number', 'email']],
    'admission_letter' => ['name' => 'Jamb Admission Letter Print', 'price' => 2500, 'fields' => ['reg_number', 'profile_code']],
    'mock_slip' => ['name' => 'Jamb 2025 Mock Exam Slip', 'price' => 2000, 'fields' => ['reg_number']]
];

// Now, query the database to get the latest prices and names
$sql_prices = "SELECT service_key, service_name, price FROM services WHERE category = 'JAMB Services'";
if ($result_prices = $conn->query($sql_prices)) {
    while ($row = $result_prices->fetch_assoc()) {
        // Check if the service_key from the DB exists in our structure
        if (isset($jamb_services[$row['service_key']])) {
            // If it exists, update the name and price with the live data from the database
            $jamb_services[$row['service_key']]['name'] = $row['service_name'];
            $jamb_services[$row['service_key']]['price'] = $row['price'];
        }
    }
}
// --- END OF FIX ---


$service_key = $_GET['service'] ?? '';

if (!array_key_exists($service_key, $jamb_services)) {
    // Redirect to the main selection page if the service key is invalid
    header("location: jamb-services.php");
    exit;
}

$selected_service = $jamb_services[$service_key];
$cost = $selected_service['price'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $userId = $_SESSION["id"];

    // Combine form details into a single string for the database
    $details = '';
    foreach ($_POST as $key => $value) {
        if ($key != 'service_key') {
            $details .= ucfirst(str_replace('_', ' ', $key)) . ": " . htmlspecialchars($value) . "\n";
        }
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

    $description = "Payment for " . $selected_service['name'];
    $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt_trans->bind_param("ids", $userId, $cost, $description);

    $stmt_req = $conn->prepare("INSERT INTO jamb_requests (user_id, service_type, details, cost) VALUES (?, ?, ?, ?)");
    $stmt_req->bind_param("issd", $userId, $selected_service['name'], $details, $cost);

    if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Your request has been submitted successfully!']);
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error: Transaction failed.']);
    }

    $stmt_wallet->close();
    $stmt_trans->close();
    $stmt_req->close();
    $conn->close(); 
    exit;
}

// Fetch user's request history for the table
$requests = [];
$sql = "SELECT service_type, details, status, result_file_path FROM jamb_requests WHERE user_id = ? ORDER BY request_date DESC";
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
    <title><?php echo htmlspecialchars($selected_service['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #008000; --primary-hover: #006400; --secondary-color: #64748b;
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
        .btn-secondary { background-color: var(--card-bg); color: var(--secondary-color); border: 1px solid var(--border-color); font-size: 0.9rem; padding: 0.5rem 1rem;}
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0; font-size: 1.25rem; color: var(--heading-color); }
        .card-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input, .form-group select { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .selected-service-display { text-align: center; margin-bottom: 2rem; padding: 1rem; background-color: #f8fafc; border-radius: 8px; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: pre-wrap; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .status-badge.pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .status-badge.completed { background-color: var(--success-bg); color: var(--success-text); }
        .status-badge.rejected { background-color: var(--rejected-bg); color: var(--rejected-text); }
        .result { margin-top: 1rem; text-align: center; padding: 1rem; border-radius: 8px; }
        .result.success { background-color: var(--success-bg); color: var(--success-text); }
        .result.error { background-color: var(--rejected-bg); color: var(--rejected-text); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-header-title">
                <h1>JAMB Service Order</h1>
                <p>Complete the details below for your selected service.</p>
                <a href="jamb-services.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Services</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3><?php echo htmlspecialchars($selected_service['name']); ?></h3></div>
            <div class="card-body">
                <div class="selected-service-display">
                    <h3>Service Cost: <strong>₦<?php echo number_format($cost, 2); ?></strong></h3>
                </div>
                <form id="jambOrderForm" method="POST">
                    <input type="hidden" name="service_key" value="<?php echo htmlspecialchars($service_key); ?>">
                    
                    <div class="form-group"><label for="full_name"><i class="fas fa-user"></i> Full Name</label><input type="text" id="full_name" name="full_name" required></div>
                    <div class="form-group">
                        <label for="examination_year"><i class="fas fa-calendar-alt"></i> Examination Year</label>
                        <select id="examination_year" name="examination_year" required>
                            <option value="">-- Select Year --</option>
                            <?php for ($year = 2025; $year >= 1987; $year--): ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <?php if (in_array('reg_number', $selected_service['fields'])): ?>
                        <div class="form-group"><label for="reg_number"><i class="fas fa-hashtag"></i> JAMB Registration Number</label><input type="text" id="reg_number" name="reg_number" required></div>
                    <?php endif; ?>

                    <?php if (in_array('profile_code', $selected_service['fields'])): ?>
                        <div class="form-group"><label for="profile_code"><i class="fas fa-user-circle"></i> JAMB Profile Code</label><input type="text" id="profile_code" name="profile_code" required></div>
                    <?php endif; ?>
                    
                    <?php if (in_array('phone', $selected_service['fields'])): ?>
                        <div class="form-group"><label for="phone"><i class="fas fa-phone"></i> Phone Number Used for Registration</label><input type="tel" id="phone" name="phone" required></div>
                    <?php endif; ?>

                    <?php if (in_array('email', $selected_service['fields'])): ?>
                        <div class="form-group"><label for="email"><i class="fas fa-envelope"></i> Email Address Used for Registration</label><input type="email" id="email" name="email" required></div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Request</button>
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>Your JAMB Request History</h3></div>
            <div class="card-body table-wrapper">
                <table>
                    <thead><tr><th>Service</th><th>Details</th><th>Status</th><th>Download</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 2rem;">No JAMB requests yet.</td></tr>
                        <?php else: foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['service_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['details']); ?></td>
                                <td><span class="status-badge pending"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                <td>
                                    <?php if (!empty($request['result_file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['result_file_path']); ?>" class="btn btn-secondary" download><i class="fas fa-download"></i> Download</a>
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
        document.getElementById('jambOrderForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const form = event.target;
            const resultBox = document.getElementById('resultBox');
            resultBox.textContent = 'Submitting...';
            resultBox.className = 'result';

            const formData = new FormData(form);
            fetch(window.location.href, { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                resultBox.textContent = data.message;
                resultBox.className = 'result ' + data.status;
                if (data.status === 'success') {
                    setTimeout(() => window.location.reload(), 2000);
                }
            })
            .catch(error => { console.error('Error:', error); resultBox.textContent = 'An unexpected error occurred.'; });
        });
    </script>
</body>
</html>
