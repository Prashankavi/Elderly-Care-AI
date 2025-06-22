<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  // Secure random token
}
require_once('db.php');
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Query to fetch the user details from the database
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify the password using password_verify
        if (password_verify($password, $user['password'])) {
            // Store both firstname and user_id in the session
            $_SESSION['user_id'] = $user['id'];  // Storing user ID for later use (for example, profile page)
            $_SESSION['firstname'] = $user['firstname'];  // Storing firstname for greeting purposes
            header("Location: ../index.php");
            exit;
        } else {
            echo "<p>Invalid email or password.</p>";
        }
    } else {
        echo "<p>Invalid email or password.</p>";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ElderCare Health Monitoring System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #ef476f;
            --success: #06d6a0;
            --warning: #ffd166;
            --danger: #ef233c;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header & Navigation */
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
        }

        .logo i {
            font-size: 1.8rem;
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 1.5rem;
        }

        nav a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        nav a:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Main Content */
        main {
            flex: 1;
            padding: 3rem 0;
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .login-image {
            flex: 1;
            background: linear-gradient(rgba(67, 97, 238, 0.8), rgba(63, 55, 201, 0.8)),
                url('/api/placeholder/800/600') center/cover no-repeat;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }

        .login-image h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .login-image p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .features {
            margin-top: 2rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .feature-item i {
            font-size: 1.2rem;
            color: var(--accent);
        }

        .login-form-container {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h3 {
            font-size: 1.75rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: var(--gray);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 500;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            border: 1px solid #ddd;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            cursor: pointer;
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input {
            accent-color: var(--primary);
        }

        .forgot-password {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .forgot-password:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, var(--secondary), var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .social-divider {
            display: flex;
            align-items: center;
            margin: 1.5rem 0;
            color: var(--gray);
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background-color: #ddd;
        }

        .divider-text {
            padding: 0 1rem;
            font-size: 0.9rem;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .social-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            background-color: #f5f7ff;
            color: var(--dark);
            transition: var(--transition);
        }

        .social-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
        }

        .register-link {
            text-align: center;
            font-size: 0.95rem;
        }

        .register-link a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .register-link a:hover {
            color: var(--secondary);
            text-decoration: underline;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            text-align: center;
        }

        .alert-error {
            background-color: rgba(239, 35, 60, 0.2);
            color: #ef233c;
            border: 1px solid rgba(239, 35, 60, 0.3);
        }

        .alert-success {
            background-color: rgba(6, 214, 160, 0.2);
            color: #06d6a0;
            border: 1px solid rgba(6, 214, 160, 0.3);
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 0;
            text-align: center;
            margin-top: auto;
        }

        .footer-content {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 600px;
            }

            .login-image {
                padding: 3rem 2rem;
            }
        }

        @media (max-width: 768px) {
            .login-form-container {
                padding: 2rem;
            }

            .navbar {
                flex-direction: column;
                padding: 1rem 0 0.5rem;
            }

            nav ul {
                margin-top: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .login-image {
                padding: 2rem 1rem;
            }

            .login-form-container {
                padding: 1.5rem;
            }

            .form-options {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="navbar">
                <a href="../index.php" class="logo">
                    <i class="fas fa-heartbeat"></i>
                    ElderCare
                </a>
                <nav>
                    <ul>
                        <li><a href="../index.php"><i class="fas fa-home"></i> Home</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="#"><i class="fas fa-user"></i> Hi,
                                    <?php echo htmlspecialchars($_SESSION['firstname']); ?></a></li>
                            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        <?php else: ?>
                            <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                            <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>

            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="login-container">
                <div class="login-image">
                    <h2>Welcome to ElderCare</h2>
                    <p>Your comprehensive health monitoring system for elderly care</p>

                    <div class="features">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Real-time health monitoring</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Instant alerts for abnormal readings</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>AI-powered health recommendations</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <span>Smartwatch integration</span>
                        </div>
                    </div>
                </div>

                <div class="login-form-container">
                    <div class="login-header">
                        <h3>Sign In</h3>
                        <p>Please login to access your dashboard</p>
                    </div>

                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($successMessage)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($successMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST"
                        autocomplete="off">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control"
                                    placeholder="Enter your email" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control"
                                    placeholder="Enter your password" required>
                                <span class="password-toggle" id="togglePassword">
                                    <i class="far fa-eye"></i>
                                </span>
                            </div>
                        </div>

                        <div class="form-options">
                            <div class="checkbox-group">
                                <input type="checkbox" id="remember" name="remember">
                                <label for="remember">Remember me</label>
                            </div>
                            <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
                        </div>

                        <button type="submit" class="btn-login">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </button>
                    </form>

                    <div class="social-divider">
                        <div class="divider-line"></div>
                        <span class="divider-text">Or sign in with</span>
                        <div class="divider-line"></div>
                    </div>

                    <div class="social-login">
                        <a href="social-login.php?provider=google" class="social-btn">
                            <i class="fab fa-google"></i>
                        </a>
                        <a href="social-login.php?provider=facebook" class="social-btn">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="social-login.php?provider=apple" class="social-btn">
                            <i class="fab fa-apple"></i>
                        </a>
                    </div>

                    <div class="register-link">
                        Don't have an account? <a href="register.php">Register now</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-content">
                &copy; <?php echo date('Y'); ?> ElderCare Health Monitoring System. All rights reserved.
            </div>
        </div>
    </footer>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle eye icon
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Fade out alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                setTimeout(function () {
                    alerts.forEach(function (alert) {
                        alert.style.transition = 'opacity 1s';
                        alert.style.opacity = '0';
                        setTimeout(function () {
                            alert.style.display = 'none';
                        }, 1000);
                    });
                }, 5000);
            }
        });
    </script>
</body>

</html>