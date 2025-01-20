<?php

// Debugging: Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ini_set('log_errors', '1');
ini_set('error_log', 'C:/xampp/php/php_error_log.txt'); 

// Retrieve the JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Simulate an API call to OpenAI or process summaries
$summaries = $data['summaries'] ?? [];
$overallSummary = "This is a combined summary of all the news items: " . implode(' ', $summaries);



error_log($overallSummary);

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
        ['role' => 'user', 'content' => "Briefly Summarize these combined summaries with the end text in mind: $overallSummary"]
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

// Return the overall summary as JSON
header('Content-Type: application/json');

$json_response = json_encode(['summary' => $summary]);

error_log("json response: " . $json_response);

echo $json_response;



?>
