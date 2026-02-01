<?php
session_start();
require_once "db.php";
require_once "config.php";

if (isset($_GET['fetch_plans'])) {
    header('Content-Type: application/json');
    $network = strtoupper(trim($_GET['fetch_plans']));
    $plans = [];
    $sql = "SELECT id, category, plan_name, selling_price FROM vtu_products WHERE service_type = 'data' AND network = ? AND status = 'active' ORDER BY category, selling_price ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $network);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $plans[$row['category']][] = $row;
        }
        $stmt->close();
    }
    echo json_encode($plans);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    header('Content-Type: application/json');
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) { echo json_encode(['status' => 'error', 'message' => 'Authentication error. Please log in again.']); exit; }
    $userId = $_SESSION["id"];

    $network = strtoupper(trim($_POST['network'] ?? ''));
    $phone = trim($_POST['phone_number'] ?? '');
    $plan_id = filter_var($_POST['plan_id'] ?? 0, FILTER_VALIDATE_INT);

    if (empty($network) || empty($phone) || !$plan_id) { echo json_encode(['status' => 'error', 'message' => 'Please fill all fields correctly.']); exit; }
    if (!preg_match('/^0[789][01]\d{8}$/', $phone)) { echo json_encode(['status' => 'error', 'message' => 'Please enter a valid 11-digit phone number.']); exit; }

    $plan_details_stmt = $conn->prepare("SELECT api_code, plan_name, selling_price FROM vtu_products WHERE id = ? AND network = ? AND status = 'active'");
    $plan_details_stmt->bind_param("is", $plan_id, $network);
    $plan_details_stmt->execute();
    $plan_result = $plan_details_stmt->get_result();
    if ($plan_result->num_rows == 0) { echo json_encode(['status' => 'error', 'message' => 'Invalid or unavailable data plan selected.']); exit; }
    $plan = $plan_result->fetch_assoc();
    $cost = $plan['selling_price'];
    $api_code = $plan['api_code'];
    $plan_name = $plan['plan_name'];

    $conn->autocommit(FALSE);

    $stmt_check = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt_check->bind_param("i", $userId);
    $stmt_check->execute();
    $db_user = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    if (!$db_user || $db_user['wallet_balance'] < $cost) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Insufficient wallet balance.']); exit; }

    $newBalance = $db_user['wallet_balance'] - $cost;
    $stmt_wallet = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $stmt_wallet->bind_param("di", $newBalance, $userId);
    if (!$stmt_wallet->execute()) { $conn->rollback(); echo json_encode(['status' => 'error', 'message' => 'Wallet deduction failed.']); exit; }

    $transaction_id = 'MKOLO-VTU-' . strtoupper(uniqid());
    $payload = json_encode([ "product_code" => $api_code, "phone_number" => $phone, "action" => "vend", "user_reference" => $transaction_id, "bypass_network" => "yes" ]);
    $curl = curl_init();
    curl_setopt_array($curl, [ CURLOPT_URL => CHEAPDATASALES_API_URL, CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => 'POST', CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Bearer: ' . CHEAPDATASALES_API_KEY], ]);
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
    $stmt_vtu = $conn->prepare("INSERT INTO vtu_transactions (user_id, transaction_id, service_type, network, phone_number, plan_name, amount_charged, status, api_response, api_transaction_id) VALUES (?, ?, 'data', ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt_vtu->bind_param("isssdssss", $userId, $transaction_id, $network, $phone, $plan_name, $cost, $final_status, $api_response_text, $api_ref);

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
        echo json_encode(['status' => 'error', 'message' => 'Transaction Failed: ' . $api_response_text]);
    }
    exit;
}

$pageTitle = "Buy Data";
include_once 'header.php';
?>

<style>
    .vtu-container { max-width: 550px; margin: 0 auto; }
    .card { background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.07); margin-bottom: 2rem; }
    .page-header { text-align: center; margin-bottom: 2rem; }
    h1 { font-size: 2rem; color: #1e293b; margin: 0; }
    .page-header p { color: #64748b; margin-top: 0.25rem; }
    .card-body { padding: 2rem; }
    .form-group { margin-bottom: 1.5rem; }
    label { display: block; font-weight: 600; margin-bottom: 0.75rem; color: #374151; }
    input[type="tel"] {
        box-sizing: border-box; width: 100%; padding: 0.85rem 1rem;
        border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
    .btn-primary { display: flex; align-items: center; justify-content: center; width: 100%; background-color: #4f46e5; color: white; padding: 0.85rem; border: none; font-weight: 600; font-size: 1rem; border-radius: 8px; cursor: pointer; transition: background-color 0.2s; }
    .btn-primary:disabled { background-color: #9ca3af; cursor: not-allowed; }
    .network-selector { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .network-label { display: flex; flex-direction: column; align-items: center; padding: 1rem; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.2s ease; }
    .network-label img { width: 40px; height: 40px; border-radius: 50%; }
    .network-label span { font-weight: 500; margin-top: 0.5rem; font-size: 0.9rem; }
    input[type="radio"] { display: none; }
    input[type="radio"]:checked + .network-label { border-color: #4f46e5; background-color: #eef2ff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
    .summary { background-color: #f9fafb; padding: 1rem; border-radius: 8px; margin-top: 1.5rem; text-align: center; color: #374151; }
    .summary span { font-weight: 700; color: #111827; }
    .result-message { padding: 1rem; border-radius: 8px; margin-top: 1.5rem; font-weight: 500; text-align: center; display: none; }
    .result-message.success { background-color: #dcfce7; color: #166534; }
    .result-message.error { background-color: #fee2e2; color: #991b1b; }
    #plans-loader { text-align: center; color: #6b7280; padding: 2rem; }
    .accordion-header {
        background-color: #f9fafb; border: 1px solid #e5e7eb; width: 100%; text-align: left;
        padding: 1rem; font-size: 1rem; font-weight: 600; border-radius: 8px;
        cursor: pointer; transition: background-color 0.2s; margin-bottom: 0.5rem;
        display: flex; justify-content: space-between; align-items: center;
    }
    .accordion-header:hover, .accordion-header.active { background-color: #eef2ff; }
    .accordion-header::after { content: '\f078'; font-family: 'Font Awesome 5 Free'; font-weight: 900; transition: transform 0.2s; }
    .accordion-header.active::after { transform: rotate(180deg); }
    .accordion-panel {
        padding: 0; max-height: 0; overflow: hidden;
        transition: max-height 0.3s ease-out, padding 0.3s ease-out;
    }
    .plan-buttons-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.75rem; padding: 1rem 0.5rem;
    }
    .plan-btn {
        padding: 0.75rem 0.5rem; border: 2px solid #e5e7eb; border-radius: 8px;
        background-color: #fff; cursor: pointer; text-align: center;
        transition: all 0.2s ease;
    }
    .plan-btn:hover { border-color: #c7d2fe; }
    .plan-btn.active { border-color: #4f46e5; background-color: #eef2ff; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
    .plan-btn .plan-name { font-weight: 600; font-size: 0.9rem; color: #111827; display: block; }
    .plan-btn .plan-price { font-weight: 500; font-size: 0.85rem; color: #4f46e5; display: block; margin-top: 0.25rem; }
</style>

<div class="vtu-container">
    <div class="page-header">
        <h1>Buy Data</h1>
        <p>Purchase mobile data for any network.</p>
    </div>

    <div class="card">
        <div class="card-body">
            <form id="dataForm" method="POST">
                <input type="hidden" id="plan_id" name="plan_id">

                <div class="form-group">
                    <label>1. Select Network</label>
                    <div class="network-selector">
                        <div><input type="radio" id="mtn" name="network" value="MTN" required><label for="mtn" class="network-label"><img src="img/mtn-logo.png" alt="MTN"><span>MTN</span></label></div>
                        <div><input type="radio" id="glo" name="network" value="GLO" required><label for="glo" class="network-label"><img src="img/glo-logo.png" alt="GLO"><span>Glo</span></label></div>
                        <div><input type="radio" id="airtel" name="network" value="AIRTEL" required><label for="airtel" class="network-label"><img src="img/airtel-logo.png" alt="Airtel"><span>Airtel</span></label></div>
                        <div><input type="radio" id="9mobile" name="network" value="9MOBILE" required><label for="9mobile" class="network-label"><img src="img/9mobile-logo.png" alt="9mobile"><span>9mobile</span></label></div>
                    </div>
                </div>

                <div id="plans-accordion"></div>
                <div id="plans-loader" style="display: none;"><i class="fas fa-spinner fa-spin"></i> Loading plans...</div>

                <div id="phone-container" style="display: none;">
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" placeholder="08012345678" required maxlength="11">
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
    const form = document.getElementById('dataForm');
    const networkRadios = form.querySelectorAll('input[name="network"]');
    const plansAccordionContainer = document.getElementById('plans-accordion');
    const plansLoader = document.getElementById('plans-loader');
    const hiddenPlanIdInput = document.getElementById('plan_id');
    const phoneContainer = document.getElementById('phone-container');
    const summary = document.getElementById('summary');
    const submitBtn = document.getElementById('submitBtn');
    
    const fetchPlans = (network) => {
        plansAccordionContainer.innerHTML = '';
        phoneContainer.style.display = 'none';
        summary.style.display = 'none';
        submitBtn.disabled = true;
        plansLoader.style.display = 'block';

        fetch(`data.php?fetch_plans=${network}`)
            .then(response => response.json())
            .then(data => {
                plansLoader.style.display = 'none';
                if (Object.keys(data).length === 0) {
                    plansAccordionContainer.innerHTML = '<p style="text-align:center; color:#6b7280;">No data plans available for this network.</p>';
                    return;
                }
                
                for (const category in data) {
                    const categoryDiv = document.createElement('div');
                    categoryDiv.innerHTML = `
                        <button type="button" class="accordion-header">${category}</button>
                        <div class="accordion-panel">
                            <div class="plan-buttons-grid">
                                ${data[category].map(plan => `
                                    <button type="button" class="plan-btn" data-id="${plan.id}" data-price="${plan.selling_price}" data-name="${plan.plan_name}">
                                        <span class="plan-name">${plan.plan_name}</span>
                                        <span class="plan-price">₦${plan.selling_price}</span>
                                    </button>
                                `).join('')}
                            </div>
                        </div>
                    `;
                    plansAccordionContainer.appendChild(categoryDiv);
                }

                plansAccordionContainer.querySelectorAll('.accordion-header').forEach(header => {
                    header.addEventListener('click', () => {
                        plansAccordionContainer.querySelectorAll('.accordion-header.active').forEach(activeHeader => {
                            if (activeHeader !== header) {
                                activeHeader.classList.remove('active');
                                activeHeader.nextElementSibling.style.maxHeight = null;
                            }
                        });
                        
                        header.classList.toggle('active');
                        const panel = header.nextElementSibling;
                        if (panel.style.maxHeight) {
                            panel.style.maxHeight = null;
                        } else {
                            panel.style.maxHeight = panel.scrollHeight + "px";
                        }
                    });
                });
                
                plansAccordionContainer.querySelectorAll('.plan-btn').forEach(button => {
                    button.addEventListener('click', () => {
                        plansAccordionContainer.querySelectorAll('.plan-btn.active').forEach(activeBtn => activeBtn.classList.remove('active'));
                        button.classList.add('active');
                        hiddenPlanIdInput.value = button.dataset.id;
                        phoneContainer.style.display = 'block';
                        validateForm();
                    });
                });
            })
            .catch(error => {
                console.error('Error fetching plans:', error);
                plansLoader.innerHTML = 'Could not load plans.';
            });
    };

    networkRadios.forEach(radio => radio.addEventListener('change', () => fetchPlans(radio.value)));

    const validateForm = () => {
        const network = form.querySelector('input[name="network"]:checked');
        const planId = hiddenPlanIdInput.value;
        const phone = document.getElementById('phone_number').value;
        const selectedPlanBtn = plansAccordionContainer.querySelector('.plan-btn.active');

        let isValid = network && planId && phone.match(/^0[789][01]\d{8}$/);
        
        if (isValid && selectedPlanBtn) {
            summary.innerHTML = `You are about to buy <span>${selectedPlanBtn.dataset.name}</span> for <span>${phone}</span> at <span>₦${selectedPlanBtn.dataset.price}</span>.`;
            summary.style.display = 'block';
            submitBtn.disabled = false;
        } else {
            summary.style.display = 'none';
            submitBtn.disabled = true;
        }
    };
    
    document.getElementById('phone_number').addEventListener('input', validateForm);

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

        fetch('data.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                resultBox.textContent = 'Success! Redirecting to receipt...';
                resultBox.className = 'result-message success';
                resultBox.style.display = 'block';
                setTimeout(() => { window.location.href = data.receipt_url; }, 1500);
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
            resultBox.textContent = 'A network error occurred. Please try again.';
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
