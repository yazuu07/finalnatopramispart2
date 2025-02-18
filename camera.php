<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get the JSON input
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if image data exists
    if (isset($data['image'])) {
        $imageData = $data['image'];
        $userId = isset($data['user_id']) ? $data['user_id'] : null;
        $location = isset($data['location']) ? $data['location'] : 'Unknown location'; // Default location if not available

        // Remove the part of the string that tells us the encoding type (e.g., data:image/jpeg;base64,)
        $imageData = str_replace('data:image/jpeg;base64,', '', $imageData);
        $imageData = base64_decode($imageData);

        // Define the file path
        $filePath = 'uploads/photo_' . time() . '.jpg';

        // Store the image in the uploads directory
        if (file_put_contents($filePath, $imageData)) {
            // Success

            // Database connection (replace with your details)
            $host = 'localhost';
            $user = 'root';
            $password = '';
            $dbname = 'users';
            $conn = new mysqli($host, $user, $password, $dbname);

            if ($conn->connect_error) {
                die('Connection failed: ' . $conn->connect_error);
            }

            // Insert the image details into the database
            $stmt = $conn->prepare("INSERT INTO images (user_id, image_path, location) VALUES (?, ?, ?)");
            $stmt->bind_param('iss', $userId, $filePath, $location); // Assuming user_id is an integer
            $stmt->execute();
            $stmt->close();
            $conn->close();

            echo json_encode(['success' => true]);
        } else {
            // Error while saving the file
            echo json_encode(['success' => false, 'error' => 'Failed to save image']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No image data received']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Capture</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }
        .camera-container {
            position: relative;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #222;
        }
        .top-line, .bottom-line {
            position: absolute;
            width: 100%;
            height: 150px;
            background: black;
            z-index: 20;
        }
        .top-line {
            top: 0;
        }
        .bottom-line {
            bottom: 0;
            height: 200px;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 10;
        }
        .gridlines {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            grid-template-rows: 1fr 1fr 1fr;
        }
        .gridlines div {
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        video, img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1);
        }
        video {
            position: relative;
            z-index: 10;
        }
        canvas {
            display: none;
        }
        .capture-btn, .retake-btn, .confirm-btn {
            position: absolute;
            bottom: 60px;
            width: 90px;
            height: 90px;
            background: white;
            border-radius: 50%;
            border: 6px solid rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: 0.3s;
            z-index: 20;
        }
        .capture-btn {
            left: 50%;
            transform: translateX(-50%);
        }
        .retake-btn {
            left: 20%;
            display: none;
        }
        .confirm-btn {
            right: 20%;
            display: none;
        }
        .capture-btn:active, .retake-btn:active, .confirm-btn:active {
            background: lightgray;
        }
    </style>
</head>
<body>
    <div class="camera-container">
        <div class="top-line"></div>
        <video id="video" autoplay></video>
        <div class="gridlines">
            <div></div><div></div><div></div>
            <div></div><div></div><div></div>
            <div></div><div></div><div></div>
        </div>
        <canvas id="canvas"></canvas>
        <img id="photo" style="display:none;">
        <button id="capture" class="capture-btn"></button>
        <button id="retake" class="retake-btn"></button>
        <button id="confirm" class="confirm-btn"></button>
        <div class="bottom-line"></div>
    </div>
    <script>
        const video = document.getElementById("video");
        const canvas = document.getElementById("canvas");
        const ctx = canvas.getContext("2d");
        const photo = document.getElementById("photo");
        const captureBtn = document.getElementById("capture");
        const retakeBtn = document.getElementById("retake");
        const confirmBtn = document.getElementById("confirm");
        let lastPhotoData = null;
        let locationData = "Fetching location...";

        navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
            .then(stream => { video.srcObject = stream; })
            .catch(err => console.error("Camera access denied:", err));

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async (position) => {
                const { latitude, longitude } = position.coords;
                try {
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`);
                    const data = await response.json();
                    locationData = data.display_name || "Unknown Location";
                } catch (error) {
                    locationData = "Location fetch failed";
                }
            }, () => {
                locationData = "Location access denied";
            });
        }

        captureBtn.addEventListener("click", () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = "white";
            ctx.font = "30px Arial";
            const dateTime = new Date().toLocaleString();
            ctx.fillText(dateTime, 20, canvas.height - 90);
            ctx.fillText(locationData, 20, canvas.height - 50);
            
            lastPhotoData = canvas.toDataURL("image/jpeg");
            photo.src = lastPhotoData;
            photo.style.display = "block";
            video.style.display = "none";
            captureBtn.style.display = "none";
            retakeBtn.style.display = "block";
            confirmBtn.style.display = "block";
        });

        retakeBtn.addEventListener("click", () => {
            photo.style.display = "none";
            video.style.display = "block";
            captureBtn.style.display = "block";
            retakeBtn.style.display = "none";
            confirmBtn.style.display = "none";
        });

        confirmBtn.addEventListener("click", () => {
            console.log("Sending image:", lastPhotoData); // Debugging: check if image data is present
            fetch("upload_image.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({
                    image: lastPhotoData, // Base64 image data
                }),
            })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    alert("Image uploaded successfully!");
                    retakeBtn.click(); // Reset the camera view
                } else {
                    alert("Image upload failed: " + data.error);
                }
            })
            .catch((error) => {
                console.error("Error uploading image:", error);
                alert("An error occurred while uploading the image.");
            });
        });
    </script>
</body>
</html>
