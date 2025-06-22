<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  // Secure random token
}
require_once('db.php'); // Include the database connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "CSRF token validation failed. Please try again.";
    } else {
        // Get input values from the form
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $email = $_POST['email'];
        $phone_number = $_POST['phone_number'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        // Check if passwords match
        if ($password !== $confirm_password) {
            $errorMessage = "Passwords do not match.";
        } else {
            // Check if email already exists
            $checkEmail = "SELECT id FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkEmail);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errorMessage = "Email address is already registered.";
            } else {
                // Hash the password before storing it in the database
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Insert the new user into the database
                $query = "INSERT INTO users (firstname, lastname, email, phone_number, password) 
                          VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssss", $firstname, $lastname, $email, $phone_number, $hashed_password);

                if ($stmt->execute()) {
                    // Set success message and redirect to login page
                    $_SESSION['successMessage'] = "Registration successful! Please login.";
                    header("Location: login.php");
                    exit;
                } else {
                    $errorMessage = "Error: Could not register user. Please try again.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ElderCare Health Monitoring System</title>
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

        .register-container {
            display: flex;
            max-width: 1000px;
            margin: 0 auto;
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .register-image {
            flex: 1;
            background: linear-gradient(rgba(67, 97, 238, 0.8), rgba(63, 55, 201, 0.8)),
                url('/api/placeholder/800/600') center/cover no-repeat;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }

        .register-image h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .register-image p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            opacity: 0.9;
        }

        .benefits {
            margin-top: 2rem;
        }

        .benefit-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .benefit-item i {
            font-size: 1.2rem;
            color: var(--accent);
        }

        .register-form-container {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header h3 {
            font-size: 1.75rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: var(--gray);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-col {
            flex: 1;
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

        .btn-register {
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

        .btn-register:hover {
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

        .social-register {
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

        .login-link {
            text-align: center;
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
        }

        .login-link a:hover {
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

        /* Password strength indicator */
        .password-strength {
            height: 5px;
            width: 100%;
            background-color: #ddd;
            border-radius: 5px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            text-align: right;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .register-container {
                flex-direction: column;
                max-width: 600px;
            }

            .register-image {
                padding: 3rem 2rem;
            }
        }

        @media (max-width: 768px) {
            .register-form-container {
                padding: 2rem;
            }

            .navbar {
                flex-direction: column;
                padding: 1rem 0 0.5rem;
            }

            nav ul {
                margin-top: 0.5rem;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }

        @media (max-width: 576px) {
            .register-image {
                padding: 2rem 1rem;
            }

            .register-form-container {
                padding: 1.5rem;
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
            <div class="register-container">
                <div class="register-image">
                    <h2>Join ElderCare Today</h2>
                    <p>Create your account and start monitoring the health of your loved ones</p>

                    <div class="benefits">
                        <div class="benefit-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>Secure and private data storage</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-bell"></i>
                            <span>Instant notifications for emergencies</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Comprehensive health reports</span>
                        </div>
                        <div class="benefit-item">
                            <i class="fas fa-mobile-alt"></i>
                            <span>Mobile app for on-the-go monitoring</span>
                        </div>
                    </div>
                </div>

                <div class="register-form-container">
                    <div class="register-header">
                        <h3>Create an Account</h3>
                        <p>Fill in your details to get started</p>
                    </div>

                    <?php if (!empty($errorMessage)): ?>
                        <div class="alert alert-error">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST"
                        autocomplete="off" id="registerForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <div class="form-row">
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="firstname">First Name</label>
                                    <div class="input-group">
                                        <i class="fas fa-user"></i>
                                        <input type="text" id="firstname" name="firstname" class="form-control"
                                            placeholder="Enter first name" required
                                            value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="form-col">
                                <div class="form-group">
                                    <label for="lastname">Last Name</label>
                                    <div class="input-group">
                                        <i class="fas fa-user"></i>
                                        <input type="text" id="lastname" name="lastname" class="form-control"
                                            placeholder="Enter last name" required
                                            value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-group">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control"
                                    placeholder="Enter your email" required
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="phone_number">Phone Number</label>
                            <div class="input-group">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone_number" name="phone_number" class="form-control"
                                    placeholder="Enter your phone number" required
                                    value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="password" name="password" class="form-control"
                                    placeholder="Create a password" required>
                                <span class="password-toggle" id="togglePassword">
                                    <i class="far fa-eye"></i>
                                </span>
                            </div>
                            <div class="password-strength">
                                <div class="strength-meter" id="strengthMeter"></div>
                            </div>
                            <div class="strength-text" id="strengthText"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                                    placeholder="Confirm your password" required>
                                <span class="password-toggle" id="toggleConfirmPassword">
                                    <i class="far fa-eye"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" class="btn-register">
                            <i class="fas fa-user-plus"></i> Create Account
                        </button>
                    </form>

                    <div class="social-divider">
                        <div class="divider-line"></div>
                        <span class="divider-text">Or register with</span>
                        <div class="divider-line"></div>
                    </div>

                    <div class="social-register">
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

                    <div class="login-link">
                        Already have an account? <a href="login.php">Login now</a>
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
        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordInput = document.getElementById('confirm_password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        toggleConfirmPassword.addEventListener('click', function () {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            
            // Toggle eye icon
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });

        // Password strength indicator
        const strengthMeter = document.getElementById('strengthMeter');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            
            // Update strength meter
            strengthMeter.style.width = strength.percent + '%';
            strengthMeter.style.backgroundColor = strength.color;
            strengthText.textContent = strength.text;
            strengthText.style.color = strength.color;
        });

        function calculatePasswordStrength(password) {
            // Check password length
            const length = password.length;
            
            if (length === 0) {
                return { percent: 0, color: '#ddd', text: '' };
            }
            
            // Calculate strength
            let strength = 0;
            let feedbackText = '';
            
            // Add points for length
            if (length > 7) strength += 25;
            
            // Add points for complexity
            if (/[A-Z]/.test(password)) strength += 15;
            if (/[a-z]/.test(password)) strength += 15;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^A-Za-z0-9]/.test(password)) strength += 30;
            
            // Determine color and text based on strength
            let color = '';
            
            if (strength < 30) {
                color = '#ff4d4d';
                feedbackText = 'Weak';
            } else if (strength < 60) {
                color = '#ffa64d';
                feedbackText = 'Medium';
            } else if (strength < 85) {
                color = '#2bd1fc';
                feedbackText = 'Strong';
            } else {
                color = '#1af075';
                feedbackText = 'Very Strong';
            }
            
            return {
                percent: Math.min(100, strength),
                color: color,
                text: feedbackText
            };
        }

        // Form validation
        const registerForm = document.getElementById('registerForm');
        const confirmPassword = document.getElementById('confirm_password');
        
        registerForm.addEventListener('submit', function(event) {
            if (passwordInput.value !== confirmPassword.value) {
                event.preventDefault();
                alert('Passwords do not match!');
                confirmPassword.focus();
            }
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