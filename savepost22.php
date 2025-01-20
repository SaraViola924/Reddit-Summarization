<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Get the posted data
$postData = file_get_contents("php://input");
$posts = json_decode($postData, true);

if (!$posts) {
    die(json_encode(["status" => "error", "message" => "Invalid input data"]));
}

// Prepare SQL statements
$checkStmt = $conn->prepare("SELECT id FROM reddit_news WHERE link = ?");
$insertStmt = $conn->prepare("INSERT INTO reddit_news (title, author, post_date, link, summary) VALUES (?, ?, ?, ?, ?)");

if (!$checkStmt || !$insertStmt) {
    die(json_encode(["status" => "error", "message" => "SQL statement preparation failed: " . $conn->error]));
}

// Process each post
foreach ($posts as $post) {
    $readMoreLink = $post['permalink'];
    
    // Check if the post already exists
    $checkStmt->bind_param("s", $readMoreLink);
    $checkStmt->execute();
    $checkStmt->store_result();
    
    if ($checkStmt->num_rows > 0) {
        // Skip saving if the post already exists
        continue;
    }

    // Generate summary using the title and content
    $summary = generateSummary($post['title']);

    // Insert new post
    $insertStmt->bind_param(
        "sssss",
        $post['title'],
        $post['author'],
        $post['date'],
        $readMoreLink,
        $summary
    );
    $insertStmt->execute();
}

// Close the prepared statements and database connection
$checkStmt->close();
$insertStmt->close();
$conn->close();

// Return a success message as JSON response
echo json_encode(["status" => "success", "message" => "Data saved and summarized successfully"]);

// Function to call the AI API for summarization
function generateSummary($text) {
    $apiUrl = "https://api.openai.com/v1/completions";
    $apiKey = "secretKey";

    $data = [
        "prompt" => "Summarize this: $text",
        "max_tokens" => 50,
        "model" => "text-davinci-003"
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey"
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $error) {
        return "Summary not available";
    }

    $result = json_decode($response, true);
    return $result['choices'][0]['text'] ?? "Summary not available";
}
?>
