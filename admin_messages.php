<?php
require_once 'session_manager.php';
validateUserAccess('admin');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$admin_id = $_SESSION['user_id'];

// Fetch all clients with profile images
$clients = [];
$stmt = $conn->prepare("SELECT id, name, profile_image FROM user_form WHERE user_type='client'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $img = $row['profile_image'];
    if (!$img || !file_exists($img)) $img = 'images/default-avatar.jpg';
    $row['profile_image'] = $img;
    $clients[] = $row;
}

// Handle AJAX fetch messages
if (isset($_POST['action']) && $_POST['action'] === 'fetch_messages') {
    $client_id = intval($_POST['client_id']);
    $msgs = [];
    
    // Fetch admin profile image
    $admin_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $admin_img = $row['profile_image'];
    if (!$admin_img || !file_exists($admin_img)) $admin_img = 'images/default-avatar.jpg';
    
    // Fetch client profile image
    $client_img = '';
    $stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) $client_img = $row['profile_image'];
    if (!$client_img || !file_exists($client_img)) $client_img = 'images/default-avatar.jpg';
    
    // Fetch admin to client messages
    $stmt1 = $conn->prepare("SELECT message, sent_at, 'admin' as sender FROM admin_messages WHERE admin_id=? AND recipient_id=?");
    $stmt1->bind_param('ii', $admin_id, $client_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while ($row = $result1->fetch_assoc()) {
        $row['profile_image'] = $admin_img;
        $msgs[] = $row;
    }
    
    // Fetch client to admin messages
    // Now admin_id = sender (client's ID), recipient_id = recipient (admin's ID)
    $stmt2 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM admin_messages WHERE admin_id=? AND recipient_id=?");
    $stmt2->bind_param('ii', $client_id, $admin_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) {
        $row['profile_image'] = $client_img;
        $msgs[] = $row;
    }
    
    // Sort by sent_at
    usort($msgs, function($a, $b) { return strtotime($a['sent_at']) - strtotime($b['sent_at']); });
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}

// Handle AJAX send message
if (isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $client_id = intval($_POST['client_id']);
    $msg = $_POST['message'];
    $stmt = $conn->prepare("INSERT INTO admin_messages (admin_id, recipient_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $admin_id, $client_id, $msg);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log message sending to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Message Send',
            'Communication',
            "Sent message to client ID: $client_id",
            'success',
            'low'
        );
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

// Handle AJAX create case from chat
if (isset($_POST['action']) && $_POST['action'] === 'create_case_from_chat') {
    $client_id = intval($_POST['client_id']);
    $title = $_POST['case_title'];
    $description = $_POST['summary'];
    $case_type = isset($_POST['case_type']) ? $_POST['case_type'] : null;
    $status = 'Active'; // Automatically set to Active
    $next_hearing = null; // No next hearing field anymore
    $stmt = $conn->prepare("INSERT INTO attorney_cases (title, description, attorney_id, client_id, case_type, status, next_hearing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiisss', $title, $description, $admin_id, $client_id, $case_type, $status, $next_hearing);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log case creation from chat to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Create from Chat',
            'Case Management',
            "Created case from chat: $title (Type: $case_type, Status: Active)",
            'success',
            'medium'
        );
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$res = $stmt->get_result();
$admin_profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $admin_profile_image = $row['profile_image'];
}
if (!$admin_profile_image || !file_exists($admin_profile_image)) {
    $admin_profile_image = 'images/default-avatar.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - Opiña Law Office</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="images/logo.jpg" alt="Logo">
            <h2>Opiña Law Office</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="admin_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="admin_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generations</span></a></li>
            <li><a href="admin_schedule.php"><i class="fas fa-calendar-alt"></i><span>Schedule</span></a></li>
            <li><a href="admin_usermanagement.php"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
            <li><a href="admin_managecases.php"><i class="fas fa-gavel"></i><span>Case Management</span></a></li>
            <li><a href="admin_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="admin_messages.php" class="active"><i class="fas fa-comments"></i><span>Messages</span></a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="chat-container">
            <!-- Client List -->
            <div class="client-list">
                <h3>Clients</h3>
                <ul id="clientList">
                    <?php foreach ($clients as $c): ?>
                    <li class="client-item" data-id="<?= $c['id'] ?>" onclick="selectClient(<?= $c['id'] ?>, '<?= htmlspecialchars($c['name']) ?>')">
                        <img src='<?= htmlspecialchars($c['profile_image']) ?>' alt='Client' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'><span><?= htmlspecialchars($c['name']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <!-- Chat Area -->
            <div class="chat-area">
                <div class="chat-header">
                    <h2 id="selectedClient">Select a client</h2>
                    <button class="btn btn-primary" id="createCaseBtn" style="display:none;" onclick="openCreateCaseModal()"><i class="fas fa-gavel"></i> Create Case</button>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <p style="color:#888;text-align:center;">Select a client to start conversation.</p>
                </div>
                <div class="chat-compose" id="chatCompose" style="display:none;">
                    <textarea id="messageInput" placeholder="Type your message..."></textarea>
                    <button class="btn btn-primary" onclick="sendMessage()">Send</button>
                </div>
            </div>
        </div>
        <!-- Create Case Modal -->
        <div class="modal" id="createCaseModal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-gavel" style="margin-right: 12px; color: var(--accent-color);"></i>Create New Case</h2>
                    <button class="close-modal" onclick="closeCreateCaseModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="createCaseForm">
                        <div class="form-row">
                            <div class="form-column">
                                <div class="form-group">
                                    <label><i class="fas fa-user" style="margin-right: 8px; color: var(--accent-color);"></i>Client Name</label>
                                    <input type="text" name="client_name" id="caseClientName" readonly placeholder="Client name will be auto-filled">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-file-alt" style="margin-right: 8px; color: var(--accent-color);"></i>Case Title</label>
                                    <input type="text" name="case_title" required placeholder="Enter case title">
                                </div>
                            </div>
                            <div class="form-column">
                                <div class="form-group">
                                    <label><i class="fas fa-tags" style="margin-right: 8px; color: var(--accent-color);"></i>Case Type</label>
                                    <select name="case_type" required>
                                        <option value="">Select Type</option>
                                        <option value="criminal">Criminal</option>
                                        <option value="civil">Civil</option>
                                        <option value="family">Family</option>
                                        <option value="corporate">Corporate</option>
                                    </select>
                                </div>

                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label><i class="fas fa-align-left" style="margin-right: 8px; color: var(--accent-color);"></i>Summary</label>
                            <textarea name="summary" rows="3" placeholder="Provide a brief summary of the case"></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeCreateCaseModal()">
                                <i class="fas fa-times" style="margin-right: 8px;"></i>Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save" style="margin-right: 8px;"></i>Create Case
                            </button>
                        </div>
                    </form>
                    <div id="caseSuccessMsg" style="display:none;">
                        <i class="fas fa-check-circle" style="margin-right: 8px;"></i>Case created successfully!
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        :root {
            --primary-color: #931426;
            --secondary-color: #c41e3a;
            --accent-color: #e74c3c;
            --text-color: #2c3e50;
            --border-radius: 12px;
            --card-shadow: 0 4px 20px rgba(93, 14, 38, 0.1);
        }
        
        .chat-container { display: flex; height: 80vh; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); overflow: hidden; }
        .client-list { width: 260px; background: #f7f7f7; border-right: 1px solid #eee; padding: 20px 0; }
        .client-list h3 { text-align: center; margin-bottom: 18px; color: #1976d2; }
        .client-list ul { list-style: none; padding: 0; margin: 0; }
        .client-item { display: flex; align-items: center; gap: 12px; padding: 12px 24px; cursor: pointer; border-radius: 8px; transition: background 0.2s; }
        .client-item.active, .client-item:hover { background: #e3f2fd; }
        .client-item img { width: 38px; height: 38px; border-radius: 50%; }
        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #eee; background: #fafafa; }
        .chat-header h2 { margin: 0; font-size: 1.2rem; color: #1976d2; }
        .chat-header button { margin-left: 10px; }
        .chat-messages { flex: 1; padding: 24px; overflow-y: auto; background: #f9f9f9; }
        .message-bubble { max-width: 70%; margin-bottom: 14px; padding: 12px 18px; border-radius: 16px; font-size: 1rem; position: relative; }
        .message-bubble.sent { background: #e3f2fd; margin-left: auto; }
        .message-bubble.received { background: #fff; border: 1px solid #eee; }
        .message-meta { font-size: 0.85em; color: #888; margin-top: 4px; text-align: right; }
        .chat-compose { display: flex; gap: 10px; padding: 18px 24px; border-top: 1px solid #eee; background: #fafafa; }
        .chat-compose textarea { flex: 1; border-radius: 8px; border: 1px solid #ddd; padding: 10px; resize: none; font-size: 1rem; }
        .chat-compose button { padding: 10px 24px; border-radius: 8px; background: #1976d2; color: #fff; border: none; font-weight: 500; cursor: pointer; }
        @media (max-width: 900px) { .chat-container { flex-direction: column; height: auto; } .client-list { width: 100%; border-right: none; border-bottom: 1px solid #eee; } }

        /* Professional Modal Styling */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 20px;
            padding: 0;
            max-width: 800px;
            width: 95%;
            height: auto;
            max-height: 70vh;
            overflow: visible;
            box-shadow: 
                0 20px 60px rgba(93, 14, 38, 0.3),
                0 10px 30px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(93, 14, 38, 0.1);
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color), var(--accent-color));
            border-radius: 20px 20px 0 0;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0;
            padding: 16px 32px 12px 32px;
            border-bottom: 2px solid rgba(93, 14, 38, 0.08);
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 50%, #ffffff 100%);
            position: relative;
        }

        .modal-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(93, 14, 38, 0.1), transparent);
        }

        .modal-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            text-shadow: 0 1px 2px rgba(93, 14, 38, 0.1);
            position: relative;
        }

        .modal-header h2::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .close-modal {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid rgba(93, 14, 38, 0.1);
            font-size: 1.2rem;
            cursor: pointer;
            color: var(--primary-color);
            padding: 0;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
        }

        .close-modal:hover {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-color: transparent;
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
        }

        .modal-body {
            padding: 16px 32px 20px 32px;
            background: white;
        }

        .form-group {
            margin-bottom: 14px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .form-group label::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 20px;
            height: 2px;
            background: var(--accent-color);
            border-radius: 1px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid rgba(93, 14, 38, 0.1);
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            box-shadow: 
                inset 0 2px 4px rgba(93, 14, 38, 0.05),
                0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 
                0 0 0 4px rgba(93, 14, 38, 0.1),
                inset 0 2px 4px rgba(93, 14, 38, 0.05);
            background: white;
            transform: translateY(-2px);
        }

        .form-group input[readonly] {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-color: rgba(93, 14, 38, 0.15);
            color: var(--primary-color);
            font-weight: 600;
            cursor: not-allowed;
        }

        .form-group select {
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23931426' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
            padding-right: 48px;
            appearance: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
            line-height: 1.6;
        }

        .form-group small {
            display: block;
            margin-top: 6px;
            color: #666;
            font-size: 0.85rem;
            font-style: italic;
        }

        /* Two-Column Form Layout */
        .form-row {
            display: flex;
            gap: 32px;
            margin-bottom: 16px;
        }

        .form-column {
            flex: 1;
            min-width: 0;
        }

        .form-column:first-child {
            flex: 0.8;
        }

        .form-column:last-child {
            flex: 1.2;
        }

        .form-group.full-width {
            width: 100%;
            margin-top: 8px;
        }

        /* Responsive adjustments for two-column layout */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-column {
                margin-bottom: 0;
            }
        }

        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid rgba(93, 14, 38, 0.08);
            position: relative;
        }

        .form-actions::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(93, 14, 38, 0.1), transparent);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-secondary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        .btn-secondary:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        #caseSuccessMsg {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 16px 20px;
            border-radius: 12px;
            border: 2px solid #c3e6cb;
            text-align: center;
            font-weight: 600;
            margin-top: 20px;
            animation: successFadeIn 0.5s ease-out;
        }

        @keyframes successFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .modal-content {
                width: 95%;
                max-height: 90vh;
                margin: 20px;
            }
            
            .modal-header {
                padding: 20px 24px 16px 24px;
            }
            
            .modal-header h2 {
                font-size: 1.5rem;
            }
            
            .modal-body {
                padding: 24px;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 12px;
            }
            
            .btn-secondary,
            .btn-primary {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .modal-content {
                width: 98%;
                margin: 10px;
            }
            
            .modal-header {
                padding: 16px 20px 12px 20px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                padding: 14px 16px;
            }
        }
    </style>
    <script>
        let selectedClientId = null;
        function selectClient(id, name) {
            selectedClientId = id;
            document.getElementById('selectedClient').innerText = name;
            document.getElementById('createCaseBtn').style.display = 'inline-block';
            document.getElementById('chatCompose').style.display = 'flex';
            fetchMessages();
        }
        function fetchMessages() {
            if (!selectedClientId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('client_id', selectedClientId);
            fetch('admin_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('chatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'admin';
                        chat.innerHTML += `
                <div class='message-bubble ${sent ? 'sent' : 'received'}' style='display:flex;align-items:flex-end;gap:10px;'>
                    ${sent ? '' : `<img src='${m.profile_image}' alt='Client' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>`}
                    <div style='flex:1;'>
                        <div class='message-text'><p>${m.message}</p></div>
                        <div class='message-meta'><span class='message-time'>${m.sent_at}</span></div>
                    </div>
                    ${sent ? `<img src='${m.profile_image}' alt='Admin' style='width:38px;height:38px;border-radius:50%;border:1.5px solid #1976d2;object-fit:cover;'>` : ''}
                </div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                });
        }
        function sendMessage() {
            const input = document.getElementById('messageInput');
            if (!input.value.trim() || !selectedClientId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('client_id', selectedClientId);
            fd.append('message', input.value);
            fetch('admin_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
        document.getElementById('createCaseForm').onsubmit = function(e) {
            e.preventDefault();
            if (!selectedClientId) return;
            const fd = new FormData(this);
            fd.append('action', 'create_case_from_chat');
            fd.append('client_id', selectedClientId);
            fetch('admin_messages.php', { method: 'POST', body: fd })
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
        function openCreateCaseModal() {
            if (selectedClientId !== null) {
                const clientName = document.querySelector('.client-item[data-id="'+selectedClientId+'"] span').innerText;
                document.getElementById('caseClientName').value = clientName;
                document.getElementById('createCaseModal').style.display = 'block';
            }
        }
        function closeCreateCaseModal() {
            document.getElementById('createCaseModal').style.display = 'none';
        }
    </script>
</body>
</html>
