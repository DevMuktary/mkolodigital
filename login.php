<?php
session_start();
// Prevent database connection if the page is just being viewed
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    require_once "db.php";
}

$message = "";
$message_type = "error"; // All messages on this page are errors

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['loginEmail']);
    $password = $_POST['loginPassword'];

    if(empty($email) || empty($password)){
        $message = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, password, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $hashedPassword, $fullName);
            $stmt->fetch();
            if (password_verify($password, $hashedPassword)) {
                // Password is correct, start session
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $id;
                $_SESSION['fullName'] = $fullName;

                header("Location: dashboard.php");
                exit;
            } else {
                $message = "The password you entered was not valid.";
            }
        } else {
            $message = "No account found with that email address.";
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Trust Identity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        :root {
            --primary-color: #0d6efd;
            --background-color: #f1f5f9;
            --card-bg: #ffffff;
            --text-color: #334155;
            --heading-color: #1e293b;
            --border-color: #e2e8f0;
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
        .login-container {
            width: 100%;
            max-width: 450px;
            background-color: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            padding: 2.5rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
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
            padding: 12px 12px 12px 40px; /* Space for the icon */
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        .btn-login {
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
        .btn-login:hover { background-color: #0b5ed7; }
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-color);
        }
        .register-link a {
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
            background-color: var(--error-bg); 
            color: var(--error-text);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Welcome Back!</h1>
            <p>Please sign in to continue</p>
        </div>

        <?php if(!empty($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" id="loginEmail" name="loginEmail" placeholder="Email Address" required>
            </div>
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="loginPassword" name="loginPassword" placeholder="Password" required>
            </div>
            <button type="submit" class="btn-login">Sign In</button>
        </form>

        <div class="register-link">
            <p>Don’t have an account? <a href="register.php">Register here</a></p>
        </div>
    </div>
</body>
</html>
