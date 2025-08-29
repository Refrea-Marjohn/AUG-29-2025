<?php
session_start();
if (!isset($_SESSION['attorney_name']) || $_SESSION['user_type'] !== 'attorney') {
    header('Location: login_form.php');
    exit();
}
require_once 'config.php';
$attorney_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT profile_image FROM user_form WHERE id=?");
$stmt->bind_param("i", $attorney_id);
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
    <title>E-Filing - Opiña Law Office</title>
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
        <!-- Header -->
        <div class="header">
            <div class="header-title">
                <h1>E-Filing</h1>
                <p>Manage electronic court filings</p>
            </div>
            <div class="user-info">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Attorney" style="object-fit:cover;width:60px;height:60px;border-radius:50%;border:2px solid #1976d2;">
                <div class="user-details">
                    <h3><?php echo $_SESSION['attorney_name']; ?></h3>
                    <p>Attorney</p>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-primary" id="newFilingBtn">
                <i class="fas fa-plus"></i> New Filing
            </button>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search filings...">
            </div>
            <button class="btn btn-secondary">
                <i class="fas fa-filter"></i> Filter
            </button>
        </div>

        <!-- Filing Statistics -->
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div class="card-info">
                    <h3>Total Filings</h3>
                    <p>25</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-info">
                    <h3>Pending</h3>
                    <p>8</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-info">
                    <h3>Approved</h3>
                    <p>15</p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="card-info">
                    <h3>Rejected</h3>
                    <p>2</p>
                </div>
            </div>
        </div>

        <!-- Recent Filings -->
        <div class="table-container">
            <div class="table-header">
                <h2 class="table-title">Recent Filings</h2>
                <div class="table-actions">
                    <button class="btn btn-secondary">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Filing ID</th>
                        <th>Case</th>
                        <th>Document Type</th>
                        <th>Court</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>FIL-2024-001</td>
                        <td>#2024-001</td>
                        <td>Motion to Dismiss</td>
                        <td>Regional Trial Court</td>
                        <td>2024-03-18</td>
                        <td><span class="status-badge status-pending">Pending</span></td>
                        <td>
                            <button class="btn btn-icon"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-icon"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-icon"><i class="fas fa-download"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td>FIL-2024-002</td>
                        <td>#2024-002</td>
                        <td>Answer</td>
                        <td>Municipal Court</td>
                        <td>2024-03-17</td>
                        <td><span class="status-badge status-approved">Approved</span></td>
                        <td>
                            <button class="btn btn-icon"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-icon"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-icon"><i class="fas fa-download"></i></button>
                        </td>
                    </tr>
                    <tr>
                        <td>FIL-2024-003</td>
                        <td>#2024-003</td>
                        <td>Complaint</td>
                        <td>Regional Trial Court</td>
                        <td>2024-03-16</td>
                        <td><span class="status-badge status-rejected">Rejected</span></td>
                        <td>
                            <button class="btn btn-icon"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-icon"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-icon"><i class="fas fa-download"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- New Filing Modal -->
        <div class="modal" id="filingModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>New Court Filing</h2>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="filingForm">
                        <div class="form-group">
                            <label>Case</label>
                            <select name="case" required>
                                <option value="">Select Case</option>
                                <option value="#2024-001">#2024-001</option>
                                <option value="#2024-002">#2024-002</option>
                                <option value="#2024-003">#2024-003</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Document Type</label>
                            <select name="document_type" required>
                                <option value="">Select Type</option>
                                <option value="complaint">Complaint</option>
                                <option value="answer">Answer</option>
                                <option value="motion">Motion</option>
                                <option value="brief">Brief</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Court</label>
                            <select name="court" required>
                                <option value="">Select Court</option>
                                <option value="rct">Regional Trial Court</option>
                                <option value="mc">Municipal Court</option>
                                <option value="sc">Supreme Court</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Document</label>
                            <input type="file" name="document" required>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancelFiling">Cancel</button>
                    <button class="btn btn-primary" id="submitFiling">Submit Filing</button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #ffc107;
            color: #212529;
        }

        .status-approved {
            background: #28a745;
            color: white;
        }

        .status-rejected {
            background: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .form-group input[type="file"] {
            padding: 5px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .search-box {
                width: 100%;
            }

            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
        }
    </style>

    <script>
        // Modal functionality
        const modal = document.getElementById('filingModal');
        const newFilingBtn = document.getElementById('newFilingBtn');
        const closeModal = document.querySelector('.close-modal');
        const cancelFiling = document.getElementById('cancelFiling');

        newFilingBtn.onclick = function() {
            modal.style.display = "block";
        }

        closeModal.onclick = function() {
            modal.style.display = "none";
        }

        cancelFiling.onclick = function() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html> 