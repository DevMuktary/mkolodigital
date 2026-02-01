<?php
session_start();
// Prevent database connection if the page is just being viewed
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once "db.php";
    // config.php is no longer needed here as we are not calling the API
    // require_once "config.php"; 
}

$message = "";
$message_type = ""; // Will be 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullName = trim($_POST['fullName']);
    $email = trim($_POST['regEmail']);
    $phone = trim($_POST['regPhone']);
    $password = $_POST['regPassword'];

    // --- Check if email already exists ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $message = "An account with this email already exists.";
        $message_type = "error";
        $stmt->close();
    } else {
        // --- Insert new user into your database ---
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        // Note: The account number columns will be NULL by default
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullName, $email, $phone, $hashed_password);

        if ($stmt->execute()) {
            // [REMOVED] The entire API call block to generate virtual accounts has been removed from this file.
            // It will now be handled on the fund-wallet page.

            // --- Finalize registration ---
            $message = "Registration successful! Redirecting to login...";
            $message_type = "success";
            header("Refresh:2; url=login.php");

        } else {
            $message = "Registration failed. Please try again.";
            $message_type = "error";
        }
        $stmt->close();
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an Account - Trust Identity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd;
            --primary-hover: #0b5ed7;
            --background-color: #f1f5f9;
            --card-bg: #ffffff;
            --text-color: #334155;
            --heading-color: #1e293b;
            --border-color: #e2e8f0;
            --success-bg: #dcfce7;
            --success-text: #166534;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .register-container {
            width: 100%;
            max-width: 450px;
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 2.5rem;
        }
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .register-header h1 {
            color: var(--heading-color);
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
        }
        .register-header p {
            color: var(--text-color);
            margin: 0;
        }
        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        .input-group input {
            width: 100%;
            padding: 12px 12px 12px 40px; /* Space for the icon */
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px; /* Prevents iOS auto-zoom */
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.2);
        }
        .btn-register {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background-color: var(--primary-color);
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-register:hover { background-color: var(--primary-hover); }
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-color);
        }
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        .message.success { background-color: var(--success-bg); color: var(--success-text); }
        .message.error { background-color: var(--error-bg); color: var(--error-text); }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Create Your Account</h1>
            <p>Join us to get started with our services</p>
        </div>

        <?php if(!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" id="fullName" name="fullName" placeholder="Full Name" required>
            </div>
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" id="regEmail" name="regEmail" placeholder="Email Address" required>
            </div>
            <div class="input-group">
                <i class="fas fa-phone"></i>
                <input type="tel" id="regPhone" name="regPhone" placeholder="Phone Number" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="regPassword" name="regPassword" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-register">Create Account</button>
        </form>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
