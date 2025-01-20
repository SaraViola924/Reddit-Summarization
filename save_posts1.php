
<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

$conn = new mysqli($servername, $username, $password, $dbname); // Create connection

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Get the posted data
$postData = file_get_contents("php://input");
$posts = json_decode($postData, true);

// Prepare the SQL statement with the correct fields (id, title, author, post_date, link, summary)
$stmt = $conn->prepare("INSERT INTO reddit_news (title, author, post_date, link, summary) VALUES (?, ?, ?, ?, ?)");

if ($stmt === false) {
    error_log('Error preparing statement: ' . $conn->error);
    die(json_encode(["status" => "error", "message" => "Failed to prepare the SQL statement: " . $conn->error]));
}

// Loop through each post and process the data
foreach ($posts as $post) {
    // Construct the full "Read more" link using the permalink
    $readMoreLink = "https://reddit.com" . $post['permalink'];  

    // Call AI API for summarization
    $summary = generateSummary($post['title']);  

    // Bind parameters and execute the prepared statement
    $stmt->bind_param("sssss", $post['title'], $post['author'], $post['post_date'], $readMoreLink, $summary);
    $stmt->execute();
}

// Close the statement and connection
$stmt->close();
$conn->close();

// Return a success message as JSON response
echo json_encode(["status" => "success", "message" => "Data saved and summarized successfully"]);

// Function to call the AI API for summarization
function generateSummary($text) {
    $apiUrl = "https://api.openai.com/v1/completions";
    $data = [
        'prompt' => $text,
        'max_tokens' => 50,  // Limit to a shorter summary if needed
        'model' => 'text-davinci-003'
    ];

    // Initialize cURL request
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer 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'  // Replace with your API key
    ]);

    // Get the API response
    $response = curl_exec($ch);
    curl_close($ch);

    // Check if API call was successful
    if ($response === false) {
        return "Summary not available";
    }

    $result = json_decode($response, true);
    return $result['choices'][0]['text'] ?? 'Summary not available';  // Adjusted for expected OpenAI response format
}
?>
