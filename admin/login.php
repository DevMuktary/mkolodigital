<?php
session_start();
// If admin is already logged in, redirect to the dashboard
if (isset($_SESSION["admin_loggedin"]) && $_SESSION["admin_loggedin"] === true) {
    header("location: dashboard.php");
    exit;
}

require_once "../db.php"; // Go up one directory to find db.php

$email = $password = "";
$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT id, full_name, password, role FROM users WHERE email = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $fullName, $hashed_password, $role);
            if ($stmt->fetch()) {
                // IMPORTANT: Check if the user's role is 'admin'
                if ($role !== 'admin') {
                    $login_err = "Access Denied. This is not an admin account.";
                } elseif (password_verify($password, $hashed_password)) {
                    // Password is correct, start a new session for the admin
                    session_start();
                    $_SESSION["admin_loggedin"] = true;
                    $_SESSION["admin_id"] = $id;
                    $_SESSION["admin_name"] = $fullName;
                    
                    header("location: dashboard.php");
                } else {
                    $login_err = "Invalid email or password.";
                }
            }
        } else {
            $login_err = "No account found with that email address.";
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
    <title>Admin Login - Trust Identity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #dc3545;
            --primary-hover: #c82333;
            --background-gradient: linear-gradient(135deg, #2c3e50, #34495e);
            --card-bg: #ffffff;
            --text-color: #4a5568;
            --heading-color: #1a202c;
            --border-color: #e2e8f0;
            --error-bg: #fee2e2;
            --error-text: #991b1b;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-image: var(--background-gradient);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1rem;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            padding: 2.5rem 3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header .icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .login-header h1 {
            color: var(--heading-color);
            margin: 0 0 0.5rem 0;
            font-size: 1.8rem;
        }
        .login-header p {
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
            padding: 14px 14px 14px 45px; /* Space for the icon */
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.2);
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background-color: var(--primary-color);
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-login:hover {
            background-color: var(--primary-hover);
            transform: translateY(-2px);
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            background-color: var(--error-bg); 
            color: var(--error-text);
            border: 1px solid var(--error-text);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon"><i class="fas fa-user-shield"></i></div>
            <h1>Admin Control Panel</h1>
            <p>Please sign in to continue</p>
        </div>

        <?php if(!empty($login_err)): ?>
            <div class="message"><?php echo $login_err; ?></div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" placeholder="Admin Email Address" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>
</body>
</html>
