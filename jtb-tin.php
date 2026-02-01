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
$sql_prices = "SELECT service_key, service_name, price FROM services WHERE category = 'TIN Registration'";
if ($result_prices = $conn->query($sql_prices)) {
    while ($row = $result_prices->fetch_assoc()) {
        // This page uses a 'key' => ['price' => ..., 'label' => ...] structure
        $prices[$row['service_key']] = [
            'price' => $row['price'],
            'label' => $row['service_name']
        ];
    }
}
// If this fails, the dropdown will be empty. Ensure you have run the SQL queries from Step 1.
// --- END OF FIX ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $userId = $_SESSION["id"];
    $tinType = $_POST['tinType'] ?? '';
    $cost = $prices[$tinType]['price'] ?? 0;
    
    if ($cost == 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid TIN type.']); exit; }

    // --- FILE UPLOAD HANDLING ---
    $photoPath = '';
    $cacPath = null;
    function handleUpload($fileKey, $uploadDir = 'uploads/') {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] != 0) return ['error' => 'File not found or upload error.'];
        $file = $_FILES[$fileKey];
        $fileName = uniqid() . '-' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) return ['path' => $targetPath];
        return ['error' => 'Failed to save uploaded file.'];
    }

    $photoUpload = handleUpload('passportPhoto');
    if (isset($photoUpload['error'])) { echo json_encode(['status' => 'error', 'message' => 'Passport Photo: ' . $photoUpload['error']]); exit; }
    $photoPath = $photoUpload['path'];

    if ($tinType === 'business') {
        $cacUpload = handleUpload('cacCertificate');
        if (isset($cacUpload['error'])) { echo json_encode(['status' => 'error', 'message' => 'CAC Certificate: ' . $cacUpload['error']]); exit; }
        $cacPath = $cacUpload['path'];
    }

    // --- DATABASE TRANSACTION ---
    $conn->autocommit(FALSE);
    $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $userId);
    $stmt_check->execute();
    $user = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($user['wallet_balance'] < $cost) { echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance.']); $conn->rollback(); exit; }

    // 1. DEDUCT FROM WALLET
    $newBalance = $user['wallet_balance'] - $cost;
    $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt_wallet->bind_param("di", $newBalance, $userId);

    // 2. LOG THE DEBIT TRANSACTION
    $description = "Payment for " . $prices[$tinType]['label'];
    $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt_trans->bind_param("ids", $userId, $cost, $description);

    // 3. CREATE THE SERVICE REQUEST
    $stmt_req = $conn->prepare("INSERT INTO tin_requests (user_id, tin_type, full_name, email, phone, dob, bvn, nin, address, cost, passport_photo_path, business_name, business_rc_number, business_commencement_date, cac_certificate_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $businessName = $_POST['businessName'] ?? null;
    $businessRcNumber = $_POST['businessRcNumber'] ?? null;
    $commencementDate = $_POST['commencementDate'] ?? null;
    $stmt_req->bind_param("issssssssdsisss", 
        $userId, $prices[$tinType]['label'], $_POST['fullName'], $_POST['email'], $_POST['phone'], 
        $_POST['dob'], $_POST['bvn'], $_POST['nin'], $_POST['address'], $cost, $photoPath,
        $businessName, $businessRcNumber, $commencementDate, $cacPath
    );

    if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Your TIN request has been submitted!']);
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Database error: Transaction failed.']);
    }
    
    $stmt_wallet->close(); $stmt_trans->close(); $stmt_req->close(); $conn->close(); exit;
}

// Fetch user's request history
$requests = [];
$sql = "SELECT tin_type, full_name, status, request_date, certificate_path FROM tin_requests WHERE user_id = ? ORDER BY request_date DESC";
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
    <title>JTB-TIN Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd; --primary-hover: #0b5ed7; --secondary-color: #64748b;
            --background-color: #f1f5f9; --card-bg: #ffffff; --text-color: #334155;
            --heading-color: #1e293b; --border-color: #e2e8f0; --success-bg: #dcfce7;
            --success-text: #166534; --pending-bg: #fef9c3; --pending-text: #854d0e;
            --error-bg: #fee2e2; --error-text: #991b1b;
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
        .card-header, .card-body { padding: 1.5rem; }
        .card-header { border-bottom: 1px solid var(--border-color); }
        .form-section-title { font-weight: 600; color: var(--heading-color); margin-top: 2rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .price-display { text-align: center; font-size: 1.5rem; font-weight: 700; margin: 2rem 0; padding: 1rem; background-color: #f8fafc; border-radius: 8px; color: var(--primary-color); }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .status-badge.pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .file-upload-wrapper { border: 2px dashed var(--border-color); border-radius: 8px; padding: 1rem; text-align: center; cursor: pointer; }
        #imagePreview { max-width: 100px; max-height: 100px; margin-top: 1rem; border-radius: 8px; display: none; }
        .result { margin-top: 1rem; text-align: center; padding: 1rem; border-radius: 8px; }
        .result.success { background-color: var(--success-bg); color: var(--success-text); }
        .result.error { background-color: var(--error-bg); color: var(--error-text); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-header-title">
                <h1>JTB-TIN Registration</h1>
                <p>Submit and track your TIN requests.</p>
                <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>New TIN Request</h3></div>
            <div class="card-body">
                <form id="tinForm" method="POST" enctype="multipart/form-data">
                    <div class="form-group"><label>1. Select TIN Type</label><select id="tinType" name="tinType" required><option value="">-- Select Service --</option><?php foreach ($prices as $key => $details): ?><option value="<?php echo $key; ?>"><?php echo $details['label']; ?></option><?php endforeach; ?></select></div>
                    
                    <div class="form-section-title">2. Proprietor's Details</div>
                    <div class="form-group"><input type="text" name="fullName" placeholder="Full Name" required></div>
                    <div class="form-group"><input type="date" name="dob" required></div>
                    <div class="form-group"><input type="tel" name="phone" placeholder="Phone Number" required></div>
                    <div class="form-group"><input type="email" name="email" placeholder="Email Address" required></div>
                    <div class="form-group"><input type="text" name="bvn" placeholder="BVN (11 digits)" required maxlength="11"></div>
                    <div class="form-group"><input type="text" name="nin" placeholder="NIN (11 digits)" required maxlength="11"></div>
                    <div class="form-group"><textarea name="address" rows="3" placeholder="Full Residential Address" required></textarea></div>
                    <div class="form-group">
                        <label>Passport Photograph</label>
                        <label for="passportPhoto" class="file-upload-wrapper"><span>Click to Upload Photo</span><img id="imagePreview" src="#"/></label>
                        <input type="file" id="passportPhoto" name="passportPhoto" accept="image/*" required style="display:none;">
                    </div>
                    
                    <div id="businessFields" style="display: none;">
                        <div class="form-section-title">3. Business Details</div>
                        <div class="form-group"><input type="text" name="businessName" placeholder="Business Name"></div>
                        <div class="form-group"><input type="text" name="businessRcNumber" placeholder="Business RC Number"></div>
                        <div class="form-group"><label>Business Commencement Date</label><input type="date" name="commencementDate"></div>
                        <div class="form-group">
                            <label>CAC Certificate</label>
                            <label for="cacCertificate" class="file-upload-wrapper"><span>Click to Upload Certificate (PDF/Image)</span></label>
                            <input type="file" id="cacCertificate" name="cacCertificate" accept="image/*,.pdf" style="display:none;">
                        </div>
                    </div>

                    <div class="price-display"><span>Request Cost:</span><span id="displayPrice">₦0.00</span></div>
                    <button type="submit" class="btn btn-primary" disabled>Submit Request</button>
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>Your Request History</h3></div>
            <div class="card-body table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th>Type</th><th>Name</th><th>Status</th><th>Certificate</th></tr></thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem;">No TIN requests yet.</td></tr>
                        <?php else: foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo date("d M Y", strtotime($request['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($request['tin_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                <td><span class="status-badge pending"><?php echo htmlspecialchars($request['status']); ?></span></td>
                                <td>
                                    <?php if (!empty($request['certificate_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($request['certificate_path']); ?>" class="btn btn-secondary" download><i class="fas fa-download"></i> Download</a>
                                    <?php else: ?>
                                        <span>Not Ready</span>
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
            const tinTypeSelect = document.getElementById('tinType');
            const displayPriceEl = document.getElementById('displayPrice');
            const submitButton = document.querySelector('#tinForm button[type="submit"]');
            const businessFields = document.getElementById('businessFields');
            const businessInputs = Array.from(businessFields.querySelectorAll('input, textarea'));
            const tinForm = document.getElementById('tinForm');
            const resultBox = document.getElementById('resultBox');
            const prices = <?php echo json_encode($prices); ?>;

            tinTypeSelect.addEventListener('change', () => {
                const selectedType = tinTypeSelect.value;
                const cost = prices[selectedType]?.price || 0;
                displayPriceEl.textContent = `₦${cost.toLocaleString()}`;
                submitButton.disabled = (cost === 0);

                if (selectedType === 'business') {
                    businessFields.style.display = 'block';
                    businessInputs.forEach(input => input.required = true);
                } else {
                    businessFields.style.display = 'none';
                    businessInputs.forEach(input => input.required = false);
                }
            });

            const photoInput = document.getElementById('passportPhoto');
            const imagePreview = document.getElementById('imagePreview');
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (e) => { imagePreview.src = e.target.result; imagePreview.style.display = 'block'; };
                    reader.readAsDataURL(this.files[0]);
                }
            });

            tinForm.addEventListener('submit', function(e) {
                e.preventDefault();
                resultBox.textContent = 'Submitting your request...';
                resultBox.className = 'result';
                
                const formData = new FormData(tinForm);
                fetch('jtb-tin.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    resultBox.textContent = data.message;
                    resultBox.className = 'result ' + data.status;
                    if (data.status === 'success') {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    resultBox.textContent = 'An unexpected error occurred.';
                    resultBox.className = 'result error';
                });
            });
        });
    </script>
</body>
</html>
