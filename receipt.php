<?php
$pageTitle = "Transaction Receipt";
include_once 'header.php'; // This includes the session, db connection, and user info

$transaction_id = trim($_GET['tx_id'] ?? '');
$transaction = null;

if (!empty($transaction_id)) {
    // [SECURITY] This query is excellent because it validates both the transaction ID and the user ID.
    $sql = "SELECT * FROM vtu_transactions WHERE transaction_id = ? AND user_id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("si", $transaction_id, $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $transaction = $result->fetch_assoc();
        }
        $stmt->close();
    }
}
?>

<style>
    .receipt-container { max-width: 550px; margin: 0 auto; }
    .card { background-color: #ffffff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.07); }
    .card-body { padding: 2.5rem; text-align: center; }
    .status-icon {
        width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.5rem auto; font-size: 2.5rem; color: #ffffff;
    }
    .status-icon.success { background-color: #16a34a; }
    .status-icon.failed { background-color: #dc2626; }
    .status-title { font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
    .status-subtitle { font-size: 1rem; color: #64748b; margin-bottom: 2.5rem; }
    .details-list { list-style: none; padding: 0; margin: 0; text-align: left; border-top: 1px solid #e5e7eb; }
    .details-list li { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #e5e7eb; }
    .details-list .label { font-weight: 500; color: #64748b; }
    .details-list .value { font-weight: 600; color: #1e293b; }
    .details-list .value.status-success, .value.status-failed { text-transform: uppercase; }
    .details-list .value.status-success { color: #16a34a; }
    .details-list .value.status-failed { color: #dc2626; }
    .action-buttons { margin-top: 2.5rem; display: flex; gap: 1rem; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; flex: 1; }
    .btn-primary { background-color: #4f46e5; color: white; }
    .btn-secondary { background-color: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
    @media print { body { background-color: #fff; } .sidebar, .top-header, .action-buttons, .sidebar-overlay { display: none !important; } .main-wrapper { margin-left: 0 !important; } .main-content { padding: 0 !important; } .receipt-container { max-width: 100%; margin: 0; } .card { box-shadow: none; border: 1px solid #ccc; } }
</style>

<div class="receipt-container">
    <?php if ($transaction): 
        $status = strtolower($transaction['status']);
        $iconClass = ($status === 'success') ? 'success' : 'failed';
        $iconAwesome = ($status === 'success') ? 'fa-check' : 'fa-times';
    ?>
        <div class="card" id="receipt-card">
            <div class="card-body">
                <div class="status-icon <?php echo $iconClass; ?>">
                    <i class="fas <?php echo $iconAwesome; ?>"></i>
                </div>
                <h1 class="status-title">Transaction <?php echo ucfirst($status); ?></h1>
                <p class="status-subtitle"><?php echo htmlspecialchars($transaction['api_response']); ?></p>
                <ul class="details-list">
                    <li><span class="label">Transaction ID</span><span class="value"><?php echo htmlspecialchars($transaction['transaction_id']); ?></span></li>
                    <li><span class="label">Date & Time</span><span class="value"><?php echo date("M j, Y, g:i A", strtotime($transaction['created_at'])); ?></span></li>
                    <li><span class="label">Service</span><span class="value"><?php echo htmlspecialchars($transaction['plan_name']); ?></span></li>
                    <li><span class="label">Phone Number</span><span class="value"><?php echo htmlspecialchars($transaction['phone_number']); ?></span></li>
                    <li><span class="label">Amount Paid</span><span class="value">₦<?php echo number_format($transaction['amount_charged'], 2); ?></span></li>
                    <li><span class="label">Status</span><span class="value status-<?php echo $status; ?>"><?php echo strtoupper($status); ?></span></li>
                </ul>
                <div class="action-buttons">
                    <button id="printBtn" class="btn btn-secondary"><i class="fas fa-print"></i> Print Receipt</button>
                    <a href="airtime.php" class="btn btn-primary">New Transaction</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="status-icon failed"><i class="fas fa-exclamation-triangle"></i></div>
                <h1 class="status-title">Transaction Not Found</h1>
                <p class="status-subtitle">The receipt you are looking for does not exist or you do not have permission to view it.</p>
                <div class="action-buttons"><a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const printButton = document.getElementById('printBtn');
    if (printButton) {
        printButton.addEventListener('click', () => { window.print(); });
    }
});
</script>
