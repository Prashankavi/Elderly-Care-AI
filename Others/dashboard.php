<?php
// dashboard.php - Modern mobile-friendly dashboard with enhanced styling
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Styles */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(78, 115, 223, 0.1), transparent);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            color: #4e73df;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #4e73df;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 2;
            border: none;
        }

        .back-button:hover {
            background: #2e59d9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
            color: white;
            text-decoration: none;
        }

        /* App Icons Grid */
        .app-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .app-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px 20px;
            height: 200px;
            text-decoration: none;
            color: #5a5c69;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .app-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(78, 115, 223, 0.1), transparent);
            transition: left 0.5s;
        }

        .app-icon:hover::before {
            left: 100%;
        }

        .app-icon:hover,
        .app-icon:focus {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            color: #4e73df;
            text-decoration: none;
        }

        .disabled-icon {
            pointer-events: none;
            opacity: 0.7;
        }

        .icon-container {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #4e73df;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(78, 115, 223, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 50%;
            width: 80px;
            transition: all 0.3s ease;
        }

        .app-icon:hover .icon-container {
            background: linear-gradient(135deg, #4e73df, #764ba2);
            color: white;
            transform: scale(1.1);
        }

        .icon-label {
            font-size: 1rem;
            text-align: center;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .update-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #212529;
            font-size: 0.7rem;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: bold;
            z-index: 2;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Scroll to Top Button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #4e73df;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
        }

        .scroll-top.visible {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            background: #2e59d9;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(78, 115, 223, 0.4);
            color: white;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(78, 115, 223, 0.3);
            border-radius: 50%;
            border-top-color: #4e73df;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .dashboard-header h1 {
                font-size: 2rem;
            }

            .app-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }

            .back-button {
                position: static;
                margin-bottom: 20px;
                align-self: flex-start;
            }

            .app-icon {
                height: 180px;
                padding: 25px 15px;
            }

            .icon-container {
                font-size: 2.5rem;
                width: 70px;
                height: 70px;
            }
        }

        @media (max-width: 480px) {
            .app-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="dashboard-header">
            <a href="../index.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <h1>Dashboard</h1>
        </div>

        <div class="app-grid">
            <!-- News Icon - Available -->
            <a href="News/news.php" class="app-icon">
                <div class="icon-container">
                    <i class="fas fa-newspaper"></i>
                </div>
                <div class="icon-label">Today's News</div>
            </a>

            <!-- Tablet Info Icon - Available -->
            <a href="Tablets/tablet.html" class="app-icon">
                <div class="icon-container">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="icon-label">Tablet Info</div>
            </a>

            <!-- Story App Icon - Available -->
            <a href="story/stories.php" class="app-icon">
                <div class="icon-container">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="icon-label">Story App</div>
            </a>

            <!-- Common Old Age Problems Icon - Available -->
            <a href="OldAgeProblems/index.html" class="app-icon">                    
                <div class="icon-container">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="icon-label">Common Old Age Problems</div>
            </a>

            <!-- Yoga Guidance Icon - Available -->
            <a href="Yoga/index.html" class="app-icon">
                <div class="icon-container">
                    <i class="fas fa-pray"></i>
                </div>
                <div class="icon-label">Yoga Guidance</div>
            </a>

            <!-- Exercise Routines Icon - Available -->
            <a href='Exercise/index.html' class="app-icon">
                <div class="icon-container">
                    <i class="fas fa-running"></i>
                </div>
                <div class="icon-label">Exercise Routines</div>
            </a>
        </div>
    </div>

    <!-- Scroll to Top Button -->
    <a href="#" class="scroll-top" id="scrollTop">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Scroll to top functionality
        const scrollTopBtn = document.getElementById('scrollTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('visible');
            } else {
                scrollTopBtn.classList.remove('visible');
            }
        });
        
        scrollTopBtn.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>

</html>