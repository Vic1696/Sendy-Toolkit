
<?php

// IMPORTANT: Replace the placeholder API key with your actual Sendy Master API Key
define('SENDY_API_KEY', '_______'); // REPLACE THIS WITH YOUR ACTUAL API KEY
define('SENDY_API_URL_BASE', '_______'); // Your Sendy installation base URL
define('SENDY_SUBSCRIBE_ENDPOINT', '/subscribe'); // Path for subscribing
define('SENDY_GET_BRANDS_ENDPOINT', '/api/brands/get-brands.php'); // Path for getting brands
define('SENDY_GET_LISTS_ENDPOINT', '/api/lists/get-lists.php'); // Path for getting lists

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
    // Direct verbose output to PHP error log. This will show cURL's internal workings.
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
    if ($response && is_array($response)) { // Check if it's an array (which it is, of key-value pairs)
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
    return $brandsArray; // Return the properly formatted array
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

    // Lists API might return similar structured object or an array.
    // Let's assume it returns a direct array or needs similar processing if it's an object.
    $listsArray = [];
    if ($response && is_array($response)) {
        // If Sendy's get-lists API returns an object like get-brands, iterate similarly
        // If it returns a direct array of list objects, this loop will still work if keys are numeric (0, 1, 2...)
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

// --- Main Logic: Handle various requests from frontend ---

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

    $listId = $_POST['listId'] ?? ''; // This can now come from dropdown or text input

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
        'failedEmails' => $failedEmails
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

        /* Styling for inputs and dropdowns */
        input[type="file"],
        input[type="text"],
        select { /* Added select */
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

        #listIdManualInputGroup {
            display: block; /* Default to showing the text input */
        }

        #listIdDropdownGroup {
            display: none; /* Hide the dropdown by default */
        }

    </style>
</head>
<body>
    <div class="container">
        <h1>Bulk Subscriber</h1>

        <div class="input-group">
            <label for="brandSelect">Select Brand:</label>
            <select id="brandSelect" disabled>
                <option value="">Loading brands...</option>
            </select>
        </div>

        <div class="input-group" id="listIdDropdownGroup">
            <label for="listSelect">Select List:</label>
            <select id="listSelect" disabled>
                <option value="">Select a brand first</option>
            </select>
        </div>

        <div class="input-group" id="listIdManualInputGroup">
            <label for="sendyListId">Or Enter Sendy List ID (Optional):</label>
            <input type="text" id="sendyListId" placeholder="e.g., 3v2wVngsLxKRf892fLB7vGKA">
        </div>

        <input type="file" id="csvFile" accept=".csv">
        <button onclick="uploadFile()">Upload CSV</button>
        <div id="status-area"></div>
    </div>

    <script>
        const statusArea = document.getElementById('status-area');
        const brandSelect = document.getElementById('brandSelect');
        const listSelect = document.getElementById('listSelect');
        const listIdManualInput = document.getElementById('sendyListId');
        const listIdDropdownGroup = document.getElementById('listIdDropdownGroup');
        const listIdManualInputGroup = document.getElementById('listIdManualInputGroup');

        async function initForm() {
            statusArea.textContent = 'Loading brands...';
            statusArea.style.color = '#555';

            await fetchBrands();

            // Event listener for brand selection
            brandSelect.addEventListener('change', async () => {
                const selectedBrandId = brandSelect.value;
                listIdManualInput.value = ''; // Clear manual input if dropdown is used
                toggleListInput(); // Adjust visibility

                if (selectedBrandId) {
                    statusArea.textContent = 'Loading lists for selected brand...';
                    await fetchLists(selectedBrandId);
                } else {
                    listSelect.innerHTML = '<option value="">Select a brand first</option>';
                    listSelect.disabled = true;
                    statusArea.textContent = '';
                }
            });

            // Event listener for list selection (from dropdown)
            listSelect.addEventListener('change', () => {
                // When a list is selected, update the hidden manual input (or just ensure its value is used)
                listIdManualInput.value = listSelect.value;
            });

            // Event listener for manual input change
            listIdManualInput.addEventListener('input', () => {
                // If user types in manual input, clear dropdown selection
                listSelect.value = '';
                toggleListInput(); // Adjust visibility
            });

            // Initial toggle based on input focus/value
            toggleListInput();
        }

        async function fetchBrands() {
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

                const brands = await response.json(); // This will now receive the *processed* array from PHP

                brandSelect.innerHTML = '<option value="">-- Select a Brand --</option>';
                if (brands.length > 0) {
                    brands.forEach(brand => {
                        const option = document.createElement('option');
                        option.value = brand.id;
                        option.textContent = brand.name;
                        brandSelect.appendChild(option);
                    });
                    brandSelect.disabled = false;
                    statusArea.textContent = 'Brands loaded. Please select a brand.';
                } else {
                    brandSelect.innerHTML = '<option value="">No brands found</option>';
                    statusArea.textContent = 'No brands found. Please check your Sendy API key and configuration.';
                    statusArea.style.color = '#f44336';
                }
            } catch (error) {
                console.error('Error fetching brands:', error);
                statusArea.textContent = `Error fetching brands: ${error.message}. Check server logs.`;
                statusArea.style.color = '#f44336';
                brandSelect.innerHTML = '<option value="">Error loading brands</option>';
            } finally {
                brandSelect.disabled = false; // Always re-enable, even if error
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
                    statusArea.textContent = `Lists loaded for brand. Choose a list or enter ID.`;
                } else {
                    listSelect.innerHTML = '<option value="">No lists found for this brand</option>';
                    statusArea.textContent = `No lists found for this brand. You can still enter a List ID manually.`;
                    statusArea.style.color = '#f44336';
                }
            } catch (error) {
                console.error('Error fetching lists:', error);
                statusArea.textContent = `Error fetching lists: ${error.message}. Check server logs.`;
                statusArea.style.color = '#f44336';
                listSelect.innerHTML = '<option value="">Error loading lists</option>';
            } finally {
                listSelect.disabled = false; // Always re-enable, even if error
            }
        }

        // Toggles visibility between dropdown and manual input for List ID
        function toggleListInput() {
            if (listSelect.value) { // If a list is selected from the dropdown
                listIdManualInputGroup.style.display = 'none';
                listIdDropdownGroup.style.display = 'block';
            } else if (listIdManualInput.value.trim() !== '') { // If something is typed in manual input
                listIdDropdownGroup.style.display = 'none';
                listIdManualInputGroup.style.display = 'block';
            } else { // Default state or if both are empty
                listIdDropdownGroup.style.display = 'block'; // Show dropdown by default for selection
                listIdManualInputGroup.style.display = 'block'; // Keep manual input visible as option
            }
        }


        async function uploadFile() {
            const fileInput = document.getElementById('csvFile');
            const file = fileInput.files[0];

            // Determine listId: Prioritize dropdown selection, then manual input
            let listId = '';
            if (listSelect.value) {
                listId = listSelect.value;
            } else {
                listId = listIdManualInput.value.trim();
            }

            if (!listId) {
                statusArea.textContent = 'Please select a Sendy List or enter a List ID.';
                statusArea.style.color = '#f44336';
                return;
            }

            if (!file) {
                statusArea.textContent = 'Please select a CSV file.';
                statusArea.style.color = '#f44336';
                return;
            }

            statusArea.textContent = 'Processing file... This may take a moment.';
            statusArea.style.color = '#555';

            const formData = new FormData();
            formData.append('csvFile', file);
            formData.append('listId', listId); // Send the determined listId

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
                statusArea.textContent = `Error during upload or processing: ${error.message}. Please check your server logs for more details.`;
                statusArea.style.color = '#f44336';
            }
        }

        // Initialize the form when the DOM is ready
        document.addEventListener('DOMContentLoaded', initForm);
    </script>
</body>
</html>