<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $login = trim($_POST["username"] ?? '');
    $password = $_POST["password"] ?? '';

    if (empty($login) || empty($password)) {
        $error = "Please enter username/email and password.";
    } else {
        try {
            $connPDO = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            // Try to find user in barangay_registration (Officials)
            $stmt = $connPDO->prepare("SELECT * FROM barangay_registration WHERE (username = :login OR email = :login) AND status = 'approved' LIMIT 1");
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();

            if ($user) {
                // Check if password is correct (hashed or plain text)
                if (password_verify($password, $user['password']) || $user['password'] === $password) {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'user_id'   => $user['id'],
                        'username'  => $user['username'],
                        'full_name' => $user['full_name'],
                        'position'  => $user['position'] ?? 'Barangay Official',
                        'role'      => 'official',
                        'barangay_name' => $user['barangay_name'] ?? '',
                        'municipality' => $user['municipality'] ?? '',
                        'province' => $user['province'] ?? ''
                    ];
                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                // Try to find user in super_admin table
                $stmt2 = $connPDO->prepare("SELECT * FROM super_admin WHERE username = :login OR email = :login LIMIT 1");
                $stmt2->execute([':login' => $login]);
                $superAdmin = $stmt2->fetch();

                if ($superAdmin) {
                    // Verify super admin password
                    if (password_verify($password, $superAdmin['password']) || $superAdmin['password'] === $password) {
                        session_regenerate_id(true);
                        $_SESSION['user'] = [
                            'user_id'   => $superAdmin['id'],
                            'username'  => $superAdmin['username'],
                            'full_name' => $superAdmin['full_name'] ?? 'Super Administrator',
                            'role'      => 'super_admin',
                            'email'     => $superAdmin['email']
                        ];
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Incorrect password.";
                    }
                } else {
                    // Try users table as fallback
                    $stmt3 = $connPDO->prepare("SELECT * FROM users WHERE username = :login OR email = :login LIMIT 1");
                    $stmt3->execute([':login' => $login]);
                    $systemUser = $stmt3->fetch();

                    if ($systemUser && $systemUser['status'] === 'active') {
                        if (password_verify($password, $systemUser['password']) || $systemUser['password'] === $password) {
                            session_regenerate_id(true);
                            $_SESSION['user'] = [
                                'user_id'   => $systemUser['id'],
                                'username'  => $systemUser['username'],
                                'full_name' => $systemUser['full_name'],
                                'role'      => $systemUser['role'],
                                'email'     => $systemUser['email']
                            ];
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            $error = "Incorrect password.";
                        }
                    } else {
                        $error = "Username or email not found or account not active.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Barangay Login</title>
    <link rel="stylesheet" href="login.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <style>
        .back-btn {
            position: absolute;
            top: 10px;
            left: 15px;
            color: #333;
            font-size: 22px;
            cursor: pointer;
        }
        .back-btn:hover {
            color: #1d3b71;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background-color: #ffe6e6;
            border: 1px solid #ffcccc;
            border-radius: 5px;
        }
        .success-message {
            color: green;
            text-align: center;
            margin-top: 10px;
            padding: 10px;
            background-color: #e6ffe6;
            border: 1px solid #ccffcc;
            border-radius: 5px;
        }
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            z-index: 2;
        }
        .input-group input {
            width: 100%;
            padding: 12px 12px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .input-group input:focus {
            outline: none;
            border-color: #1d3b71;
        }
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-options label {
            display: flex;
            align-items: center;
            color: #666;
            font-size: 14px;
        }
        .form-options input[type="checkbox"] {
            margin-right: 5px;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background-color: #1d3b71;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .login-btn:hover {
            background-color: #152a56;
        }
        .additional-links {
            text-align: center;
        }
        .register-link {
            color: #1d3b71;
            text-decoration: none;
        }
        .register-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div style="margin: 0px;">
    <button type="button" 
            onclick="window.location.href='index.php';"  
            style="background-color:#1d3b71; 
            color:white; 
            border:none; 
            padding:10px 20px; 
            border-radius:4px; 
            cursor:pointer;">
        ← Go to main page
    </button>
</div> 
    <main class="login-screen">
        <h1>Barangay Events/Program Profiling System</h1>
        <h2>Login to your account</h2>

        <!-- Display success message if redirected from registration -->
        <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
            <div class="success-message">
                Registration successful! Please wait for admin approval.
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form" action="login.php" novalidate>
            <div class="input-group">
                <i class="fas fa-user"></i>
                <input type="text" name="username" placeholder="Username or Email" required />
            </div>
            
            <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required />
            </div>

            <div class="form-options">
                <label><input type="checkbox" name="remember" /> Remember me</label>
            </div>

            <button type="submit" class="login-btn">Login</button>
            
            <?php if (!empty($error)): ?>
                <p class="error-message"><?= htmlspecialchars($error) ?></p>
            <?php endif; ?>
        </form>

        <div class="additional-links">
            <a href="register.php" class="register-link">Don't have an account? Register as Barangay Official</a>
        </div>
    </main>
</body>
</html>