<?php
session_start();

// Include your database connection
if (file_exists('config.php')) {
    include 'config.php';
} else {
    include 'db_connect.php';
}

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Handle Text Fields (Fullname & Contact)
    $fullname = trim($_POST['fullname'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    
    if (!empty($fullname)) {
        $update_query = "UPDATE users SET fullname = ?, contact = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssi", $fullname, $contact, $user_id);
        if ($stmt->execute()) {
            $_SESSION['fullname'] = $fullname; // Update session
        }
        $stmt->close();
    }

    // 2. Handle Profile Picture Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        
        // Ensure the upload directory exists
        $upload_dir = 'uploads/profile_pics/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = $_FILES['profile_pic']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed image types
        $allowed_extensions = ["jpg", "jpeg", "png", "gif", "webp"];
        
        if (in_array($file_ext, $allowed_extensions)) {
            // Create a unique file name to prevent overwriting
            $new_file_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
            $target_file = $upload_dir . $new_file_name;
            
            // Move file to the directory
            if (move_uploaded_file($file_tmp, $target_file)) {
                
                // Update Database with the new file path
                $query = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $target_file, $user_id);
                
                if ($stmt->execute()) {
                    // Update Session so the navbar and profile update instantly
                    $_SESSION['profile_pic'] = $target_file; 
                }
                $stmt->close();
            }
        }
    }

    // Redirect back to profile with a success message
    header("Location: profile.php?success=1");
    exit();
}
?>