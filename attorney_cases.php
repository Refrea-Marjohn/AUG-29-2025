<?php
require_once 'session_manager.php';
validateUserAccess('attorney');
require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';
$attorney_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image FROM user_form WHERE id=$attorney_id");
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }
// Fetch all clients for dropdown
$clients = [];
$stmt = $conn->prepare("SELECT id, name FROM user_form WHERE user_type='client'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;
// Ensure tables for document request workflow
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES attorney_cases(id) ON DELETE CASCADE,
    FOREIGN KEY (attorney_id) REFERENCES user_form(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES user_form(id) ON DELETE CASCADE
);");

// Add attorney_comment column if it doesn't exist
$conn->query("ALTER TABLE document_requests ADD COLUMN IF NOT EXISTS attorney_comment TEXT NULL AFTER status");
$conn->query("CREATE TABLE IF NOT EXISTS document_request_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    client_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES user_form(id) ON DELETE CASCADE
);");

// Handle AJAX add case
if (isset($_POST['action']) && $_POST['action'] === 'add_case') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $client_id = intval($_POST['client_id']);
    $case_type = $_POST['case_type'];
    $status = 'Active'; // Automatically set to Active
    $next_hearing = null; // No next hearing field anymore
    $stmt = $conn->prepare("INSERT INTO attorney_cases (title, description, attorney_id, client_id, case_type, status, next_hearing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiisss', $title, $description, $attorney_id, $client_id, $case_type, $status, $next_hearing);
    $stmt->execute();

    // Notify client about the new case
    if ($stmt->affected_rows > 0) {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $attorney_id,
            $_SESSION['attorney_name'],
            'attorney',
            'Case Create',
            'Case Management',
            "Created new case: $title (Type: $case_type, Status: Active, Client ID: $client_id)",
            'success',
            'medium'
        );
        
        $notif_msg = "A new case has been created for you: $title";
        // Also write to notifications table if present
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $stmtN = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'client', ?, ?, 'info')");
            $titleN = 'New Case Assigned';
            $stmtN->bind_param('iss', $client_id, $titleN, $notif_msg);
            $stmtN->execute();
        }
    }

    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle creating a document request for a case
if (isset($_POST['action']) && $_POST['action'] === 'create_request') {
    $case_id = intval($_POST['case_id']);
    $client_id = intval($_POST['client_id']);
    $titleR = trim($_POST['title']);
    $descR = trim($_POST['description'] ?? '');
    $dueR = empty($_POST['due_date']) ? null : $_POST['due_date'];
    $stmt = $conn->prepare("INSERT INTO document_requests (case_id, attorney_id, client_id, title, description, due_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiisss', $case_id, $attorney_id, $client_id, $titleR, $descR, $dueR);
    $ok = $stmt->execute();
    if ($ok) {
        // Log to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $attorney_id,
            $_SESSION['attorney_name'],
            'attorney',
            'Document Request Create',
            'Document Management',
            "Created document request: $titleR for case ID: $case_id (Client ID: $client_id)",
            'success',
            'medium'
        );
        
        // Notify client
        if ($conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
            $nTitle = 'New Document Request';
            $nMsg = "Your attorney requested: " . $titleR . (empty($dueR) ? '' : " (Due: $dueR)");
            $stmtN = $conn->prepare("INSERT INTO notifications (user_type, title, message, type) VALUES (?, 'client', ?, ?, 'warning')");
            $stmtN->bind_param('iss', $client_id, $nTitle, $nMsg);
            $stmtN->execute();
        }
    }
    echo $ok ? 'success' : 'error';
    exit();
}
// Handle fetching requests for a case
if (isset($_POST['action']) && $_POST['action'] === 'list_requests') {
    $case_id = intval($_POST['case_id']);
    $stmt = $conn->prepare("SELECT dr.*, (
        SELECT COUNT(*) FROM document_request_files f WHERE f.request_id = dr.id
    ) as upload_count FROM document_requests dr WHERE dr.case_id=? ORDER BY dr.created_at DESC");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}
// Update a document request status
if (isset($_POST['action']) && $_POST['action'] === 'update_request_status') {
    $request_id = intval($_POST['request_id']);
    $new_status = $_POST['status'] ?? 'Requested';
    $comment = trim($_POST['comment'] ?? '');
    $allowed = ['Requested','Submitted','Reviewed','Approved','Rejected','Called'];
    if (!in_array($new_status, $allowed, true)) { echo 'error'; exit(); }
    
    // Update status and add comment if provided
    $stmt = $conn->prepare("UPDATE document_requests SET status=?, attorney_comment=? WHERE id=? AND attorney_id=?");
    $stmt->bind_param('ssii', $new_status, $comment, $request_id, $attorney_id);
    $ok = $stmt->execute();
    
    if ($ok && $comment) {
        // Store comment in a separate table for better tracking
        $conn->query("CREATE TABLE IF NOT EXISTS document_request_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            attorney_id INT NOT NULL,
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE
        )");
        
        $stmtC = $conn->prepare("INSERT INTO document_request_comments (request_id, attorney_id, comment) VALUES (?, ?, ?)");
        $stmtC->bind_param('iis', $request_id, $attorney_id, $comment);
        $stmtC->execute();
    }
    
    // Notify client about the status change
    if ($ok && $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows) {
        $stmtN = $conn->prepare("SELECT client_id, title FROM document_requests WHERE id=?");
        $stmtN->bind_param('i', $request_id);
        $stmtN->execute();
        $row = $stmtN->get_result()->fetch_assoc();
        if ($row) {
            $statusText = ucfirst($new_status);
            $nTitle = "Document Request $statusText";
            $nMsg = "Your document request '{$row['title']}' has been $statusText" . ($comment ? ": $comment" : "");
            $stmtNotif = $conn->prepare("INSERT INTO notifications (user_id, user_type, title, message, type) VALUES (?, 'client', ?, ?, ?)");
            $type = ($new_status === 'Approved') ? 'success' : (($new_status === 'Rejected') ? 'error' : 'warning');
            $stmtNotif->bind_param('isss', $row['client_id'], $nTitle, $nMsg, $type);
            $stmtNotif->execute();
        }
    }
    
    echo $ok ? 'success' : 'error';
    exit();
}
// List uploaded files for a request
if (isset($_POST['action']) && $_POST['action'] === 'list_request_files') {
    $request_id = intval($_POST['request_id']);
    $stmt = $conn->prepare("SELECT id, file_path, original_name, uploaded_at FROM document_request_files WHERE request_id=? ORDER BY uploaded_at DESC");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit();
}
// Handle AJAX fetch conversation for a case
if (isset($_POST['action']) && $_POST['action'] === 'fetch_conversation') {
    $client_id = intval($_POST['client_id']);
    $msgs = [];
    // Attorney to client (all messages)
    $stmt1 = $conn->prepare("SELECT message, sent_at, 'attorney' as sender FROM attorney_messages WHERE attorney_id=? AND recipient_id=?");
    $stmt1->bind_param('ii', $attorney_id, $client_id);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    while ($row = $result1->fetch_assoc()) $msgs[] = $row;
    // Client to attorney (all messages)
    $stmt2 = $conn->prepare("SELECT message, sent_at, 'client' as sender FROM client_messages WHERE client_id=? AND recipient_id=?");
    $stmt2->bind_param('ii', $client_id, $attorney_id);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    while ($row = $result2->fetch_assoc()) $msgs[] = $row;
    // Sort by sent_at
    usort($msgs, function($a, $b) { return strtotime($a['sent_at']) - strtotime($b['sent_at']); });
    header('Content-Type: application/json');
    echo json_encode($msgs);
    exit();
}
// Handle AJAX update case (edit)
if (isset($_POST['action']) && $_POST['action'] === 'edit_case') {
    $case_id = intval($_POST['case_id']);
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE attorney_cases SET status=? WHERE id=? AND attorney_id=?");
    $stmt->bind_param('sii', $status, $case_id, $attorney_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Handle AJAX delete case
if (isset($_POST['action']) && $_POST['action'] === 'delete_case') {
    $case_id = intval($_POST['case_id']);
    $stmt = $conn->prepare("DELETE FROM attorney_cases WHERE id=? AND attorney_id=?");
    $stmt->bind_param('ii', $case_id, $attorney_id);
    $stmt->execute();
    echo $stmt->affected_rows > 0 ? 'success' : 'error';
    exit();
}
// Fetch cases for this attorney (with client name)
$cases = [];
$sql = "SELECT ac.*, uf.name as client_name FROM attorney_cases ac LEFT JOIN user_form uf ON ac.client_id = uf.id WHERE ac.attorney_id=? ORDER BY ac.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $attorney_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
            <li><a href="attorney_dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
            <li><a href="attorney_cases.php" class="active"><i class="fas fa-gavel"></i><span>Manage Cases</span></a></li>
            <li><a href="attorney_documents.php"><i class="fas fa-file-alt"></i><span>Document Storage</span></a></li>
            <li><a href="attorney_document_generation.php"><i class="fas fa-file-alt"></i><span>Document Generation</span></a></li>
            <li><a href="attorney_schedule.php"><i class="fas fa-calendar-alt"></i><span>My Schedule</span></a></li>
            <li><a href="attorney_clients.php"><i class="fas fa-users"></i><span>My Clients</span></a></li>
            <li><a href="attorney_messages.php"><i class="fas fa-envelope"></i><span>Messages</span></a></li>
            <li><a href="attorney_efiling.php"><i class="fas fa-paper-plane"></i><span>E-Filing</span></a></li>
            <li><a href="attorney_audit.php"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-title">
                <h1>My Cases</h1>
                <p>All cases you are handling as an attorney</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Attorney" style="object-fit:cover;width:60px;height:60px;border-radius:50%;border:2px solid #1976d2;">
                <div class="user-details">
                    <h3><?php echo $_SESSION['attorney_name']; ?></h3>
                    <p>Attorney at Law</p>
                </div>
            </div>
        </div>
			<div class="cases-container">
				<div class="cases-header" style="justify-content: space-between;">
					<h2 style="margin:0;font-weight:600;color:#1976d2;">Cases</h2>
					<button class="btn btn-primary" onclick="openAddCaseModal()"><i class="fas fa-plus"></i> Add New Case</button>
				</div>
				<div class="cases-grid" id="casesGrid">
					<?php foreach ($cases as $case): ?>
						<div class="case-card">
							<div class="case-card-header">
								<span class="status-badge status-<?= strtolower($case['status'] ?? 'active') ?>"><?= htmlspecialchars($case['status'] ?? '-') ?></span>
							</div>
							<div class="case-card-body">
								<h3 class="case-client"><?= htmlspecialchars($case['client_name'] ?? '-') ?></h3>
								<p class="case-title"><?= htmlspecialchars($case['title']) ?></p>
							</div>
							<div class="case-card-footer">
								<button class="btn btn-primary btn-sm" onclick="openCaseView(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title'] ?? '') ?>', '<?= htmlspecialchars($case['client_name'] ?? '') ?>', '<?= htmlspecialchars($case['description'] ?? '') ?>', '<?= htmlspecialchars($case['status'] ?? '') ?>', <?= $case['client_id'] ?>)"><i class="fas fa-eye"></i> View Case</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
        <!-- Document Requests Modal -->
        <div class="modal" id="requestModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="max-height: 90vh; overflow-y: auto;" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Document Requests</h2>
                    <button class="close-modal" onclick="closeRequestModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <form id="requestForm" style="margin-bottom:14px;">
                        <input type="hidden" name="case_id" id="reqCaseId">
                        <input type="hidden" name="client_id" id="reqClientId">
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" name="title" required>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" rows="3" placeholder="e.g. Please upload a scanned copy of your ID, PSA, etc."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Due Date</label>
                            <input type="date" name="due_date">
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeRequestModal()">Close</button>
                            <button type="submit" class="btn btn-primary">Create Request</button>
                        </div>
                    </form>
                    <div id="requestsList"></div>
                </div>
            </div>
        </div>
        <!-- Add Case Modal -->
        <div class="modal" id="addCaseModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Add New Case</h2>
                    <button class="close-modal" onclick="closeAddCaseModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <form id="addCaseForm">
                        <div class="form-group">
                            <label>Client</label>
                            <select name="client_id" id="clientSelect" required>
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Case Title</label>
                            <input type="text" name="title" id="caseTitle" required>
                        </div>
                        <div class="form-group">
                            <label>Case Type</label>
                            <select name="case_type" required>
                                <option value="">Select Type</option>
                                <option value="Criminal">Criminal</option>
                                <option value="Civil">Civil</option>
                                <option value="Family">Family</option>
                                <option value="Corporate">Corporate</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Summary</label>
                            <textarea name="description" id="caseDescription" required></textarea>
                        </div>


                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeAddCaseModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Case</button>
                        </div>
                    </form>
                    <div id="caseSuccessMsg" style="display:none; color:green; margin-top:10px;">Case added successfully!</div>
                </div>
            </div>
        </div>
        <!-- Conversation Modal -->
        <div class="modal" id="conversationModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Conversation with Client</h2>
                    <button class="close-modal" onclick="closeConversationModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <div class="chat-messages" id="modalChatMessages" style="max-height:300px;overflow-y:auto;">
                        <!-- Dynamic chat here -->
                    </div>
                </div>
                <div class="modal-footer" style="z-index: 9999 !important;">
                    <button class="btn btn-secondary" onclick="closeConversationModal()">Close</button>
                </div>
            </div>
        </div>
        <!-- Edit Case Modal -->
        <div class="modal" id="editCaseModal" style="display:none;" style="z-index: 9999 !important;">
            <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                <div class="modal-header" style="z-index: 9999 !important;">
                    <h2>Edit Case Status</h2>
                    <button class="close-modal" onclick="closeEditCaseModal()">&times;</button>
                </div>
                <div class="modal-body" style="z-index: 9999 !important;">
                    <form id="editCaseForm">
                        <input type="hidden" name="case_id" id="editCaseId">
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" id="editCaseStatus" required>
                                <option value="Active">Active</option>
                                <option value="Pending">Pending</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeEditCaseModal()">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                    <div id="editCaseSuccessMsg" style="display:none; color:green; margin-top:10px;">Status updated successfully!</div>
                </div>
            </div>
        </div>
                 <!-- Add this modal after the Edit Case Modal -->
         <div class="modal" id="summaryModal" style="display:none;" style="z-index: 9999 !important;">
             <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                 <div class="modal-header" style="z-index: 9999 !important;">
                     <h2>Case Summary</h2>
                     <button class="close-modal" onclick="closeSummaryModal()">&times;</button>
                 </div>
                 <div class="modal-body" style="z-index: 9999 !important;">
                     <p id="summaryText" style="white-space: pre-line;"></p>
                 </div>
             </div>
         </div>
         
         <!-- Document Request Status Update Modal -->
         <div class="modal" id="statusUpdateModal" style="display:none;" style="z-index: 9999 !important;">
             <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
                 <div class="modal-header" style="z-index: 9999 !important;">
                     <h2>Update Document Request Status</h2>
                     <button class="close-modal" onclick="closeStatusUpdateModal()">&times;</button>
                 </div>
                 <div class="modal-body" style="z-index: 9999 !important;">
                     <form id="statusUpdateForm">
                         <input type="hidden" name="request_id" id="statusUpdateRequestId">
                         <div class="form-group">
                             <label>Status</label>
                             <select name="status" id="statusUpdateStatus" required>
                                 <option value="Approved">Approved</option>
                                 <option value="Rejected">Rejected</option>
                                 <option value="Called">Called for Additional Documents</option>
                             </select>
                         </div>
                         <div class="form-group">
                             <label>Comment (Optional)</label>
                             <textarea name="comment" id="statusUpdateComment" rows="3" placeholder="Add your comment or feedback..."></textarea>
                         </div>
                         <div class="form-actions">
                             <button type="button" class="btn btn-secondary" onclick="closeStatusUpdateModal()">Cancel</button>
                             <button type="submit" class="btn btn-primary">Update Status</button>
                         </div>
                     </form>
                 </div>
             </div>
         </div>
    </div>
    <style>
        .cases-container { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 24px; margin-top: 24px; }
        .cases-header { display: flex; align-items: center; margin-bottom: 18px; }
                 .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8rem; font-weight: 500; }
         .status-active { background: #28a745; color: white; }
         .status-pending { background: #ffc107; color: #333; }
         .status-closed { background: #999; color: #fff; }
         .status-requested { background: #ffc107; color: #333; }
         .status-submitted { background: #17a2b8; color: white; }
         .status-reviewed { background: #6f42c1; color: white; }
         .status-approved { background: #28a745; color: white; }
         .status-rejected { background: #dc3545; color: white; }
         .status-called { background: #fd7e14; color: white; }
        .btn-xs { font-size: 0.9em; padding: 4px 10px; margin-right: 4px; }
        .btn-sm { font-size: 0.95em; padding: 6px 12px; }
        .cases-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .case-card { border: 1px solid #eee; border-radius: 12px; overflow: hidden; background:#fff; box-shadow: 0 2px 6px rgba(0,0,0,0.05); display:flex; flex-direction:column; }
        .case-card-header { padding: 10px; border-bottom: 1px solid #f0f0f0; display:flex; justify-content:flex-end; }
        .case-card-body { padding: 14px; }
        .case-client { margin:0 0 6px 0; font-size: 1rem; color:#1976d2; }
        .case-title { margin:0; font-size: 0.95rem; color:#333; }
        .case-card-footer { padding: 12px 14px; border-top: 1px solid #f0f0f0; display:flex; justify-content:flex-end; }
        @media (max-width: 1100px) { .cases-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .cases-grid { grid-template-columns: 1fr; } }
    </style>
    <script>
        function openAddCaseModal() {
            document.getElementById('addCaseModal').style.display = 'block';
        }
        function closeAddCaseModal() {
            document.getElementById('addCaseModal').style.display = 'none';
        }
        document.getElementById('addCaseForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_case');
            fetch('attorney_cases.php', {
                method: 'POST',
                body: formData
            }).then(r => r.text()).then(res => {
                if (res === 'success') {
                    document.getElementById('caseSuccessMsg').style.display = 'block';
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    alert('Error adding case.');
                }
            });
        };
        function openConversationModal(clientId) {
            // Generic: fetch all messages between attorney and client
            const fd = new FormData();
            fd.append('action', 'fetch_conversation');
            fd.append('client_id', clientId);
            fetch('attorney_cases.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(msgs => {
                    const chat = document.getElementById('modalChatMessages');
                    chat.innerHTML = '';
                    if (msgs.length === 0) {
                        chat.innerHTML = '<p style="color:#888;text-align:center;">No conversation yet.</p>';
                    } else {
                        msgs.forEach(m => {
                            const sent = m.sender === 'attorney';
                            chat.innerHTML += `<div class='message-bubble ${sent ? 'sent' : 'received'}'><div class='message-text'><p>${m.message}</p></div><div class='message-meta'><span class='message-time'>${m.sent_at}</span></div></div>`;
                        });
                    }
                    document.getElementById('conversationModal').style.display = 'block';
                });
        }
        function closeConversationModal() {
            document.getElementById('conversationModal').style.display = 'none';
        }
        function openEditCaseModal(caseId, title, clientName, description, status, clientId) {
            document.getElementById('editCaseId').value = caseId;
            document.getElementById('editCaseStatus').value = status || 'Active';
            document.getElementById('editCaseModal').style.display = 'block';
        }
        function closeEditCaseModal() {
            document.getElementById('editCaseModal').style.display = 'none';
        }
        document.getElementById('editCaseForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit_case');
            fetch('attorney_cases.php', {
                method: 'POST',
                body: formData
            }).then(r => r.text()).then(res => {
                if (res === 'success') {
                    document.getElementById('editCaseSuccessMsg').style.display = 'block';
                    setTimeout(() => { location.reload(); }, 1000);
                } else {
                    alert('Error updating status.');
                }
            });
        };
        function deleteCase(caseId) {
            if (!confirm('Are you sure you want to delete this case?')) return;
            const fd = new FormData();
            fd.append('action', 'delete_case');
            fd.append('case_id', caseId);
            fetch('attorney_cases.php', { method: 'POST', body: fd })
                .then(r => r.text()).then(res => {
                    if (res === 'success') {
                        location.reload();
                    } else {
                        alert('Error deleting case.');
                    }
                });
        }
        function openSummaryModal(summary) {
            document.getElementById('summaryText').innerText = summary;
            document.getElementById('summaryModal').style.display = 'block';
        }
        function closeSummaryModal() {
            document.getElementById('summaryModal').style.display = 'none';
        }
        function openCaseView(caseId, title, clientName, description, status, clientId) {
            const html = `
                <div style="padding:6px 0;color:#1976d2;font-weight:600;">${title || ''}</div>
                <div style="margin-bottom:10px;color:#444;">Client: ${clientName || '-'}</div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;">
                    <button class='btn btn-primary btn-xs' onclick='openConversationModal(${clientId})'><i class="fas fa-comments"></i> Conversation</button>
                    <button class='btn btn-info btn-xs' onclick='openSummaryModal(${JSON.stringify((description||'').replace(/"/g, "\\\""))})'><i class="fas fa-info-circle"></i> Summary</button>
                    <button class='btn btn-secondary btn-xs' onclick='openEditCaseModal(${caseId}, "${title?.replace(/"/g, "\\\"") || ''}", "${clientName?.replace(/"/g, "\\\"") || ''}", "${description?.replace(/"/g, "\\\"") || ''}", "${status || ''}", ${clientId})'><i class="fas fa-edit"></i> Edit</button>
                    <button class='btn btn-warning btn-xs' onclick='openRequestModal(${caseId}, ${clientId})'><i class="fas fa-file-upload"></i> Request Docs</button>
                </div>`;
            document.getElementById('summaryText').innerHTML = html;
            document.getElementById('summaryModal').style.display = 'block';
        }
        function openRequestModal(caseId, clientId) {
            document.getElementById('reqCaseId').value = caseId;
            document.getElementById('reqClientId').value = clientId;
            document.getElementById('requestsList').innerHTML = '';
            document.getElementById('requestModal').style.display = 'block';
            fetchRequests(caseId);
        }
        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
        }
        function fetchRequests(caseId) {
            const fd = new FormData();
            fd.append('action','list_requests');
            fd.append('case_id', caseId);
            fetch('attorney_cases.php', { method: 'POST', body: fd })
                .then(r=>r.json()).then(rows=>{
                    const wrap = document.getElementById('requestsList');
                    if (!rows.length) { wrap.innerHTML = '<p style="color:#888;">No requests yet.</p>'; return; }
                                         wrap.innerHTML = rows.map(r=>`
                         <div style="border:1px solid #eee;border-radius:8px;padding:10px;margin-bottom:8px;">
                             <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                                 <div style="display:flex;align-items:center;gap:8px;">
                                     <strong>${r.title}</strong>
                                     <span class="status-badge status-${(r.status||'Requested').toLowerCase()}">${r.status}</span>
                                 </div>
                                 <div style="display:flex;gap:6px;">
                                     <button class="btn btn-info btn-xs" onclick="viewRequestFiles(${r.id})"><i class='fas fa-folder-open'></i> View Files</button>
                                     <button class="btn btn-warning btn-xs" onclick="openStatusModal(${r.id}, '${r.status||'Requested'}')"><i class='fas fa-edit'></i> Update Status</button>
                                 </div>
                             </div>
                             <div style="color:#555;margin-top:4px;">${r.description || ''}</div>
                             <div style="color:#888;margin-top:4px;">Due: ${r.due_date || '—'} • Uploads: ${r.upload_count}</div>
                             <div style="color:#aaa;margin-top:2px;">Created: ${r.created_at}</div>
                             ${r.attorney_comment ? `<div style="color:#1976d2;margin-top:4px;font-style:italic;">Attorney Comment: ${r.attorney_comment}</div>` : ''}
                             <div id="reqFiles-${r.id}" style="margin-top:8px;display:none;background:#fafafa;border:1px dashed #ddd;padding:8px;border-radius:8px;"></div>
                         </div>
                     `).join('');
                });
        }
        document.getElementById('requestForm').onsubmit = function(e){
            e.preventDefault();
            const fd = new FormData(this);
            fd.append('action','create_request');
            fetch('attorney_cases.php', { method:'POST', body: fd })
                .then(r=>r.text()).then(res=>{
                    if (res==='success') {
                        alert('Document request created and client notified.');
                        fetchRequests(document.getElementById('reqCaseId').value);
                        this.reset();
                    } else {
                        alert('Error creating request');
                    }
                });
        };
        function viewRequestFiles(requestId) {
            const box = document.getElementById('reqFiles-'+requestId);
            const fd = new FormData();
            fd.append('action','list_request_files');
            fd.append('request_id', requestId);
            fetch('attorney_cases.php', { method:'POST', body: fd })
                .then(r=>r.json()).then(files=>{
                    if (files.length===0) { box.innerHTML = '<em style="color:#888;">No files uploaded yet.</em>'; }
                    else {
                        box.innerHTML = files.map(f=>`<div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:6px;"><a href="${f.file_path}" target="_blank">${f.original_name}</a><span style="color:#888;">${f.uploaded_at}</span></div>`).join('');
                    }
                    box.style.display = 'block';
                });
        }
                 function openStatusModal(requestId, currentStatus) {
             document.getElementById('statusUpdateRequestId').value = requestId;
             document.getElementById('statusUpdateStatus').value = currentStatus;
             document.getElementById('statusUpdateComment').value = '';
             document.getElementById('statusUpdateModal').style.display = 'block';
         }
         
         function closeStatusUpdateModal() {
             document.getElementById('statusUpdateModal').style.display = 'none';
         }
         
         document.getElementById('statusUpdateForm').onsubmit = function(e) {
             e.preventDefault();
             const formData = new FormData(this);
             formData.append('action', 'update_request_status');
             
             fetch('attorney_cases.php', { method: 'POST', body: formData })
                 .then(r => r.text()).then(res => {
                     if (res === 'success') {
                         alert('Status updated successfully!');
                         closeStatusUpdateModal();
                         fetchRequests(document.getElementById('reqCaseId').value);
                     } else {
                         alert('Failed to update status');
                     }
                 });
         };
    </script>
</body>
</html> 