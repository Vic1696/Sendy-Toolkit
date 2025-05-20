<?php
define('SENDY_API_KEY', 'YOUR_SENDY_API_KEY'); // Replace with your Sendy API key
define('SENDY_API_URL', 'YOUR_SENDY_API_URL'); // Replace with your Sendy API URL

$masterApiKey = SENDY_API_KEY;
$sendyApiUrl = SENDY_API_URL;

/**
 * Subscribes a user to a Sendy list.
 * @param string $email 
 * @param string $name 
 * @param string $listId 
 * @param string $apiKey 
 * @param string $subscribeUrl 
 * @return array.
 */
function subscribeUser($email, $name, $listId, $apiKey, $subscribeUrl) {
    if (empty($apiKey) || empty($listId) || empty($subscribeUrl)) {
        error_log('Error: Missing SENDY_API_KEY, listId, or SENDY_API_URL.');
        if (empty($listId)) {
            return ['status' => 'error', 'message' => 'No Sendy List ID provided for subscription.'];
        }
        return ['status' => 'error', 'message' => 'Server configuration error (missing API key or URL).'];
    }

    $params = [
        'api_key' => $apiKey,
        'list' => $listId,
        'email' => trim($email),
    ];

    if (!empty($name)) {
        $params['name'] = urlencode(trim($name));
    }

    $postFields = http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $subscribeUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($postFields), 
    ]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
    curl_setopt($ch, CURLOPT_VERBOSE, true); 
    curl_setopt($ch, CURLOPT_STDERR, fopen('php://stderr', 'w')); 

    $responseText = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch); 

    curl_close($ch);

    if ($curlError) {
        error_log("cURL Error ($curlErrno): " . $curlError . " for email: $email");
        return ['status' => 'error', 'message' => "API request error for $email: " . $curlError];
    }

    // error_log("Sendy Response for $email (HTTP $httpCode, List: $listId, Name: \"$name\"): " . $responseText);


    if (strpos($responseText, "<title>You're subscribed!</title>") !== false || trim($responseText) === '1') {
        return ['status' => 'subscribed', 'message' => "Successfully subscribed $email " . (!empty($name) ? "($name)" : '')];
    } elseif (strpos($responseText, "<title>You're already subscribed!</title>") !== false || trim($responseText) === '2') {
        return ['status' => 'already_subscribed', 'message' => "$email is already subscribed."];
    } elseif (strpos($responseText, "<title>Email address is bounced.</title>") !== false || strpos($responseText, "Email address is bounced.") !== false) {
        return ['status' => 'bounced', 'message' => "Failed to subscribe $email - email address is bounced."];
    } elseif (strpos($responseText, "<title>Email address is invalid.</title>") !== false || strpos($responseText, "Email address is invalid.") !== false) {

        return ['status' => 'invalid', 'message' => "Failed to subscribe $email - email address is invalid or Sendy rejected subscription."];
    } elseif (strpos($responseText, "Some fields are missing.") !== false) {
        return ['status' => 'error', 'message' => "Failed to subscribe $email - Some fields are missing (likely API key or list ID issue on Sendy's side)."];
    } else {
        error_log("Unexpected Sendy Response for $email (Status $httpCode): " . $responseText); 
        return ['status' => 'unexpected', 'message' => "Failed to subscribe $email. Unexpected response from Sendy. (Check server logs for full response)."];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    header('Content-Type: application/json');

    $listId = $_POST['listId'] ?? '';

    if (empty($_FILES['csvFile']['tmp_name'])) {
        http_response_code(400);
        echo json_encode(['message' => 'No file uploaded.']);
        exit;
    }

    if (empty($listId)) {
        http_response_code(400);
        echo json_encode(['message' => 'No Sendy List ID provided.']);
        exit;
    }

    if (empty($masterApiKey) || empty($sendyApiUrl)) {
        http_response_code(500);
        echo json_encode(['message' => 'Server configuration error: Sendy API key or URL not set in index.php. Please edit the configuration section.', 'errors' => ['Missing API key or URL in script']]);
        exit;
    }

    $fileContent = file_get_contents($_FILES['csvFile']['tmp_name']);
    $fileContent = preg_replace('/^[\xEF\xBB\xBF]/', '', $fileContent);

    $lines = array_filter(array_map('trim', explode("\n", $fileContent))); 

    $subscribedCount = 0;
    $bouncedCount = 0;
    $alreadySubscribedCount = 0;
    $invalidCount = 0;
    $errors = [];
    $failedEmails = [
        'bounced' => [],
        'invalid' => [],
        'other_errors' => []
    ];

    $headerSkipped = false;
    foreach ($lines as $line) {
        if (!$headerSkipped) {
            $headerSkipped = true;
            continue; 
        }

        $columns = str_getcsv($line); 
        $email = null;
        $name = null;

        if (isset($columns[0]) && filter_var(trim($columns[0]), FILTER_VALIDATE_EMAIL)) {
            $email = trim($columns[0]);
            if (isset($columns[1])) {
                $name = trim($columns[1]);
            }
        } else {
            $email = isset($columns[0]) ? trim($columns[0]) : null; 
            $invalidCount++;
            $errors[] = 'Invalid or unparsable email (expected in first column) from line: "' . substr($line, 0, 50) . '..." (Email: ' . ($email ?? 'N/A') . ')';
            continue; 
        }


        if (!empty($email)) { 
            $subscriptionResult = subscribeUser($email, $name, $listId, $masterApiKey, $sendyApiUrl);

            switch ($subscriptionResult['status']) {
                case 'subscribed':
                    $subscribedCount++;
                    break;
                case 'already_subscribed':
                    $alreadySubscribedCount++;
                    break;
                case 'bounced':
                    $bouncedCount++;
                    $failedEmails['bounced'][] = $email;
                    break;
                case 'invalid':
                    $invalidCount++;
                    $failedEmails['invalid'][] = $email;
                    break;
                case 'unexpected':
                case 'error':
                    $errors[] = $subscriptionResult['message'];
                    $failedEmails['other_errors'][] = $email;
                    break;
            }
        }
    }

    echo json_encode([
        'subscribed' => $subscribedCount,
        'bounced' => $bouncedCount,
        'alreadySubscribed' => $alreadySubscribedCount,
        'invalid' => $invalidCount,
        'errors' => $errors,
        'failedEmails' => $failedEmails
    ]);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Subscriber</title>
    <style>
        @import url('https://fonts.cdnfonts.com/css/konnect');

        body {
            font-family: 'Konnect', sans-serif;
            background-color: #f7f7f7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 400px;
            max-width: 90%;
        }

        h1 {
            color: #33b1ba;
            margin-bottom: 20px;
        }

        input[type="file"],
        input[type="text"] {
            margin-bottom: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Konnect', sans-serif;
        }

        button {
            background-color: #f3a929;
            color: #ffffff;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
            width: 100%;
            box-sizing: border-box;
            margin-top: 10px;
        }

        button:hover {
            background-color: #e09a24;
        }

        #status-area {
            margin-top: 20px;
            font-size: 14px;
            color: #555;
            text-align: left;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #eee;
            padding: 10px;
            background-color: #fefefe;
            border-radius: 4px;
        }

        .input-group {
            text-align: left;
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Bulk Subscriber</h1>

        <div class="input-group">
            <label for="sendyListId">Enter Sendy List ID:</label>
            <input type="text" id="sendyListId" placeholder="e.g., 3v2wVngsLxKRf892fLB7vGKA">
        </div>

        <input type="file" id="csvFile" accept=".csv">
        <button onclick="uploadFile()">Upload CSV</button>
        <div id="status-area"></div>
    </div>

    <script>
        async function uploadFile() {
            const fileInput = document.getElementById('csvFile');
            const statusArea = document.getElementById('status-area');
            const sendyListIdInput = document.getElementById('sendyListId');
            const listId = sendyListIdInput.value.trim();
            const file = fileInput.files[0];

            if (!listId) {
                statusArea.textContent = 'Please enter a Sendy List ID.';
                statusArea.style.color = '#f44336';
                return;
            }

            if (!file) {
                statusArea.textContent = 'Please select a CSV file.';
                statusArea.style.color = '#f44336';
                return;
            }

            statusArea.textContent = 'Processing file... This may may take a moment.';
            statusArea.style.color = '#555';

            const formData = new FormData();
            formData.append('csvFile', file);
            formData.append('listId', listId);

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                });

                const data = await response.json();

                let statusText = 'Upload Results:\n';
                statusText += `New subscribers: ${data.subscribed}\n`;
                statusText += `Already subscribed: ${data.alreadySubscribed}\n`;
                statusText += `Bounced emails: ${data.bounced}\n`;
                statusText += `Invalid or unparsable entries: ${data.invalid}\n`;


                if (data.failedEmails && (data.failedEmails.bounced.length > 0 || data.failedEmails.invalid.length > 0 || data.failedEmails.other_errors.length > 0)) {
                    statusText += '\n--- Details of Failed Emails ---\n';
                    if (data.failedEmails.bounced.length > 0) {
                        statusText += 'Bounced:\n';
                        data.failedEmails.bounced.forEach(email => {
                            statusText += `- ${email}\n`;
                        });
                    }
                    if (data.failedEmails.invalid.length > 0) {
                        statusText += 'Invalid (format or Sendy rejection):\n';
                        data.failedEmails.invalid.forEach(email => {
                            statusText += `- ${email}\n`;
                        });
                    }
                     if (data.failedEmails.other_errors.length > 0) {
                        statusText += 'Other errors (check server logs for details):\n';
                        data.failedEmails.other_errors.forEach(email => {
                            statusText += `- ${email}\n`;
                        });
                    }
                }

                if (data.errors && data.errors.length > 0) {
                    statusText += '\n--- Backend Errors (for developer) ---\n';
                    data.errors.forEach(msg => {
                        statusText += `- ${msg}\n`;
                    });
                }

                if (statusText.trim() === 'Upload Results:') {
                    statusText = 'No valid users found in the CSV or an unexpected error occurred.';
                }

                statusArea.textContent = statusText.trim();
                statusArea.style.color = '#555';

            } catch (error) {
                statusArea.textContent = `Error during upload or processing: ${error.message}. Check your server logs.`;
                statusArea.style.color = '#f44336';
            }
        }
    </script>
</body>
</html>