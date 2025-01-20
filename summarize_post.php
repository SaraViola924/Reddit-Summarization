
<?php
// Debugging: Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', '1');
ini_set('error_log', 'C:/xampp/php/php_error_log.txt'); 

// Ensure JSON output
header('Content-Type: application/json');

// Database connection settings
$host = 'localhost';
$dbname = 'capstone';
$user = 'root';
$password = '';

// Set up PDO for database connection

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
} 

// Decode the incoming JSON request
$request = json_decode(file_get_contents("php://input"), true);
if (!isset($request['permalink'])) {
    echo json_encode(['error' => 'Invalid input: Missing permalink']);
    exit;
}


$permalink = $request['permalink'];


// Step 1: Check if the summary already exists in the database
try {
    $checkStmt = $pdo->prepare("SELECT summary FROM reddit_news WHERE link = :permalink");
    $checkStmt->execute(['permalink' => $permalink]);
    $existingSummary = $checkStmt->fetchColumn();

    if ($existingSummary) {
        echo json_encode(['summary' => $existingSummary]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    error_log("database query failed");
    exit;
} 

// Step 2: Authenticate and Fetch Post Content from Reddit
$username = 'user';
$password = 'pass';

function getRedditToken() {
    // Set your Reddit API credentials
    $clientId = 'secretID';
    $clientSecret = 'secretKey';

    $username = 'user';
    $password = 'pass';

    // Post params
    $params = array(
        'grant_type' => 'password',
        'username' => $username,
        'password' => $password
    );

    // cURL settings and call to Reddit
    $ch = curl_init('https://www.reddit.com/api/v1/access_token');
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $clientSecret);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); // Make sure the params are encoded properly
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: YourApp/0.1 by ' . $username
    ]);

    // cURL response from Reddit
    $response_raw = curl_exec($ch);
    
    // Check for cURL errors
    if(curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    // Decode the response
    $response = json_decode($response_raw, true);

    // Check if the response contains an access token
    if (isset($response['access_token'])) {
        //error_log('Access token: ' . $response['access_token']);
        return $response['access_token'];
    } else {
        // If no access token, log the error and return null
        error_log('Failed to get access token. Response: ' . print_r($response, true));
        return null;
    }
}

function getRedditPost($access_token, $permalink) {
    // Construct the full URL for the specific post using the permalink
    $url = 'https://oauth.reddit.com' . $permalink;  // Example: '/r/subreddit/comments/post_id/title' 

    // Prepare the headers for the request
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'User-Agent: YourAppName/1.0'  // Replace with your app's name
    ];

    // Set up the cURL request to fetch the specific post
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the cURL request and capture the response
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if(curl_errno($ch)) {
        error_log('cURL Error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    // Log the raw response for debugging
    //error_log('Reddit API Response: ' . $response);

    // Decode the JSON response
    $post_details = json_decode($response, true);

    //error_log('Content: ' . $post_details[0]['data']['children'][0]['data']['selftext']);

    // Check if the response contains post data
    if (isset($post_details[0]['data']['children'][0]['data'])) {
        // Extract the post details
        $post = $post_details[0]['data']['children'][0]['data'];

        // Check if 'selftext' exists in the post data
        if (isset($post['selftext'])) {
            // Extract and store the content of the post into $content
            $content = $post['selftext'];

            //error_log("Content is" . $content);
            // Return the content
            return $content;
        } else {
            // Handle case where 'selftext' is missing (e.g., the post might be a link post)
            error_log('Post has no selftext.');
            return null;
        }
    } else {
        // Handle errors if the post is not found or the response is malformed
        error_log('Failed to find post data in the response.');
        return null;
    }
}

$access_token = getRedditToken();

if ($access_token) {
    $content = getRedditPost($access_token, $permalink);
    //error_log("Content is: " . $content);
} else {
    error_log("Error: could not retrieve access token");
}

// Step 3: Summarize the Content Using OpenAI's API
$api_key = 'secretKey';

// Set up the cURL request
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'user', 'content' => "Summarize this post: $content"]
    ]
]));

// Execute the request and capture the response
$summarize_response = curl_exec($ch);

// Handle any errors that occur
if(curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
}

curl_close($ch);

// Decode the response
$summarize_data = json_decode($summarize_response, true);

// Debugging: Check the raw response from OpenAI
//error_log("OpenAI API Response: " . $summarize_response);

// Check if the expected response structure exists
if (isset($summarize_data['choices'][0]['message']['content'])) {
    $summary = $summarize_data['choices'][0]['message']['content'];
} else {
    $summary = 'No summary available.';
}

// Save the generated summary to the database
try {
    $saveStmt = $pdo->prepare("INSERT INTO reddit_news (link, summary) VALUES (:permalink, :summary)");
    $saveStmt->execute(['permalink' => $permalink, 'summary' => $summary]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database save failed: ' . $e->getMessage()]);
    error_log("Database save failed");
    exit;
}

// step 5: output json response
$json_response = json_encode(['summary' => $summary]);

error_log("json response: " . $json_response);

echo $json_response;
exit;

?>
