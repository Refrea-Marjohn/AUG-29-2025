<?php
require_once 'session_manager.php';
validateUserAccess('admin');

require_once 'config.php';
require_once 'audit_logger.php';
require_once 'action_logger_helper.php';

$admin_id = $_SESSION['user_id'];

// Fetch all cases with client and attorney information
$cases = [];
$sql = "SELECT ac.*, 
        c.name as client_name, 
        a.name as attorney_name,
        ac.created_at as date_filed
        FROM attorney_cases ac 
        LEFT JOIN user_form c ON ac.client_id = c.id 
        LEFT JOIN user_form a ON ac.attorney_id = a.id 
        ORDER BY ac.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $cases[] = $row;
    }
}

// Fetch all clients for dropdown
$clients = [];
$stmt = $conn->prepare("SELECT id, name FROM user_form WHERE user_type='client'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $clients[] = $row;

// Fetch all attorneys for dropdown
$attorneys = [];
$stmt = $conn->prepare("SELECT id, name FROM user_form WHERE user_type='attorney'");
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $attorneys[] = $row;

// Handle AJAX add case
if (isset($_POST['action']) && $_POST['action'] === 'add_case') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $client_id = intval($_POST['client_id']);
    $attorney_id = intval($_POST['attorney_id']);
    $case_type = $_POST['case_type'];
    $status = 'Active'; // Automatically set to Active
    $next_hearing = null; // No next hearing field anymore
    
    $stmt = $conn->prepare("INSERT INTO attorney_cases (title, description, attorney_id, client_id, case_type, status, next_hearing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ssiisss', $title, $description, $attorney_id, $client_id, $case_type, $status, $next_hearing);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // Log case creation to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Create',
            'Case Management',
            "Created new case: $title (Type: $case_type, Status: Active)",
            'success',
            'medium'
        );
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

// Handle AJAX update case
if (isset($_POST['action']) && $_POST['action'] === 'edit_case') {
    $case_id = intval($_POST['case_id']);
    $status = $_POST['status'];
    $attorney_id = intval($_POST['attorney_id']);
    
    $stmt = $conn->prepare("UPDATE attorney_cases SET status=?, attorney_id=? WHERE id=?");
    $stmt->bind_param('sii', $status, $attorney_id, $case_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log case update to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Update',
            'Case Management',
            "Updated case ID: $case_id (Status: $status)",
            'success',
            'medium'
        );
        echo 'success';
    } else {
        echo 'error';
    }
    exit();
}

// Handle AJAX delete case
if (isset($_POST['action']) && $_POST['action'] === 'delete_case') {
    $case_id = intval($_POST['case_id']);
    
    // Get case details before deletion for audit logging
    $caseStmt = $conn->prepare("SELECT title, case_type FROM attorney_cases WHERE id = ?");
    $caseStmt->bind_param('i', $case_id);
    $caseStmt->execute();
    $caseResult = $caseStmt->get_result();
    $caseData = $caseResult->fetch_assoc();
    
    $stmt = $conn->prepare("DELETE FROM attorney_cases WHERE id=?");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        // Log case deletion to audit trail
        global $auditLogger;
        $auditLogger->logAction(
            $admin_id,
            $_SESSION['admin_name'],
            'admin',
            'Case Delete',
            'Case Management',
            "Deleted case: " . ($caseData['title'] ?? "ID: $case_id") . " (Type: " . ($caseData['case_type'] ?? 'Unknown') . ")",
            'success',
            'high' // HIGH priority for deletions
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
$profile_image = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
}
if (!$profile_image || !file_exists($profile_image)) {
        $profile_image = 'images/default-avatar.jpg';
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Management - Admin Dashboard</title>
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
            <li><a href="admin_messages.php"><i class="fas fa-comments"></i><span>Messages</span></a></li>
            <li><a href="admin_audit.php" class="active"><i class="fas fa-history"></i><span>Audit Trail</span></a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="header">
            <div class="header-title">
                <h1>Case Management</h1>
                <p>Manage all cases in the system</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Admin" style="object-fit:cover;width:60px;height:60px;border-radius:50%;border:2px solid #1976d2;">
                <div class="user-details">
                    <h3><?php echo $_SESSION['user_name']; ?></h3>
                    <p>Administrator</p>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="action-bar">
                <button class="btn-primary" onclick="showAddCaseModal()">
                    <i class="fas fa-plus"></i> Add New Case
                </button>
                <div class="filters">
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="Active">Active</option>
                        <option value="Pending">Pending</option>
                        <option value="Closed">Closed</option>
                    </select>
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <option value="Criminal">Criminal</option>
                        <option value="Civil">Civil</option>
                        <option value="Family">Family</option>
                        <option value="Corporate">Corporate</option>
                    </select>
                </div>
                <div class="search-bar">
                    <input type="text" id="searchInput" placeholder="Search cases...">
                    <button><i class="fas fa-search"></i></button>
                </div>
            </div>

            <div class="cases-grid" id="casesGrid">
                <?php if (empty($cases)): ?>
                <div class="no-cases">
                    <i class="fas fa-folder-open"></i>
                    <h3>No cases found</h3>
                    <p>Add your first case using the button above</p>
                </div>
                <?php else: ?>
                <?php foreach ($cases as $case): ?>
                <div class="case-card" data-status="<?= htmlspecialchars($case['status']) ?>" data-type="<?= htmlspecialchars($case['case_type']) ?>">
                    <div class="case-header">
                        <div class="case-id">#<?= $case['id'] ?></div>
                        <div class="case-status status-<?= strtolower($case['status']) ?>"><?= htmlspecialchars($case['status']) ?></div>
                    </div>
                    
                    <div class="client-name">
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($case['client_name'] ?? 'N/A') ?>
                    </div>
                    
                    <div class="case-actions">
                        <button class="btn-view" onclick="viewCase(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title']) ?>', '<?= htmlspecialchars($case['client_name'] ?? 'N/A') ?>', '<?= htmlspecialchars($case['attorney_name'] ?? 'N/A') ?>', '<?= htmlspecialchars($case['case_type']) ?>', '<?= htmlspecialchars($case['status']) ?>', '<?= htmlspecialchars($case['description'] ?? '') ?>', '<?= date('M d, Y', strtotime($case['date_filed'])) ?>', '<?= htmlspecialchars($case['next_hearing'] ?? '') ?>')">
                            <i class="fas fa-eye"></i> View Case
                        </button>
                        <button class="btn-edit" onclick="editCase(<?= $case['id'] ?>, '<?= htmlspecialchars($case['title']) ?>', '<?= htmlspecialchars($case['status']) ?>', <?= $case['attorney_id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-delete" onclick="deleteCase(<?= $case['id'] ?>)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Case Modal -->
    <div id="addCaseModal" class="modal" style="z-index: 9999 !important;">
        <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <div class="modal-header">
                <div class="header-content">
                    <div class="case-icon">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="header-text">
                        <h2>Add New Case</h2>
                        <p>Create a new case for client management</p>
                    </div>
                </div>
                <span class="close">&times;</span>
            </div>
            <form id="addCaseForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>Case Title</label>
                        <input type="text" name="title" required placeholder="Enter case title">
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
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Client</label>
                        <select name="client_id" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Attorney</label>
                        <select name="attorney_id" required>
                            <option value="">Select Attorney</option>
                            <?php foreach ($attorneys as $attorney): ?>
                            <option value="<?= $attorney['id'] ?>"><?= htmlspecialchars($attorney['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group description-group">
                    <label>Description</label>
                    <textarea name="description" rows="4" required placeholder="Enter detailed case description"></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Save Case</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Case Modal -->
    <div id="editCaseModal" class="modal" style="z-index: 9999 !important;">
        <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <div class="modal-header">
                <div class="header-content">
                    <div class="case-icon">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="header-text">
                        <h2>Edit Case</h2>
                        <p>Update case information and status</p>
                    </div>
                </div>
                <span class="close">&times;</span>
            </div>
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
                <div class="form-group">
                    <label>Attorney</label>
                    <select name="attorney_id" id="editCaseAttorney" required>
                        <option value="">Select Attorney</option>
                        <?php foreach ($attorneys as $attorney): ?>
                        <option value="<?= $attorney['id'] ?>"><?= htmlspecialchars($attorney['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-save">Update Case</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Case Modal -->
    <div id="viewCaseModal" class="modal" style="z-index: 9999 !important;">
        <div class="modal-content" style="z-index: 9999 !important;" style="z-index: 10000 !important;">
            <!-- Content will be dynamically populated -->
        </div>
    </div>

    <style>
        .dashboard-container { display: flex; min-height: 100vh; }
        .content { padding: 20px; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 20px; }
        .btn-primary { background: #1976d2; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .filters { display: flex; gap: 10px; }
        .filters select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        .search-bar { display: flex; gap: 5px; }
        .search-bar input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 200px; }
        .search-bar button { padding: 8px 12px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer; }
        
        /* New Card Layout Styles */
        .cases-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, 350px); 
            gap: 20px; 
            margin-top: 20px;
            justify-content: center;
        }
        
        .case-card { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            padding: 20px; 
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            width: 100%;
            max-width: 350px;
            box-sizing: border-box;
        }
        
        .case-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(0,0,0,0.15); 
        }
        
        .case-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
        }
        
        .case-id { 
            font-size: 1.2em; 
            font-weight: 700; 
            color: #1976d2; 
        }
        
        .case-status { 
            padding: 6px 12px; 
            border-radius: 20px; 
            font-size: 0.8em; 
            font-weight: 600; 
            text-transform: uppercase; 
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-closed { background: #f8d7da; color: #721c24; }
        
        .client-name { 
            font-size: 1.4em; 
            font-weight: 600; 
            color: #1f2937; 
            margin-bottom: 20px; 
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .client-name i { 
            color: #1976d2; 
            font-size: 1.2em; 
        }
        
        .case-actions { 
            display: flex; 
            gap: 8px; 
            align-items: center; 
        }
        
        .btn-view { 
            background: #1976d2; 
            color: white; 
            border: none; 
            padding: 10px 16px; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 500; 
            flex: 1; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 6px; 
        }
        
        .btn-view:hover { 
            background: #1565c0; 
        }
        
        .btn-edit, .btn-delete { 
            padding: 10px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            width: 40px; 
            height: 40px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
        }
        
        .btn-edit { 
            background: #ffc107; 
            color: #212529; 
        }
        
        .btn-delete { 
            background: #dc3545; 
            color: white; 
        }
        
        .btn-edit:hover { background: #e0a800; }
        .btn-delete:hover { background: #c82333; }
        
        .no-cases { 
            grid-column: 1 / -1; 
            text-align: center; 
            padding: 60px 20px; 
            color: #6b7280; 
        }
        
        .no-cases i { 
            font-size: 4em; 
            margin-bottom: 20px; 
            color: #d1d5db; 
        }
        
        .no-cases h3 { 
            margin-bottom: 10px; 
            color: #374151; 
        }
        
        /* Modal Styles */
        .modal { 
            display: none; 
            position: fixed; 
            z-index: 9999; 
            left: 0; 
            top: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.6); 
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        .modal-content { 
            background-color: white; 
            margin: 3% auto; 
            padding: 0; 
            border-radius: 16px; 
            width: 90%; 
            max-width: 700px; 
            min-height: 600px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: 2px solid #5D0E26;
            animation: slideIn 0.4s ease-out;
            transform-origin: center;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-30px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        
        /* Professional Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 14px 14px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #5D0E26;
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .case-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .header-text h2 {
            margin: 0 0 0.25rem 0;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .header-text p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.8rem;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .close {
            color: white;
            float: right;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.3);
            position: absolute;
            top: 15px;
            right: 20px;
        }
        
        .close:hover,
        .close:focus {
            color: #5D0E26;
            background: white;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }



        .modal-body {
            padding: 2rem;
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
        }

        .case-overview {
            text-align: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .status-banner {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .status-banner.status-active {
            background: rgba(76, 175, 80, 0.1);
            color: #2e7d32;
            border: 1px solid rgba(76, 175, 80, 0.3);
        }

        .status-banner.status-pending {
            background: rgba(255, 152, 0, 0.1);
            color: #f57c00;
            border: 1px solid rgba(255, 152, 0, 0.3);
        }

        .status-banner.status-closed {
            background: rgba(158, 158, 158, 0.1);
            color: #616161;
            border: 1px solid rgba(158, 158, 158, 0.3);
        }

        .status-banner i {
            font-size: 0.6rem;
        }

        .case-title {
            margin: 0 0 0.25rem 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f2937;
        }

        .case-description {
            margin: 0;
            color: #6b7280;
            font-size: 0.85rem;
            line-height: 1.3;
        }

        /* Case Details Grid */
        .case-details-grid { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 0.75rem; 
            margin: 0.75rem 0; 
        }
        
        .detail-section {
            background: white;
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
        }

        .detail-section h4 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1976d2;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.25rem;
            border-bottom: 1px solid #e3f2fd;
        }

        .detail-section h4 i {
            color: #1976d2;
        }
        
        .detail-item { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label { 
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600; 
            color: #374151; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .detail-label i {
            color: #1976d2;
            font-size: 0.75rem;
        }
        
        .detail-value { 
            color: #1f2937; 
            font-weight: 600; 
            font-size: 0.95rem;
            text-align: right;
        }
        
        .modal-footer {
            background: #f8fafc;
            padding: 0.5rem 1.5rem;
            border-radius: 0 0 12px 12px;
            border-top: 1px solid #e5e7eb;
        }

        .footer-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }

        .btn-primary {
            background: #1976d2;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-1px);
        }
        
        .form-group { 
            margin-bottom: 25px; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600; 
            color: #5D0E26;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid #e5e7eb; 
            border-radius: 8px; 
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            box-sizing: border-box;
        }
        
        .form-group select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
            appearance: none;
        }
        .form-group input:hover, .form-group select:hover, .form-group textarea:hover {
            border-color: #8B1538;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { 
            outline: none; 
            border-color: #5D0E26; 
            box-shadow: 0 0 0 3px rgba(93, 14, 38, 0.1);
            transform: translateY(-1px);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Form layout improvements */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row .form-group {
            margin-bottom: 20px;
        }
        

        
        /* Description takes full width */
        .form-group.description-group {
            grid-column: 1 / -1;
        }
        .form-actions { 
            display: flex; 
            gap: 15px; 
            justify-content: flex-end; 
            margin-top: 30px; 
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        .btn-cancel, .btn-save { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            min-width: 120px;
        }
        .btn-cancel { 
            background: #6c757d; 
            color: white; 
        }
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .btn-save { 
            background: #5D0E26; 
            color: white; 
        }
        .btn-save:hover {
            background: #8B1538;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .cases-grid { 
                grid-template-columns: repeat(auto-fill, 320px); 
            }
            .case-details-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .modal-content {
                width: 95%;
                max-width: 650px;
                margin: 2% auto;
            }
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .cases-grid { 
                grid-template-columns: repeat(auto-fill, 300px); 
            }
            .action-bar { 
                flex-direction: column; 
                align-items: stretch; 
            }
            .case-details-grid {
                grid-template-columns: 1fr;
            }
            .modal-content {
                width: 98%;
                margin: 2% auto;
            }
        }
        
        @media (max-width: 400px) {
            .cases-grid { 
                grid-template-columns: 1fr; 
            }
        }
    </style>

    <script>
        // Modal functionality
        function showAddCaseModal() {
            document.getElementById('addCaseModal').style.display = 'block';
        }

        function editCase(caseId, title, status, attorneyId) {
            document.getElementById('editCaseId').value = caseId;
            document.getElementById('editCaseStatus').value = status;
            document.getElementById('editCaseAttorney').value = attorneyId;
            document.getElementById('editCaseModal').style.display = 'block';
        }

        function deleteCase(caseId) {
            if (confirm('Are you sure you want to delete this case?')) {
                const formData = new FormData();
                formData.append('action', 'delete_case');
                formData.append('case_id', caseId);
                
                fetch('manage_cases.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.text()).then(result => {
                    if (result === 'success') {
                        location.reload();
                    } else {
                        alert('Error deleting case');
                    }
                });
            }
        }

        function viewCase(caseId, title, clientName, attorneyName, caseType, status, description, dateFiled, nextHearing) {
            const modalContent = document.getElementById('viewCaseModal').querySelector('.modal-content');
            modalContent.innerHTML = `
                <div class="modal-header" style="z-index: 9999 !important;">
                    <div class="header-content">
                        <div class="case-icon">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="header-text">
                            <h2>Case Details</h2>
                            <p>Comprehensive information about case #${caseId}</p>
                        </div>
                    </div>
                    <div class="header-actions">
                    </div>
                </div>
                
                <div class="modal-body" style="z-index: 9999 !important;">
                    <div class="case-overview">
                        <div class="status-banner status-${status.toLowerCase()}">
                            <i class="fas fa-circle"></i>
                            <span>${status}</span>
                        </div>
                        <h3 class="case-title">${title}</h3>
                        <p class="case-description">${description}</p>
                    </div>
                    
                    <div class="case-details-grid">
                        <div class="detail-section">
                            <h4><i class="fas fa-info-circle"></i> Case Information</h4>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-hashtag"></i>
                                    <span>Case ID</span>
                                </div>
                                <div class="detail-value">#${caseId}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-tag"></i>
                                    <span>Type</span>
                                </div>
                                <div class="detail-value">${caseType}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Date Filed</span>
                                </div>
                                <div class="detail-value">${dateFiled}</div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-users"></i> People Involved</h4>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-user"></i>
                                    <span>Client</span>
                                </div>
                                <div class="detail-value">${clientName}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-user-tie"></i>
                                    <span>Attorney</span>
                                </div>
                                <div class="detail-value">${attorneyName}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-clock"></i>
                                    <span>Next Hearing</span>
                                </div>
                                <div class="detail-value">${nextHearing || 'Not Scheduled'}</div>
                            </div>
                        </div>
                        
                        <div class="detail-section">
                            <h4><i class="fas fa-file-alt"></i> Case Details</h4>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-align-left"></i>
                                    <span>Title</span>
                                </div>
                                <div class="detail-value">${title}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-info"></i>
                                    <span>Status</span>
                                </div>
                                <div class="detail-value">${status}</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">
                                    <i class="fas fa-comment"></i>
                                    <span>Description</span>
                                </div>
                                <div class="detail-value">${description}</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer" style="z-index: 9999 !important;">
                    <div class="footer-actions">
                        <button type="button" class="btn-secondary" onclick="editCase(${caseId}, '${title}', '${status}', ${attorneyName ? 'getAttorneyIdByName(\'' + attorneyName + '\')' : 'null'})">
                            <i class="fas fa-edit"></i> Edit Case
                        </button>
                        <button type="button" class="btn-primary" onclick="closeViewModal()">
                            <i class="fas fa-check"></i> Close
                        </button>
                    </div>
                </div>
            `;
            document.getElementById('viewCaseModal').style.display = 'block';
        }
        
        function closeViewModal() {
            document.getElementById('viewCaseModal').style.display = 'none';
        }

        // Close modals
        document.querySelectorAll('.close').forEach(closeBtn => {
            closeBtn.onclick = function() {
                this.closest('.modal').style.display = 'none';
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
                // Close button functionality for view case modal
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-cancel')) {
                e.target.closest('.modal').style.display = 'none';
            }
        });

        // Add case form submission
        document.getElementById('addCaseForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_case');
            
            fetch('manage_cases.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(result => {
                if (result === 'success') {
                    alert('Case added successfully!');
                    location.reload();
                } else {
                    alert('Error adding case');
                }
            });
        };

        // Edit case form submission
        document.getElementById('editCaseForm').onsubmit = function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'edit_case');
            
            fetch('manage_cases.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(result => {
                if (result === 'success') {
                    alert('Case updated successfully!');
                    location.reload();
                } else {
                    alert('Error updating case');
                }
            });
        };

        // Filter functionality
        document.getElementById('statusFilter').addEventListener('change', filterCases);
        document.getElementById('typeFilter').addEventListener('change', filterCases);
        document.getElementById('searchInput').addEventListener('input', filterCases);

        function filterCases() {
            const statusFilter = document.getElementById('statusFilter').value;
            const typeFilter = document.getElementById('typeFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#casesGrid .case-card'); // Changed selector

            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const type = row.getAttribute('data-type');
                const text = row.textContent.toLowerCase();
                
                const statusMatch = !statusFilter || status === statusFilter;
                const typeMatch = !typeFilter || type === typeFilter;
                const searchMatch = !searchTerm || text.includes(searchTerm);
                
                row.style.display = statusMatch && typeMatch && searchMatch ? '' : 'none';
            });
        }
    </script>
</body>
</html> </html> 
