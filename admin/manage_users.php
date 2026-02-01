<?php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once "../db.php";

$message = "";
$message_type = "";

// --- HANDLE ACTIONS (ADD FUNDS, DELETE USER) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $admin_id = $_SESSION["admin_id"];

    // Action: Add funds to a user's wallet
    if (isset($_POST['action']) && $_POST['action'] == 'add_funds') {
        $user_id = $_POST['user_id'];
        $amount = $_POST['amount'];

        if (is_numeric($amount) && $amount > 0) {
            $conn->autocommit(FALSE); // Start transaction for data integrity
            
            // 1. Update user's wallet
            $sql_update = "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("di", $amount, $user_id);
            
            // 2. Log the transaction in funding_history
            $sql_log = "INSERT INTO funding_history (user_id, amount, funded_by_admin_id) VALUES (?, ?, ?)";
            $stmt_log = $conn->prepare($sql_log);
            $stmt_log->bind_param("idi", $user_id, $amount, $admin_id);

            // Execute both queries
            if ($stmt_update->execute() && $stmt_log->execute()) {
                $conn->commit(); // Both successful, commit changes
                $message = "Successfully added ₦" . number_format($amount) . " and logged the transaction.";
                $message_type = "success";
            } else {
                $conn->rollback(); // An error occurred, rollback changes
                $message = "Error: Transaction failed.";
                $message_type = "error";
            }
            $stmt_update->close();
            $stmt_log->close();

        } else {
            $message = "Invalid amount entered.";
            $message_type = "error";
        }
    }

    // Action: Delete a user
    if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
        $user_id_to_delete = $_POST['user_id_to_delete'];
        $sql = "DELETE FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $user_id_to_delete);
            if ($stmt->execute()) {
                $message = "User has been deleted successfully.";
                $message_type = "success";
            } else {
                $message = "Error deleting user.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch all non-admin users
$users = [];
// [MODIFIED] Changed ORDER BY to prioritize users with the highest wallet balance
$sql = "SELECT id, full_name, email, phone, wallet_balance, created_at FROM users WHERE role != 'admin' ORDER BY wallet_balance DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #dc3545; --primary-hover: #c82333; --secondary-color: #6c757d;
            --background-color: #f8f9fa; --card-bg: #ffffff; --text-color: #495057;
            --heading-color: #212529; --border-color: #dee2e6;
            --success-bg: #d1e7dd; --success-text: #0f5132; --error-bg: #f8d7da; --error-text: #842029;
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
        .search-box { 
            width: 100%; max-width: 300px; padding: 0.5rem 1rem; 
            border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; /* Fix for mobile auto-zoom */
        }
        .card-body { padding: 0; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); }
        tbody tr:last-child td { border-bottom: none; }
        .actions { display: flex; gap: 0.5rem; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; font-size: 14px; }
        .btn-success { background-color: #28a745; color: white; }
        .btn-danger { background-color: #dc3545; color: white; }
        .message { padding: 1rem; border-radius: 8px; margin: 0 0 1.5rem 0; text-align: center; font-weight: 500; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; }
        .modal-content { background-color: #fff; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px; }
        .modal-content h3 { margin-top: 0; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input { 
            width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px;
            font-size: 16px; /* Fix for mobile auto-zoom */
        }
        .modal-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem; }
        .btn-close { background-color: var(--secondary-color); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header class="top-header">
            <h3><i class="fas fa-users-cog"></i> Manage Users</h3>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </header>

        <?php if(!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>All Users (<?php echo count($users); ?>)</h3>
                <input type="text" id="searchInput" class="search-box" placeholder="Search by name or email...">
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table id="usersTable">
                        <thead>
                            <tr><th>User Details</th><th>Wallet Balance</th><th>Joined On</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="4" style="text-align:center; padding: 2rem;">No users found.</td></tr>
                            <?php else: foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($user['email']); ?></small>
                                    </td>
                                    <td>₦<?php echo number_format($user['wallet_balance'], 2); ?></td>
                                    <td><?php echo date("d M, Y", strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-success add-funds-btn" data-userid="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['full_name']); ?>">Add Funds</button>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id_to_delete" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="addFundsModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Add Funds</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_funds">
                <input type="hidden" id="modalUserId" name="user_id">
                <div class="form-group">
                    <label for="amount">Amount (₦)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="1" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-close">Cancel</button>
                    <button type="submit" class="btn btn-success">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Modal Logic
            const modal = document.getElementById('addFundsModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalUserIdInput = document.getElementById('modalUserId');
            const addFundsButtons = document.querySelectorAll('.add-funds-btn');
            const closeModalButton = document.querySelector('.btn-close');

            addFundsButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const userId = button.dataset.userid;
                    const userName = button.dataset.username;
                    modalTitle.textContent = `Add Funds for ${userName}`;
                    modalUserIdInput.value = userId;
                    modal.style.display = 'flex';
                });
            });

            const closeTheModal = () => modal.style.display = 'none';
            closeModalButton.addEventListener('click', closeTheModal);
            window.addEventListener('click', (event) => {
                if (event.target == modal) {
                    closeTheModal();
                }
            });
            
            // Search/Filter Logic
            const searchInput = document.getElementById('searchInput');
            const usersTable = document.getElementById('usersTable').getElementsByTagName('tbody')[0];
            const rows = usersTable.getElementsByTagName('tr');

            searchInput.addEventListener('keyup', () => {
                const filter = searchInput.value.toLowerCase();
                for (let i = 0; i < rows.length; i++) {
                    let text = rows[i].textContent || rows[i].innerText;
                    if (text.toLowerCase().indexOf(filter) > -1) {
                        rows[i].style.display = "";
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            });
        });
    </script>
</body>
</html>
