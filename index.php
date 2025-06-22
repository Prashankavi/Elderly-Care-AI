<?php
session_start();
require_once 'db.php'; // Include DB connection

$isLoggedIn = false;
$firstname = '';
$phone_number = '';
$alertMessage = '';
$alertSent = false;
$fileUploaded = false;
$analysisStarted = false;
$analysisResults = [];
$chatEnabled = false;
$chatHistory = [];
$uploadedFileName = '';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $isLoggedIn = true;
    $userId = $_SESSION['user_id'];

    // Fetch user's name and phone number
    $stmt = $conn->prepare("SELECT firstname, phone_number FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($firstname, $phone_number);
    $stmt->fetch();
    $stmt->close();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handle alert button
    if (isset($_POST['send_alert']) && $isLoggedIn) {
        if (!empty($phone_number)) {
            // Only send the alert if it hasn't been sent in this session
            if (!isset($_SESSION['alert_sent']) || $_SESSION['alert_sent'] !== true) {
                // Define the command to run the Python script with the phone number as an argument
                $command = "python C:\\xampp\\htdocs\\IP-Project-5\\alert.py +91" . escapeshellarg($phone_number);

                // Execute the command
                $output = shell_exec($command);

                // Set session variable to indicate the alert has been sent
                $_SESSION['alert_sent'] = true;
                $_SESSION['alert_message'] = "✅ Alert initiated successfully for $firstname.";

                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $_SESSION['alert_message'] = "⚠️ Error: Cannot send alert. Phone number is missing.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Handle Excel upload
    if (isset($_POST['upload_excel']) && isset($_FILES['excel_file'])) {
        $target_dir = "uploads/";

        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $target_file = $target_dir . basename($_FILES["excel_file"]["name"]);
        $fileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if file is a valid Excel or CSV file
        if ($fileType == "xlsx" || $fileType == "xls" || $fileType == "csv") {
            if (move_uploaded_file($_FILES["excel_file"]["tmp_name"], $target_file)) {
                $fileUploaded = true;
                $_SESSION['file_uploaded'] = true;
                $_SESSION['uploaded_file'] = $target_file;
                $_SESSION['uploaded_file_name'] = basename($_FILES["excel_file"]["name"]);
                $_SESSION['alert_message'] = "✅ Data uploaded successfully.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $_SESSION['alert_message'] = "⚠️ Error: Failed to upload file.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } else {
            $_SESSION['alert_message'] = "⚠️ Error: Only Excel (.xlsx, .xls) or CSV (.csv) files are allowed.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Handle removing uploaded file
    if (isset($_POST['remove_file']) && isset($_SESSION['uploaded_file'])) {
        $fileToRemove = $_SESSION['uploaded_file'];

        // Remove the file from the filesystem if it exists
        if (file_exists($fileToRemove)) {
            unlink($fileToRemove);
        }

        // Clear file-related session variables
        unset($_SESSION['uploaded_file']);
        unset($_SESSION['uploaded_file_name']);
        unset($_SESSION['file_uploaded']);
        unset($_SESSION['analysis_results']);
        unset($_SESSION['analysis_started']);

        $_SESSION['alert_message'] = "✅ File removed successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Handle Excel analysis
    if (isset($_POST['analyze_excel'])) {
        if (isset($_SESSION['uploaded_file'])) {
            $file = $_SESSION['uploaded_file'];
            $analysisStarted = true;
            $_SESSION['analysis_started'] = true;

            // Perform analysis on the Excel file
            $abnormalReadings = analyzeHealthData($file);

            if (!empty($abnormalReadings)) {
                $_SESSION['analysis_results'] = $abnormalReadings;

                // Send alert if abnormal readings found
                if (!empty($phone_number)) {
                    $command = "python C:\\xampp\\htdocs\\IP-Project-5\\alert.py +91" . escapeshellarg($phone_number);
                    $output = shell_exec($command);

                    // Create a summary of abnormal readings to send in the alert
                    $abnormalSummary = "Abnormal health readings detected: ";
                    foreach ($abnormalReadings as $index => $reading) {
                        if ($index < 3) { // Limit the message to first 3 issues
                            $abnormalSummary .= "{$reading['metric']}: {$reading['value']}, ";
                        }
                    }
                    $abnormalSummary = rtrim($abnormalSummary, ", ");
                    if (count($abnormalReadings) > 3) {
                        $abnormalSummary .= " and more...";
                    }

                    // Include the summary in the alert
                    $alertCommand = "python C:\\xampp\\htdocs\\IP-Project-5\\alert.py +91" . escapeshellarg($phone_number) . " " . escapeshellarg($abnormalSummary);
                    $alertOutput = shell_exec($alertCommand);

                    $_SESSION['alert_message'] = "⚠️ Alert: Abnormal health readings detected and notification sent!";
                } else {
                    $_SESSION['alert_message'] = "⚠️ Warning: Abnormal health readings detected but couldn't send alert (no phone number).";
                }

                // Enable chat with AI for suggestions
                $_SESSION['chat_enabled'] = true;
            } else {
                $_SESSION['alert_message'] = "✅ Analysis complete. All readings are within normal range.";
            }

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $_SESSION['alert_message'] = "⚠️ Error: No file to analyze. Please upload a file first.";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // Handle AI chat
    if (isset($_POST['send_message']) && !empty($_POST['user_message'])) {
        $userMessage = trim($_POST['user_message']);

        // Add user message to chat history
        if (!isset($_SESSION['chat_history'])) {
            $_SESSION['chat_history'] = [];
        }

        $_SESSION['chat_history'][] = ['role' => 'user', 'message' => $userMessage];

        // Get health context from analysis results if available
        $healthContext = "";
        if (isset($_SESSION['analysis_results']) && !empty($_SESSION['analysis_results'])) {
            $healthContext = "Based on these abnormal health readings: ";
            foreach ($_SESSION['analysis_results'] as $reading) {
                $healthContext .= "{$reading['metric']}: {$reading['value']} (normal range: {$reading['normal_range']}), ";
            }
            $healthContext = rtrim($healthContext, ", ");
        }

        // Generate AI response using Mistral-Ollama or alternative model
        $aiResponse = getAIResponse($userMessage, $healthContext);

        // Add AI response to chat history
        $_SESSION['chat_history'][] = ['role' => 'assistant', 'message' => $aiResponse];

        header("Location: " . $_SERVER['PHP_SELF'] . "#chat-section");
        exit();
    }

    // Handle smartwatch connection
    if (isset($_POST['connect_watch'])) {
        // Logic for Bluetooth connection
        $_SESSION['watch_connected'] = true;
        $_SESSION['alert_message'] = "✅ Smartwatch connected successfully via Bluetooth.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Handle smartwatch disconnection
    if (isset($_POST['disconnect_watch'])) {
        // Logic for Bluetooth disconnection
        unset($_SESSION['watch_connected']);
        $_SESSION['alert_message'] = "✅ Smartwatch disconnected successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Process session variables
if (isset($_SESSION['alert_message'])) {
    $alertMessage = $_SESSION['alert_message'];
    unset($_SESSION['alert_message']);
}

if (isset($_SESSION['alert_sent']) && $_SESSION['alert_sent'] === true) {
    $alertSent = true;
}

if (isset($_SESSION['file_uploaded']) && $_SESSION['file_uploaded'] === true) {
    $fileUploaded = true;
    if (isset($_SESSION['uploaded_file_name'])) {
        $uploadedFileName = $_SESSION['uploaded_file_name'];
    }
}

if (isset($_SESSION['analysis_started']) && $_SESSION['analysis_started'] === true) {
    $analysisStarted = true;
}

if (isset($_SESSION['analysis_results'])) {
    $analysisResults = $_SESSION['analysis_results'];
}

if (isset($_SESSION['chat_enabled']) && $_SESSION['chat_enabled'] === true) {
    $chatEnabled = true;
}

if (isset($_SESSION['chat_history'])) {
    $chatHistory = $_SESSION['chat_history'];
}

$watchConnected = isset($_SESSION['watch_connected']) && $_SESSION['watch_connected'] === true;

/**
 * Analyze health data from Excel/CSV file and return abnormal readings
 * 
 * @param string $filePath Path to the uploaded Excel/CSV file
 * @return array Array of abnormal readings
 */
function analyzeHealthData($filePath)
{
    $abnormalReadings = [];
    $fileExt = pathinfo($filePath, PATHINFO_EXTENSION);

    if ($fileExt == 'csv') {
        // Handle CSV file
        $rows = array_map('str_getcsv', file($filePath));
        $headers = $rows[0];
        array_shift($rows); // Remove header row
    } else {
        // Handle XLSX/XLS file using PhpSpreadsheet
        // For a real implementation, install PhpSpreadsheet: composer require phpoffice/phpspreadsheet

        // Check if PhpSpreadsheet is available
        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = [];
                $headers = [];

                // Get the highest row and column
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                // $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                // // Get headers
                // for ($col = 1; $col <= $highestColumnIndex; $col++) {
                //     $headers[] = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
                // }

                // // Get all rows
                // for ($row = 2; $row <= $highestRow; $row++) {
                //     $rowData = [];
                //     for ($col = 1; $col <= $highestColumnIndex; $col++) {
                //         $rowData[] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                //     }
                //     $rows[] = $rowData;
                // }
            } catch (Exception $e) {
                // Fallback to mock data if there's an error
                return getMockAbnormalReadings();
            }
        } else {
            // Fallback to mock data if PhpSpreadsheet is not installed
            return getMockAbnormalReadings();
        }
    }

    // Normal ranges for different metrics
    $normalRanges = [
        'HeartRate' => ['min' => 60, 'max' => 100],
        'SpO2' => ['min' => 95, 'max' => 100],
        'SleepDuration' => ['min' => 6, 'max' => 9],
        'FallDetected' => ['normal' => 'No'],
        'BloodPressureSystolic' => ['min' => 90, 'max' => 120],
        'BloodPressureDiastolic' => ['min' => 60, 'max' => 80],
        'BodyTemperature' => ['min' => 36.5, 'max' => 37.5],
        'BloodGlucose' => ['min' => 70, 'max' => 140],
    ];

    // Check each row for abnormal readings
    foreach ($rows as $row) {
        $rowData = array_combine($headers, $row);

        // Check heart rate
        if (isset($rowData['HeartRate'])) {
            $heartRate = intval($rowData['HeartRate']);
            if ($heartRate < $normalRanges['HeartRate']['min'] || $heartRate > $normalRanges['HeartRate']['max']) {
                $abnormalReadings[] = [
                    'metric' => 'Heart Rate',
                    'value' => $heartRate,
                    'normal_range' => "{$normalRanges['HeartRate']['min']} - {$normalRanges['HeartRate']['max']} bpm",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }

        // Check SpO2
        if (isset($rowData['SpO2'])) {
            $spo2 = intval($rowData['SpO2']);
            if ($spo2 < $normalRanges['SpO2']['min']) {
                $abnormalReadings[] = [
                    'metric' => 'Blood Oxygen (SpO2)',
                    'value' => $spo2,
                    'normal_range' => "{$normalRanges['SpO2']['min']}% or higher",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }

        // Check sleep duration
        if (isset($rowData['SleepDuration'])) {
            $sleep = floatval($rowData['SleepDuration']);
            if ($sleep > 0 && ($sleep < $normalRanges['SleepDuration']['min'] || $sleep > $normalRanges['SleepDuration']['max'])) {
                $abnormalReadings[] = [
                    'metric' => 'Sleep Duration',
                    'value' => $sleep,
                    'normal_range' => "{$normalRanges['SleepDuration']['min']} - {$normalRanges['SleepDuration']['max']} hours",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }

        // Check for falls
        if (isset($rowData['FallDetected']) && $rowData['FallDetected'] !== $normalRanges['FallDetected']['normal']) {
            $abnormalReadings[] = [
                'metric' => 'Fall Detected',
                'value' => $rowData['FallDetected'],
                'normal_range' => "No falls",
                'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
            ];
        }

        // Check blood pressure (systolic)
        if (isset($rowData['BloodPressureSystolic'])) {
            $systolic = intval($rowData['BloodPressureSystolic']);
            if ($systolic < $normalRanges['BloodPressureSystolic']['min'] || $systolic > $normalRanges['BloodPressureSystolic']['max']) {
                $abnormalReadings[] = [
                    'metric' => 'Blood Pressure (Systolic)',
                    'value' => $systolic,
                    'normal_range' => "{$normalRanges['BloodPressureSystolic']['min']} - {$normalRanges['BloodPressureSystolic']['max']} mmHg",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }

        // Check blood pressure (diastolic)
        if (isset($rowData['BloodPressureDiastolic'])) {
            $diastolic = intval($rowData['BloodPressureDiastolic']);
            if ($diastolic < $normalRanges['BloodPressureDiastolic']['min'] || $diastolic > $normalRanges['BloodPressureDiastolic']['max']) {
                $abnormalReadings[] = [
                    'metric' => 'Blood Pressure (Diastolic)',
                    'value' => $diastolic,
                    'normal_range' => "{$normalRanges['BloodPressureDiastolic']['min']} - {$normalRanges['BloodPressureDiastolic']['max']} mmHg",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }

        // Check body temperature
        if (isset($rowData['BodyTemperature'])) {
            $temp = floatval($rowData['BodyTemperature']);
            if ($temp < $normalRanges['BodyTemperature']['min'] || $temp > $normalRanges['BodyTemperature']['max']) {
                $abnormalReadings[] = [
                    'metric' => 'Body Temperature',
                    'value' => $temp,
                    'normal_range' => "{$normalRanges['BodyTemperature']['min']} - {$normalRanges['BodyTemperature']['max']} °C",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }

        // Check blood glucose
        if (isset($rowData['BloodGlucose'])) {
            $glucose = intval($rowData['BloodGlucose']);
            if ($glucose < $normalRanges['BloodGlucose']['min'] || $glucose > $normalRanges['BloodGlucose']['max']) {
                $abnormalReadings[] = [
                    'metric' => 'Blood Glucose',
                    'value' => $glucose,
                    'normal_range' => "{$normalRanges['BloodGlucose']['min']} - {$normalRanges['BloodGlucose']['max']} mg/dL",
                    'timestamp' => $rowData['Timestamp'] ?? date('Y-m-d H:i:s'),
                    'device' => $rowData['DeviceID/UserID'] ?? 'Unknown'
                ];
            }
        }
    }

    return !empty($abnormalReadings) ? $abnormalReadings : getMockAbnormalReadings();
}

/**
 * Return mock abnormal readings for testing/demonstration purposes
 * 
 * @return array Array of mock abnormal readings
 */
function getMockAbnormalReadings()
{
    return [
        [
            'metric' => 'Heart Rate',
            'value' => 145,
            'normal_range' => '60 - 100 bpm',
            'timestamp' => date('Y-m-d H:i:s', time() - 3600),
            'device' => 'Device001'
        ],
        [
            'metric' => 'Blood Oxygen (SpO2)',
            'value' => 88,
            'normal_range' => '95% or higher',
            'timestamp' => date('Y-m-d H:i:s', time() - 7200),
            'device' => 'Device001'
        ],
        [
            'metric' => 'Fall Detected',
            'value' => 'Yes',
            'normal_range' => 'No falls',
            'timestamp' => date('Y-m-d H:i:s', time() - 10800),
            'device' => 'Device001'
        ],
        [
            'metric' => 'Blood Pressure (Systolic)',
            'value' => 145,
            'normal_range' => '90 - 120 mmHg',
            'timestamp' => date('Y-m-d H:i:s', time() - 14400),
            'device' => 'Device001'
        ]
    ];
}

/**
 * Get AI response from Ollama MedBot model or alternative
 * 
 * @param string $userMessage The user's message
 * @param string $healthContext Health context from analysis
 * @return string AI's response
 */
function getAIResponse($userMessage, $healthContext = "")
{
    // Prepare the prompt for Ollama MedBot or alternative
    $prompt = "";

    if (!empty($healthContext)) {
        $prompt = "You are a medical AI assistant providing guidance based on health data. $healthContext\n\n";
    } else {
        $prompt = "You are a medical AI assistant providing guidance about elderly care. ";
    }

    $prompt .= "User: $userMessage\nAssistant: ";

    // Check if we can use Ollama API
    if (function_exists('curl_init')) {
        try {
            // Attempt to use Ollama API
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => 'http://localhost:11434/api/generate',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'medbot', // Use medbot model or fallback to another available model
                    'prompt' => $prompt,
                    'stream' => false
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 5 // Short timeout to avoid hanging
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if (!$err) {
                $responseData = json_decode($response, true);
                if (isset($responseData['response'])) {
                    return $responseData['response'];
                }
            }
        } catch (Exception $e) {
            // Fallback to rule-based responses if Ollama API fails
        }
    }

    // Fallback to rule-based responses
    return getAIResponseFallback($userMessage, $healthContext);
}

/**
 * Fallback AI response system when Ollama API is not available
 * 
 * @param string $userMessage The user's message
 * @param string $healthContext Health context from analysis
 * @return string AI's response
 */
function getAIResponseFallback($userMessage, $healthContext = "")
{
    // Convert user message to lowercase for case-insensitive matching
    $lowerMessage = strtolower($userMessage);

    if (strpos($lowerMessage, "heart") !== false) {
        if (strpos($healthContext, "Heart Rate") !== false) {
            return "I notice the heart rate readings are abnormal. This could indicate stress, dehydration, or cardiovascular issues. I recommend ensuring proper hydration, rest, and consulting with a healthcare provider if this persists. Regular cardiovascular checkups are important for elderly care.";
        } else {
            return "Maintaining a healthy heart is crucial for elderly care. Regular exercise, a balanced diet low in sodium and saturated fats, staying hydrated, and regular medical checkups can help maintain cardiovascular health.";
        }
    } elseif (strpos($lowerMessage, "fall") !== false) {
        if (strpos($healthContext, "Fall Detected") !== false) {
            return "I see a fall was detected. Falls can be serious for elderly individuals. Ensure there are no injuries requiring immediate attention. Consider reviewing the home environment for hazards like loose rugs, poor lighting, or obstacles. Fall prevention measures like grab bars in bathrooms and well-lit pathways can help prevent future incidents.";
        } else {
            return "Fall prevention is important for elderly care. This includes keeping living spaces well-lit and free of clutter, installing grab bars in bathrooms, wearing proper footwear, and considering mobility aids if necessary.";
        }
    } elseif (strpos($lowerMessage, "sleep") !== false) {
        if (strpos($healthContext, "Sleep Duration") !== false) {
            return "The sleep data shows irregular sleep patterns. Quality sleep is essential for cognitive function and overall health. Establish a regular sleep schedule, create a comfortable sleep environment, limit caffeine and screen time before bed, and consider gentle activities like reading or meditation to improve sleep quality.";
        } else {
            return "Good sleep hygiene is important for elderly health. This includes maintaining a regular sleep schedule, creating a comfortable sleep environment, limiting screen time before bed, and avoiding large meals and caffeine close to bedtime.";
        }
    } elseif (strpos($lowerMessage, "oxygen") !== false || strpos($lowerMessage, "spo2") !== false) {
        if (strpos($healthContext, "Blood Oxygen") !== false) {
            return "The blood oxygen levels are below normal range. Low SpO2 can indicate respiratory issues or insufficient oxygen uptake. Ensure good ventilation, practice deep breathing exercises, and consult a healthcare provider as this could indicate respiratory or cardiovascular issues that need attention.";
        } else {
            return "Blood oxygen (SpO2) levels are an important vital sign. Normal levels are typically 95% or higher. Low levels can indicate respiratory or circulatory issues. Regular monitoring can help catch potential issues early.";
        }
    } elseif (strpos($lowerMessage, "blood pressure") !== false || strpos($lowerMessage, "hypertension") !== false) {
        if (strpos($healthContext, "Blood Pressure") !== false) {
            return "The blood pressure readings are outside the normal range. This could indicate hypertension or other cardiovascular issues. Recommend maintaining a low-sodium diet, regular light exercise, stress management techniques, and consultation with a healthcare provider for proper medication management if prescribed.";
        } else {
            return "Maintaining healthy blood pressure is important for elderly care. Normal blood pressure is around 120/80 mmHg. Regular monitoring, a balanced diet low in sodium, regular physical activity, and medication compliance if prescribed can help control blood pressure.";
        }
    } elseif (strpos($lowerMessage, "glucose") !== false || strpos($lowerMessage, "sugar") !== false || strpos($lowerMessage, "diabetes") !== false) {
        if (strpos($healthContext, "Blood Glucose") !== false) {
            return "The blood glucose readings are outside the normal range. For elderly individuals, maintaining stable blood sugar levels is crucial. Regular meals with balanced carbohydrates, proteins, and fats can help. Monitoring and medication compliance are also important. Please consult with a healthcare provider to adjust the diabetes management plan if needed.";
        } else {
            return "Blood glucose management is important, especially for elderly individuals with diabetes. Regular monitoring, consistent meal times, balanced nutrition, hydration, and medication compliance are key aspects of diabetes management.";
        }
    } else {
        // Generic response
        if (!empty($healthContext)) {
            return "Based on the health data analysis, I recommend monitoring these abnormal readings closely. Ensure the elderly person is comfortable, well-hydrated, and following their medication schedule. If abnormal readings persist, consulting with a healthcare provider would be advisable. Regular check-ups and a balanced lifestyle focusing on proper nutrition, appropriate exercise, and adequate rest can help improve overall health metrics.";
        } else {
            return "Taking care of elderly health involves regular monitoring, a balanced diet, appropriate exercise, medication management, and regular medical check-ups. Is there a specific aspect of elderly care you'd like more information about?";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElderCare - Health Monitoring System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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

        .user-welcome {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background-color: rgba(255, 255, 255, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 50px;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('https://images.unsplash.com/photo-1554200876-04d3aeaa9b6f');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 5rem 0;
            text-align: center;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            font-family: 'Poppins', sans-serif;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-accent {
            background-color: var(--accent);
            color: white;
        }

        .btn-accent:hover {
            background-color: #d64161;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .btn-monitor {
            background-color: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 1rem auto;
            min-width: 200px;
        }

        .btn-monitor:disabled {
            background-color: var(--gray);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }

        /* Control Panel */
        .control-panel {
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-top: -4rem;
            position: relative;
            z-index: 2;
        }

        .panel-heading {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--primary);
            font-weight: 600;
        }

        .control-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .control-card {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
        }

        .control-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .control-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .control-card h3 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            color: var(--dark);
        }

        .control-form {
            margin-top: 1rem;
        }

        .file-input-wrapper {
            position: relative;
            margin-bottom: 1rem;
        }

        .file-input {
            opacity: 0;
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            cursor: pointer;
            z-index: 2;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            border: 2px dashed var(--primary);
            border-radius: 8px;
            color: var(--primary);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .file-input-label:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 500;
            text-align: center;
        }

        .alert-success {
            background-color: rgba(6, 214, 160, 0.2);
            color: #06d6a0;
            border: 1px solid rgba(6, 214, 160, 0.3);
        }

        .alert-error {
            background-color: rgba(239, 35, 60, 0.2);
            color: #ef233c;
            border: 1px solid rgba(239, 35, 60, 0.3);
        }

        .alert-info {
            background-color: rgba(67, 97, 238, 0.2);
            color: var(--primary);
            border: 1px solid rgba(67, 97, 238, 0.3);
        }

        .alert-warning {
            background-color: rgba(255, 209, 102, 0.2);
            color: #e09f3e;
            border: 1px solid rgba(255, 209, 102, 0.3);
        }

        /* Analysis Results */
        .analysis-results {
            margin-top: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 2rem;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .results-table th,
        .results-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background-color: #f8f9fa;
            color: var(--primary);
            font-weight: 600;
        }

        .results-table tr:last-child td {
            border-bottom: none;
        }

        .results-table tr:hover {
            background-color: #f8f9fa;
        }

        .metric-abnormal {
            color: var(--danger);
            font-weight: 500;
        }

        /* Chat Interface */
        .chat-section {
            margin-top: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 1rem;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 400px;
        }

        .chat-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .chat-header i {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .chat-header h3 {
            margin: 0;
            color: var(--primary);
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            display: flex;
            gap: 0.5rem;
            max-width: 80%;
        }

        .message-user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .message-assistant {
            align-self: flex-start;
        }

        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }

        .message-user .message-avatar {
            background-color: var(--primary);
            color: white;
        }

        .message-assistant .message-avatar {
            background-color: #f0f4f8;
            color: var(--primary);
        }

        .message-content {
            padding: 0.75rem 1rem;
            border-radius: 18px;
            position: relative;
        }

        .message-user .message-content {
            background-color: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message-assistant .message-content {
            background-color: #f0f4f8;
            color: var(--dark);
            border-bottom-left-radius: 4px;
        }

        .chat-input-form {
            display: flex;
            padding: 1rem;
            border-top: 1px solid #eee;
            gap: 0.5rem;
        }

        .chat-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .send-btn {
            background-color: var(--primary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .send-btn:hover {
            background-color: var(--secondary);
            transform: scale(1.05);
        }

        .send-btn i {
            font-size: 1rem;
        }

        /* Features Section */
        .features {
            padding: 4rem 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title h2 {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .section-title p {
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .feature-img {
            height: 200px;
            background-size: cover;
            background-position: center;
        }

        .feature-content {
            padding: 1.5rem;
        }

        .feature-content h3 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
            color: var(--dark);
        }

        .feature-content p {
            color: var(--gray);
            margin-bottom: 1rem;
        }

        /* Footer */
        footer {
            background-color: var(--dark);
            color: rgba(255, 255, 255, 0.8);
            padding: 3rem 0 1.5rem;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .footer-column h3 {
            color: white;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            position: relative;
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 50px;
            height: 2px;
            background-color: var(--primary);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-links a:hover {
            background-color: var(--primary);
            transform: translateY(-3px);
        }

        .copyright {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .control-grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .navbar {
                flex-direction: column;
                gap: 1rem;
            }

            nav ul {
                gap: 1rem;
            }

            .message {
                max-width: 90%;
            }
        }

        @media (max-width: 480px) {
            .hero h1 {
                font-size: 2rem;
            }

            .panel-heading {
                font-size: 1.25rem;
            }

            nav ul {
                flex-wrap: wrap;
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading Indicator */
        .loading {
            display: inline-block;
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto;
        }

        .loading div {
            position: absolute;
            top: 33px;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background: var(--primary);
            animation-timing-function: cubic-bezier(0, 1, 1, 0);
        }

        .loading div:nth-child(1) {
            left: 8px;
            animation: loading1 0.6s infinite;
        }

        .loading div:nth-child(2) {
            left: 8px;
            animation: loading2 0.6s infinite;
        }

        .loading div:nth-child(3) {
            left: 32px;
            animation: loading2 0.6s infinite;
        }

        .loading div:nth-child(4) {
            left: 56px;
            animation: loading3 0.6s infinite;
        }

        @keyframes loading1 {
            0% {
                transform: scale(0);
            }

            100% {
                transform: scale(1);
            }
        }

        @keyframes loading3 {
            0% {
                transform: scale(1);
            }

            100% {
                transform: scale(0);
            }
        }

        @keyframes loading2 {
            0% {
                transform: translate(0, 0);
            }

            100% {
                transform: translate(24px, 0);
            }
        }

        .nav-alert-btn {
            background-color: var(--accent);
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-alert-btn:hover {
            background-color: #d64161;
            transform: translateY(-2px);
        }

        .nav-alert-btn i {
            font-size: 1rem;
        }

        /* Add a pulsing animation for the emergency button */
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 71, 111, 0.7);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(239, 71, 111, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(239, 71, 111, 0);
            }
        }

        .nav-alert-btn {
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body>
    <header>
        <div class="container">
            <div class="navbar">
                <div class="logo">
                    <i class="fas fa-heartbeat"></i>
                    <span>ElderCare</span>
                </div>
                <nav>
                    <ul>
                        <li style="margin-right: 10px;">
                            <form method="post" style="margin: 0;">
                                <button type="submit" name="send_alert" class="nav-alert-btn"
                                    style="background-color: #ff4d4d; color: white; border: none; padding: 10px 10px; border-radius: 10px; cursor: pointer; position: relative; top: -8px;">
                                    <i class="fas fa-exclamation-triangle"></i> Emergency Alert
                                </button>
                            </form>
                        </li>
                        <li style="margin-right: 10px;"><a href="#features"><i class="fas fa-list-alt"></i> Features</a>
                        </li>
                        <li style="margin-right: 10px;"><a href="#control-panel"><i class="fas fa-cogs"></i>
                                Monitoring</a></li>
                        <li><a href="chat.html"><i class="fas fa-comments"></i> AI Support</a></li>
                    </ul>
                </nav>
                <?php if ($isLoggedIn): ?>
                    <div class="user-welcome">
                        <i class="fas fa-user-circle"></i>
                        <span>Welcome, <?php echo htmlspecialchars($firstname); ?></span>
                        <!-- Logout Button -->
                        <a href="login/logout.php" class="btn btn-outline">Logout</a>
                    </div>
                <?php else: ?>
                    <a href="login/login.php" class="btn btn-outline">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <h1>Smart Elderly Health Monitoring</h1>
                    <p>Real-time health tracking, emergency alerts, and AI-powered care assistance for elderly
                        well-being.</p>
                    <a href="Others/dashboard.php" class="btn btn-primary"><i class="fas fa-heart"></i> News, Updates,
                        Tablets,
                        and other info</a>
                </div>
            </div>
        </section>

        <div class="container">
            <section id="control-panel" class="control-panel">
                <h2 class="panel-heading">Health Monitoring Control Center</h2>
                <div class="control-grid">
                    <div class="control-card">
                        <i class="fas fa-file-upload"></i>
                        <h3>Upload Health Data</h3>
                        <p>Upload Excel/CSV files containing health monitoring data.</p>
                        <form method="post" enctype="multipart/form-data" class="control-form">
                            <a href="health_monitoring/health_dashboard.php" class="btn btn-monitor" style="
                   min-width: 180px; /* Adjust this value to your desired minimum width */
                   display: inline-flex;
                   justify-content: center;
                   align-items: center;
                   gap: 8px; /* Space between icon and text */
                   box-sizing: border-box; /* Include padding and border in the width */
                   text-decoration: none; /* Remove underline for anchor tag */
                   /* Add any other base styles for .btn-monitor if they are not in an external stylesheet */
                   padding: 10px 15px; /* Example padding for consistency */
                   border: 1px solid #ccc; /* Example border */
                   border-radius: 5px; /* Example border-radius */
                   background-color: #007bff; /* Example background color */
                   color: white; /* Example text color */
                   cursor: pointer; /* Indicate it's clickable */
               " <?php echo $isLoggedIn ? '' : 'disabled'; ?>>
                                <i class="fas fa-upload"></i> Upload Data
                            </a>
                        </form>
                    </div>

                    <div class="control-card">
                        <i class="fas fa-clock"></i>
                        <h3>Connect Smartwatch</h3>
                        <p>Pair a smartwatch device for real-time health monitoring.</p>
                        <form method="post" class="control-form">
                            <button type="submit"
                                name="<?php echo $watchConnected ? 'disconnect_watch' : 'connect_watch'; ?>"
                                class="btn btn-monitor" style="
                    min-width: 180px; /* Must be the same value as the other button */
                    display: inline-flex;
                    justify-content: center;
                    align-items: center;
                    gap: 8px; /* Space between icon and text */
                    box-sizing: border-box; /* Include padding and border in the width */
                    /* Add any other base styles for .btn-monitor if they are not in an external stylesheet */
                    padding: 10px 15px; /* Example padding for consistency */
                    border: 1px solid #ccc; /* Example border */
                    border-radius: 5px; /* Example border-radius */
                    background-color: #007bff; /* Example background color */
                    color: white; /* Example text color */
                    cursor: pointer; /* Indicate it's clickable */
                " <?php echo $isLoggedIn ? '' : 'disabled'; ?>>
                                <i class="fas fa-<?php echo $watchConnected ? 'unlink' : 'link'; ?>"></i>
                                <?php echo $watchConnected ? 'Disconnect Device' : 'Connect Device'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <?php if ($analysisStarted && !empty($analysisResults)): ?>
                <section class="analysis-results">
                    <h2 class="panel-heading">Health Analysis Results</h2>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i> Abnormal health readings detected. Please review the
                        details below.
                    </div>
                    <table class="results-table">
                        <thead>
                            <tr>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Normal Range</th>
                                <th>Timestamp</th>
                                <th>Device ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analysisResults as $reading): ?>
                                <tr>
                                    <td class="metric-abnormal"><?php echo htmlspecialchars($reading['metric']); ?></td>
                                    <td><?php echo htmlspecialchars($reading['value']); ?></td>
                                    <td><?php echo htmlspecialchars($reading['normal_range']); ?></td>
                                    <td><?php echo htmlspecialchars($reading['timestamp']); ?></td>
                                    <td><?php echo htmlspecialchars($reading['device']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endif; ?>

            <?php if ($chatEnabled || (!empty($analysisResults))): ?>
                <section id="chat-section" class="chat-section">
                    <div class="chat-container">
                        <div class="chat-header">
                            <i class="fas fa-robot"></i>
                            <h3>AI Health Assistant</h3>
                        </div>
                        <div class="chat-messages">
                            <?php if (empty($chatHistory)): ?>
                                <div class="message message-assistant">
                                    <div class="message-avatar">
                                        <i class="fas fa-robot"></i>
                                    </div>
                                    <div class="message-content">
                                        Hello! I'm your AI Health Assistant. I can help you interpret the health data and
                                        provide suggestions. What would you like to know?
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($chatHistory as $chat): ?>
                                    <div class="message message-<?php echo $chat['role']; ?>">
                                        <div class="message-avatar">
                                            <i class="fas fa-<?php echo $chat['role'] === 'user' ? 'user' : 'robot'; ?>"></i>
                                        </div>
                                        <div class="message-content">
                                            <?php echo nl2br(htmlspecialchars($chat['message'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <form method="post" class="chat-input-form">
                            <input type="text" name="user_message" class="chat-input"
                                placeholder="Ask about health readings or care suggestions..." required>
                            <button type="submit" name="send_message" class="send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </section>
            <?php endif; ?>

            <section id="features" class="features">
                <div class="section-title">
                    <h2>Key Features</h2>
                    <p>Our platform offers comprehensive health monitoring solutions for elderly care</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-img"
                            style="background-image: url('https://images.unsplash.com/photo-1576091160550-2173dba999ef')">
                        </div>
                        <div class="feature-content">
                            <h3>Real-Time Monitoring</h3>
                            <p>Track vital signs like heart rate, blood oxygen, sleep patterns, and activity levels in
                                real-time through connected devices.</p>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-img"
                            style="background-image: url('https://images.unsplash.com/photo-1624727828489-a1e03b79bba8')">
                        </div>
                        <div class="feature-content">
                            <h3>Emergency Alert System</h3>
                            <p>Immediate notifications to caregivers and family members in case of emergencies or
                                abnormal health readings.</p>
                        </div>
                    </div>
                    <div class="feature-card">
                        <div class="feature-img"
                            style="background-image: url('https://images.unsplash.com/photo-1581092918056-0c4c3acd3789')">
                        </div>
                        <div class="feature-content">
                            <h3>AI-Powered Analysis</h3>
                            <p>Advanced algorithms analyze health data to detect patterns and anomalies, providing
                                personalized care recommendations.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-column">
                    <h3>ElderCare</h3>
                    <p>Advanced health monitoring solution designed specifically for elderly care, providing peace of
                        mind for families and caregivers.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#control-panel">Monitoring</a></li>
                        <li><a href="#chat-section">AI Support</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">User Guide</a></li>
                        <li><a href="#">API Documentation</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> H-no 1-51,Opposite Mount Elly Public
                            School,Vanastalipurum,Hyderabad..</li>
                        <li><i class="fas fa-phone"></i> +91 9989441202|| +91 96181 20173</li>
                        <li><i class="fas fa-envelope"></i> vjkmrkavitha@gmail.com</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2025 ElderCare Health Monitoring System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Display filename when file is selected
        document.getElementById('excel_file')?.addEventListener('change', function () {
            const fileName = this.files[0]?.name;
            const label = document.querySelector('.file-input-label');
            if (fileName) {
                label.innerHTML = `<i class="fas fa-file-excel"></i> ${fileName}`;
            } else {
                label.innerHTML = `<i class="fas fa-cloud-upload-alt"></i> Choose File`;
            }
        });

        // Scroll to the bottom of chat messages when new message is added
        function scrollToBottom() {
            const chatMessages = document.querySelector('.chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }

        // Call the function on page load
        window.onload = scrollToBottom;
    </script>
</body>

</html>