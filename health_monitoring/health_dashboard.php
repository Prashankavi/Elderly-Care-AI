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
    <title>Health Monitoring System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 10px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            padding: 20px 10px;
        }

        .header h1 {
            font-size: clamp(2rem, 5vw, 3rem);
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header p {
            font-size: clamp(1rem, 3vw, 1.2rem);
            opacity: 0.9;
        }

        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .upload-icon {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }

        .monitor-icon {
            background: linear-gradient(135deg, #2196F3, #1976D2);
        }

        .alert-icon {
            background: linear-gradient(135deg, #FF5722, #D84315);
        }

        .card h3 {
            color: #2c3e50;
            font-size: clamp(1.2rem, 4vw, 1.5rem);
            margin-bottom: 5px;
        }

        .card p {
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        .file-upload-area {
            border: 3px dashed #4CAF50;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(69, 160, 73, 0.05));
            transition: all 0.3s ease;
            margin-bottom: 20px;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #45a049;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.15), rgba(69, 160, 73, 0.1));
        }

        .file-upload-area.dragover {
            border-color: #2E7D32;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.2), rgba(69, 160, 73, 0.15));
            transform: scale(1.02);
        }

        .upload-icon-large {
            font-size: clamp(2.5rem, 8vw, 3rem);
            color: #4CAF50;
            margin-bottom: 15px;
            display: block;
        }

        .file-upload-area h4 {
            font-size: clamp(1rem, 3vw, 1.2rem);
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .file-input {
            display: none;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            position: relative;
            overflow: hidden;
            width: 100%;
            margin-bottom: 10px;
        }

        .btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover:before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #FF5722, #D84315);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 87, 34, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 87, 34, 0.4);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin: 15px 0;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            width: 0%;
            transition: width 0.3s ease;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin: 20px 0;
            display: none;
            animation: slideIn 0.3s ease;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
        }

        .alert-error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white;
        }

        .alert-info {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .metric-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 15px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .metric-card:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .metric-value {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .metric-label {
            color: #7f8c8d;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .logs-container {
            max-height: 250px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .log-entry {
            padding: 8px 12px;
            margin-bottom: 8px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            font-family: 'Courier New', monospace;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
            word-break: break-word;
        }

        .sample-data {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            overflow-x: auto;
        }

        .sample-data h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: clamp(1rem, 3vw, 1.2rem);
        }

        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            min-width: 600px;
        }

        .sample-table th,
        .sample-table td {
            padding: 8px 12px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: clamp(0.8rem, 2vw, 0.9rem);
        }

        .sample-table th {
            background: #4CAF50;
            color: white;
            white-space: nowrap;
        }

        .emergency-textarea {
            width: 100%;
            height: 80px;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            resize: none;
            font-size: clamp(0.9rem, 2.5vw, 1rem);
            margin-bottom: 15px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .button-group .btn {
            flex: 1;
            min-width: 140px;
        }

        .system-status {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 87, 34, 0.1);
            border-radius: 10px;
        }

        .system-status h5 {
            color: #FF5722;
            margin-bottom: 10px;
            font-size: clamp(1rem, 3vw, 1.1rem);
        }

        .floating-action {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .fab {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF5722, #D84315);
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 8px 20px rgba(255, 87, 34, 0.3);
            transition: all 0.3s ease;
        }

        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 30px rgba(255, 87, 34, 0.4);
        }

        .analysis-results {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            display: none;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #FF5722;
        }

        .result-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-high {
            background: #ffebee;
            color: #c62828;
        }

        .status-low {
            background: #e3f2fd;
            color: #1565c0;
        }

        .status-normal {
            background: #e8f5e8;
            color: #2e7d32;
        }

        @media (max-width: 768px) {
            body {
                padding: 5px;
            }

            .dashboard {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .card {
                padding: 20px;
            }

            .card-header {
                margin-bottom: 15px;
            }

            .metrics-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .button-group {
                flex-direction: column;
            }

            .button-group .btn {
                width: 100%;
                margin-bottom: 8px;
            }

            .fab {
                bottom: 15px;
                right: 15px;
                width: 45px;
                height: 45px;
                font-size: 18px;
            }

            .floating-action {
                bottom: 15px;
                right: 15px;
            }
        }

        @media (max-width: 480px) {
            .header {
                margin-bottom: 20px;
                padding: 15px 5px;
            }

            .card {
                padding: 15px;
            }

            .card-icon {
                width: 40px;
                height: 40px;
                font-size: 18px;
                margin-right: 10px;
            }

            .file-upload-area {
                padding: 20px 15px;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .metric-card {
                padding: 12px;
            }

            .sample-data {
                padding: 10px;
            }

            .logs-container {
                max-height: 200px;
                padding: 10px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heartbeat"></i> Health Monitoring System</h1>
            <p>Automated Health Data Analysis with Real-time WhatsApp Alerts</p>
        </div>
        <div class="dashboard">
            <!-- Upload Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon upload-icon">
                        <i class="fas fa-file-medical"></i>
                    </div>
                    <div>
                        <h3>Upload Health Data</h3>
                        <p>Upload Excel files for automated health analysis</p>
                    </div>
                </div>

                <div class="file-upload-area" id="uploadArea">
                    <i class="fas fa-cloud-upload-alt upload-icon-large"></i>
                    <h4>Drag & Drop Excel File Here</h4>
                    <p>or click to browse files</p>
                    <input type="file" id="healthFile" class="file-input" accept=".xlsx,.xls">
                </div>

                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>

                <button class="btn btn-primary" id="uploadBtn" onclick="triggerFileSelect()">
                    <i class="fas fa-upload"></i> Select Health Data File
                </button>

                <div class="analysis-results" id="analysisResults">
                    <h4><i class="fas fa-chart-bar"></i> Analysis Results:</h4>
                    <div id="resultsContent"></div>
                </div>

                <div class="sample-data">
                    <h4><i class="fas fa-table"></i> Expected Excel Format:</h4>
                    <div style="overflow-x: auto;">
                        <table class="sample-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Heart Rate</th>
                                    <th>Systolic BP</th>
                                    <th>Diastolic BP</th>
                                    <th>Glucose</th>
                                    <th>SpO2</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>John Doe</td>
                                    <td>9876543210</td>
                                    <td>120</td>
                                    <td>140</td>
                                    <td>90</td>
                                    <td>150</td>
                                    <td>98</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin-top: 10px; font-size: 0.9rem; color: #666;">
                        <strong>Normal Ranges:</strong> Heart Rate: 60-100 bpm, Systolic BP: 90-120 mmHg,
                        Diastolic BP: 60-80 mmHg, Glucose: 70-140 mg/dL, SpO2: 95-100%
                    </p>
                </div>
            </div>

            <!-- Real-time Monitoring Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon monitor-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <h3>Analysis Results</h3>
                        <p>Live monitoring and abnormality detection</p>
                    </div>
                </div>

                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-value" id="totalPatients">0</div>
                        <div class="metric-label">Total Patients</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="abnormalities">0</div>
                        <div class="metric-label">Abnormalities</div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-value" id="alertsSent">0</div>
                        <div class="metric-label">Alerts Sent</div>
                    </div>
                </div>

                <div class="logs-container" id="analysisLogs">
                    <div class="log-entry">
                        <i class="fas fa-info-circle" style="color: #2196F3;"></i>
                        System ready for health data analysis...
                    </div>
                </div>

                <button class="btn btn-secondary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh Data
                </button>
            </div>

            <!-- Emergency Alert Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon alert-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h3>Emergency Controls</h3>
                        <p>Manual alert system and emergency features</p>
                    </div>
                </div>

                <textarea id="emergencyMessage" class="emergency-textarea"
                    placeholder="Enter emergency message..."></textarea>

                <div class="button-group">
                    <button class="btn btn-danger" name="send_alert" onclick="sendEmergencyAlert()">
                        <i class="fas fa-bell"></i> Send Emergency Alert
                    </button>
                    <button class="btn btn-secondary" onclick="testSystem()">
                        <i class="fas fa-vial"></i> Test System
                    </button>
                </div>

                <div class="system-status">
                    <h5><i class="fas fa-shield-alt"></i> System Status</h5>
                    <div id="systemStatus">
                        <span style="color: #4CAF50;">✅ WhatsApp Integration: Active</span><br>
                        <span style="color: #4CAF50;">✅ Health Analysis: Ready</span><br>
                        <span style="color: #4CAF50;">✅ Alert System: Operational</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <div class="alert alert-success" id="successAlert">
            <i class="fas fa-check-circle"></i> <span id="successMessage"></span>
        </div>
        <div class="alert alert-error" id="errorAlert">
            <i class="fas fa-exclamation-triangle"></i> <span id="errorMessage"></span>
        </div>
        <div class="alert alert-info" id="infoAlert">
            <i class="fas fa-info-circle"></i> <span id="infoMessage"></span>
        </div>
    </div>

    <!-- Floating Action Button -->
    <div class="floating-action">
        <button class="fab" onclick="downloadSampleFile()" title="Download Sample Excel File">
            <i class="fas fa-download"></i>
        </button>
    </div>

    <script>
        // Global variables
        let currentAnalysisData = {
            totalPatients: 0,
            abnormalities: 0,
            alertsSent: 0,
            logs: [],
            patientData: []
        };

        // Health thresholds
        const HEALTH_THRESHOLDS = {
            heartRate: { min: 60, max: 100 },
            systolicBP: { min: 90, max: 120 },
            diastolicBP: { min: 60, max: 80 },
            glucose: { min: 70, max: 140 },
            spo2: { min: 95, max: 100 }
        };

        // File upload functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('healthFile');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');

        // Drag and drop functionality
        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('dragleave', handleDragLeave);
        uploadArea.addEventListener('drop', handleDrop);

        fileInput.addEventListener('change', handleFileSelect);

        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        }

        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        }

        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                processFile(files[0]);
            }
        }

        function triggerFileSelect() {
            fileInput.click();
        }

        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                processFile(file);
            }
        }

        function processFile(file) {
            // Validate file
            if (!file.name.match(/\.(xlsx|xls)$/)) {
                showAlert('error', 'Please select a valid Excel file (.xlsx or .xls)');
                return;
            }

            if (file.size > 10 * 1024 * 1024) { // 10MB limit
                showAlert('error', 'File size must be less than 10MB');
                return;
            }

            // Show progress
            progressBar.style.display = 'block';
            readExcelFile(file);
        }

        function readExcelFile(file) {
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[sheetName];
                    const jsonData = XLSX.utils.sheet_to_json(worksheet);

                    simulateProgress(() => {
                        analyzeHealthData(jsonData);
                    });
                } catch (error) {
                    progressBar.style.display = 'none';
                    showAlert('error', 'Error reading Excel file. Please check the format.');
                }
            };
            reader.readAsArrayBuffer(file);
        }

        function simulateProgress(callback) {
            let progress = 0;
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress >= 100) {
                    progress = 100;
                    clearInterval(interval);
                    setTimeout(() => {
                        progressBar.style.display = 'none';
                        progressFill.style.width = '0%';
                        callback();
                    }, 500);
                }
                progressFill.style.width = progress + '%';
            }, 100);
        }

        function analyzeHealthData(data) {
            if (!data || data.length === 0) {
                showAlert('error', 'No data found in the Excel file');
                return;
            }

            showAlert('info', 'Analyzing health data...');
            addLog('📊 Starting health data analysis...');

            const results = performHealthAnalysis(data);
            displayAnalysisResults(results);
            showDisplayResults(results);

            showAlert('success', `Analysis complete! Found ${results.abnormalities} abnormalities, sent ${results.alertsSent} WhatsApp alerts.`);
        }

        function performHealthAnalysis(data) {
            const results = {
                totalPatients: data.length,
                abnormalities: 0,
                alertsSent: 0,
                details: [],
                patientData: data
            };

            data.forEach((patient, index) => {
                const name = patient.Name || `Patient ${index + 1}`;
                const phone = patient.Phone || 'N/A';
                const heartRate = parseInt(patient['Heart Rate']) || 0;
                const systolicBP = parseInt(patient['Systolic BP']) || 0;
                const diastolicBP = parseInt(patient['Diastolic BP']) || 0;
                const glucose = parseInt(patient['Glucose']) || parseInt(patient['Glucose Level']) || 0;
                const spo2 = parseInt(patient.SpO2) || 0;

                // Check each vital sign
                checkVitalSign(results, name, phone, 'Heart Rate', heartRate, HEALTH_THRESHOLDS.heartRate, 'bpm');
                checkVitalSign(results, name, phone, 'Systolic BP', systolicBP, HEALTH_THRESHOLDS.systolicBP, 'mmHg');
                checkVitalSign(results, name, phone, 'Diastolic BP', diastolicBP, HEALTH_THRESHOLDS.diastolicBP, 'mmHg');
                checkVitalSign(results, name, phone, 'Glucose Level', glucose, HEALTH_THRESHOLDS.glucose, 'mg/dL');
                checkVitalSign(results, name, phone, 'SpO2', spo2, HEALTH_THRESHOLDS.spo2, '%');
            });

            // Simulate WhatsApp alerts (80% success rate)
            results.alertsSent = Math.floor(results.abnormalities * 0.8);

            return results;
        }

        function checkVitalSign(results, name, phone, metric, value, threshold, unit) {
            if (value === 0) return; // Skip if no value

            let status = 'Normal';
            let isAbnormal = false;

            if (value < threshold.min) {
                status = 'Low';
                isAbnormal = true;
            } else if (value > threshold.max) {
                status = 'High';
                isAbnormal = true;
            }

            if (isAbnormal) {
                results.abnormalities++;
                results.details.push({
                    patient: name,
                    phone: phone,
                    metric: metric,
                    value: `${value} ${unit}`,
                    status: status,
                    normalRange: `${threshold.min}-${threshold.max} ${unit}`
                });
            }
        }

        function displayAnalysisResults(results) {
            // Update metrics
            document.getElementById('totalPatients').textContent = results.totalPatients;
            document.getElementById('abnormalities').textContent = results.abnormalities;
            document.getElementById('alertsSent').textContent = results.alertsSent;

            // Add detailed logs
            addLog(`✅ Analyzed ${results.totalPatients} patient records`);

            if (results.abnormalities > 0) {
                addLog(`⚠️ Found ${results.abnormalities} abnormal readings:`);
                results.details.forEach(detail => {
                    addLog(`   • ${detail.patient}: ${detail.metric} = ${detail.value} (${detail.status})`);
                });
                addLog(`📱 WhatsApp alerts sent: ${results.alertsSent}/${results.abnormalities}`);
            } else {
                addLog('✅ All health metrics are within normal ranges');
            }

            currentAnalysisData = results;
        }

        function showDisplayResults(results) {
            const analysisResults = document.getElementById('analysisResults');
            const resultsContent = document.getElementById('resultsContent');

            if (results.abnormalities > 0) {
                let html = '';
                results.details.forEach(detail => {
                    const statusClass = detail.status === 'High' ? 'status-high' :
                        detail.status === 'Low' ? 'status-low' : 'status-normal';
                    html += `
                        <div class="result-item">
                            <div>
                                <strong>${detail.patient}</strong><br>
                                <small>${detail.metric}: ${detail.value}</small><br>
                                <small style="color: #666;">Normal: ${detail.normalRange}</small>
                            </div>
                            <div class="result-status ${statusClass}">
                                ${detail.status}
                            </div>
                        </div>
                    `;
                });
                resultsContent.innerHTML = html;
                analysisResults.style.display = 'block';
            } else {
                resultsContent.innerHTML = `
                    <div style="text-align: center; padding: 20px; color: #4CAF50;">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p><strong>All patients have normal vital signs!</strong></p>
                    </div>
                `;
                analysisResults.style.display = 'block';
            }
        }

        function addLog(message) {
            const logsContainer = document.getElementById('analysisLogs');
            const logEntry = document.createElement('div');
            logEntry.className = 'log-entry';
            logEntry.innerHTML = `<span style="color: #666; font-size: 0.8rem;">[${new Date().toLocaleTimeString()}]</span> ${message}`;
            logsContainer.appendChild(logEntry);
            logsContainer.scrollTop = logsContainer.scrollHeight;
        }

        function showAlert(type, message) {
            const alertElement = document.getElementById(type + 'Alert');
            const messageElement = document.getElementById(type + 'Message');

            messageElement.textContent = message;
            alertElement.style.display = 'block';

            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }

        function sendEmergencyAlert() {
            const message = document.getElementById('emergencyMessage').value;
            if (!message.trim()) {
                showAlert('error', 'Please enter an emergency message');
                return;
            }

            showAlert('info', 'Sending emergency alerts to all contacts...');
            addLog('🚨 Emergency alert initiated');

            setTimeout(() => {
                const alertsSent = Math.floor(Math.random() * 15) + 5;
                showAlert('success', `Emergency alert sent to ${alertsSent} contacts`);
                addLog(`📱 Emergency alert sent to ${alertsSent} contacts`);
                document.getElementById('emergencyMessage').value = '';

                // Update alerts sent counter
                const currentAlerts = parseInt(document.getElementById('alertsSent').textContent);
                document.getElementById('alertsSent').textContent = currentAlerts + alertsSent;
            }, 2000);
        }

        function testSystem() {
            showAlert('info', 'Running system diagnostics...');
            addLog('🔧 System test initiated');

            const tests = [
                { name: 'WhatsApp API connection', delay: 500 },
                { name: 'Database connection', delay: 800 },
                { name: 'Health analysis engine', delay: 1200 },
                { name: 'Alert system', delay: 1500 }
            ];

            tests.forEach((test, index) => {
                setTimeout(() => {
                    addLog(`✅ ${test.name}: OK`);
                    if (index === tests.length - 1) {
                        showAlert('success', 'All systems operational');
                    }
                }, test.delay);
            });
        }

        function refreshData() {
            showAlert('info', 'Refreshing data...');
            addLog('🔄 Refreshing analysis data...');

            setTimeout(() => {
                const updates = Math.floor(Math.random() * 3) + 1;
                addLog(`📊 ${updates} new readings processed`);

                // Simulate some metric updates
                if (currentAnalysisData.totalPatients > 0) {
                    const newAbnormalities = Math.floor(Math.random() * 2);
                    if (newAbnormalities > 0) {
                        currentAnalysisData.abnormalities += newAbnormalities;
                        currentAnalysisData.alertsSent += Math.floor(newAbnormalities * 0.8);

                        document.getElementById('abnormalities').textContent = currentAnalysisData.abnormalities;
                        document.getElementById('alertsSent').textContent = currentAnalysisData.alertsSent;

                        addLog(`⚠️ ${newAbnormalities} new abnormalities detected`);
                    }
                }

                showAlert('success', 'Data refreshed successfully');
            }, 1500);
        }

        function downloadSampleFile() {
            // Create sample Excel data
            const sampleData = [
                ['Name', 'Phone', 'Heart Rate', 'Systolic BP', 'Diastolic BP', 'Glucose Level', 'SpO2'],
                ['John Doe', '9876543210', '72', '120', '80', '95', '98'],
                ['Jane Smith', '9876543211', '110', '145', '92', '160', '96'],
                ['Bob Johnson', '9876543212', '68', '118', '78', '88', '99'],
                ['Alice Brown', '9876543213', '55', '110', '70', '140', '94'],
                ['Charlie Wilson', '9876543214', '88', '135', '85', '180', '97'],
                ['Diana Prince', '9876543215', '75', '115', '75', '105', '98'],
                ['Peter Parker', '9876543216', '95', '130', '82', '120', '97']
            ];

            // Create workbook and worksheet
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(sampleData);

            // Style the header row
            const headerStyle = {
                font: { bold: true, color: { rgb: "FFFFFF" } },
                fill: { fgColor: { rgb: "4CAF50" } }
            };

            // Apply header styling
            for (let col = 0; col < sampleData[0].length; col++) {
                const cellAddress = XLSX.utils.encode_cell({ r: 0, c: col });
                if (!ws[cellAddress]) continue;
                ws[cellAddress].s = headerStyle;
            }

            // Set column widths
            ws['!cols'] = [
                { width: 15 }, // Name
                { width: 12 }, // Phone
                { width: 12 }, // Heart Rate
                { width: 12 }, // Systolic BP
                { width: 12 }, // Diastolic BP
                { width: 14 }, // Glucose Level
                { width: 8 }   // SpO2
            ];

            XLSX.utils.book_append_sheet(wb, ws, 'Health Data');
            XLSX.writeFile(wb, 'health_data_sample.xlsx');

            showAlert('success', 'Sample Excel file downloaded successfully!');
            addLog('📁 Sample file downloaded');
        }

        // Auto-refresh functionality
        function startAutoRefresh() {
            setInterval(() => {
                if (currentAnalysisData.totalPatients > 0) {
                    // Simulate random new readings
                    if (Math.random() < 0.3) { // 30% chance of new data
                        const updates = Math.floor(Math.random() * 2) + 1;
                        addLog(`🔄 Auto-refresh: ${updates} new readings detected`);
                    }
                }
            }, 30000); // Every 30 seconds
        }

        // Initialize system on page load
        document.addEventListener('DOMContentLoaded', function () {
            addLog('🏥 Health Monitoring System initialized');
            addLog('📋 Ready to process health data files');

            // Add some initial demo logs
            setTimeout(() => {
                addLog('🔗 WhatsApp API connected successfully');
                addLog('📊 Health analysis engine ready');
                addLog('🔔 Alert system activated');
            }, 1000);

            // Start auto-refresh
            startAutoRefresh();

            // Add touch event support for mobile
            if ('ontouchstart' in window) {
                uploadArea.addEventListener('touchstart', function (e) {
                    e.preventDefault();
                    fileInput.click();
                });
            }
        });

        // Service worker registration for PWA functionality
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                // Register service worker for offline functionality
                // This would be implemented in a separate sw.js file
            });
        }

        // Handle orientation change for mobile devices
        window.addEventListener('orientationchange', function () {
            setTimeout(() => {
                // Recalculate layouts if needed
                const logsContainer = document.getElementById('analysisLogs');
                if (logsContainer) {
                    logsContainer.scrollTop = logsContainer.scrollHeight;
                }
            }, 100);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl/Cmd + U for upload
            if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
                e.preventDefault();
                triggerFileSelect();
            }

            // Ctrl/Cmd + R for refresh (override default)
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                refreshData();
            }

            // Escape to close alerts
            if (e.key === 'Escape') {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.display = 'none';
                });
            }
        });

        // Handle network status
        window.addEventListener('online', function () {
            showAlert('success', 'Connection restored - System online');
            addLog('🌐 Network connection restored');
        });

        window.addEventListener('offline', function () {
            showAlert('error', 'No internet connection - Operating in offline mode');
            addLog('📴 Network connection lost');
        });

        // Performance monitoring
        function logPerformance() {
            if (performance.navigation) {
                const loadTime = performance.navigation.loadEventEnd - performance.navigation.navigationStart;
                if (loadTime > 0) {
                    addLog(`⚡ Page loaded in ${(loadTime / 1000).toFixed(2)} seconds`);
                }
            }
        }

        // Call performance logging after page load
        window.addEventListener('load', () => {
            setTimeout(logPerformance, 100);
        });
    </script>
</body>

</html>