<?php
require_once 'session_manager.php';
validateUserAccess('client');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$client_id = $_SESSION['user_id'];
error_log("Client messages page loaded. Client ID: $client_id, Session data: " . print_r($_SESSION, true));

// Fetch client profile image, email, and name
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$client_email = '';
$client_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $client_email = $row['email'];
    $client_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }

// Fetch all attorneys and admins with profile images (so clients can message both)
$attorneys = [];
$stmt = $conn->prepare("SELECT id, name, profile_image, user_type FROM user_form WHERE user_type IN ('attorney', 'admin') ORDER BY user_type, name");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $img = $row['profile_image'];
    if (!$img || !file_exists($img)) {
        $img = $row['user_type'] === 'admin' ? 'images/default-avatar.jpg' : 'images/default-avatar.jpg';
    }
    $row['profile_image'] = $img;
    $attorneys[] = $row;
}
// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $attorney_id = intval($_POST['attorney_id']);
    $msgs = [];
    
    // Debug: Log the IDs being used
    error_log("Fetching messages: client_id = $client_id, attorney_id = $attorney_id");
    // Fetch client profile image
    $client_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'images/default-avatar.jpg';
    // Fetch attorney profile image
    $attorney_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $attorney_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $attorney_img = $row['profile_image'];
    if (!$attorney_img || !file_exists($attorney_img)) $attorney_img = 'images/default-avatar.jpg';
    // Fetch client to attorney/admin messages
    $stmt1 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?");
    $stmt1->bind_param('ii', $client_id, $attorney_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    $client_messages_count = 0;
    while ($row = $result1->fetch_assoc()) {
        $row['profile_image'] = $client_img;
        $msgs[] = $row;
        $client_messages_count++;
    }
    error_log("Found $client_messages_count client messages in client_messages table");
    
    // Fetch client to admin messages (stored in admin_messages table)
    // When client sends to admin: admin_id = client_id, recipient_id = admin_id
    $stmt1a = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM admin_messages WHERE admin_id=? AND recipient_id=?");
    if (!$stmt1a) {
        error_log("Error preparing statement for client to admin messages: " . $conn->error);
    }
    $stmt1a->bind_param('ii', $client_id, $attorney_id);
    if (!$stmt1a->execute()) {
        error_log("Error executing statement for client to admin messages: " . $stmt1a->error);
    }
    $result1a = $stmt1a->get_result();
    if (!$result1a) {
        error_log("Error getting result for client to admin messages: " . $stmt1a->error);
    }
    $admin_messages_count = 0;
    while ($row = $result1a->fetch_assoc()) {
        $row['profile_image'] = $client_img;
        $msgs[] = $row;
        $admin_messages_count++;
    }
    error_log("Found $admin_messages_count client messages in admin_messages table");
    
    // Debug: Let's also check what's in the admin_messages table for this client
    $debug_stmt = $conn->prepare("SELECT COUNT(*) as total FROM admin_messages WHERE admin_id=? OR recipient_id=?");
    $debug_stmt->bind_param('ii', $client_id, $client_id);
    $debug_stmt->execute();
    $debug_result = $debug_stmt->get_result();
    if ($debug_row = $debug_result->fetch_assoc()) {
        error_log("Total admin_messages records for client $client_id: " . $debug_row['total']);
    }
    
    // Debug: Let's also check what's in the admin_messages table for this specific conversation
    $debug_stmt2 = $conn->prepare("SELECT admin_id, recipient_id, message FROM admin_messages WHERE (admin_id=? AND recipient_id=?) OR (admin_id=? AND recipient_id=?)");
    if (!$debug_stmt2) {
        error_log("Error preparing debug statement: " . $conn->error);
    }
    $debug_stmt2->bind_param('iiii', $client_id, $attorney_id, $attorney_id, $client_id);
    if (!$debug_stmt2->execute()) {
        error_log("Error executing debug statement: " . $debug_stmt2->error);
    }
    $debug_result2 = $debug_stmt2->get_result();
    if (!$debug_result2) {
        error_log("Error getting debug result: " . $debug_stmt2->error);
    }
    $debug_count = 0;
    while ($debug_row2 = $debug_result2->fetch_assoc()) {
        error_log("Debug message: admin_id=" . $debug_row2['admin_id'] . ", recipient_id=" . $debug_row2['recipient_id'] . ", message=" . $debug_row2['message']);
        $debug_count++;
    }
    error_log("Total messages in conversation between client $client_id and attorney/admin $attorney_id: $debug_count");
    
    // Fetch attorney to client messages
    $stmt2 = $conn->prepare("SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?");
    $stmt2->bind_param('ii', $attorney_id, $client_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $row['profile_image'] = $attorney_img;
        $msgs[] = $row;
    }
    
    // Fetch admin to client messages
    $stmt3 = $conn->prepare("SELECT message, sent_at, 'admin' as sender FROM admin_messages WHERE admin_id=? AND recipient_id=?");
    $stmt3->bind_param('ii', $attorney_id, $client_id);
    $stmt3->execute();
    $result3 = $stmt3->get_result();
    while ($row = $result3->fetch_assoc()) {
        $row['profile_image'] = $attorney_img;
        $msgs[] = $row;
    }
    // Sort by sent_at
    usort($msgs, function($a, $b) { return strtotime($a['sent_at']) - strtotime($b['sent_at']); });
    
    // Debug: Log the messages being sent
    error_log("Client $client_id fetching messages with attorney/admin $attorney_id. Found " . count($msgs) . " messages.");
    
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}
// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $recipient_id = intval($_POST['attorney_id']);
    $msg = $_POST['message'];
    
    // Check if recipient is admin or attorney
    $stmt = $conn->prepare("SELECT user_type FROM user_form WHERE id=?");
    $stmt->bind_param("i", $recipient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['user_type'] === 'admin') {
            // Send to admin_messages table - for client-to-admin messages
            // We'll use admin_id = client's ID (sender) and recipient_id = admin's ID (recipient)
            // This way it's clearer who sent the message
            $stmt = $conn->prepare("INSERT INTO admin_messages (admin_id, recipient_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $client_id, $recipient_id, $msg);
            error_log("Client $client_id sending message to admin $recipient_id: $msg");
        } else {
            // Send to client_messages table (for attorneys)
            $stmt = $conn->prepare("INSERT INTO client_messages (client_id, recipient_id, message) VALUES (?, ?, ?)");
            $stmt->bind_param('iis', $client_id, $recipient_id, $msg);
            error_log("Client $client_id sending message to attorney $recipient_id: $msg");
        }
        $stmt->execute();
        $result = $stmt->affected_rows > 0 ? 'success' : 'error';
        
        if ($result === 'success') {
            // Log to audit trail
            global $auditLogger;
            $auditLogger->logAction(
                $client_id,
                $client_name,
                'client',
                'Message Send',
                'Communication',
                "Sent message to " . ($row['user_type'] === 'admin' ? 'admin' : 'attorney') . " ID: $recipient_id",
                'success',
                'low'
            );
        }
        
        error_log("Message insert result: $result");
        echo $result;
    } else {
        echo 'error';
    }
    exit();
}
// Handle AJAX create case
if (isset($_POST['action']) && $_POST['action'] === 'create_case_from_chat') {
    $attorney_id = intval($_POST['attorney_id']);
    $title = $_POST['case_title'];
    $description = $_POST['summary'];
    $stmt = $conn->prepare("INSERT INTO client_cases (title, description, client_id) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $title, $description, $client_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">

</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
                <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="client_dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="client_documents.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Document Generation</span>
                </a>
            </li>
            <li>
                <a href="client_cases.php">
                    <i class="fas fa-gavel"></i>
                    <span>My Cases</span>
                </a>
            </li>
            <li>
                <a href="client_schedule.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>My Schedule</span>
                </a>
            </li>
            <li>
                <a href="client_messages.php">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </li>

        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>Messages</h1>
                <p>Communicate with your attorneys</p>
            </div>
            <div class="user-info">
                <div class="profile-dropdown" style="display: flex; align-items: center; gap: 12px;">
                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Client" style="object-fit:cover;width:42px;height:42px;border-radius:50%;border:2px solid #1976d2;cursor:pointer;" onclick="toggleProfileDropdown()">
                    <div class="user-details">
                        <h3><?php echo $_SESSION['client_name']; ?></h3>
                        <p>Client</p>
                    </div>
                    
                    <!-- Profile Dropdown Menu -->
                    <div class="profile-dropdown-content" id="profileDropdown">
                        <a href="#" onclick="editProfile()">
                            <i class="fas fa-user-edit"></i>
                            Edit Profile
                        </a>
                        <a href="logout.php" class="logout">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="chat-container">
            <!-- Attorney/Admin List -->
            <div class="attorney-list">
                <h3>Attorneys & Administrators</h3>
                <ul id="attorneyList">
                    <?php foreach ($attorneys as $a): ?>
                    <li class="attorney-item" data-id="<?= $a['id'] ?>" onclick="selectAttorney(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
                        <img src='<?= htmlspecialchars($a['profile_image']) ?>' alt='<?= ucfirst($a['user_type']) ?>' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;object-fit:cover;'>
                        <span><?= htmlspecialchars($a['name']) ?></span>
                        <small style="color: #666; font-size: 11px; display: block; margin-top: -2px;">
                            <?= ucfirst($a['user_type']) ?>
                        </small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <h2 id="selectedAttorney">Select an attorney</h2>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <p style="color:#888;text-align:center;">Select an attorney to start conversation.</p>
                </div>
                <div class="chat-compose" id="chatCompose" style="display:none;">
                    <textarea id="messageInput" placeholder="Type your message..."></textarea>
                    <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal" id="createCaseModal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Case from Conversation</h2>
                <button class="close-modal" onclick="closeCreateCaseModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createCaseForm">
                    <div class="form-group">
                        <label>Attorney Name</label>
                        <input type="text" name="attorney_name" id="caseAttorneyName" readonly>
                    </div>
                    <div class="form-group">
                        <label>Case Title</label>
                        <input type="text" name="case_title" required>
                    </div>
                    <div class="form-group">
                        <label>Summary</label>
                        <textarea name="summary" rows="3"></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeCreateCaseModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Case</button>
                    </div>
                </form>
                <div id="caseSuccessMsg" style="display:none; color:green; margin-top:10px;">Case created successfully!</div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <span class="close" onclick="closeEditProfileModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="profile-edit-container">
                    <form id="editProfileForm" class="profile-form">
                        <div class="form-section">
                            <div class="profile-image-section">
                                <div class="current-profile-image">
                                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Current Profile" id="currentProfileImage">
                                </div>
                                <div class="image-upload">
                                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" onchange="previewImage(this)">
                                    <label for="profileImageInput" class="upload-btn">
                                        <i class="fas fa-camera"></i>
                                        Change Photo
                                    </label>
                                    <p class="upload-hint">Click to upload a new profile picture</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <div class="form-group">
                                <label for="profileName">Full Name</label>
                                <input type="text" id="profileName" name="name" value="<?= htmlspecialchars($client_name) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="profileEmail">Email</label>
                                <input type="email" id="profileEmail" name="email" value="<?= htmlspecialchars($client_email) ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                                <small style="color: #666; font-size: 12px;">Email address cannot be changed for security reasons.</small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeEditProfileModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Professional Chat Container */
        .chat-container { 
            display: flex; 
            height: 75vh; 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%); 
            border-radius: 20px; 
            box-shadow: 
                0 8px 32px rgba(93, 14, 38, 0.12),
                0 4px 16px rgba(93, 14, 38, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8); 
            overflow: hidden; 
            border: 1px solid rgba(93, 14, 38, 0.1);
            margin-top: 20px;
            position: relative;
        }
        
        .chat-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
            z-index: 10;
        }

        /* Attorney List Styling */
        .attorney-list { 
            width: 280px; 
            background: linear-gradient(180deg, #f8f9fa 0%, #ffffff 50%, #f8f9fa 100%); 
            border-right: 2px solid rgba(93, 14, 38, 0.08); 
            padding: 24px 0; 
            position: relative;
            overflow: hidden;
        }
        
        .attorney-list::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 1px;
            height: 100%;
            background: linear-gradient(180deg, transparent, rgba(93, 14, 38, 0.1), transparent);
        }
        
        .attorney-list h3 { 
            text-align: center; 
            margin-bottom: 24px; 
            color: var(--primary-color); 
            font-size: 1.4rem;
            font-weight: 700;
            padding: 0 20px;
            position: relative;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .attorney-list h3::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }
        
        .attorney-list ul { 
            list-style: none; 
            padding: 0; 
            margin: 0; 
        }
        
        .attorney-item { 
            display: flex; 
            align-items: center; 
            gap: 16px; 
            padding: 18px 24px; 
            cursor: pointer; 
            border-radius: 16px; 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            margin: 0 16px 10px 16px;
            border: 2px solid transparent;
            position: relative;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
        }
        
        .attorney-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(93, 14, 38, 0.02), rgba(93, 14, 38, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .attorney-item:hover { 
            background: linear-gradient(135deg, #e3f2fd 0%, #f3f8ff 100%); 
            border-color: var(--primary-color);
            transform: translateY(-2px) scale(1.02);
            box-shadow: 
                0 8px 25px rgba(93, 14, 38, 0.15),
                0 4px 15px rgba(93, 14, 38, 0.1);
        }
        
        .attorney-item:hover::before {
            opacity: 1;
        }
        
        .attorney-item.active { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            color: white;
            box-shadow: 
                0 8px 25px rgba(93, 14, 38, 0.25),
                0 4px 15px rgba(93, 14, 38, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .attorney-item.active::before {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            opacity: 1;
        }
        
        .attorney-item.active span {
            color: white;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .attorney-item img { 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            border: 3px solid var(--primary-color);
            object-fit: cover;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(93, 14, 38, 0.15);
        }
        
        .attorney-item:hover img {
            border-color: var(--secondary-color);
            transform: scale(1.1);
        }
        
        .attorney-item.active img {
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
        }
        
        .attorney-item span {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-color);
            transition: all 0.3s ease;
        }

        /* Chat Area Styling */
        .chat-area { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
        }
        
        .chat-header { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            padding: 24px 32px; 
            border-bottom: 2px solid rgba(93, 14, 38, 0.08); 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
            border-radius: 0 20px 0 0;
            position: relative;
            overflow: hidden;
        }
        
        .chat-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(93, 14, 38, 0.1), transparent);
        }
        
        .chat-header h2 { 
            margin: 0; 
            font-size: 1.5rem; 
            color: var(--primary-color); 
            font-weight: 700;
            position: relative;
            text-shadow: 0 1px 2px rgba(93, 14, 38, 0.1);
        }
        
        .chat-header h2::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 30px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 1px;
        }

        /* Chat Messages Styling */
        .chat-messages { 
            flex: 1; 
            padding: 28px; 
            overflow-y: auto; 
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            position: relative;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }
        
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Message Bubble Styling */
        .message-bubble { 
            max-width: 65%; 
            margin-bottom: 24px; 
            padding: 18px 22px; 
            border-radius: 24px; 
            font-size: 0.95rem; 
            position: relative; 
            line-height: 1.6;
            box-shadow: 
                0 4px 15px rgba(0, 0, 0, 0.08),
                0 2px 8px rgba(0, 0, 0, 0.04);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: flex-end;
            gap: 14px;
            overflow: hidden;
        }
        
        .message-bubble::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .message-content {
            flex: 1;
            min-width: 0;
            position: relative;
            z-index: 2;
        }
        
        .message-bubble:hover {
            transform: translateY(-2px) scale(1.01);
            box-shadow: 
                0 8px 25px rgba(0, 0, 0, 0.12),
                0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .message-bubble:hover::before {
            opacity: 1;
        }
        
        .message-bubble.sent { 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            margin-left: auto; 
            color: white;
            border-bottom-right-radius: 12px;
            position: relative;
        }
        
        .message-bubble.sent::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0;
            height: 0;
            border-left: 12px solid var(--secondary-color);
            border-top: 12px solid transparent;
        }
        
        .message-bubble.received { 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); 
            border: 2px solid rgba(93, 14, 38, 0.08); 
            color: var(--text-color);
            border-bottom-left-radius: 12px;
            position: relative;
        }
        
        .message-bubble.received::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 0;
            border-right: 12px solid #f8f9fa;
            border-top: 12px solid transparent;
        }
        
        .message-text p {
            margin: 0;
            word-wrap: break-word;
        }
        
        .message-meta { 
            font-size: 0.8rem; 
            color: rgba(255, 255, 255, 0.9); 
            margin-top: 10px; 
            text-align: right; 
            font-weight: 500;
            opacity: 0.9;
            transition: opacity 0.3s ease;
        }
        
        .message-bubble.received .message-meta {
            color: #666;
        }
        
        .message-bubble:hover .message-meta {
            opacity: 1;
        }
        
        .message-time {
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        
        .message-bubble.received .message-time {
            background: rgba(93, 14, 38, 0.1);
        }

        /* Chat Compose Styling */
        .chat-compose { 
            display: flex; 
            gap: 18px; 
            padding: 28px 32px; 
            border-top: 2px solid rgba(93, 14, 38, 0.08); 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
            border-radius: 0 0 20px 20px;
            position: relative;
            overflow: hidden;
        }
        
        .chat-compose::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(93, 14, 38, 0.1), transparent);
        }
        
        .chat-compose textarea { 
            flex: 1; 
            border-radius: 16px; 
            border: 2px solid rgba(93, 14, 38, 0.1); 
            padding: 18px 22px; 
            resize: none; 
            font-size: 0.95rem; 
            font-family: inherit;
            line-height: 1.5;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            min-height: 55px;
            max-height: 120px;
            box-shadow: 
                inset 0 2px 4px rgba(93, 14, 38, 0.05),
                0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .chat-compose textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 
                0 0 0 4px rgba(93, 14, 38, 0.1),
                inset 0 2px 4px rgba(93, 14, 38, 0.05);
            background: white;
            transform: translateY(-1px);
        }
        
        .chat-compose button { 
            padding: 18px 32px; 
            border-radius: 16px; 
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); 
            color: #fff; 
            border: none; 
            font-weight: 700; 
            cursor: pointer; 
            font-size: 1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            min-width: 120px;
            box-shadow: 
                0 4px 15px rgba(93, 14, 38, 0.2),
                0 2px 8px rgba(93, 14, 38, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .chat-compose button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .chat-compose button:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 
                0 8px 25px rgba(93, 14, 38, 0.3),
                0 4px 15px rgba(93, 14, 38, 0.2);
        }
        
        .chat-compose button:hover::before {
            left: 100%;
        }

        /* Empty State Styling */
        .chat-messages p[style*="color:#888"] {
            color: #666 !important;
            font-size: 1.2rem;
            text-align: center;
            padding: 80px 40px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            border: 3px dashed rgba(93, 14, 38, 0.15);
            margin: 40px 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 
                0 4px 20px rgba(93, 14, 38, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }
        
        .chat-messages p[style*="color:#888"]::before {
            content: '💬';
            font-size: 3rem;
            display: block;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        
        .chat-messages p[style*="color:#888"]::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(93, 14, 38, 0.05), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Responsive Design */
        @media (max-width: 900px) { 
            .chat-container { 
                flex-direction: column; 
                height: auto; 
                margin: 20px 10px;
            } 
            .attorney-list { 
                width: 100%; 
                border-right: none; 
                border-bottom: 1px solid #e9ecef; 
                padding: 20px 0;
            }
            .attorney-item {
                margin: 0 20px 8px 20px;
            }
            .chat-messages {
                padding: 20px;
            }
            .chat-compose {
                padding: 20px;
            }
        }
    </style>
    <script>
        let selectedAttorneyId = null;
        function selectAttorney(id, name) {
            selectedAttorneyId = id;
            document.getElementById('selectedAttorney').innerText = name;
            document.getElementById('chatCompose').style.display = 'flex';
            fetchMessages();
        }
        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('attorney_id', selectedAttorneyId);
            fd.append('message', input.value);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
        function openCreateCaseModal() {
            if (selectedAttorneyId !== null) {
                const attorneyName = document.querySelector('.attorney-item[data-id="'+selectedAttorneyId+'"] span').innerText;
                document.getElementById('caseAttorneyName').value = attorneyName;
                document.getElementById('createCaseModal').style.display = 'block';
            }
        }
        function closeCreateCaseModal() {
            document.getElementById('createCaseModal').style.display = 'none';
        }
        document.getElementById('createCaseForm').onsubmit = function(e) {
            e.preventDefault();
            if (!selectedAttorneyId) return;
            const fd = new FormData(this);
            fd.append('action', 'create_case_from_chat');
            fd.append('attorney_id', selectedAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        document.getElementById('caseSuccessMsg').style.display = 'block';
                        setTimeout(() => {
                            closeCreateCaseModal();
                            document.getElementById('caseSuccessMsg').style.display = 'none';
                        }, 1000);
                    } else {
                        alert('Error creating case.');
                    }
                });
        };
    </script>
    <script>
        function fetchMessages() {
            if (!selectedAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('attorney_id', selectedAttorneyId);
            console.log('Fetching messages for attorney/admin:', selectedAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    console.log('Received messages:', msgs);
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'client';
                        console.log('Message:', m.message, 'Sender:', m.sender, 'Sent:', sent);
                        chat.innerHTML += `
                <div class='message-bubble ${sent ? 'sent' : 'received'}'>
                    ${sent ? '' : `<img src='${m.profile_image}' alt='Attorney' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-right:12px;'>`}
                    <div class='message-content'>
                        <div class='message-text'><p>${m.message}</p></div>
                        <div class='message-meta'><span class='message-time'>${m.sent_at}</span></div>
                    </div>
                    ${sent ? `<img src='${m.profile_image}' alt='Client' style='width:42px;height:42px;border-radius:50%;border:2px solid var(--primary-color);object-fit:cover;margin-left:12px;'>` : ''}
                </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                })
                .catch(error => {
                    console.error('Error fetching messages:', error);
                });
        }
        
        // Profile Dropdown Functions
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        
        function editProfile() {
            document.getElementById('editProfileModal').style.display = 'block';
            // Close dropdown when opening modal
            document.getElementById('profileDropdown').classList.remove('show');
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').style.display = 'none';
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('img') && !event.target.closest('.profile-dropdown')) {
                const dropdowns = document.getElementsByClassName('profile-dropdown-content');
                for (let dropdown of dropdowns) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                    }
                }
            }
            
            // Close modal when clicking outside
            if (event.target == document.getElementById('editProfileModal')) {
                closeEditProfileModal();
            }
        }

        // Profile image preview function
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('currentProfileImage').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Handle edit profile form submission
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_profile');
            
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating profile: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating profile. Please try again.');
            });
        });
    </script>
</body>
</html> 