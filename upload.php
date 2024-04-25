<?php

// Get the JSON data from the request body
$jsonData = file_get_contents('php://input');

// Decode the JSON data into a PHP array
$data = json_decode($jsonData, true);

// Get the text data from the PHP array
$textData = $data['text'];
echo $textData;

// Save the text data into a file
file_put_contents("text_data.txt", PHP_EOL . $textData, FILE_APPEND);

// Send a response back to the client
// echo "Text data received.";
