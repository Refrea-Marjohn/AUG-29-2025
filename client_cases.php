<?php
require_once 'session_manager.php';
validateUserAccess('client');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$client_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_image, email, name FROM user_form WHERE id=?");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$res = $stmt->get_result();
$profile_image = '';
$user_email = '';
$user_name = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $user_email = $row['email'];
    $user_name = $row['name'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }
$cases = [];
$sql = "SELECT ac.*, uf.name as attorney_name FROM attorney_cases ac LEFT JOIN user_form uf ON ac.attorney_id = uf.id WHERE ac.client_id=? ORDER BY ac.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $client_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}

// Ensure document request tables exist (in case attorney page has not created them yet)
$conn->query("CREATE TABLE IF NOT EXISTS document_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    attorney_id INT NOT NULL,
    client_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE NULL,
    status ENUM('Requested','Submitted','Reviewed','Approved','Rejected','Called') DEFAULT 'Requested',
    attorney_comment TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

// Add attorney_comment column if it doesn't exist
$conn->query("ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS attorney_comment TEXT NULL AFTER status");
$conn->query("CREATE TABLE IF NOT EXISTS document_request_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    client_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);");

// Client uploads files for a request
if (isset($_POST['action']) && $_POST['action'] === 'upload_request_files') {
    $request_id = intval($_POST['request_id']);
    $upload_dir = __DIR__ . '/uploads/client/';
    if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
    $saved = 0;
    if (!empty($_FILES['files'])) {
        foreach ($_FILES['files']['tmp_name'] as $idx => $tmp) {
            if (!is_uploaded_file($tmp)) continue;
            $orig = basename($_FILES['files']['name'][$idx]);
            $ext = pathinfo($orig, PATHINFO_EXTENSION);
            $safe = $client_id . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/','_', $orig);
            $dest = $upload_dir . $safe;
            if (move_uploaded_file($tmp, $dest)) {
                $rel = 'uploads/client/' . $safe;
                $stmt = $conn->prepare("INSERT INTO document_request_files (request_id, client_id, file_path, original_name) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('iiss', $request_id, $client_id, $rel, $orig);
                $stmt->execute();
                $saved++;
            }
        }
    }
    if ($saved > 0) {
        // Update request status and notify attorney
        $stmt = $conn->prepare("UPDATE document_requests SET status='Submitted' WHERE id=?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $client_id,
            $user_name,
            'client',
            'Document Upload',
            'Document Access',
            "Uploaded $saved files for document request ID: $request_id",
            'success',
            'medium'
        );
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $stmt = $conn->prepare("SELECT attorney_id, title FROM document_requests WHERE id=?");
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                $nTitle = 'Client Document Submitted';
                $nMsg = 'Client uploaded files for request: ' . $row['title'];
                $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'attorney', ?, ?, 'success')");
                $stmtN->bind_param('iss', $row['attorney_id'], $nTitle, $nMsg);
                $stmtN->execute();
            }
        }
    }
    echo $saved > 0 ? 'success' : 'error';
    exit();
}

// List requests for a given case
if (isset($_POST['action']) && $_POST['action'] === 'list_requests') {
    $case_id = intval($_POST['case_id']);
    $stmt = $conn->prepare("SELECT dr.*, (
        SELECT COUNT(*) FROM document_request_files f WHERE f.request_id = dr.id
    ) as upload_count FROM document_requests dr WHERE dr.case_id=? AND dr.client_id=? ORDER BY dr.created_at DESC");
    $stmt->bind_param('ii', $case_id, $client_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}

// Mark all cases as read for this client
$conn->query("UPDATE client_cases SET is_read=1 WHERE client_id=$client_id AND is_read=0");
if (isset($_POST['action']) && $_POST['action'] === 'add_case') {
    $title = $_POST['title'];
    $description = $_POST['description'];
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
    <title>Case Tracking - Opiña Law Office</title>
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
            <li><a href="client_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="client_documents.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="client_cases.php" class="active"><i class="fas fa-gavel"></i><span>My Cases</span></a></li>
            <li><a href="client_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="client_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-title">
                <h1>My Cases</h1>
                <p>Track your cases, status, and schedule</p>
            </div>
            <div class="user-info">
                <div class="profile-dropdown" style="display: flex; align-items: center; gap: 12px;">
                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Client" style="object-fit:cover;width:42px;height:42px;border-radius:50%;border:2px solid #1976d2;cursor:pointer;" onclick="toggleProfileDropdown()">
                    <div class="user-details">
                        <h3><?php echo $_SESSION['client_name']; ?></h3>
                        <p>Client</p>
                    </div>
                    
                                <!-- Profile Dropdown Menu -->
            <div class="profile-dropdown-content" id="profileDropdown" style="z-index: 5000 !important;">
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

<!-- Edit Profile Modal -->
<div id="editProfileModal" class="modal" style="z-index: 9999 !important;">
    <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
        <div class="modal-header" style="z-index: 9999 !important;">
            <h2>Edit Profile</h2>
            <span class="close" onclick="closeEditProfileModal()">&times;</span>
        </div>
        <div class="modal-body" style="z-index: 9999 !important;">
            <div class="profile-edit-container">
                <form method="POST" enctype="multipart/form-data" class="profile-form" id="editProfileForm">
                    <div class="form-section">
                        <h3>Profile Picture</h3>
                        <div class="profile-image-section">
                            <img src="<?= htmlspecialchars($profile_image) ?>" alt="Current Profile" id="currentProfileImage" class="current-profile-image">
                            <div class="image-upload">
                                <label for="profile_image" class="upload-btn">
                                    <i class="fas fa-camera"></i>
                                    Change Photo
                                </label>
                                <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                                <p class="upload-hint">JPG, PNG, or GIF. Max 5MB.</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Personal Information</h3>
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?= htmlspecialchars($user_name) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_email ?? '') ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
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
        <div class="cases-container">
            <table class="cases-table">
                <thead>
                    <tr>
                        <th>Case ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Attorney</th>
                        <th>Next Hearing</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="casesTableBody">
                    <?php foreach ($cases as $case): ?>
                    <tr>
                        <td><?= htmlspecialchars($case['id']) ?></td>
                        <td><?= htmlspecialchars($case['title']) ?></td>
                        <td><?= htmlspecialchars(ucfirst(strtolower($case['case_type'] ?? '-'))) ?></td>
                        <td><span class="status-badge status-<?= strtolower($case['status'] ?? 'active') ?>"><?= htmlspecialchars($case['status'] ?? '-') ?></span></td>
                        <td><?= htmlspecialchars($case['attorney_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($case['next_hearing'] ?? '-') ?></td>
                        <td>
                            <button class="btn btn-primary btn-xs" onclick="openConversationModal(<?= $case['attorney_id'] ?>, '<?= htmlspecialchars(addslashes($case['attorney_name'])) ?>')">
                                <i class="fas fa-comments"></i> View Conversation
                            </button>
                            <button class="btn btn-warning btn-xs" onclick="openRequestsModal(<?= $case['id'] ?>)"><i class="fas fa-file-upload"></i> Document Requests</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Case Details Modal -->
        <div class="modal" id="caseModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Case Details</h2>
                    <button class="close-modal" onclick="closeCaseModal()">&times;</button>
                </div>
                <div class="modal-body" id="caseModalBody" style="z-index: 9999 !important;">
                    <!-- Dynamic case details here -->
                </div>
                <div class="modal-footer" style="z-index: 9999 !important;">
                    <button class="btn btn-secondary" onclick="closeCaseModal()">Close</button>
                </div>
            </div>
        </div>

        <!-- Conversation Modal -->
        <div class="modal" id="conversationModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="max-width:600px;" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Conversation with <span id="convAttorneyName"></span></h2>
                    <button class="close-modal" onclick="closeConversationModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <div class="chat-messages" id="convChatMessages" style="height:300px;overflow-y:auto;background:#f9f9f9;padding:16px;border-radius:8px;margin-bottom:10px;"></div>
                    <div class="chat-compose" id="convChatCompose" style="display:flex;gap:10px;">
                        <textarea id="convMessageInput" placeholder="Type your message..." style="flex:1;border-radius:8px;border:1px solid #ddd;padding:10px;resize:none;font-size:1rem;"></textarea>
                        <button class="btn btn-primary" onclick="sendConvMessage()">Send</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Requests Modal -->
        <div class="modal" id="requestsModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="max-width:700px; max-height: 90vh; overflow-y: auto;" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Document Requests</h2>
                    <button class="close-modal" onclick="closeRequestsModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <div id="clientRequestsList" style="margin-bottom:12px;"></div>
                    <form id="uploadRequestForm" style="display:none;">
                        <input type="hidden" name="request_id" id="uploadRequestId">
                        <div class="form-group">
                            <label>Upload Files</label>
                            <input type="file" name="files[]" multiple required>
                            <small style="color:#666;">Accepted: PDF, JPG, PNG. Max 10MB each.</small>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeRequestsModal()">Close</button>
                            <button type="submit" class="btn btn-primary">Submit Files</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <style>
        .cases-container { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 24px; margin-top: 24px; }
        .cases-table { width: 100%; border-collapse: collapse; background: #fff; }
        .cases-table th, .cases-table td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eee; }
        .cases-table th { background: #f7f7f7; color: #1976d2; font-weight: 600; }
        .cases-table tr:last-child td { border-bottom: none; }
        .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; }
        .status-active { background: #28a745; color: white; }
                 .status-requested { background: #ffc107; color: #333; }
         .status-submitted { background: #17a2b8; color: white; }
         .status-reviewed { background: #6f42c1; color: white; }
         .status-approved { background: #28a745; color: white; }
         .status-rejected { background: #dc3545; color: white; }
         .status-called { background: #fd7e14; color: white; }
        .btn-xs { font-size: 0.9em; padding: 4px 10px; margin-right: 4px; }
        .timeline { border-left: 2px solid #28a745; padding-left: 15px; }
        .timeline-item { margin-bottom: 15px; }
        .timeline-item.new-case { background: #eaffea; border-left: 4px solid #28a745; border-radius: 6px; padding-left: 10px; }
        .timeline-date { font-weight: bold; color: #28a745; margin-bottom: 3px; }
        .timeline-content h4 { margin: 0 0 2px 0; font-size: 1rem; }
        .timeline-content p { margin: 0; font-size: 0.95rem; color: #444; }
        @media (max-width: 900px) { .cases-container { padding: 10px; } .cases-table th, .cases-table td { padding: 8px 4px; } }
        .message-bubble { max-width: 70%; margin-bottom: 14px; padding: 12px 18px; border-radius: 16px; font-size: 1rem; position: relative; }
        .message-bubble.sent { background: #e3f2fd; margin-left: auto; }
        .message-bubble.received { background: #fff; border: 1px solid #eee; }
        .message-meta { font-size: 0.85em; color: #888; margin-top: 4px; text-align: right; }
    </style>
    <script>
        let convAttorneyId = null;
        function openConversationModal(attorneyId, attorneyName) {
            convAttorneyId = attorneyId;
            document.getElementById('convAttorneyName').innerText = attorneyName;
            document.getElementById('conversationModal').style.display = 'block';
            fetchConvMessages();
        }
        function closeConversationModal() {
            document.getElementById('conversationModal').style.display = 'none';
            document.getElementById('convChatMessages').innerHTML = '';
            document.getElementById('convMessageInput').value = '';
        }
        function fetchConvMessages() {
            if (!convAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'fetch_messages');
            fd.append('attorney_id', convAttorneyId);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('convChatMessages');
                    chat.innerHTML = '';
                    msgs.forEach(m => {
                        const sent = m.sender === 'client';
                        chat.innerHTML += `<div class='message-bubble ${sent ? 'sent' : 'received'}'><div class='message-text'><p>${m.message}</p></div><div class='message-meta'><span class='message-time'>${m.sent_at}</span></div></div>`;
                    });
                    chat.scrollTop = chat.scrollHeight;
                });
        }
        function sendConvMessage() {
            const input = document.getElementById('convMessageInput');
            if (!input.value.trim() || !convAttorneyId) return;
            const fd = new FormData();
            fd.append('action', 'send_message');
            fd.append('attorney_id', convAttorneyId);
            fd.append('message', input.value);
            fetch('client_messages.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        input.value = '';
                        fetchConvMessages();
                    } else {
                        alert('Error sending message.');
                    }
                });
        }
        // Requests UX
        let activeCaseId = null;
        function openRequestsModal(caseId) {
            activeCaseId = caseId;
            document.getElementById('clientRequestsList').innerHTML = '';
            document.getElementById('requestsModal').style.display = 'block';
            fetchClientRequests();
        }
        function closeRequestsModal() {
            document.getElementById('requestsModal').style.display = 'none';
            document.getElementById('uploadRequestForm').style.display = 'none';
        }
        function fetchClientRequests() {
            if (!activeCaseId) return;
            const fd = new FormData();
            fd.append('action','list_requests');
            fd.append('case_id', activeCaseId);
            fetch('client_cases.php', { method:'POST', body: fd })
                .then(r=>r.json()).then(rows=>{
                    const wrap = document.getElementById('clientRequestsList');
                    if (!rows.length) { wrap.innerHTML = '<p style="color:#888;">No document requests yet.</p>'; return; }
                                         wrap.innerHTML = rows.map(r=>`
                         <div style="border:1px solid #eee;border-radius:8px;padding:10px;margin-bottom:8px;">
                             <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                                 <div style="flex:1;">
                                     <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                         <strong>${r.title}</strong>
                                         <span class="status-badge status-${(r.status||'Requested').toLowerCase()}" style="padding:4px 8px;border-radius:12px;font-size:0.8rem;font-weight:500;">${r.status}</span>
                                     </div>
                                     <div style="color:#666;margin-bottom:4px;">${r.description || ''}</div>
                                     <div style="color:#888;font-size:0.9rem;">Due: ${r.due_date || '—'} • Uploads: ${r.upload_count}</div>
                                     <div style="color:#aaa;font-size:0.85rem;">Created: ${r.created_at}</div>
                                     ${r.attorney_comment ? `<div style="color:#1976d2;margin-top:4px;font-style:italic;background:#f0f8ff;padding:8px;border-radius:6px;border-left:3px solid #1976d2;"><strong>Attorney Feedback:</strong> ${r.attorney_comment}</div>` : ''}
                                     <div id="clientFiles-${r.id}" style="margin-top:8px;display:none;background:#f9f9f9;border:1px solid #eee;padding:8px;border-radius:6px;"></div>
                                 </div>
                                 <div style="display:flex;flex-direction:column;gap:6px;">
                                     <button class="btn btn-info btn-xs" onclick="viewClientFiles(${r.id})"><i class='fas fa-folder-open'></i> View Files</button>
                                     ${r.status === 'Requested' || r.status === 'Called' ? `<button class="btn btn-primary btn-xs" onclick="startUpload(${r.id})"><i class='fas fa-upload'></i> Upload</button>` : ''}
                                 </div>
                             </div>
                         </div>
                     `).join('');
                });
        }
        function startUpload(requestId) {
            document.getElementById('uploadRequestId').value = requestId;
            document.getElementById('uploadRequestForm').style.display = 'block';
        }
        
        function viewClientFiles(requestId) {
            const box = document.getElementById('clientFiles-'+requestId);
            const fd = new FormData();
            fd.append('action','list_request_files');
            fd.append('request_id', requestId);
            fetch('client_cases.php', { method:'POST', body: fd })
                .then(r=>r.json()).then(files=>{
                    if (files.length===0) { 
                        box.innerHTML = '<em style="color:#888;">No files uploaded yet.</em>'; 
                    } else {
                        box.innerHTML = files.map(f=>`
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;">
                                <a href="${f.file_path}" target="_blank" style="color:#1976d2;text-decoration:none;">
                                    <i class="fas fa-file"></i> ${f.original_name}
                                </a>
                                <span style="color:#888;font-size:0.85rem;">${f.uploaded_at}</span>
                            </div>
                        `).join('');
                    }
                    box.style.display = box.style.display === 'none' ? 'block' : 'none';
                });
        }
        document.getElementById('uploadRequestForm').onsubmit = function(e){
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action','upload_request_files');
            fetch('client_cases.php', { method:'POST', body: fd })
                .then(r=>r.text()).then(res=>{
                    if (res==='success') {
                        alert('Files submitted.');
                        this.reset();
                        document.getElementById('uploadRequestForm').style.display = 'none';
                        fetchClientRequests();
                    } else {
                        alert('Upload failed. Please try again.');
                    }
                });
        };
        
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
            if (event.target.classList.contains('modal')) {
                closeEditProfileModal();
            }
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('currentProfileImage').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Handle form submission
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Profile updated successfully!');
                    closeEditProfileModal();
                    // Refresh the page to show updated profile
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the profile.');
            });
        });
    </script>
</body>
</html> 