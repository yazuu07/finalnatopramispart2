<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

// Check if image data is provided
if (!isset($data['image']) || empty($data['image'])) {
    echo json_encode(["success" => false, "error" => "No image provided"]);
    exit();
}

// Decode base64 image data
$imageData = $data['image'];
$imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
$imageData = str_replace(' ', '+', $imageData);
$image = base64_decode($imageData);

if (!$image) {
    echo json_encode(["success" => false, "error" => "Invalid image data"]);
    exit();
}

// Save the image file
$uploadsDir = 'uploads/';
if (!file_exists($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$filename = uniqid('photo_') . '.jpg';
$filePath = $uploadsDir . $filename;

if (file_put_contents($filePath, $image) === false) {
    echo json_encode(["success" => false, "error" => "Failed to save image"]);
    exit();
}

// Insert the file path into the database
$stmt = $pdo->prepare("INSERT INTO uploads (user_id, image_path) VALUES (?, ?)");
if ($stmt->execute([$_SESSION['user_id'], $filePath])) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Database error"]);
}
?>
