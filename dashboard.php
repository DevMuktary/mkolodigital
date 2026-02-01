<?php
$pageTitle = "Dashboard";
include_once 'header.php'; 

$walletBalance = $user['wallet_balance'] ?? 0;

// --- [FIXED] TRANSACTION QUERY ---
// We now select the ACTUAL 'type' column from the database instead of forcing 'credit'
$transactions = [];
$sql = "(SELECT type, amount, description, transaction_date FROM transactions WHERE user_id = ?)
        UNION ALL
        (SELECT 'debit' as type, amount_charged as amount, plan_name as description, created_at as transaction_date FROM vtu_transactions WHERE user_id = ? AND status = 'success')
        ORDER BY transaction_date DESC LIMIT 10";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("ii", $_SESSION["id"], $_SESSION["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
}

// --- SERVICES CONFIG ---
// Titles shortened slightly to fit mobile screens perfectly without wrapping
$services = [
    ['href' => 'nin-verification.php',       'icon' => 'fas fa-id-card',       'title' => 'NIN Verification via NIN',   'color' => '#4f46e5', 'bg' => '#e0e7ff'],
    ['href' => 'nin-phone-verification.php', 'icon' => 'fas fa-phone',         'title' => 'NIN Verification via Phone', 'color' => '#059669', 'bg' => '#d1fae5'],
    ['href' => 'airtime.php',                'icon' => 'fas fa-mobile-alt',    'title' => 'Buy Airtime',      'color' => '#d97706', 'bg' => '#fef3c7'],
    ['href' => 'data.php',                   'icon' => 'fas fa-wifi',          'title' => 'Buy Data',         'color' => '#db2777', 'bg' => '#fce7f3'],
    ['href' => 'bvn-mod.php',                'icon' => 'fas fa-user-edit',     'title' => 'BVN Modifications',         'color' => '#2563eb', 'bg' => '#dbeafe'],
    ['href' => 'bvn-retrieval.php',          'icon' => 'fas fa-search',        'title' => 'Retrieve BVN',     'color' => '#7c3aed', 'bg' => '#ede9fe'],
    ['href' => 'nin-tracking.php',           'icon' => 'fas fa-file-contract', 'title' => 'NIN Tracking',     'color' => '#64748b', 'bg' => '#f1f5f9'],
    ['href' => 'jtb-tin.php',                'icon' => 'fas fa-file-invoice',  'title' => 'Tax ID (TIN)',     'color' => '#0891b2', 'bg' => '#cffafe'],
    ['href' => 'cac-reg.php',                'icon' => 'fas fa-landmark',      'title' => 'CAC Reg',          'color' => '#16a34a', 'bg' => '#dcfce7'],
    ['href' => 'result-check.php',           'icon' => 'fas fa-graduation-cap','title' => 'Exam Pins',        'color' => '#f97316', 'bg' => '#ffedd5'],
];
?>

<style>
    :root {
        --bg-body: #f3f4f6;
        --card-bg: #ffffff;
        --text-primary: #111827;
        --text-secondary: #6b7280;
        --accent: #4f46e5;
    }

    body {
        background-color: var(--bg-body);
        font-family: 'Inter', system-ui, sans-serif;
        margin: 0;
        padding-bottom: 80px; 
    }

    /* FULL WIDTH CONTAINER */
    .dashboard-container {
        width: 100%;
        max-width: 100%;
        padding: 15px;
        box-sizing: border-box;
        overflow-x: hidden;
    }

    /* --- HEADER SECTION --- */
    .balance-card {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
        border-radius: 16px;
        padding: 25px;
        color: white;
        box-shadow: 0 10px 30px -10px rgba(0,0,0,0.3);
        margin-bottom: 25px;
        position: relative;
        overflow: hidden;
    }
    
    .balance-card::after {
        content: ''; position: absolute; right: -20px; bottom: -20px;
        width: 120px; height: 120px; background: rgba(255,255,255,0.05);
        border-radius: 50%;
    }

    .greeting { font-size: 14px; opacity: 0.8; margin-bottom: 5px; }
    .user-name { font-size: 22px; font-weight: 700; margin: 0; }
    
    .balance-row {
        display: flex; justify-content: space-between; align-items: flex-end; margin-top: 25px;
    }
    .balance-label { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7; }
    .balance-amount { font-size: 32px; font-weight: 800; line-height: 1; }
    
    .fund-btn {
        background: var(--accent); color: white; border: none;
        padding: 10px 18px; border-radius: 8px; font-weight: 600; text-decoration: none;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.4); font-size: 14px; white-space: nowrap;
    }

    /* --- SERVICES GRID --- */
    .section-label {
        font-size: 16px; font-weight: 700; color: var(--text-primary);
        margin-bottom: 15px; display: block; padding-left: 5px;
    }

    .services-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* 2 Columns Mobile */
        gap: 12px;
        margin-bottom: 30px;
    }

    @media (min-width: 600px) {
        .services-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (min-width: 900px) {
        .services-grid { grid-template-columns: repeat(5, 1fr); gap: 20px; }
    }

    .service-item {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 15px 10px;
        text-align: center;
        text-decoration: none;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        transition: transform 0.2s;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 100%;
        border: 1px solid transparent;
    }
    .service-item:active { transform: scale(0.98); }

    .icon-box {
        width: 45px; height: 45px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; margin-bottom: 10px;
    }
    .service-title {
        color: var(--text-primary); font-weight: 600; font-size: 13px;
        line-height: 1.2;
    }

    /* --- HISTORY TABLE --- */
    .history-container {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 0;
        box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        overflow: hidden;
    }
    
    .history-header-row {
        padding: 20px; border-bottom: 1px solid #f3f4f6;
        display: flex; justify-content: space-between; align-items: center;
    }

    table { width: 100%; border-collapse: collapse; }
    td { padding: 15px 20px; border-bottom: 1px solid #f9fafb; font-size: 14px; }
    tr:last-child td { border-bottom: none; }

    /* Transaction Colors */
    .amount-credit { color: #16a34a; font-weight: 700; } /* Green */
    .amount-debit { color: #dc2626; font-weight: 700; }  /* Red */
    
    .tx-desc { font-weight: 600; color: #374151; display: block; margin-bottom: 2px; }
    .tx-date { font-size: 12px; color: #9ca3af; }

    .whatsapp-float {
        position: fixed; width: 55px; height: 55px; bottom: 25px; right: 25px;
        background-color: #25d366; color: #FFF; border-radius: 50%; 
        display: flex; align-items: center; justify-content: center;
        font-size: 28px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); z-index: 999;
    }
</style>

<div class="dashboard-container">

    <div class="balance-card">
        <div class="greeting">Welcome,</div>
        <h2 class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
        
        <div class="balance-row">
            <div>
                <div class="balance-label">Total Balance</div>
                <div class="balance-amount">₦<?php echo number_format($walletBalance, 2); ?></div>
            </div>
            <a href="fund-wallet.php" class="fund-btn">
                <i class="fas fa-plus"></i> Add Money
            </a>
        </div>
    </div>

    <span class="section-label">Quick Actions</span>
    <div class="services-grid">
        <?php foreach ($services as $svc): ?>
            <a href="<?php echo $svc['href']; ?>" class="service-item">
                <div class="icon-box" style="background: <?php echo $svc['bg']; ?>; color: <?php echo $svc['color']; ?>;">
                    <i class="<?php echo $svc['icon']; ?>"></i>
                </div>
                <div class="service-title"><?php echo $svc['title']; ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="history-container">
        <div class="history-header-row">
            <span style="font-weight:700; color:#374151;">Transactions</span>
            <a href="wallet_transactions.php" style="color:var(--accent); text-decoration:none; font-size:13px; font-weight:600;">View All</a>
        </div>
        
        <table>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="2" style="text-align:center; padding:30px; color:#9ca3af;">No activity yet.</td></tr>
            <?php else: ?>
                <?php foreach ($transactions as $t): 
                    // [FIX] STRICT COLOR LOGIC
                    // We check if the DB value is explicitly 'credit'. 
                    // Anything else (debit, charge, etc.) is treated as Red.
                    $isCredit = (strtolower(trim($t['type'])) === 'credit');
                    
                    $amountClass = $isCredit ? 'amount-credit' : 'amount-debit';
                    $sign = $isCredit ? '+' : '-';
                ?>
                <tr>
                    <td>
                        <span class="tx-desc"><?php echo htmlspecialchars($t['description']); ?></span>
                        <span class="tx-date"><?php echo date("M d, h:i A", strtotime($t['transaction_date'])); ?></span>
                    </td>
                    <td style="text-align:right;" class="<?php echo $amountClass; ?>">
                        <?php echo $sign; ?>₦<?php echo number_format($t['amount'], 2); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>

</div>

<a href="https://whatsapp.com/channel/0029VbB8Ib1HQbS0UYLUXe2c" class="whatsapp-float" target="_blank">
    <i class="fab fa-whatsapp"></i>
</a>

</body>
</html>
