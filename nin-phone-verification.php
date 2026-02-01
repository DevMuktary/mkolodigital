<?php
session_start();
require_once "db.php";     
require_once "config.php"; 
require_once "fpdf.php"; // REQUIRED

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php"); exit;
}

$userId = $_SESSION["id"];
$message = ""; $msgType = "";

// State
$viewData = isset($_SESSION['phone_view_data']) ? $_SESSION['phone_view_data'] : null;
$paidSlipType = isset($_SESSION['phone_paid_slip_type']) ? $_SESSION['phone_paid_slip_type'] : null;
$currentRecordId = isset($_SESSION['phone_current_record_id']) ? $_SESSION['phone_current_record_id'] : null;

// Pricing
$dbPrices = [];
$sql = "SELECT service_key, price FROM services WHERE category = 'NIN Services'";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dbPrices[$row['service_key']] = $row['price'];
    }
}

$lookupPrice = $dbPrices['nin_phone_lookup'] ?? 150; 
$slipPrices = ['regular' => $dbPrices['nin_slip_regular'] ?? 500, 'standard' => $dbPrices['nin_slip_standard'] ?? 800, 'premium' => $dbPrices['nin_slip_premium'] ?? 1200];

// Wallet
$stmt = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$walletBalance = $user['wallet_balance'];
$stmt->close();

// ---------------- VERIFY ACTION ----------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'verify') {
    $phone = trim($_POST['phone']);

    if ($walletBalance < $lookupPrice) {
        $message = "Insufficient balance (₦" . number_format($lookupPrice) . ")."; $msgType = "error";
    } else {
        $curl = curl_init();
        
        $payload = json_encode(["phone" => $phone, "reference" => "PHP-PH-" . uniqid()]);
        
        // PHONE ENDPOINT
        $apiUrl = str_replace('nin-verify', 'phone-verify', AGENTLINK_API_URL);

        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl, 
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . AGENTLINK_API_KEY, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 45
        ]);
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $apiResult = json_decode($response, true);

        if ($httpCode == 200 && isset($apiResult['status']) && $apiResult['status'] === true) {
            $newBal = $walletBalance - $lookupPrice;
            $conn->query("UPDATE users SET wallet_balance = $newBal WHERE id = $userId");
            $conn->query("INSERT INTO transactions (user_id, type, amount, description) VALUES ($userId, 'debit', $lookupPrice, 'NIN Phone Search: $phone')");
            $walletBalance = $newBal;

            $dataJson = json_encode($apiResult['data']);
            $photo = $apiResult['data']['photo'];
            $stmt = $conn->prepare("INSERT INTO verification_history (user_id, search_mode, search_value, api_data, photo_base64) VALUES (?, 'PHONE', ?, ?, ?)");
            $stmt->bind_param("isss", $userId, $phone, $dataJson, $photo);
            $stmt->execute();
            
            $_SESSION['phone_view_data'] = $apiResult['data'];
            $_SESSION['phone_current_record_id'] = $stmt->insert_id;
            $_SESSION['phone_paid_slip_type'] = null;
            $viewData = $apiResult['data'];
            $paidSlipType = null;
            $currentRecordId = $stmt->insert_id;
            
            $message = "Phone Search Successful!"; $msgType = "success";
        } else {
            $message = $apiResult['error'] ?? "Verification Failed. Number not found."; $msgType = "error";
        }
    }
}

// ---------------- PRINT ACTION ----------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'print') {
    $type = $_POST['slip_type'];
    $cost = $slipPrices[$type];

    $isFree = false;
    if ($currentRecordId) {
        $checkStmt = $conn->query("SELECT paid_slip FROM verification_history WHERE id = $currentRecordId");
        $row = $checkStmt->fetch_assoc();
        if ($row['paid_slip'] == $type || $row['paid_slip'] == 'premium') $isFree = true;
    }

    if (!$isFree && $walletBalance < $cost) {
        $message = "Insufficient balance (₦" . number_format($cost) . ")."; $msgType = "error";
    } else {
        try {
            if (!$isFree) {
                $conn->query("UPDATE users SET wallet_balance = wallet_balance - $cost WHERE id = $userId");
                $conn->query("INSERT INTO transactions (user_id, type, amount, description) VALUES ($userId, 'debit', $cost, 'Generated Slip via Phone ($type)')");
                $walletBalance -= $cost;
                if ($currentRecordId) {
                    $conn->query("UPDATE verification_history SET paid_slip = '$type' WHERE id = $currentRecordId");
                    $_SESSION['phone_paid_slip_type'] = $type;
                    $paidSlipType = $type;
                }
            }
            generatePdf($viewData, $type);
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage(); $msgType = "error";
        }
    }
}

// History Loader
if (isset($_GET['view_id'])) {
    $histId = intval($_GET['view_id']);
    // Fetch only PHONE records
    $res = $conn->query("SELECT * FROM verification_history WHERE id = $histId AND user_id = $userId");
    if ($row = $res->fetch_assoc()) {
        $_SESSION['phone_view_data'] = json_decode($row['api_data'], true);
        $_SESSION['phone_current_record_id'] = $row['id'];
        $_SESSION['phone_paid_slip_type'] = $row['paid_slip'];
        header("Location: nin-phone-verification.php"); exit;
    }
}

// Reset
if (isset($_POST['reset'])) {
    unset($_SESSION['phone_view_data']);
    unset($_SESSION['phone_current_record_id']);
    unset($_SESSION['phone_paid_slip_type']);
    header("Location: nin-phone-verification.php"); exit;
}

$history = [];
$hRes = $conn->query("SELECT * FROM verification_history WHERE user_id = $userId AND search_mode = 'PHONE' ORDER BY created_at DESC LIMIT 5");
while($row = $hRes->fetch_assoc()) { $history[] = $row; }

// --- PDF GENERATOR ---
function generatePdf($data, $type) {
    $templatePath = "templates/nin_{$type}.png";
    $tempPhoto = "generated_slips/temp_p_" . uniqid() . ".jpg";
    $tempQr = "generated_slips/temp_q_" . uniqid() . ".png";

    if (!file_exists($templatePath)) throw new Exception("Template ($type) not found.");
    if (!is_dir("generated_slips")) mkdir("generated_slips", 0755, true);

    file_put_contents($tempPhoto, base64_decode($data['photo']));
    $qrText = "surname: {$data['surname']} | givenNames: {$data['firstname']} | dob: {$data['birthdate']}";
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrText);
    file_put_contents($tempQr, file_get_contents($qrUrl));

    list($width, $height) = getimagesize($templatePath);
    $orientation = ($width > $height) ? 'L' : 'P';

    $pdf = new FPDF($orientation, 'pt', [$width, $height]);
    $pdf->AddPage();
    $pdf->Image($templatePath, 0, 0, $width, $height);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(50, 50, 50);

    if ($type == 'regular') {
        $pdf->Text(122, 170, $data['nin']);
        $pdf->Text(105, 133, $data['trackingId'] ?? 'N/A');
        $pdf->Text(296, 130, strtoupper($data['surname']));
        $pdf->Text(296, 170, strtoupper($data['firstname']));
        $pdf->Text(296, 203, strtoupper($data['middlename']));
        $pdf->Text(296, 232, strtoupper($data['gender']));
        $pdf->SetXY(437, 130);
        $pdf->MultiCell(160, 12, strtoupper($data['residence_AdressLine1']), 0, 'L');
        $pdf->Image($tempPhoto, 615, 112, 105, 115);
    } 
    elseif ($type == 'standard') {
        $n = $data['nin'];
        $fmtNin = substr($n,0,4)."  ".substr($n,4,3)."  ".substr($n,7);
        $pdf->SetFont('Helvetica', 'B', 23);
        $pdf->Text(322, 247, $fmtNin);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(320, 110, strtoupper($data['surname']));
        $pdf->Text(320, 150, strtoupper($data['firstname']) . ',');
        $pdf->Text(393, 150, strtoupper($data['middlename']));
        $pdf->Text(320, 185, $data['birthdate']);
        $pdf->Image($tempPhoto, 207, 87, 90, 100);
        $pdf->Image($tempQr, 498, 90, 90, 90);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Text(518, 187, "ISSUE DATE");
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(518, 197, date("d-M-Y"));
    } 
    elseif ($type == 'premium') {
        $n = $data['nin'];
        $fmtNin = substr($n,0,4)."  ".substr($n,4,3)."  ".substr($n,7);
        $pdf->SetFont('Helvetica', 'B', 56);
        $pdf->Text(445, 1048, $fmtNin);
        $pdf->SetTextColor(200, 200, 200);
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->Text(270, 570, $data['nin']);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('Helvetica', '', 32);
        $pdf->Text(475, 695, strtoupper($data['surname']));
        $pdf->Text(470, 792, strtoupper($data['firstname']));
        $pdf->Text(632, 792, strtoupper($data['middlename']));
        $pdf->Text(465, 880, $data['birthdate']);
        $pdf->Text(714, 880, strtoupper($data['gender']));
        $pdf->Text(955, 935, date("d-M-Y"));
        $pdf->Image($tempPhoto, 169, 605, 260, 324);
        $pdf->Image($tempQr, 870, 488, 344, 326);
    }

    @unlink($tempPhoto);
    @unlink($tempQr);
    $pdf->Output('D', "NIN_Slip_{$type}.pdf");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Phone Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f3f4f6; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .back-btn { text-decoration: none; color: #64748b; font-weight: 500; }
        .balance { background: #e0e7ff; color: #1e40af; padding: 6px 14px; border-radius: 20px; font-weight: 700; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert.error { background: #fee2e2; color: #991b1b; }
        .alert.success { background: #dcfce7; color: #15803d; }
        
        .data-container { display: flex; gap: 30px; flex-wrap: wrap; }
        .photo-box { flex: 0 0 150px; }
        .photo-box img { width: 100%; border-radius: 10px; border: 4px solid #f1f5f9; }
        .info-box { flex: 1; }
        .field-row { display: flex; border-bottom: 1px solid #f1f5f9; padding: 12px 0; }
        .field-label { width: 140px; color: #64748b; font-weight: 500; font-size: 0.9rem; }
        .field-value { color: #1e293b; font-weight: 600; flex: 1; }

        .slip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 25px; }
        .slip-card { border: 2px solid #e2e8f0; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: 0.2s; background: white; }
        .slip-card:hover { border-color: #2563eb; background: #eff6ff; }
        .slip-card i { font-size: 32px; color: #2563eb; margin-bottom: 10px; display: block; }
        .slip-card h4 { margin: 0; color: #1e293b; font-size: 16px; }
        .slip-card .price { color: #64748b; font-size: 0.9rem; margin-top: 5px; display: block; }
        .slip-card .free-badge { background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; display: inline-block; margin-top: 5px; }

        .history-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 600px; }
        th { text-align: left; color: #64748b; padding: 12px; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-badge.paid { background: #dcfce7; color: #15803d; }
        .status-badge.view { background: #fef9c3; color: #a16207; }

        input { 
            width: 100%; 
            padding: 14px; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px; 
            box-sizing: border-box; 
            font-size: 16px !important; /* FIX ZOOM */
        }
        .btn-verify { width: 100%; padding: 14px; background: #2563eb; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 15px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <div class="header">
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <div class="balance">Wallet: ₦<?php echo number_format($walletBalance); ?></div>
        </div>
        <h2 style="margin:0; color:#1e293b;">Phone Lookup Portal</h2>
        <p style="color:#64748b; margin-top:5px;">Search NIN using Phone Number (Cost: ₦<?php echo number_format($lookupPrice); ?>)</p>
    </div>

    <?php if ($message): ?>
        <div class="alert <?php echo $msgType; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!$viewData): ?>
    <div class="card">
        <form method="POST">
            <input type="hidden" name="action" value="verify">
            <label style="font-weight:600; color:#334155; display:block; margin-bottom:8px;">Enter Phone Number</label>
            <input type="text" name="phone" placeholder="e.g. 08012345678" required maxlength="11" style="margin-top:10px;">
            <button type="submit" class="btn-verify">Search Database</button>
        </form>
    </div>
    
    <div class="card">
        <h3 style="margin-top:0; color:#334155;">Recent Phone Searches</h3>
        <div class="history-wrapper">
            <table>
                <thead><tr><th>Date</th><th>Phone</th><th>Name</th><th>Slip Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if(empty($history)): ?><tr><td colspan="5" style="text-align:center; padding:20px;">No history found.</td></tr><?php endif; ?>
                    <?php foreach($history as $h): $d = json_decode($h['api_data'], true); ?>
                    <tr>
                        <td><?php echo date('d M, H:i', strtotime($h['created_at'])); ?></td>
                        <td><?php echo $h['search_value']; ?></td>
                        <td><?php echo $d['firstname'] . ' ' . $d['surname']; ?></td>
                        <td><?php echo $h['paid_slip'] ? "<span class='status-badge paid'>Paid</span>" : "<span class='status-badge view'>View Only</span>"; ?></td>
                        <td><a href="?view_id=<?php echo $h['id']; ?>" style="color:#2563eb; font-weight:600; text-decoration:none;">Open</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($viewData): ?>
    <div class="card">
        <div class="header">
            <h3 style="margin:0; color:#1e293b;">Identity Details</h3>
            <form method="POST"><button name="reset" style="border:none; background:none; color:#dc2626; cursor:pointer; font-weight:600;">Close</button></form>
        </div>

        <div class="data-container">
            <div class="photo-box">
                <?php if (!empty($viewData['photo'])): ?>
                    <img src="data:image/jpeg;base64,<?php echo $viewData['photo']; ?>">
                <?php endif; ?>
            </div>
            <div class="info-box">
                <div class="field-row"><div class="field-label">Surname</div><div class="field-value"><?php echo $viewData['surname']; ?></div></div>
                <div class="field-row"><div class="field-label">First Name</div><div class="field-value"><?php echo $viewData['firstname']; ?></div></div>
                <div class="field-row"><div class="field-label">Middle Name</div><div class="field-value"><?php echo $viewData['middlename'] ?? '-'; ?></div></div>
                <div class="field-row"><div class="field-label">DOB</div><div class="field-value"><?php echo $viewData['birthdate']; ?></div></div>
                <div class="field-row"><div class="field-label">NIN Number</div><div class="field-value"><?php echo $viewData['nin']; ?></div></div>
                <div class="field-row"><div class="field-label">Gender</div><div class="field-value"><?php echo $viewData['gender']; ?></div></div>
                <div class="field-row"><div class="field-label">Phone</div><div class="field-value"><?php echo $viewData['telephoneno']; ?></div></div>
                <div class="field-row"><div class="field-label">Address</div><div class="field-value"><?php echo $viewData['residence_AdressLine1']; ?></div></div>
            </div>
        </div>

        <h3 style="margin-top:30px; margin-bottom:15px; border-top:1px solid #f1f5f9; padding-top:20px; color:#334155;">Download Official Slip</h3>
        
        <form method="POST" class="slip-grid">
            <input type="hidden" name="action" value="print">
            <button type="submit" name="slip_type" value="regular" class="slip-card">
                <i class="far fa-file-alt"></i> <h4>Regular</h4>
                <?php if($paidSlipType == 'regular' || $paidSlipType == 'premium'): ?><span class="free-badge">Download Free</span>
                <?php else: ?><span class="price">₦<?php echo number_format($slipPrices['regular']); ?></span><?php endif; ?>
            </button>
            <button type="submit" name="slip_type" value="standard" class="slip-card">
                <i class="far fa-id-card"></i> <h4>Standard</h4>
                <?php if($paidSlipType == 'standard' || $paidSlipType == 'premium'): ?><span class="free-badge">Download Free</span>
                <?php else: ?><span class="price">₦<?php echo number_format($slipPrices['standard']); ?></span><?php endif; ?>
            </button>
            <button type="submit" name="slip_type" value="premium" class="slip-card">
                <i class="fas fa-certificate"></i> <h4>Premium</h4>
                <?php if($paidSlipType == 'premium'): ?><span class="free-badge">Download Free</span>
                <?php else: ?><span class="price">₦<?php echo number_format($slipPrices['premium']); ?></span><?php endif; ?>
            </button>
        </form>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
