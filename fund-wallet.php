<?php
session_start();
require_once "db.php";
require_once "config.php"; // [ADDED] API config is now needed in this file

// [MOVED & MODIFIED] All the logic from generate-accounts.php is now here.
// This block will ONLY run when the "Generate" button is clicked (i.e., when the page receives a POST request).
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION["id"];

    // Fetch the user's details to make the API call
    $user_data = null;
    if ($stmt = $conn->prepare("SELECT full_name, email, phone FROM users WHERE id = ?")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
    }

    if ($user_data) {
        // --- CREATE THE VIRTUAL ACCOUNTS VIA API ---
        $api_url = 'https://api.paymentpoint.co/api/v1/createVirtualAccount';
        $request_body = json_encode([
            'email' => $user_data['email'],
            'name' => $user_data['full_name'],
            'phoneNumber' => $user_data['phone'],
            'bankCode' => ['20946', '20897'], // Palmpay and OPAY codes
            'businessId' => PAYMENTPOINT_BUSINESS_ID
        ]);

        $headers = [
            'Authorization: Bearer ' . PAYMENTPOINT_SECRET_KEY,
            'api-key: ' . PAYMENTPOINT_API_KEY,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch); // Check for cURL errors
        curl_close($ch);
        $api_data = json_decode($response, true);

        // --- [IMPROVED] Check API response and save account numbers ---
        if ($api_data && isset($api_data['status']) && $api_data['status'] === 'success' && !empty($api_data['bankAccounts'])) {
            $palmpay_acc_num = null; $palmpay_acc_name = null;
            $opay_acc_num = null; $opay_acc_name = null;

            foreach ($api_data['bankAccounts'] as $account) {
                if ($account['bankCode'] === '20946') { $palmpay_acc_num = $account['accountNumber']; $palmpay_acc_name = $account['accountName']; } 
                elseif ($account['bankCode'] === '20897') { $opay_acc_num = $account['accountNumber']; $opay_acc_name = $account['accountName']; }
            }

            $update_stmt = $conn->prepare("UPDATE users SET palmpay_account_number = ?, palmpay_account_name = ?, opay_account_number = ?, opay_account_name = ? WHERE id = ?");
            $update_stmt->bind_param("ssssi", $palmpay_acc_num, $palmpay_acc_name, $opay_acc_num, $opay_acc_name, $user_id);
            if ($update_stmt->execute()) {
                 $_SESSION['message'] = "Your virtual accounts have been generated successfully!";
                 $_SESSION['message_type'] = "success";
            }
            $update_stmt->close();
        } else {
            // [ADDED] This is the crucial error handling part
            $errorMessage = "Failed to generate accounts. ";
            if ($curl_error) {
                $errorMessage .= "Connection Error: " . $curl_error;
            } elseif (isset($api_data['message'])) {
                $errorMessage .= "API Error: " . $api_data['message'];
            } else {
                $errorMessage .= "An unknown error occurred. Please contact support.";
            }
            $_SESSION['message'] = $errorMessage;
            $_SESSION['message_type'] = "error";
        }
    }

    // Redirect back to this same page to show the result and prevent form resubmission
    header("Location: fund-wallet.php");
    exit;
}


// --- The rest of the page loads normally after the POST logic is done ---

// Fetch the user's details for display
$user_id = $_SESSION["id"];
$user_accounts = null;
if ($stmt = $conn->prepare("SELECT palmpay_account_number, palmpay_account_name, opay_account_number, opay_account_name FROM users WHERE id = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_accounts = $result->fetch_assoc();
    $stmt->close();
}
$conn->close();

// Get (and clear) messages from the session
$message = $_SESSION['message'] ?? null;
$message_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fund Your Wallet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd; --primary-hover: #0b5ed7; --secondary-color: #64748b;
            --background-color: #f1f5f9; --card-bg: #ffffff; --text-color: #334155;
            --heading-color: #1e293b; --border-color: #e2e8f0;
            --success-bg: #dcfce7; --success-text: #166534;
            --error-bg: #fee2e2; --error-text: #991b1b;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container { width: 100%; max-width: 900px; margin: 0 auto; padding: 1rem; }
        .page-header { text-align: center; margin-bottom: 2rem; margin-top: 1rem;}
        .page-header h1 { font-size: 2rem; color: var(--heading-color); margin: 0; }
        .page-header p { color: var(--secondary-color); margin-top: 0.25rem; max-width: 600px; margin-left:auto; margin-right:auto; }
        .back-link { display: inline-block; margin-top: 1rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; text-decoration: none; cursor: pointer; }
        .btn-primary { background-color: var(--primary-color); color: white; transition: background-color 0.2s; }
        .btn-primary:hover { background-color: var(--primary-hover); }
        .btn-secondary { background-color: var(--card-bg); color: var(--secondary-color); border: 1px solid var(--border-color); }
        .accounts-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        .account-card { background-color: var(--card-bg); border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 2rem; border: 1px solid var(--border-color); }
        .bank-header { display: flex; align-items: center; gap: 1rem; padding-bottom: 1rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .bank-header img { max-height: 30px; }
        .bank-header h3 { margin: 0; font-size: 1.25rem; }
        .detail-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .detail-item .label { font-weight: 500; color: var(--secondary-color); }
        .detail-item .value { font-weight: 600; color: var(--heading-color); display: flex; align-items: center; gap: 0.5rem; }
        .copy-btn { background: none; border: none; color: var(--primary-color); cursor: pointer; font-size: 1rem; }
        .notice { margin-top: 2rem; padding: 1rem; background-color: #eef2ff; color: #3730a3; border-radius: 8px; text-align: center; font-size: 0.9rem; }
        
        /* [ADDED] Styles for messages */
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); border: 1px solid var(--error-text); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>Fund Your Wallet</h1>
            <?php if ($user_accounts && (!empty($user_accounts['opay_account_number']) || !empty($user_accounts['palmpay_account_number']))): ?>
                <p> Make a transfer to any of your unique virtual accounts below. Your wallet will be credited automatically.</p>
            <?php else: ?>
                <p>Click the button below to generate your personal virtual account numbers for easy wallet funding.</p>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
        
        <?php if(!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($user_accounts && (!empty($user_accounts['opay_account_number']) || !empty($user_accounts['palmpay_account_number']))): ?>
            <div class="accounts-grid">
                <?php if (!empty($user_accounts['opay_account_number'])): ?>
                <div class="account-card">
                    <div class="bank-header"><img src="img/opay-logo.png" alt="OPAY Logo"><h3>OPAY</h3></div>
                    <div class="detail-item"><span class="label">Account Name</span><span class="value"><?php echo htmlspecialchars($user_accounts['opay_account_name']); ?></span></div>
                    <div class="detail-item"><span class="label">Account Number</span><span class="value"><span id="opay-acc-num"><?php echo htmlspecialchars($user_accounts['opay_account_number']); ?></span><button class="copy-btn" data-clipboard-target="#opay-acc-num"><i class="far fa-copy"></i></button></span></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($user_accounts['palmpay_account_number'])): ?>
                <div class="account-card">
                    <div class="bank-header"><img src="img/palmpay-logo.png" alt="Palmpay Logo"><h3>PALMPAY</h3></div>
                    <div class="detail-item"><span class="label">Account Name</span><span class="value"><?php echo htmlspecialchars($user_accounts['palmpay_account_name']); ?></span></div>
                    <div class="detail-item"><span class="label">Account Number</span><span class="value"><span id="palmpay-acc-num"><?php echo htmlspecialchars($user_accounts['palmpay_account_number']); ?></span><button class="copy-btn" data-clipboard-target="#palmpay-acc-num"><i class="far fa-copy"></i></button></span></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="notice">
                <i class="fas fa-info-circle"></i> Note: Money deposited cannot be withdrawn to your bank account, can only be used for another services.
            </div>
        <?php else: ?>
            <div class="account-card" style="text-align: center; max-width: 500px; margin: 2rem auto;">
                <i class="fas fa-university" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                <h3>Generate Your Personal Accounts</h3>
                <p style="color: var(--secondary-color); margin-bottom: 2rem;">Click the button to instantly create your dedicated OPay and Palmpay account numbers for automatic wallet funding.</p>
                <form action="" method="POST">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">
                        <i class="fas fa-cogs"></i> Generate My Virtual Accounts
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const copyButtons = document.querySelectorAll('.copy-btn');
            copyButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const target = document.querySelector(button.dataset.clipboardTarget);
                    if (target) {
                        navigator.clipboard.writeText(target.textContent).then(() => {
                            const originalIcon = button.innerHTML;
                            button.innerHTML = '<i class="fas fa-check" style="color: green;"></i>';
                            setTimeout(() => { button.innerHTML = originalIcon; }, 2000);
                        }).catch(err => { console.error('Failed to copy text: ', err); });
                    }
                });
            });
        });
    </script>
</body>
</html>
