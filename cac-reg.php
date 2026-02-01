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
$sql_prices = "SELECT service_name, price FROM services WHERE category = 'CAC Registration' ORDER BY price ASC";
if ($result_prices = $conn->query($sql_prices)) {
    while ($row = $result_prices->fetch_assoc()) {
        // This page uses a 'name' => price structure
        $prices[$row['service_name']] = $row['price'];
    }
}
// If this fails, the dropdown will be empty. Ensure you have run the SQL queries from Step 1.
// --- END OF FIX ---


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    $userId = $_SESSION["id"];

    // --- FILE UPLOAD HANDLING ---
    function handleUpload($fileKey, $uploadDir = 'uploads/') {
        if (!isset($_FILES[$fileKey])) return ['error' => "File '$fileKey' is missing."];
        $file = $_FILES[$fileKey];
        if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => 'File upload error code: ' . $file['error']];
        if ($file['size'] > 2000000) return ['error' => 'File is too large (Max 2MB).'];
        $fileName = uniqid() . '-' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) return ['path' => $targetPath];
        return ['error' => 'Failed to move uploaded file.'];
    }
    $idUpload = handleUpload('idCard');
    $photoUpload = handleUpload('passportPhoto');
    $signatureUpload = handleUpload('signature');
    if (isset($idUpload['error'])) { echo json_encode(['status' => 'error', 'message' => 'ID Card: ' . $idUpload['error']]); exit; }
    if (isset($photoUpload['error'])) { echo json_encode(['status' => 'error', 'message' => 'Passport Photo: ' . $photoUpload['error']]); exit; }
    if (isset($signatureUpload['error'])) { echo json_encode(['status' => 'error', 'message' => 'Signature: ' . $signatureUpload['error']]); exit; }
    $idCardPath = $idUpload['path'];
    $passportPhotoPath = $photoUpload['path'];
    $signaturePath = $signatureUpload['path'];
    
    // --- SERVER-SIDE PRICE CALCULATION ---
    $service = $_POST['serviceType'] ?? '';
    $cost = $prices[$service] ?? 0; // Uses the dynamic $prices array
    if ($cost == 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid service selected.']); exit; }

    // --- DATABASE TRANSACTION ---
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
    
    $description = "Payment for " . $service;
    $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'debit', ?, ?)");
    $stmt_trans->bind_param("ids", $userId, $cost, $description);

    $stmt_req = $conn->prepare("INSERT INTO cac_requests (user_id, service_type, business_name_1, business_name_2, nature_of_business, business_address, proprietor_name, proprietor_phone, proprietor_email, proprietor_dob, proprietor_gender, id_card_path, passport_photo_path, signature_path, cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_req->bind_param("isssssssssssssd", 
        $userId, $service, $_POST['businessName1'], $_POST['businessName2'], $_POST['natureOfBusiness'], $_POST['businessAddress'],
        $_POST['proprietorName'], $_POST['proprietorPhone'], $_POST['proprietorEmail'], $_POST['proprietorDob'], $_POST['proprietorGender'],
        $idCardPath, $passportPhotoPath, $signaturePath, $cost
    );

    if ($stmt_wallet->execute() && $stmt_trans->execute() && $stmt_req->execute()) {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Your CAC registration request has been submitted!']);
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
$orders = [];
$sql = "SELECT service_type, business_name_1, cost, status, request_date, certificate_path FROM cac_requests WHERE user_id = ? ORDER BY request_date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAC Business Name Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd; --primary-hover: #0b5ed7; --secondary-color: #64748b;
            --background-color: #f1f5f9; --card-bg: #ffffff; --text-color: #334155;
            --heading-color: #1e293b; --border-color: #e2e8f0; --success-bg: #dcfce7;
            --success-text: #166534; --pending-bg: #fef9c3; --pending-text: #854d0e;
            --rejected-bg: #fee2e2; --rejected-text: #991b1b;
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
        .form-group label, .form-section-title { display: block; font-weight: 600; margin-bottom: 0.5rem; color: var(--heading-color); }
        .form-section-title { font-size: 1.1rem; margin-top: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
        .price-display { text-align: center; font-size: 1.5rem; font-weight: 700; margin: 2rem 0; padding: 1rem; background-color: #f8fafc; border-radius: 8px; color: var(--primary-color); }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-color); white-space: nowrap; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .status-badge.pending { background-color: var(--pending-bg); color: var(--pending-text); }
        .status-badge.rejected { background-color: var(--rejected-bg); color: var(--rejected-text); }
        .status-badge.completed { background-color: var(--success-bg); color: var(--success-text); }
        .result { margin-top: 1rem; text-align: center; }
        .file-upload-wrapper { border: 2px dashed var(--border-color); border-radius: 8px; padding: 1rem; text-align: center; cursor: pointer; position: relative; }
        .file-upload-wrapper input[type="file"] { display: none; }
        .image-preview { max-width: 100px; max-height: 100px; margin-top: 1rem; border-radius: 8px; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-header-title">
                <h1>CAC Business Name Registration</h1>
                <p>Register your business with the Corporate Affairs Commission.</p>
                <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>New Registration Form</h3></div>
            <div class="card-body">
                <form id="cacForm" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="serviceType">1. Select Service Package</label>
                        <select id="serviceType" name="serviceType" required>
                            <option value="">-- Select a Package --</option>
                            <?php foreach ($prices as $name => $price): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($name); ?> - ₦<?php echo number_format($price); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-section-title">2. Proposed Business Details</div>
                    <div class="form-group"><label>Business Name (1st Choice)</label><input type="text" name="businessName1" required></div>
                    <div class="form-group"><label>Business Name (2nd Choice)</label><input type="text" name="businessName2" required></div>
                    <div class="form-group"><label>Nature of Business</label><textarea name="natureOfBusiness" rows="3" placeholder="e.g., Fashion Design, Online Sales, Catering" required></textarea></div>
                    <div class="form-group"><label>Business Address</label><textarea name="businessAddress" rows="3" required></textarea></div>

                    <div class="form-section-title">3. Proprietor's Details</div>
                    <div class="form-group"><label>Full Name</label><input type="text" name="proprietorName" required></div>
                    <div class="form-group"><label>Phone Number</label><input type="tel" name="proprietorPhone" required></div>
                    <div class="form-group"><label>Email Address</label><input type="email" name="proprietorEmail" required></div>
                    <div class="form-group"><label>Date of Birth</label><input type="date" name="proprietorDob" required></div>
                    <div class="form-group"><label>Gender</label><select name="proprietorGender" required><option value="">-- Select --</option><option>Male</option><option>Female</option></select></div>

                    <div class="form-section-title">4. Document Uploads</div>
                    <div class="form-group">
                        <label>Valid ID Card</label>
                        <label class="file-upload-wrapper" for="idCard"><i class="fas fa-cloud-upload-alt"></i> <span>Click to Upload ID</span><img class="image-preview" data-preview-for="idCard"/></label>
                        <input type="file" id="idCard" name="idCard" accept="image/*,.pdf" required>
                    </div>
                    <div class="form-group">
                        <label>Passport Photograph</label>
                        <label class="file-upload-wrapper" for="passportPhoto"><i class="fas fa-cloud-upload-alt"></i> <span>Click to Upload Photo</span><img class="image-preview" data-preview-for="passportPhoto"/></label>
                        <input type="file" id="passportPhoto" name="passportPhoto" accept="image/*" required>
                    </div>
                    <div class="form-group">
                        <label>Signature on white paper</label>
                        <label class="file-upload-wrapper" for="signature"><i class="fas fa-cloud-upload-alt"></i> <span>Click to Upload Signature</span><img class="image-preview" data-preview-for="signature"/></label>
                        <input type="file" id="signature" name="signature" accept="image/*" required>
                    </div>
                    
                    <div class="price-display"><span>Request Cost:</span><span id="displayPrice">₦0.00</span></div>
                    <button type="submit" class="btn btn-primary" disabled><i class="fas fa-paper-plane"></i> Submit Registration</button>
                </form>
                <div id="resultBox" class="result"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header"><h3>Your Registration History</h3></div>
            <div class="card-body table-wrapper">
                <table>
                    <thead><tr><th>Date</th><th>Service</th><th>Business Name</th><th>Status</th><th>Certificate</th></tr></thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem;">No registration orders yet.</td></tr>
                        <?php else: foreach($orders as $order): ?>
                            <tr>
                                <td><?php echo date("d M Y", strtotime($order['request_date'])); ?></td>
                                <td><?php echo htmlspecialchars($order['service_type']); ?></td>
                                <td><?php echo htmlspecialchars($order['business_name_1']); ?></td>
                                <td><span class="status-badge pending"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                <td>
                                    <?php if (!empty($order['certificate_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($order['certificate_path']); ?>" class="btn btn-secondary" download><i class="fas fa-download"></i> Download</a>
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
            const serviceSelect = document.getElementById('serviceType');
            const displayPriceEl = document.getElementById('displayPrice');
            const submitButton = document.querySelector('#cacForm button[type="submit"]');
            const cacForm = document.getElementById('cacForm');
            const resultBox = document.getElementById('resultBox');
            
            const prices = <?php echo json_encode($prices); ?>;

            serviceSelect.addEventListener('change', () => {
                const selectedService = serviceSelect.value;
                const cost = prices[selectedService] || 0;
                displayPriceEl.textContent = `₦${cost.toLocaleString()}`;
                submitButton.disabled = (cost === 0);
            });

            function setupFilePreview(inputId) {
                const fileInput = document.getElementById(inputId);
                const previewImg = document.querySelector(`img[data-preview-for="${inputId}"]`);
                if (fileInput && previewImg) {
                    fileInput.addEventListener('change', function() {
                        if (this.files && this.files[0]) {
                            const reader = new FileReader();
                            reader.onload = function(e) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
                            reader.readAsDataURL(this.files[0]);
                        }
                    });
                }
            }
            setupFilePreview('idCard');
            setupFilePreview('passportPhoto');
            setupFilePreview('signature');

            cacForm.addEventListener('submit', function(event) {
                event.preventDefault();
                resultBox.textContent = 'Submitting...';
                const formData = new FormData(cacForm);
                fetch('cac-reg.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    resultBox.textContent = data.message;
                    resultBox.className = 'result ' + data.status;
                    if (data.status === 'success') {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .catch(error => {
                    resultBox.textContent = 'An unexpected error occurred.';
                    console.error('Fetch Error:', error);
                });
            });
        });
    </script>
</body>
</html>
