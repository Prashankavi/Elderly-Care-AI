<?php
// upload_excel.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $allowedTypes = ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
        if (in_array($_FILES['excel_file']['type'], $allowedTypes)) {
            // Save file temporarily
            $fileTmpPath = $_FILES['excel_file']['tmp_name'];
            $fileName = $_FILES['excel_file']['name'];

            // You can move it if you want:
            // move_uploaded_file($fileTmpPath, 'uploads/' . $fileName);

            // Here you would parse and validate the Excel file to check columns (we can add that if you want)

            echo "<p style='color:green;'>File '$fileName' uploaded successfully!</p>";
        } else {
            echo "<p style='color:red;'>Invalid file type. Please upload an Excel (.xlsx) or CSV file.</p>";
        }
    } else {
        echo "<p style='color:red;'>No file uploaded or upload error.</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Excel Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }
        .upload-form {
            max-width: 400px;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 10px;
        }
        .upload-form input[type="file"] {
            margin-bottom: 15px;
        }
        .upload-form button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .upload-form button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>

<h2>Upload Your Smartwatch Data Excel Sheet</h2>

<div class="upload-form">
    <form action="" method="POST" enctype="multipart/form-data">
        <label for="excel_file">Select Excel/CSV File:</label><br>
        <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.csv" required><br>
        <button type="submit">Upload</button>
    </form>
</div>

<!-- Expected Columns Reminder -->
<div style="margin-top:30px;">
    <h3>Expected Columns:</h3>
    <ul>
        <li>HeartRate</li>
        <li>SpO2</li>
        <li>StepCount</li>
        <li>CaloriesBurned</li>
        <li>MovementActivity</li>
        <li>SleepDuration</li>
        <li>SleepQuality</li>
        <li>FallDetected</li>
        <li>BatteryLevel</li>
        <li>Timestamp</li>
        <li>DeviceID/UserID</li>
    </ul>
</div>

</body>
</html>
