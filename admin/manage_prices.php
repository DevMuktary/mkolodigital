<?php
session_start();
if (!isset($_SESSION["admin_loggedin"]) || $_SESSION["admin_loggedin"] !== true) {
    header("location: login.php");
    exit;
}
require_once "../db.php";

$message = "";
$message_type = "";

// --- HANDLE INDIVIDUAL PRICE UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['service_key'])) {
    $service_key = $_POST['service_key'];
    $price = $_POST['price'];

    if (is_numeric($price) && $price >= 0) {
        $sql = "UPDATE services SET price = ? WHERE service_key = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ds", $price, $service_key);
            if ($stmt->execute()) {
                $message = "Price updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error: Could not update price.";
                $message_type = "error";
            }
            $stmt->close();
        }
    } else {
        $message = "Invalid price amount entered.";
        $message_type = "error";
    }
}

// Fetch all services and group them by category
$grouped_services = [];
$result = $conn->query("SELECT service_key, category, service_name, price FROM services ORDER BY category, service_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $grouped_services[$row['category']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Service Prices</title>
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
        .container { width: 100%; max-width: 900px; margin: 0 auto; padding: 1.5rem; }
        .top-header { display: flex; justify-content: space-between; align-items: center; background-color: var(--card-bg); padding: 1rem 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .top-header h3 { margin: 0; color: var(--heading-color); }
        .top-header a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
        .card { background-color: var(--card-bg); border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0; font-size: 1.25rem; }
        .card-body { padding: 1rem 1.5rem; }
        .service-category { margin-bottom: 2rem; }
        .service-category:last-child { margin-bottom: 0; }
        .service-category h4 { font-size: 1.1rem; color: var(--heading-color); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-top: 0; margin-bottom: 1rem; }
        .price-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f1f1f1; flex-wrap: wrap; gap: 1rem; }
        .price-item:last-child { border-bottom: none; }
        .price-item label { font-weight: 500; flex: 1 1 300px; }
        .price-item form { display: flex; align-items: center; gap: 1rem; }
        .price-input-wrapper { display: flex; align-items: center; background-color: #f8f9fa; border: 1px solid var(--border-color); border-radius: 8px; }
        .price-input-wrapper span { padding-left: 0.75rem; color: var(--secondary-color); font-weight: 600; }
        .price-input-wrapper input { border: none; background: transparent; padding: 0.75rem; font-size: 16px; width: 100px; text-align: right; }
        .btn-update { padding: 0.75rem 1.5rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 500; background-color: var(--primary-color); color: white; }
        .message { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center; font-weight: 500; }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }
    </style>
</head>
<body>
    <div class="container">
        <header class="top-header">
            <h3><i class="fas fa-tags"></i> Manage Prices</h3>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </header>

        <?php if(!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3>Update Service Prices Individually</h3></div>
            <div class="card-body">
                <?php if (empty($grouped_services)): ?>
                    <p>No services found in the database.</p>
                <?php else: foreach ($grouped_services as $category => $services): ?>
                    <div class="service-category">
                        <h4><?php echo htmlspecialchars($category); ?></h4>
                        <?php 
                        // This loop automatically displays every service from the database in this category
                        foreach ($services as $service): 
                        ?>
                            <div class="price-item">
                                <label for="price-<?php echo $service['service_key']; ?>"><?php echo htmlspecialchars($service['service_name']); ?></label>
                                <form method="POST">
                                    <input type="hidden" name="service_key" value="<?php echo $service['service_key']; ?>">
                                    <div class="price-input-wrapper">
                                        <span>₦</span>
                                        <input 
                                            type="number" 
                                            id="price-<?php echo $service['service_key']; ?>" 
                                            name="price" 
                                            value="<?php echo htmlspecialchars($service['price']); ?>"
                                            step="50" min="0" required
                                        >
                                    </div>
                                    <button type="submit" class="btn-update">Update</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</body>
</html>