<?php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once "../db.php";

// [REMOVED] The check for is_beta_tester is no longer needed here.

// --- Comprehensive stats for the dashboard ---
$total_users = $conn->query("SELECT COUNT(id) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'];
$total_balance = $conn->query("SELECT SUM(wallet_balance) as total FROM users WHERE role != 'admin'")->fetch_assoc()['total'];
$all_request_tables = ['bvn_requests', 'bvn_retrievals', 'cac_requests', 'tin_requests', 'result_requests', 'jamb_requests', 'nin_requests'];
function build_status_query($tables, $status) {
    $sub_queries = [];
    foreach ($tables as $table) {
        $sub_queries[] = "(SELECT COUNT(id) FROM `{$table}` WHERE status='{$status}')";
    }
    return "SELECT (" . implode(" + ", $sub_queries) . ") as total";
}
$pending_requests = $conn->query(build_status_query($all_request_tables, 'Pending'))->fetch_assoc()['total'];
$processing_requests = $conn->query(build_status_query($all_request_tables, 'Processing'))->fetch_assoc()['total'];
$completed_requests = $conn->query(build_status_query($all_request_tables, 'Completed'))->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #dc3545; --primary-hover: #c82333; --secondary-color: #6c757d;
            --background-color: #f8f9fa; --card-bg: #ffffff; --text-color: #495057;
            --heading-color: #212529; --border-color: #dee2e6;
            --pending-color: #ffc107; --processing-color: #0d6efd; --completed-color: #198754;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container { width: 100%; max-width: 1300px; margin: 0 auto; padding: 1.5rem; }
        .top-header { display: flex; justify-content: space-between; align-items: center; background-color: var(--card-bg); padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .top-header h3 { margin: 0; color: var(--heading-color); }
        .top-header a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .stat-card { background-color: var(--card-bg); border-radius: 12px; padding: 1.5rem; display: flex; align-items: center; gap: 1.5rem; border: 1px solid var(--border-color); }
        .stat-card .icon { font-size: 2rem; }
        .stat-card .icon.users { color: #6f42c1; }
        .stat-card .icon.funds { color: #fd7e14; }
        .stat-card .icon.pending { color: var(--pending-color); }
        .stat-card .icon.processing { color: var(--processing-color); }
        .stat-card .icon.completed { color: var(--completed-color); }
        .stat-card .info h3 { margin: 0; font-size: 1rem; color: var(--secondary-color); font-weight: 500; text-transform: uppercase; }
        .stat-card .info p { margin: 0.25rem 0 0 0; font-size: 2rem; font-weight: 700; color: var(--heading-color); }
        .section-header { font-size: 1.5rem; font-weight: 600; color: var(--heading-color); margin-bottom: 1.5rem; border-bottom: 2px solid var(--border-color); padding-bottom: 0.5rem; }
        .management-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; }
        .mgmt-card { display: block; background-color: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 1.5rem; text-decoration: none; color: var(--heading-color); transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .mgmt-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); border-color: var(--primary-color); }
        .mgmt-card .icon { font-size: 1.8rem; color: var(--primary-color); margin-bottom: 1rem; }
        .mgmt-card h3 { margin: 0 0 0.5rem 0; font-size: 1.2rem; }
        .mgmt-card p { margin: 0; color: var(--text-color); font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <header class="top-header">
            <h3><i class="fas fa-shield-alt"></i> Admin Panel</h3>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </header>
        <main>
            <div class="stats-grid">
                <div class="stat-card"><div class="icon users"><i class="fas fa-users"></i></div><div class="info"><h3>Total Users</h3><p><?php echo $total_users; ?></p></div></div>
                <div class="stat-card"><div class="icon funds"><i class="fas fa-wallet"></i></div><div class="info"><h3>User Funds</h3><p>₦<?php echo number_format($total_balance ?? 0, 2); ?></p></div></div>
                <div class="stat-card"><div class="icon pending"><i class="fas fa-clock"></i></div><div class="info"><h3>Pending</h3><p><?php echo $pending_requests; ?></p></div></div>
                <div class="stat-card"><div class="icon processing"><i class="fas fa-spinner fa-spin"></i></div><div class="info"><h3>Processing</h3><p><?php echo $processing_requests; ?></p></div></div>
                <div class="stat-card"><div class="icon completed"><i class="fas fa-check-circle"></i></div><div class="info"><h3>Completed</h3><p><?php echo $completed_requests; ?></p></div></div>
            </div>
            <h2 class="section-header">Management Sections</h2>
            <div class="management-grid">
                <a href="manage_users.php" class="mgmt-card"><div class="icon"><i class="fas fa-users-cog"></i></div><h3>Manage Users</h3><p>View users, add funds, and manage accounts.</p></a>
                <a href="manage_prices.php" class="mgmt-card"><div class="icon"><i class="fas fa-tags"></i></div><h3>Manage Prices</h3><p>Update the prices for all services.</p></a>
                <a href="user_funding_history.php" class="mgmt-card"><div class="icon"><i class="fas fa-history"></i></div><h3>Funding History</h3><p>View a log of all wallet funding transactions.</p></a>
                <a href="manage_bvn_mod.php" class="mgmt-card"><div class="icon"><i class="fas fa-user-edit"></i></div><h3>BVN Modifications</h3><p>Process submitted BVN modification requests.</p></a>
                <a href="manage_bvn_retrieval.php" class="mgmt-card"><div class="icon"><i class="fas fa-search"></i></div><h3>BVN Retrievals</h3><p>Process submitted BVN retrieval requests.</p></a>
                <a href="manage_nin_tracking.php" class="mgmt-card"><div class="icon"><i class="fas fa-fingerprint"></i></div><h3>NIN Retrievals</h3><p>Process NIN requests from tracking IDs.</p></a>
                <a href="manage_cac.php" class="mgmt-card"><div class="icon"><i class="fas fa-landmark"></i></div><h3>CAC Registrations</h3><p>Process submitted CAC registration requests.</p></a>
                <a href="manage_tin.php" class="mgmt-card"><div class="icon"><i class="fas fa-file-invoice-dollar"></i></div><h3>JTB-TIN Requests</h3><p>Process TIN requests and upload certificates.</p></a>
                <a href="manage_results.php" class="mgmt-card"><div class="icon"><i class="fas fa-graduation-cap"></i></div><h3>Result Checkers</h3><p>Process result checker and PIN purchase requests.</p></a>
                <a href="manage_jamb.php" class="mgmt-card"><div class="icon"><i class="fas fa-university"></i></div><h3>JAMB Services</h3><p>Process all submitted JAMB service requests.</p></a>
                
                <a href="vtu_settings.php" class="mgmt-card">
                    <div class="icon"><i class="fas fa-wifi"></i></div>
                    <h3>VTU Settings</h3>
                    <p>Manage data and airtime prices and availability.</p>
                </a>

            </div>
        </main>
    </div>
</body>
</html>
