<?php
session_start();
require_once "db.php";
require_once "config.php";

if (isset($_GET['fetch_airtime_types'])) {
    header('Content-Type: application/json');
    $network = strtoupper(trim($_GET['fetch_airtime_types']));
    $types = [];
    $sql = "SELECT api_code, plan_name FROM vtu_products WHERE service_type = 'airtime' AND network = ? AND status = 'active'";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $network);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $types[] = $row;
        }
        $stmt->close();
    }
    echo json_encode($types);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        echo json_encode(['status' => 'error', 'message' => 'Authentication error. Please log in again.']);
        exit;
    }
    $userId = $_SESSION["id"];

    $api_code = trim($_POST['api_code'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);

    if (empty($api_code) || empty($phone) || !$amount || $amount <= 0) { echo json_encode(['status' => 'error', 'message' => 'Please fill all fields correctly.']); exit; }
    if (!preg_match('/^0[789][01]\d{8}$/', $phone)) { echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 11-digit phone number.']); exit; }

    $plan_stmt = $conn->prepare("SELECT plan_name, network FROM vtu_products WHERE api_code = ? AND status = 'active'");
    $plan_stmt->bind_param("s", $api_code);
    $plan_stmt->execute();
    $plan_result = $plan_stmt->get_result();
    if ($plan_result->num_rows == 0) { echo json_encode(['status' => 'error', 'message' => 'This airtime type is not available.']); exit; }
    $plan_data = $plan_result->fetch_assoc();
    $network_name = $plan_data['network'];

    $conn->autocommit(FALSE);
    $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $userId);
    $stmt_check->execute();
    $db_user = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    if (!$db_user || $db_user['wallet_balance'] < $amount) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance.']); exit; }

    $newBalance = $db_user['wallet_balance'] - $amount;
    $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt_wallet->bind_param("di", $newBalance, $userId);
    if (!$stmt_wallet->execute()) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Wallet deduction failed.']); exit; }

    $transaction_id = 'MKOLO-VTU-' . strtoupper(uniqid());
    $payload = json_encode([ "product_code" => $api_code, "phone_number" => $phone, "amount" => (string)$amount, "action" => "vend", "user_reference" => $transaction_id, "bypass_network" => "yes" ]);
    $curl = curl_init();
    curl_setopt_array($curl, [ CURLOPT_URL => CHEAPDATASALES_API_URL, CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $payload, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Bearer: ' . CHEAPDATASALES_API_KEY] ]);
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $data = json_decode($response, true);
    $final_status = 'failed';
    $api_response_text = 'An unknown API error occurred.';

    if ($httpCode == 200 && $data) {
        $api_response_text = $data['server_message'] ?? 'Transaction status unclear.';
        if (isset($data['status']) && $data['status'] === true) {
            $final_status = 'success';
        }
    } else {
        $api_message_from_body = $data['server_message'] ?? 'The API returned a connection error.';
        $api_response_text = "API Error (Code: {$httpCode}) - {$api_message_from_body}";
    }
    
    $api_ref = $data['data']['recharge_id'] ?? null;
    
    $plan_name_for_db = $plan_data['plan_name'] . " (₦" . number_format($amount, 2) . ")";

    $stmt_vtu = $conn->prepare("INSERT INTO vtu_transactions (user_id, transaction_id, service_type, network, phone_number, plan_name, amount_charged, status, api_response, api_transaction_id) VALUES (?, ?, 'airtime', ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt_vtu->bind_param("issssdsss", $userId, $transaction_id, $network_name, $phone, $plan_name_for_db, $amount, $final_status, $api_response_text, $api_ref);
    
    if (!$stmt_vtu->execute()) {
        $db_error = $stmt_vtu->error;
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'CRITICAL: Failed to save transaction record. DB Error: ' . $db_error]);
        exit;
    }
    $stmt_vtu->close();

    if ($final_status === 'success') {
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => $api_response_text, 'receipt_url' => 'receipt.php?tx_id=' . $transaction_id]);
    } else {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $api_response_text]);
    }
    exit;
}

$pageTitle = "Buy Airtime";
include_once 'header.php';
?>

<style>
    .vtu-container { max-width: 600px; margin: 0 auto; }
    .card { background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.07); margin-bottom: 2rem; }
    .page-header { text-align: center; margin-bottom: 2rem; }
    h1 { font-size: 2rem; color: #1e293b; margin: 0; }
    .page-header p { color: #64748b; margin-top: 0.25rem; }
    .card-body { padding: 2rem; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; font-weight: 600; margin-bottom: 0.75rem; color: #374151; }
    input[type="tel"], input[type="number"] { box-sizing: border-box; width: 100%; padding: 0.85rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
    input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
    .btn-primary { display: flex; align-items: center; justify-content: center; width: 100%; background-color: #4f46e5; color: white; padding: 0.85rem; border: none; font-weight: 600; font-size: 1rem; border-radius: 8px; cursor: pointer; transition: background-color 0.2s; }
    .btn-primary:disabled { background-color: #9ca3af; cursor: not-allowed; }
    .network-selector { display: grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
    .network-label { display: flex; flex-direction: column; align-items: center; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; }
    .network-label img { width: 40px; height: 40px; border-radius: 50%; }
    .network-label span { font-weight: 500; margin-top: 0.5rem; font-size: 0.85rem; text-align: center; }
    input[type="radio"] { display: none; }
    input[type="radio"]:checked + .network-label { border-color: #4f46e5; background-color: #eef2ff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
    .quick-amounts { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem; }
    .quick-amount-btn { padding: 0.6rem; font-size: 0.9rem; font-weight: 600; color: #4f46e5; background-color: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; cursor: pointer; transition: background-color 0.2s; }
    .quick-amount-btn:hover { background-color: #c7d2fe; }
    .summary { background-color: #f9fafb; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; text-align: center; color: #374151; }
    .summary span { font-weight: 700; color: #111827; }
    .result-message { padding: 1rem; border-radius: 8px; margin-top: 1.5rem; font-weight: 500; text-align: center; display: none; }
    .result-message.success { background-color: #dcfce7; color: #166534; }
    .result-message.error { background-color: #fee2e2; color: #991b1b; }
    .airtime-type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .type-btn { padding: 1rem; font-size: 1rem; font-weight: 600; color: #374151; background-color: #f3f4f6; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; transition: all 0.2s ease; }
    .type-btn:hover { border-color: #c7d2fe; }
    .type-btn.active { border-color: #4f46e5; background-color: #eef2ff; color: #4f46e5; }
</style>

<div class="vtu-container">
    <div class="page-header">
        <h1>Buy Airtime</h1>
        <p>Top up any mobile number instantly.</p>
    </div>
    <div class="card">
        <div class="card-body">
            <form id="airtimeForm" method="POST" novalidate>
                <input type="hidden" id="api_code" name="api_code">
                <div class="form-group">
                    <label>1. Select Network</label>
                    <div class="network-selector">
                        <div><input type="radio" id="mtn" name="network" value="MTN" required><label for="mtn" class="network-label"><img src="img/mtn-logo.png" alt="MTN"><span>MTN</span></label></div>
                        <div><input type="radio" id="glo" name="network" value="GLO" required><label for="glo" class="network-label"><img src="img/glo-logo.png" alt="GLO"><span>Glo</span></label></div>
                        <div><input type="radio" id="airtel" name="network" value="AIRTEL" required><label for="airtel" class="network-label"><img src="img/airtel-logo.png" alt="Airtel"><span>Airtel</span></label></div>
                        <div><input type="radio" id="9mobile" name="network" value="9MOBILE" required><label for="9mobile" class="network-label"><img src="img/9mobile-logo.png" alt="9mobile"><span>9mobile</span></label></div>
                    </div>
                </div>
                <div id="airtime-types-container" class="form-group" style="display: none;"></div>
                <div id="loader" style="display: none; text-align: center; color: #6b7280; padding: 1rem;"><i class="fas fa-spinner fa-spin"></i></div>
                <div id="details-container" style="display: none;">
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" placeholder="08012345678" required maxlength="11" inputmode="numeric" pattern="0[789][01]\d{8}">
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount</label>
                        <div class="quick-amounts">
                            <button type="button" class="quick-amount-btn" data-value="100">₦100</button>
                            <button type="button" class="quick-amount-btn" data-value="200">₦200</button>
                            <button type="button" class="quick-amount-btn" data-value="500">₦500</button>
                            <button type="button" class="quick-amount-btn" data-value="1000">₦1000</button>
                            <button type="button" class="quick-amount-btn" data-value="2000">₦2000</button>
                        </div>
                        <input type="number" id="amount" name="amount" placeholder="Or enter a custom amount" required min="50" inputmode="numeric">
                    </div>
                </div>
                <div id="summary" class="summary" style="display: none;"></div>
                <button type="submit" id="submitBtn" class="btn-primary" disabled>
                    <span id="btnText">Buy Now</span>
                    <i id="spinner" class="fas fa-spinner fa-spin" style="display: none;"></i>
                </button>
            </form>
            <div id="resultBox" class="result-message"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('airtimeForm');
    const networkRadios = form.querySelectorAll('input[name="network"]');
    const typesContainer = document.getElementById('airtime-types-container');
    const detailsContainer = document.getElementById('details-container');
    const hiddenApiCodeInput = document.getElementById('api_code');
    const loader = document.getElementById('loader');
    const summary = document.getElementById('summary');
    const submitBtn = document.getElementById('submitBtn');
    const amountInput = document.getElementById('amount');
    const phoneInput = document.getElementById('phone_number');
    
    const detectNetwork = (number) => {
        const prefixes = {
            'MTN': ['0803', '0806', '0703', '0706', '0810', '0813', '0814', '0816', '0903', '0906', '0913', '0916', '07025', '07026', '0704'],
            'GLO': ['0805', '0807', '0705', '0811', '0815', '0905', '0915'],
            'AIRTEL': ['0802', '0808', '0701', '0708', '0812', '0901', '0902', '0904', '0907', '0912'],
            '9MOBILE': ['0809', '0817', '0818', '0908', '0909']
        };
        const prefix4 = number.substring(0, 4);
        const prefix5 = number.substring(0, 5);
        for (const network in prefixes) {
            if (prefixes[network].includes(prefix4) || prefixes[network].includes(prefix5)) {
                return network;
            }
        }
        return null;
    };

    phoneInput.addEventListener('input', () => {
        if (phoneInput.value.length >= 4) {
            const detectedNetwork = detectNetwork(phoneInput.value);
            if (detectedNetwork) {
                const radio = document.getElementById(detectedNetwork.toLowerCase());
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                }
            }
        }
    });

    const fetchAirtimeTypes = async (network) => {
        typesContainer.style.display = 'none';
        detailsContainer.style.display = 'none';
        summary.style.display = 'none';
        submitBtn.disabled = true;
        hiddenApiCodeInput.value = '';
        typesContainer.innerHTML = '';
        loader.style.display = 'block';
        try {
            const response = await fetch(`airtime.php?fetch_airtime_types=${network}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            
            loader.style.display = 'none';
            if (data.length === 1) {
                hiddenApiCodeInput.value = data[0].api_code;
                detailsContainer.style.display = 'block';
            } else if (data.length > 1) {
                let buttonsHTML = '<label>2. Select Airtime Type</label><div class="airtime-type-selector">';
                data.forEach(type => {
                    buttonsHTML += `<button type="button" class="type-btn" data-code="${type.api_code}">${type.plan_name}</button>`;
                });
                buttonsHTML += '</div>';
                typesContainer.innerHTML = buttonsHTML;
                typesContainer.style.display = 'block';
            } else {
                typesContainer.innerHTML = '<p style="text-align:center; color: #991b1b;">No airtime types available for this network.</p>';
                typesContainer.style.display = 'block';
            }
        } catch (error) {
            console.error('Fetch Airtime Types Error:', error);
            loader.style.display = 'none';
            typesContainer.innerHTML = '<p style="text-align:center; color: #991b1b;">Could not load airtime types. Please check your connection and try again.</p>';
            typesContainer.style.display = 'block';
        }
    };

    networkRadios.forEach(radio => radio.addEventListener('change', () => fetchAirtimeTypes(radio.value)));

    typesContainer.addEventListener('click', (e) => {
        if (e.target && e.target.classList.contains('type-btn')) {
            typesContainer.querySelectorAll('.type-btn.active').forEach(btn => btn.classList.remove('active'));
            e.target.classList.add('active');
            hiddenApiCodeInput.value = e.target.dataset.code;
            detailsContainer.style.display = 'block';
            validateForm();
        }
    });

    const validateForm = () => {
        const network = form.querySelector('input[name="network"]:checked');
        const apiCode = hiddenApiCodeInput.value;
        const phone = phoneInput.value;
        const amount = amountInput.value;
        let isValid = network && apiCode && phone.match(/^0[789][01]\d{8}$/) && amount >= 50;
        
        if (isValid) {
            summary.innerHTML = `You are about to send <span>₦${amount}</span> airtime to <span>${phone}</span>.`;
            summary.style.display = 'block';
            submitBtn.disabled = false;
        } else {
            summary.style.display = 'none';
            submitBtn.disabled = true;
        }
    };
    
    form.addEventListener('input', validateForm);

    document.querySelectorAll('.quick-amount-btn').forEach(button => {
        button.addEventListener('click', () => {
            amountInput.value = button.dataset.value;
            amountInput.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    form.addEventListener('submit', function(event) {
        event.preventDefault();
        const btnText = document.getElementById('btnText');
        const spinner = document.getElementById('spinner');
        const resultBox = document.getElementById('resultBox');
        
        submitBtn.disabled = true;
        btnText.textContent = 'Processing...';
        spinner.style.display = 'inline-block';
        resultBox.style.display = 'none';

        const formData = new FormData(form);

        fetch('airtime.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                resultBox.textContent = data.message || 'Success! Redirecting to receipt...';
                resultBox.className = 'result-message success';
                resultBox.style.display = 'block';
                form.reset();
                setTimeout(() => { window.location.href = data.receipt_url; }, 2000);
            } else {
                resultBox.textContent = data.message || 'An unknown error occurred.';
                resultBox.className = 'result-message error';
                resultBox.style.display = 'block';
                submitBtn.disabled = false;
                btnText.textContent = 'Buy Now';
                spinner.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            resultBox.textContent = 'An unexpected error occurred. Please try again.';
            resultBox.className = 'result-message error';
            resultBox.style.display = 'block';
            submitBtn.disabled = false;
            btnText.textContent = 'Buy Now';
            spinner.style.display = 'none';
        });
    });
});
</script>

</body>
</html>
