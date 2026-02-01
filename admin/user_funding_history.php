<?php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once "../db.php";

// Fetch all funding history records, joining with the users table twice 
// once for the user who was funded, and once for the admin who funded them.
$history = [];
$sql = "SELECT 
            fh.id, 
            fh.amount, 
            fh.funding_date, 
            u.full_name AS user_name, 
            u.email AS user_email,
            a.full_name AS admin_name
        FROM funding_history fh
        JOIN users u ON fh.user_id = u.id
        JOIN users a ON fh.funded_by_admin_id = a.id
        ORDER BY fh.funding_date DESC";

$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Funding History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #dc3545; --primary-hover: #c82333; --secondary-color: #6c757d;
            --background-color: #f8f9fa; --card-bg: #ffffff; --text-color: #495057;
            --heading-color: #212529; --border-color: #dee2e6;
            --success-bg: #d1e7dd; --success-text: #0f5132;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 1.5rem; }
        .top-header { display: flex; justify-content: space-between; align-items: center; background-color: var(--card-bg); padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .top-header h3 { margin: 0; color: var(--heading-color); }
        .top-header a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; }
        .card-header h3 { margin: 0; font-size: 1.25rem; }
        .search-box { width: 100%; max-width: 300px; padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; }
        .card-body { padding: 0; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        tbody tr:last-child td { border-bottom: none; }
        .amount { color: var(--success-text); font-weight: 600; font-size: 1.1rem; }
    </style>
</head>
<body>
    <div class="container">
        <header class="top-header">
            <h3><i class="fas fa-history"></i> User Funding History</h3>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </header>

        <div class="card">
            <div class="card-header">
                <h3>All Transactions (<?php echo count($history); ?>)</h3>
                <input type="text" id="searchInput" class="search-box" placeholder="Search by user or admin name...">
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table id="historyTable">
                        <thead>
                            <tr><th>Date</th><th>User Funded</th><th>Amount</th><th>Funded By (Admin)</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="4" style="text-align:center; padding: 2rem;">No funding history found.</td></tr>
                            <?php else: foreach ($history as $item): ?>
                                <tr>
                                    <td><?php echo date("d M Y, g:ia", strtotime($item['funding_date'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($item['user_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($item['user_email']); ?></small>
                                    </td>
                                    <td class="amount">+ ₦<?php echo number_format($item['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($item['admin_name']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.getElementById('historyTable').getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                let text = rows[i].textContent || rows[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }
            }
        });
    </script>
</body>
</html>
