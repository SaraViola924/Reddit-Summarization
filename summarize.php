<?php
// Replace with your actual API endpoint and key
$apiUrl = 'https://api.openai.com/v1/completions';
$apiKey = 'secretKey';

header('Content-Type: application/json');
$requestData = json_decode(file_get_contents('php://input'), true);

// Assuming we pass the title or text to the summarization API
$title = $requestData['permalink']; // Adjust if you send the post content

// Prepare API request to summarization API
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'text' => $title // Replace with actual field as per the API's requirements
]));

$response = curl_exec($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['error' => 'Error communicating with summarization API']);
    exit;
}

// Decode the API response and return the summary
$responseData = json_decode($response, true);
echo json_encode(['summary' => $responseData['summary'] ?? 'No summary available.']);
