<?php
define('SENDY_API_KEY', '    '); 
define('SENDY_API_URL_BASE', '    '); 
define('SENDY_SUBSCRIBE_ENDPOINT', '/subscribe');
define('SENDY_GET_BRANDS_ENDPOINT', '/api/brands/get-brands.php');
define('SENDY_GET_LISTS_ENDPOINT', '/api/lists/get-lists.php');

$masterApiKey = SENDY_API_KEY;
$sendySubscribeUrl = SENDY_API_URL_BASE . SENDY_SUBSCRIBE_ENDPOINT;
$sendyBrandsApiUrl = SENDY_API_URL_BASE . SENDY_GET_BRANDS_ENDPOINT;
$sendyListsApiUrl = SENDY_API_URL_BASE . SENDY_GET_LISTS_ENDPOINT;

/**
 * Generic function to call Sendy API endpoints using cURL.
 * @param string $url The full URL of the Sendy API endpoint.
 * @param array $params Associative array of POST parameters.
 * @return array|null Decoded JSON response or null on error.
 */
function callSendyApi($url, $params) {
    $postFields = http_build_query($params);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
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
        error_log("cURL Error ($curlErrno) for URL: $url: " . $curlError);
        return null;
    }

    error_log("Sendy API Response from $url (HTTP $httpCode): " . $responseText);

    $decodedResponse = json_decode($responseText, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decoding error for response from $url: " . json_last_error_msg());
        return null;
    }

    return $decodedResponse;
}

/**
 * Fetches all brands from Sendy.
 * @param string $apiKey Your Sendy API key.
 * @return array An array of brands (id, name) or empty array on failure.
 */
function getSendyBrands($apiKey) {
    global $sendyBrandsApiUrl;
    $params = ['api_key' => $apiKey];
    $response = callSendyApi($sendyBrandsApiUrl, $params);

    $brandsArray = [];
    if ($response && is_array($response)) { 
        foreach ($response as $key => $brandData) {
            // Check if the key starts with 'brand' and it has 'id' and 'name'
            if (strpos($key, 'brand') === 0 && isset($brandData['id']) && isset($brandData['name'])) {
                $brandsArray[] = [
                    'id' => $brandData['id'],
                    'name' => $brandData['name']
                ];
            }
        }
    }
    return $brandsArray; 
}

/**
 * Fetches lists for a given brand from Sendy.
 * @param string $apiKey Your Sendy API key.
 * @param string $brandId The ID of the brand.
 * @return array An array of lists (id, name) or empty array on failure.
 */
function getSendyLists($apiKey, $brandId) {
    global $sendyListsApiUrl;
    $params = ['api_key' => $apiKey, 'brand_id' => $brandId];
    $response = callSendyApi($sendyListsApiUrl, $params);

    $listsArray = [];
    if ($response && is_array($response)) {
        foreach ($response as $key => $listData) {
            if (isset($listData['id']) && isset($listData['name'])) {
                $listsArray[] = [
                    'id' => $listData['id'],
                    'name' => $listData['name']
                ];
            }
        }
    }
    return $listsArray;
}

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
        error_log('Error: Missing Sendy API key, List ID, or API URL for subscription.');
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

    error_log("Sendy Response for $email (HTTP $httpCode, List: $listId, Name: \"$name\"): " . $responseText);

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

// --- Logic: Handle various requests from frontend ---

// Check for missing API key/URL early for all requests
if ($masterApiKey === '' || empty($masterApiKey) || empty($sendySubscribeUrl) || empty($sendyBrandsApiUrl) || empty($sendyListsApiUrl)) {
    http_response_code(500);
    echo json_encode(['message' => 'Server configuration error: Sendy API key or URLs not properly set. Please edit the configuration section in sendysubscribe.php.']);
    exit;
}

// Handle AJAX request to get brands
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_brands') {
    header('Content-Type: application/json');
    $brands = getSendyBrands($masterApiKey);
    echo json_encode($brands);
    exit;
}

// Handle AJAX request to get lists for a specific brand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_lists' && isset($_POST['brandId'])) {
    header('Content-Type: application/json');
    $brandId = $_POST['brandId'];
    $lists = getSendyLists($masterApiKey, $brandId);
    echo json_encode($lists);
    exit;
}

// Handle CSV file upload and subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    header('Content-Type: application/json');

    $listId = $_POST['listId'] ?? ''; 
    $listName = $_POST['listName'] ?? 'Unknown List'; 

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
            $subscriptionResult = subscribeUser($email, $name, $listId, $masterApiKey, $sendySubscribeUrl);

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
        'failedEmails' => $failedEmails,
        'listName' => $listName 
    ]);
    exit;
}

// --- Serve Frontend HTML (if not a POST request handling API calls or CSV upload) ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sendy Bulk Subscriber</title>
    <link href="https://fonts.cdnfonts.com/css/konnect" rel="stylesheet">
    <style>
        :root {
            --primary-color: #33b1ba;
            --secondary-color: #f3a929;
            --background-color: #f7f7f7;
            --card-background: #ffffff;
            --text-color: #333;
            --label-color: #555;
            --border-color: #ddd;
            --success-color: #28a745;
            --error-color: #dc3545;
            --info-color: #007bff;
            --warning-color: #ffc107;
        }

        body {
            font-family: 'Konnect', sans-serif;
            background-color: var(--background-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: var(--text-color);
        }

        .container {
            background-color: var(--card-background);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 500px; 
            max-width: 95%;
            box-sizing: border-box;
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 30px;
            font-size: 2.2em;
            font-weight: 700;
        }

        .input-section {
            background-color: #fcfcfc;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
            text-align: left;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--label-color);
            font-weight: 500;
            font-size: 0.95em;
        }

        input[type="file"],
        input[type="text"],
        select {
            margin-bottom: 0; 
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Konnect', sans-serif;
            font-size: 1em;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="file"]:focus,
        input[type="text"]:focus,
        select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(51, 177, 186, 0.2);
            outline: none;
        }

        input[type="file"] {
            padding: 10px;
        }

        button {
            background-color: var(--secondary-color);
            color: #ffffff;
            padding: 14px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.1s ease;
            width: 100%;
            box-sizing: border-box;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        button:hover:not(:disabled) {
            background-color: #e09a24;
            transform: translateY(-1px);
        }

        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            opacity: 0.8;
        }

        small {
            display: block;
            font-size: 0.85em;
            color: #888;
            margin-top: 5px;
            line-height: 1.4;
        }

        hr {
            border: none;
            border-top: 1px dashed var(--border-color);
            margin: 30px 0;
        }

        .results-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            text-align: left;
        }

        .results-section h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.5em;
            font-weight: 600;
        }

        #status-area {
            min-height: 80px; 
            font-size: 0.9em;
            line-height: 1.6;
            color: var(--text-color);
            background-color: #fefefe;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 15px;
            max-height: 350px;
            overflow-y: auto;
            white-space: pre-wrap; 
        }

        .status-success { color: var(--success-color); }
        .status-error { color: var(--error-color); font-weight: 500; }
        .status-info { color: var(--info-color); }
        .status-warning { color: var(--warning-color); }

        .spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-left-color: #ffffff;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        details {
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 10px;
            margin-top: 10px;
            background-color: #f9f9f9;
        }
        summary {
            cursor: pointer;
            font-weight: bold;
            color: var(--label-color);
            padding: 5px 0;
        }
        details pre {
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 0.85em;
            white-space: pre-wrap; 
            word-break: break-all; 
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Sendy Bulk Subscriber</h1>

        <div class="input-section">
            <div class="input-group">
                <label for="brandSelect">Select Brand:</label>
                <select id="brandSelect" disabled>
                    <option value="">Loading brands...</option>
                </select>
                <small id="brandSelectHelpText">Brands fetched from Sendy.</small>
            </div>

            <div class="input-group" id="listIdDropdownGroup">
                <label for="listSelect">Select List:</label>
                <select id="listSelect" disabled>
                    <option value="">Select a brand first</option>
                </select>
                <small id="listSelectHelpText">Lists belonging to the selected brand.</small>
            </div>

            <hr> <div class="input-group" id="listIdManualInputGroup">
                <label for="sendyListId">Or Enter List ID Manually:</label>
                <input type="text" id="sendyListId" placeholder="e.g., 3v2wVngsLxKRf892fLB7vGKA">
                <small>Use this if your list doesn't appear in the dropdown, or if you prefer to type it.</small>
            </div>
        </div>

        <div class="input-section">
            <div class="input-group">
                <label for="csvFile">Upload CSV File:</label>
                <input type="file" id="csvFile" accept=".csv">
                <small>Your CSV should have **email in the first column** and **name in the second (optional)**.</small>
            </div>
            <button id="uploadButton" onclick="uploadFile()">Upload CSV</button>
        </div>

        <div class="results-section">
            <h2>Upload Summary</h2>
            <div id="status-area" aria-live="polite">
                </div>
        </div>
    </div>

    <script>
        const statusArea = document.getElementById('status-area');
        const brandSelect = document.getElementById('brandSelect');
        const listSelect = document.getElementById('listSelect');
        const listIdManualInput = document.getElementById('sendyListId');
        const listIdDropdownGroup = document.getElementById('listIdDropdownGroup');
        const listIdManualInputGroup = document.getElementById('listIdManualInputGroup');
        const uploadButton = document.getElementById('uploadButton');

        async function initForm() {
            updateStatus('Loading brands...', 'info');
            await fetchBrands();

            brandSelect.addEventListener('change', async () => {
                const selectedBrandId = brandSelect.value;
                listIdManualInput.value = ''; 
                toggleListInputMode(); 

                if (selectedBrandId) {
                    updateStatus('Loading lists for selected brand...', 'info');
                    await fetchLists(selectedBrandId);
                } else {
                    listSelect.innerHTML = '<option value="">Select a brand first</option>';
                    listSelect.disabled = true;
                    updateStatus('Please select a brand to load lists or enter a List ID manually.', 'info');
                }
            });

            listSelect.addEventListener('change', () => {
                listIdManualInput.value = listSelect.value;
                toggleListInputMode(); 
            });

            // Event listener for manual input change
            listIdManualInput.addEventListener('input', () => {
                if (listIdManualInput.value.trim() !== '') {
                    brandSelect.value = '';
                    listSelect.innerHTML = '<option value="">Select a brand first</option>';
                    listSelect.disabled = true;
                    updateStatus('Manual List ID entered. Brand/List selections are ignored.', 'info');
                }
                toggleListInputMode();
            });

            toggleListInputMode(); 
        }

        // Helper function to update status area with classes
        function updateStatus(message, type = 'info') {
            statusArea.innerHTML = message;
            statusArea.className = `status-${type}`;
        }

        // Function to decide which input method for List ID to show/hide
        function toggleListInputMode() {
            const isManualInputPopulated = listIdManualInput.value.trim() !== '';

            if (isManualInputPopulated) {
                listIdDropdownGroup.style.display = 'none';
                listIdManualInputGroup.style.display = 'block';
            } else {
                listIdDropdownGroup.style.display = 'block';
                listIdManualInputGroup.style.display = 'block'; 
            }
        }

        async function fetchBrands() {
            brandSelect.disabled = true; 
            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=get_brands'
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText.substring(0, 200)}...`);
                }

                const brands = await response.json();

                brandSelect.innerHTML = '<option value="">-- Select a Brand --</option>';
                if (brands.length > 0) {
                    brands.forEach(brand => {
                        const option = document.createElement('option');
                        option.value = brand.id;
                        option.textContent = brand.name;
                        brandSelect.appendChild(option);
                    });
                    brandSelect.disabled = false;
                    updateStatus('Brands loaded. Please select a brand or enter a List ID.', 'info');
                } else {
                    brandSelect.innerHTML = '<option value="">No brands found</option>';
                    updateStatus('No brands found. Please check your Sendy API key and URL in the PHP file.', 'warning');
                    listIdManualInputGroup.style.display = 'block';
                    listIdDropdownGroup.style.display = 'none';
                }
            } catch (error) {
                console.error('Error fetching brands:', error);
                updateStatus(`Error fetching brands: ${error.message}. Check server logs.`, 'error');
                brandSelect.innerHTML = '<option value="">Error loading brands</option>';
                brandSelect.disabled = true;
            } finally {
                brandSelect.disabled = false;
            }
        }

        async function fetchLists(brandId) {
            listSelect.innerHTML = '<option value="">Loading lists...</option>';
            listSelect.disabled = true;
            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=get_lists&brandId=${brandId}`
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText.substring(0, 200)}...`);
                }

                const lists = await response.json();

                listSelect.innerHTML = '<option value="">-- Select a List --</option>';
                if (lists.length > 0) {
                    lists.forEach(list => {
                        const option = document.createElement('option');
                        option.value = list.id;
                        option.textContent = list.name;
                        listSelect.appendChild(option);
                    });
                    listSelect.disabled = false;
                    updateStatus(`Lists loaded for brand '${brandSelect.options[brandSelect.selectedIndex].text}'. Choose a list or enter a List ID.`, 'info');
                } else {
                    listSelect.innerHTML = '<option value="">No lists found for this brand</option>';
                    updateStatus(`No lists found for this brand. You can still enter a List ID manually.`, 'warning');
                    listIdManualInputGroup.style.display = 'block';
                }
            } catch (error) {
                console.error('Error fetching lists:', error);
                updateStatus(`Error fetching lists: ${error.message}. Check server logs.`, 'error');
                listSelect.innerHTML = '<option value="">Error loading lists</option>';
            } finally {
                listSelect.disabled = false;
            }
        }

        async function uploadFile() {
            const fileInput = document.getElementById('csvFile');
            const file = fileInput.files[0];

            let listId = '';
            let listName = 'Unknown List'; 

            // Determine listId: Prioritize manual input if present, otherwise dropdown selection
            if (listIdManualInput.value.trim() !== '') {
                listId = listIdManualInput.value.trim();
                listName = `ID: ${listId}`; 
            } else if (listSelect.value) { 
                listId = listSelect.value;
                listName = listSelect.options[listSelect.selectedIndex].textContent;
            }

            if (!listId) {
                updateStatus('Please select a Sendy List or enter a List ID.', 'error');
                return;
            }

            if (!file) {
                updateStatus('Please select a CSV file to upload.', 'error');
                return;
            }

            updateStatus('Uploading and processing file... This may take a moment. Please do not close this window.', 'info');
            uploadButton.disabled = true; 
            uploadButton.innerHTML = 'Uploading... <span class="spinner"></span>'; 

            const formData = new FormData();
            formData.append('csvFile', file);
            formData.append('listId', listId);
            formData.append('listName', listName); 

            try {
                const response = await fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData,
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! Status: ${response.status}. Response: ${errorText.substring(0, 200)}... (check server logs for full response)`);
                }

                const data = await response.json();

                // Use the listName from the response (which was passed from frontend to backend and back)
                let summaryListName = data.listName || 'Unknown List'; 

                let statusHtml = `<h3>Upload Summary for List: ${summaryListName}</h3>`;
                statusHtml += `<p><strong>Subscribed:</strong> <span class="status-success">${data.subscribed}</span></p>`;
                statusHtml += `<p><strong>Already Subscribed:</strong> ${data.alreadySubscribed}</p>`;
                statusHtml += `<p><strong>Bounced:</strong> <span class="status-warning">${data.bounced}</span></p>`;
                statusHtml += `<p><strong>Invalid/Unparsable:</strong> <span class="status-warning">${data.invalid}</span></p>`;


                if (data.failedEmails && (data.failedEmails.bounced.length > 0 || data.failedEmails.invalid.length > 0 || data.failedEmails.other_errors.length > 0)) {
                    statusHtml += `<h4>Details of Failed Emails:</h4>`;
                    if (data.failedEmails.bounced.length > 0) {
                        statusHtml += `<details><summary>Bounced (${data.failedEmails.bounced.length} emails)</summary><pre>${data.failedEmails.bounced.join('\n')}</pre></details>`;
                    }
                    if (data.failedEmails.invalid.length > 0) {
                        statusHtml += `<details><summary>Invalid/Rejected (${data.failedEmails.invalid.length} emails)</summary><pre>${data.failedEmails.invalid.join('\n')}</pre></details>`;
                    }
                    if (data.failedEmails.other_errors.length > 0) {
                        statusHtml += `<details><summary>Other Errors (${data.failedEmails.other_errors.length} emails)</summary><pre>${data.failedEmails.other_errors.join('\n')}</pre></details>`;
                    }
                }

                if (data.errors && data.errors.length > 0) {
                    statusHtml += `<h4>Backend Errors (for developer reference):</h4>`;
                    statusHtml += `<details><summary>Show Errors (${data.errors.length})</summary><pre class="status-error">${data.errors.join('\n')}</pre></details>`;
                }

                if (data.subscribed === 0 && data.alreadySubscribed === 0 && data.bounced === 0 && data.invalid === 0) {
                    statusHtml = '<p class="status-warning">No valid emails processed. Please check your CSV file format and the selected list.</p>';
                }

                statusArea.innerHTML = statusHtml; 
                statusArea.className = (data.subscribed > 0 || data.alreadySubscribed > 0) ? 'status-info' : 'status-warning';

            } catch (error) {
                updateStatus(`Error during upload or processing: ${error.message}. Please check your server logs and browser console for more details.`, 'error');
            } finally {
                uploadButton.disabled = false; 
                uploadButton.innerHTML = 'Upload CSV'; 
            }
        }
        document.addEventListener('DOMContentLoaded', initForm);
    </script>
</body>
</html>