<?php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once "../db.php";

$message = "";
$message_type = "";

// --- HANDLE UPDATES (STATUS & FILE UPLOAD) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_id'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['status'];
    $result_file_path = $_POST['existing_file']; // Keep existing file path by default

    // --- HANDLE FILE UPLOAD ---
    if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] == 0) {
        $file = $_FILES['result_file'];
        $uploadDir = '../uploads/'; // Go up one level to the main uploads folder
        $fileName = 'jamb-' . uniqid() . '-' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $result_file_path = 'uploads/' . $fileName;
        } else {
            $message = "Error: Failed to move uploaded file.";
            $message_type = "error";
        }
    }

    if (empty($message)) { // Proceed only if there was no upload error
        $sql = "UPDATE jamb_requests SET status = ?, result_file_path = ? WHERE id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $file_to_save = !empty($result_file_path) ? $result_file_path : NULL;
            $stmt->bind_param("ssi", $new_status, $file_to_save, $request_id);
            if ($stmt->execute()) {
                $message = "Request updated successfully.";
                $message_type = "success";
            } else {
                $message = "Error updating request.";
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Fetch all JAMB requests
$requests = [];
$sql = "SELECT r.*, u.full_name, u.email 
        FROM jamb_requests r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY r.request_date DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage JAMB Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #dc3545; --secondary-color: #6c757d;
            --background-color: #f8f9fa; --card-bg: #ffffff; --text-color: #495057;
            --heading-color: #212529; --border-color: #dee2e6;
            --success-bg: #d1e7dd; --success-text: #0f5132; --error-bg: #f8d7da; --error-text: #842029;
            --status-pending-bg: #fff3cd; --status-pending-text: #664d03;
            /* [ADDED] Colors for 'Processing' status */
            --status-processing-bg: #cff4fc; --status-processing-text: #055160;
            --status-completed-bg: #d1e7dd; --status-completed-text: #0f5132;
            --status-rejected-bg: #f8d7da; --status-rejected-text: #842029;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container { width: 100%; max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .top-header { display: flex; justify-content: space-between; align-items: center; background-color: var(--card-bg); padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .top-header h3 { margin: 0; color: var(--heading-color); }
        .top-header a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { margin: 0; font-size: 1.25rem; }
        .search-box { width: 100%; max-width: 300px; padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 16px; }
        .card-body { padding: 0; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; text-transform: capitalize; }
        .status-pending { background-color: var(--status-pending-bg); color: var(--status-pending-text); }
        /* [ADDED] Style for the new status badge */
        .status-processing { background-color: var(--status-processing-bg); color: var(--status-processing-text); }
        .status-completed { background-color: var(--status-completed-bg); color: var(--status-completed-text); }
        .status-rejected { background-color: var(--status-rejected-bg); color: var(--status-rejected-text); }
        .btn-update { padding: 0.5rem 1rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; background-color: var(--primary-color); color: white; margin-top: 0.5rem; width: 100%; }
        .action-form select, .action-form input { width: 100%; padding: 0.5rem; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; margin-bottom: 0.5rem; }
        pre { white-space: pre-wrap; font-family: inherit; margin: 0; }
        .document-link { display: inline-block; margin-top: 0.5rem; text-decoration: none; background-color: var(--secondary-color); color: white; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.8rem; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }
    </style>
</head>
<body>
    <div class="container">
        <header class="top-header">
            <h3><i class="fas fa-university"></i> Manage JAMB Services</h3>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </header>

        <?php if(!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3>All Requests (<?php echo count($requests); ?>)</h3>
                <input type="text" id="searchInput" class="search-box" placeholder="Search by name, email, or service...">
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table id="requestsTable">
                        <thead>
                            <tr><th>User Details</th><th>Request Details</th><th>Current Status</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr><td colspan="4" style="text-align:center; padding: 2rem;">No requests found.</td></tr>
                            <?php else: foreach ($requests as $req): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($req['full_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($req['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Service:</strong> <?php echo htmlspecialchars($req['service_type']); ?><br>
                                        <strong>Submitted:</strong><pre><?php echo htmlspecialchars($req['details']); ?></pre>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($req['status']); ?>">
                                            <?php echo htmlspecialchars($req['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form class="action-form" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="existing_file" value="<?php echo htmlspecialchars($req['result_file_path'] ?? ''); ?>">
                                            
                                            <label>Status:</label>
                                            <select name="status">
                                                <option value="Pending" <?php if($req['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                                <option value="Processing" <?php if($req['status'] == 'Processing') echo 'selected'; ?>>Processing</option>
                                                <option value="Completed" <?php if($req['status'] == 'Completed') echo 'selected'; ?>>Completed</option>
                                                <option value="Rejected" <?php if($req['status'] == 'Rejected') echo 'selected'; ?>>Rejected</option>
                                            </select>
                                            
                                            <label>Upload Result File (PDF):</label>
                                            <input type="file" name="result_file">

                                            <?php if (!empty($req['result_file_path'])): ?>
                                                <div style="margin-top: 0.5rem;"><a href="../<?php echo htmlspecialchars($req['result_file_path']); ?>" class="document-link" target="_blank">View Current File</a></div>
                                            <?php endif; ?>

                                            <button type="submit" class="btn-update">Update</button>
                                        </form>
                                    </td>
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
            const rows = document.getElementById('requestsTable').getElementsByTagName('tbody')[0].getElementsByTagName('tr');
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
