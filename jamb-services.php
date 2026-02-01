<?php
// Initialize the session
session_start();
require_once "db.php"; // [ADDED] Database connection is now needed

// Check if the user is logged in, if not then redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// --- [ADDED] FETCH JAMB SERVICES DYNAMICALLY FROM THE DATABASE ---
$jamb_services = [];
$sql = "SELECT service_key, service_name, price FROM services WHERE category = 'JAMB Services' ORDER BY price ASC";
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $jamb_services[] = $row;
    }
}

// This array maps a service_key to a specific icon for display purposes
$service_icons = [
    'profile_code' => 'fas fa-user-circle',
    'result_slip'  => 'fas fa-file-alt',
    'reg_slip' => 'fas fa-id-card',
    'admission_letter' => 'fas fa-envelope-open-text',
    'mock_slip' => 'fas fa-clipboard-list'
];
// --- END OF ADDED SECTION ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JAMB Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #008000; /* JAMB's Green */
            --primary-hover: #006400;
            --secondary-color: #64748b;
            --background-color: #f1f5f9;
            --card-bg: #ffffff;
            --text-color: #334155;
            --heading-color: #1e293b;
            --border-color: #e2e8f0;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-color); margin: 0; }
        .container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
            padding: 1rem;
        }
        .card {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }
        .page-header { text-align: center; margin-bottom: 2rem; margin-top: 1rem;}
        .jamb-logo {
            max-width: 100px;
            margin-bottom: 1rem;
        }
        .page-header-title h1 { font-size: 2rem; color: var(--heading-color); margin: 0; }
        .page-header-title p { color: var(--secondary-color); margin-top: 0.25rem; }
        .back-link { display: inline-block; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.25rem; border: none; font-weight: 600; border-radius: 8px; cursor: pointer; text-decoration: none; }
        .btn-secondary { background-color: var(--card-bg); color: var(--secondary-color); border: 1px solid var(--border-color); }
        .card-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .card-header h3 { margin: 0; font-size: 1.25rem; color: var(--heading-color); }
        .card-body { padding: 1.5rem; }
        
        .service-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .service-link {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 2rem 1.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--heading-color);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .service-link:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: var(--primary-color);
        }
        .service-link i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .service-link h3 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        /* [ADDED] Style for displaying the price */
        .service-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-header-title">
                
                <img src="img/jamb-logo.png" alt="JAMB Logo" class="jamb-logo">
                <h1>JAMB Services</h1>
                <p>Select a service below to proceed with your request.</p>
                <a href="dashboard.php" class="btn btn-secondary back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-body">
                <div class="service-grid">

                    <?php if (empty($jamb_services)): ?>
                        <p>No JAMB services are currently available. Please check back later.</p>
                    <?php else: foreach ($jamb_services as $service): ?>
                        <?php
                            // Get the icon for the current service, with a default fallback icon
                            $icon_class = $service_icons[$service['service_key']] ?? 'fas fa-cog';
                        ?>
                        <a href="jamb-order.php?service=<?php echo htmlspecialchars($service['service_key']); ?>" class="service-link">
                            <div>
                                <i class="<?php echo $icon_class; ?>"></i>
                                <h3><?php echo htmlspecialchars($service['service_name']); ?></h3>
                            </div>
                            <div class="service-price">
                                ₦<?php echo number_format($service['price']); ?>
                            </div>
                        </a>
                    <?php endforeach; endif; ?>
                    </div>
            </div>
        </div>
    </div>
</body>
</html>
