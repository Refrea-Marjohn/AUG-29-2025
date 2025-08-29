<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_type'], ['client', 'attorney', 'employee', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        
        if (empty($name)) {
            $response['message'] = "Name is required.";
        } else {
            // Get current profile image
            $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $current_data = $result->fetch_assoc();
            $profile_image = $current_data['profile_image'];
            
            // Handle profile image upload
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $file_type = $_FILES['profile_image']['type'];
                
                if (in_array($file_type, $allowed_types)) {
                    $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = $user_id . '_' . time() . '.' . $file_extension;
                    
                    // Determine upload path based on user type
                    switch ($user_type) {
                        case 'client':
                            $upload_path = 'uploads/client/' . $new_filename;
                            break;
                        case 'attorney':
                            $upload_path = 'uploads/attorney/' . $new_filename;
                            break;
                        case 'employee':
                            $upload_path = 'uploads/employee/' . $new_filename;
                            break;
                        case 'admin':
                            $upload_path = 'uploads/admin/' . $new_filename;
                            break;
                        default:
                            $upload_path = 'uploads/client/' . $new_filename;
                    }
                    
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                        // Delete old profile image if it exists and is not the default
                        $default_image = 'images/default-avatar.jpg';
                        if ($profile_image && $profile_image !== $default_image && file_exists($profile_image)) {
                            unlink($profile_image);
                        }
                        $profile_image = $upload_path;
                    }
                }
            }
            
            // Update user data - only name and profile image, email remains unchanged
            $stmt = $conn->prepare("UPDATE user_form SET name = ?, profile_image = ? WHERE id = ?");
            $stmt->bind_param('ssi', $name, $profile_image, $user_id);
            
            if ($stmt->execute()) {
                // Update appropriate session variable based on user type
                switch ($user_type) {
                    case 'client':
                        $_SESSION['client_name'] = $name;
                        break;
                    case 'attorney':
                        $_SESSION['attorney_name'] = $name;
                        break;
                    case 'employee':
                        $_SESSION['employee_name'] = $name;
                        break;
                    case 'admin':
                        $_SESSION['user_name'] = $name;
                        break;
                }
                
                $response['success'] = true;
                $response['message'] = "Profile updated successfully!";
            } else {
                $response['message'] = "Failed to update profile. Please try again.";
            }
        }
    } else {
        $response['message'] = "Invalid request method.";
    }
} catch (Exception $e) {
    $response['message'] = "An error occurred: " . $e->getMessage();
}

echo json_encode($response);
?>
