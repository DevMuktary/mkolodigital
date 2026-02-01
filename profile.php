<?php
$pageTitle = "My Profile";
include_once 'header.php'; 

$message = "";
$message_type = "";

// --- HANDLE PASSWORD CHANGE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (strlen($new_password) < 6) {
        $_SESSION['message'] = "New password must be at least 6 characters long.";
        $_SESSION['message_type'] = "error";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['message'] = "New passwords do not match.";
        $_SESSION['message_type'] = "error";
    } else {
        $sql = "SELECT password FROM users WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("i", $_SESSION['id']);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                $stmt->bind_result($hashed_password);
                $stmt->fetch();
                if (password_verify($current_password, $hashed_password)) {
                    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $sql_update = "UPDATE users SET password = ? WHERE id = ?";
                    if ($stmt_update = $conn->prepare($sql_update)) {
                        $stmt_update->bind_param("si", $new_hashed_password, $_SESSION['id']);
                        if ($stmt_update->execute()) {
                            $_SESSION['message'] = "Password changed successfully!";
                            $_SESSION['message_type'] = "success";
                        } else {
                            $_SESSION['message'] = "Error changing password.";
                            $_SESSION['message_type'] = "error";
                        }
                        $stmt_update->close();
                    }
                } else {
                    $_SESSION['message'] = "Incorrect current password.";
                    $_SESSION['message_type'] = "error";
                }
            }
            $stmt->close();
        }
    }
    header("location: profile.php");
    exit;
}

// Check for messages from session (flash messages)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

?>

<style>
    :root {
        --primary-color: #4f46e5; --primary-hover: #4338ca; --text-dark: #111827;
        --text-medium: #374151; --text-light: #6b7280; --border-color: #e2e8f0;
        --card-bg: #ffffff; --success-bg: #dcfce7; --success-text: #166534;
        --error-bg: #fee2e2; --error-text: #991b1b;
    }
    .main-content-wrapper { max-width: 1100px; margin: 0 auto; padding: 1.5rem; }
    .page-header { text-align: center; margin-bottom: 2rem; }
    .page-header-title h1 { font-size: 2rem; color: var(--text-dark); margin: 0; }
    .page-header-title p { color: var(--text-light); margin-top: 0.25rem; }
    .card { background-color: var(--card-bg); border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border: 1px solid var(--border-color); margin-bottom: 2rem; }
    .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); }
    .card-header h3 { margin: 0; font-size: 1.25rem; }
    .card-body { padding: 1.5rem; }
    .info-list p { margin: 0 0 1.25rem 0; font-size: 1rem; color: var(--text-medium); }
    .info-list p:last-child { margin-bottom: 0; }
    .info-list p strong { font-weight: 500; color: var(--text-dark); display: block; font-size: 0.9rem; margin-bottom: 0.25rem; color: var(--text-light); }
    
    /* [FIX] Allows long text like emails to break and wrap nicely */
    .info-list p span {
        overflow-wrap: break-word;
        word-break: break-word;
    }
    
    .card-footer { padding: 1rem 1.5rem; background-color: #f9fafb; border-top: 1px solid var(--border-color); text-align: right; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.65rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; }
    .btn-primary { background-color: var(--primary-color); color: white; width: 100%; transition: background-color 0.2s; }
    .btn-primary:hover { background-color: var(--primary-hover); }
    .btn-secondary { background-color: #f3f4f6; color: var(--text-medium); border: 1px solid var(--border-color); }
    .btn-secondary:hover { background-color: #e5e7eb; }
    .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
    .message.success { background-color: var(--success-bg); color: var(--success-text); }
    .message.error { background-color: var(--error-bg); color: var(--error-text); }
    
    .modal {
        display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.6); justify-content: center; align-items: center; padding: 1rem;
    }
    .modal-content {
        background-color: var(--card-bg); padding: 0; border-radius: 16px; width: 100%;
        max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); animation: slide-down 0.3s ease-out;
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); }
    .modal-header h3 { margin: 0; font-size: 1.25rem; }
    .close-btn { font-size: 1.5rem; color: var(--text-light); cursor: pointer; border: none; background: none; }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
    .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }
    @keyframes slide-down { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
</style>

<div class="main-content-wrapper">
    <div class="page-header">
        <div class="page-header-title">
            <h1>My Profile</h1>
            <p>View your account details and manage your security settings.</p>
        </div>
    </div>

    <?php if(!empty($message)): ?>
        <div class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><h3>Account Information</h3></div>
        <div class="card-body info-list">
            <p><strong>Full Name:</strong> <span><?php echo htmlspecialchars($user['full_name']); ?></span></p>
            <p><strong>Email Address:</strong> <span><?php echo htmlspecialchars($user['email']); ?></span></p>
            <p><strong>Phone Number:</strong> <span><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></span></p>
            <p><strong>Member Since:</strong> <span><?php echo date("F j, Y", strtotime($user['created_at'])); ?></span></p>
        </div>
        <div class="card-footer">
            <button id="changePasswordBtn" class="btn btn-secondary"><i class="fas fa-key"></i> Change Password</button>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3>Wallet Funding Details</h3></div>
        <div class="card-body info-list">
            <?php if (!empty($user['opay_account_number']) || !empty($user['palmpay_account_number'])): ?>
                <?php if (!empty($user['opay_account_number'])): ?>
                    <p><strong>OPay Account:</strong> <span><?php echo htmlspecialchars($user['opay_account_number']); ?></span></p>
                <?php endif; ?>
                <?php if (!empty($user['palmpay_account_number'])): ?>
                    <p><strong>Palmpay Account:</strong> <span><?php echo htmlspecialchars($user['palmpay_account_number']); ?></span></p>
                <?php endif; ?>
            <?php else: ?>
                <p style="margin: 0;">You have not generated your virtual accounts yet.</p>
                <a href="fund-wallet.php" class="btn btn-primary" style="width: auto; margin-top: 1rem;">Generate Accounts Now</a>
            <?php endif; ?>
        </div>
    </div>
</div>


<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button id="closeModalBtn" class="close-btn">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
    </div>
</div>


</div> 

<script>
document.addEventListener('DOMContentLoaded', () => {
    const passwordModal = document.getElementById('passwordModal');
    const openModalBtn = document.getElementById('changePasswordBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    if (passwordModal && openModalBtn && closeModalBtn) {
        const openModal = () => { passwordModal.style.display = 'flex'; };
        const closeModal = () => { passwordModal.style.display = 'none'; };
        openModalBtn.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal);
        window.addEventListener('click', (event) => {
            if (event.target === passwordModal) { closeModal(); }
        });
    }
});
</script>

</body>
</html>
