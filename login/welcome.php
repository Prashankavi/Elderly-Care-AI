<?php
session_start();
if (!isset($_SESSION['firstname'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Elderly Care</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['firstname']); ?>!</h1>
            <nav>
                <ul>
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section>
        <div class="container">
            <h2>You're now logged in. Enjoy exploring our services!</h2>
        </div>
    </section>
</body>
</html>
