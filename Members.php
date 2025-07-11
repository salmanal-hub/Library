<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Include required classes
require_once 'classes/BaseModel.php';
require_once 'classes/Member.php';

$memberModel = new Member();

$message = '';
$messageType = '';
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid security token");
        }

        if (isset($_POST['add_member'])) {
            // Add new member
            $data = [
                'full_name' => sanitize($_POST['full_name']),
                'email' => sanitize($_POST['email']),
                'phone' => sanitize($_POST['phone']),
                'address' => sanitize($_POST['address']),
                'date_of_birth' => $_POST['date_of_birth'] ?: null,
                'gender' => sanitize($_POST['gender']),
                'member_since' => $_POST['member_since'] ?: null
            ];

            $member = $memberModel->createMember($data);
            $message = "Member '{$data['full_name']}' has been added successfully with code: {$member['member_code']}";
            $messageType = 'success';
            logActivity('CREATE_MEMBER', "Added new member: {$data['full_name']}");
        } elseif (isset($_POST['edit_member'])) {
            // Edit member
            $memberId = (int)$_POST['member_id'];
            $data = [
                'full_name' => sanitize($_POST['full_name']),
                'email' => sanitize($_POST['email']),
                'phone' => sanitize($_POST['phone']),
                'address' => sanitize($_POST['address']),
                'date_of_birth' => $_POST['date_of_birth'] ?: null,
                'gender' => sanitize($_POST['gender']),
                'status' => sanitize($_POST['status'])
            ];

            $member = $memberModel->updateMember($memberId, $data);
            $message = "Member '{$data['full_name']}' has been updated successfully!";
            $messageType = 'success';
            logActivity('UPDATE_MEMBER', "Updated member ID: {$memberId}");
        }

        // Redirect to prevent form resubmission
        header("Location: members.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Handle actions
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $memberId = (int)$_GET['id'];
        $member = $memberModel->find($memberId);

        if ($member) {
            $memberModel->deleteMember($memberId);
            $message = "Member '{$member['full_name']}' has been deleted successfully!";
            $messageType = 'success';
            logActivity('DELETE_MEMBER', "Deleted member: {$member['full_name']}");
        } else {
            throw new Exception("Member not found");
        }

        header("Location: members.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
} elseif ($action === 'suspend' && isset($_GET['id'])) {
    try {
        $memberId = (int)$_GET['id'];
        $member = $memberModel->find($memberId);

        if ($member) {
            $memberModel->suspendMember($memberId);
            $message = "Member '{$member['full_name']}' has been suspended successfully!";
            $messageType = 'success';
            logActivity('SUSPEND_MEMBER', "Suspended member: {$member['full_name']}");
        } else {
            throw new Exception("Member not found");
        }

        header("Location: members.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
} elseif ($action === 'activate' && isset($_GET['id'])) {
    try {
        $memberId = (int)$_GET['id'];
        $member = $memberModel->find($memberId);

        if ($member) {
            $memberModel->activateMember($memberId);
            $message = "Member '{$member['full_name']}' has been activated successfully!";
            $messageType = 'success';
            logActivity('ACTIVATE_MEMBER', "Activated member: {$member['full_name']}");
        } else {
            throw new Exception("Member not found");
        }

        header("Location: members.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get message from redirect
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'] ?? 'info';
}

// Get pagination and search parameters
$page = (int)($_GET['page'] ?? 1);
$perPage = (int)($_GET['per_page'] ?? RECORDS_PER_PAGE);
$search = sanitize($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

// Get members with pagination
try {
    $members = $memberModel->getMembersWithPagination($page, $perPage, $search, $statusFilter ?: null);

    // Get member statistics
    $totalMembers = $memberModel->count();
    $activeMembers = $memberModel->count(['status' => 'active']);
    $suspendedMembers = $memberModel->count(['status' => 'suspended']);
    $newMembersThisMonth = $memberModel->getNewMembersThisMonth();

    // Get members with overdue loans
    $membersWithOverdue = $memberModel->getMembersWithOverdueLoans();
    $overdueCount = count($membersWithOverdue);
} catch (Exception $e) {
    $message = "Error loading members: " . $e->getMessage();
    $messageType = 'error';
    $members = ['data' => [], 'total_pages' => 0, 'current_page' => 1];
    $totalMembers = $activeMembers = $suspendedMembers = $newMembersThisMonth = $overdueCount = 0;
}

// Get member for editing
$editMember = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $editMember = $memberModel->find((int)$_GET['id']);
        if (!$editMember) {
            throw new Exception("Member not found");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        $action = 'list';
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members Management - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.0/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
        }

        .btn-action {
            margin: 0 2px;
        }

        .search-form {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .quick-stats {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .stat-item.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-item.success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }

        .stat-item.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .stat-item.danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ffa726 100%);
            color: white;
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
        }

        .overdue-warning {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .gender-icon {
            color: #6c757d;
        }

        .member-card {
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .member-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-book-reader me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="users.php?action=profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: calc(100vh - 56px);">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="books.php">
                                <i class="fas fa-book me-2"></i>Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white bg-white bg-opacity-25 rounded" href="members.php">
                                <i class="fas fa-users me-2"></i>Members
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="loans.php">
                                <i class="fas fa-handshake me-2"></i>Loans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="categories.php">
                                <i class="fas fa-tags me-2"></i>Categories
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link text-white" href="users.php">
                                    <i class="fas fa-user-cog me-2"></i>Users
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="container-fluid">
                        <div class="row align-items-center">
                            <div class="col">
                                <h1 class="h2 mb-0">
                                    <i class="fas fa-users me-2"></i>Members Management
                                </h1>
                                <p class="mb-0">Manage library members and memberships</p>
                            </div>
                            <div class="col-auto">
                                <?php if ($action !== 'add' && $action !== 'edit'): ?>
                                    <a href="members.php?action=add" class="btn btn-light">
                                        <i class="fas fa-plus me-2"></i>Add New Member
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Display Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-dismissible fade show">
                        <i class="fas fa-<?php echo $messageType === 'error' ? 'exclamation-circle' : ($messageType === 'success' ? 'check-circle' : 'info-circle'); ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Overdue Warning -->
                <?php if ($overdueCount > 0 && $action === 'list'): ?>
                    <div class="overdue-warning">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="mb-1">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Members with Overdue Loans
                                </h6>
                                <p class="mb-0">
                                    <strong><?php echo $overdueCount; ?></strong> members have overdue books that need attention.
                                </p>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-light btn-sm" onclick="showOverdueMembers()">
                                    <i class="fas fa-eye me-2"></i>View Overdue
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Member Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'add' ? 'Add New Member' : 'Edit Member'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="memberForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="member_id" value="<?php echo $editMember['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name"
                                            value="<?php echo htmlspecialchars($editMember['full_name'] ?? ''); ?>"
                                            required maxlength="100">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="<?php echo htmlspecialchars($editMember['email'] ?? ''); ?>"
                                            required maxlength="100">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone"
                                            value="<?php echo htmlspecialchars($editMember['phone'] ?? ''); ?>"
                                            maxlength="15">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="gender" class="form-label">Gender *</label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($editMember['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($editMember['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                            value="<?php echo $editMember['date_of_birth'] ?? ''; ?>"
                                            max="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <?php if ($action === 'add'): ?>
                                        <div class="col-md-6 mb-3">
                                            <label for="member_since" class="form-label">Member Since</label>
                                            <input type="date" class="form-control" id="member_since" name="member_since"
                                                value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                            <div class="form-text">Leave empty to use today's date</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="col-md-6 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select" id="status" name="status">
                                                <option value="active" <?php echo ($editMember['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($editMember['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="suspended" <?php echo ($editMember['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($editMember['address'] ?? ''); ?></textarea>
                                </div>

                                <?php if ($action === 'edit'): ?>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Member Code</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($editMember['member_code']); ?>" readonly>
                                            <div class="form-text">Member code cannot be changed</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Member Since</label>
                                            <input type="text" class="form-control" value="<?php echo date('F d, Y', strtotime($editMember['member_since'])); ?>" readonly>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'add_member' : 'edit_member'; ?>" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'add' ? 'Add Member' : 'Update Member'; ?>
                                    </button>
                                    <a href="members.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Members List -->

                    <!-- Quick Statistics -->
                    <div class="quick-stats">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item primary">
                                    <h3 class="mb-1"><?php echo number_format($totalMembers); ?></h3>
                                    <p class="mb-0">Total Members</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item success">
                                    <h3 class="mb-1"><?php echo number_format($activeMembers); ?></h3>
                                    <p class="mb-0">Active Members</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item warning">
                                    <h3 class="mb-1"><?php echo number_format($suspendedMembers); ?></h3>
                                    <p class="mb-0">Suspended</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item danger">
                                    <h3 class="mb-1"><?php echo number_format($newMembersThisMonth); ?></h3>
                                    <p class="mb-0">New This Month</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter Form -->
                    <div class="search-form">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Members</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Search by name, email, phone, member code..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="per_page" class="form-label">Per Page</label>
                                <select class="form-select" id="per_page" name="per_page">
                                    <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                    <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                                    <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                                    <option value="100" <?php echo $perPage == 100 ? 'selected' : ''; ?>>100</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-2"></i>Search
                                </button>
                                <a href="members.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Members Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Members List
                                <span class="badge bg-primary"><?php echo number_format($members['total_records']); ?></span>
                            </h5>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                    <i class="fas fa-download me-2"></i>Export
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="exportTable('csv')"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="exportTable('excel')"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                                    <li><a class="dropdown-item" href="#" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($members['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="membersTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Member</th>
                                                <th>Contact</th>
                                                <th>Gender</th>
                                                <th>Member Since</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($members['data'] as $member): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="member-avatar me-3">
                                                                <?php echo strtoupper(substr($member['full_name'], 0, 2)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($member['full_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($member['member_code']); ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($member['email']); ?>
                                                            </a>
                                                        </div>
                                                        <?php if ($member['phone']): ?>
                                                            <small class="text-muted">
                                                                <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>" class="text-decoration-none">
                                                                    <?php echo htmlspecialchars($member['phone']); ?>
                                                                </a>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <i class="fas fa-<?php echo $member['gender'] === 'male' ? 'mars' : 'venus'; ?> gender-icon me-1"></i>
                                                        <?php echo ucfirst($member['gender']); ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M d, Y', strtotime($member['member_since'])); ?>
                                                        <br><small class="text-muted">
                                                            <?php
                                                            $days = (strtotime(date('Y-m-d')) - strtotime($member['member_since'])) / (60 * 60 * 24);
                                                            echo ceil($days) . ' days ago';
                                                            ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = [
                                                            'active' => 'bg-success',
                                                            'inactive' => 'bg-secondary',
                                                            'suspended' => 'bg-danger'
                                                        ];
                                                        $statusIcon = [
                                                            'active' => 'fas fa-check-circle',
                                                            'inactive' => 'fas fa-pause-circle',
                                                            'suspended' => 'fas fa-ban'
                                                        ];
                                                        ?>
                                                        <span class="badge <?php echo $statusClass[$member['status']] ?? 'bg-secondary'; ?> status-badge">
                                                            <i class="<?php echo $statusIcon[$member['status']] ?? 'fas fa-circle'; ?> me-1"></i>
                                                            <?php echo ucfirst($member['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-info btn-action"
                                                                onclick="viewMember(<?php echo $member['id']; ?>)"
                                                                title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <a href="members.php?action=edit&id=<?php echo $member['id']; ?>"
                                                                class="btn btn-sm btn-outline-primary btn-action"
                                                                title="Edit Member">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($member['status'] === 'active'): ?>
                                                                <a href="loans.php?action=add&member_id=<?php echo $member['id']; ?>"
                                                                    class="btn btn-sm btn-outline-success btn-action"
                                                                    title="New Loan">
                                                                    <i class="fas fa-plus"></i>
                                                                </a>
                                                                <a href="members.php?action=suspend&id=<?php echo $member['id']; ?>"
                                                                    class="btn btn-sm btn-outline-warning btn-action"
                                                                    onclick="return confirm('Are you sure you want to suspend this member?')"
                                                                    title="Suspend Member">
                                                                    <i class="fas fa-ban"></i>
                                                                </a>
                                                            <?php elseif ($member['status'] === 'suspended'): ?>
                                                                <a href="members.php?action=activate&id=<?php echo $member['id']; ?>"
                                                                    class="btn btn-sm btn-outline-success btn-action"
                                                                    onclick="return confirm('Are you sure you want to activate this member?')"
                                                                    title="Activate Member">
                                                                    <i class="fas fa-check"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if (isAdmin()): ?>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-danger btn-action"
                                                                    onclick="deleteMember(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['full_name']); ?>')"
                                                                    title="Delete Member">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($members['total_pages'] > 1): ?>
                                    <div class="card-footer">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <small class="text-muted">
                                                    Showing <?php echo (($members['current_page'] - 1) * $members['per_page']) + 1; ?>
                                                    to <?php echo min($members['current_page'] * $members['per_page'], $members['total_records']); ?>
                                                    of <?php echo number_format($members['total_records']); ?> results
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <nav>
                                                    <ul class="pagination pagination-sm mb-0">
                                                        <?php if ($members['has_prev']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $members['prev_page']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <i class="fas fa-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start = max(1, $members['current_page'] - 2);
                                                        $end = min($members['total_pages'], $members['current_page'] + 2);

                                                        for ($i = $start; $i <= $end; $i++): ?>
                                                            <li class="page-item <?php echo $i == $members['current_page'] ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <?php if ($members['has_next']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $members['next_page']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <i class="fas fa-chevron-right"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No members found</h5>
                                    <p class="text-muted">
                                        <?php if ($search || $statusFilter): ?>
                                            Try adjusting your search criteria or <a href="members.php">view all members</a>.
                                        <?php else: ?>
                                            Start by <a href="members.php?action=add">adding your first member</a>.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Member Details Modal -->
    <div class="modal fade" id="memberModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Member Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="memberModalBody">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Overdue Members Modal -->
    <div class="modal fade" id="overdueMembersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Members with Overdue Loans
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        The following members have overdue books that need to be returned.
                    </p>

                    <?php if (!empty($membersWithOverdue)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Contact</th>
                                        <th>Overdue Books</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($membersWithOverdue as $overdueMember): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="member-avatar me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">
                                                        <?php echo strtoupper(substr($overdueMember['full_name'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($overdueMember['full_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($overdueMember['member_code']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small>
                                                    <a href="mailto:<?php echo htmlspecialchars($overdueMember['email']); ?>">
                                                        <?php echo htmlspecialchars($overdueMember['email']); ?>
                                                    </a>
                                                    <?php if ($overdueMember['phone']): ?>
                                                        <br><a href="tel:<?php echo htmlspecialchars($overdueMember['phone']); ?>">
                                                            <?php echo htmlspecialchars($overdueMember['phone']); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger"><?php echo $overdueMember['overdue_count']; ?> books</span>
                                            </td>
                                            <td>
                                                <a href="loans.php?search=<?php echo urlencode($overdueMember['member_code']); ?>&status=overdue"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i>View Loans
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted">No members with overdue loans!</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.0/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.0/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables for better functionality
        $(document).ready(function() {
            $('#membersTable').DataTable({
                "pageLength": <?php echo $perPage; ?>,
                "searching": false, // We use custom search
                "paging": false, // We use custom pagination
                "info": false, // We show custom info
                "ordering": true,
                "order": [
                    [0, 'asc']
                ], // Order by member name
                "columnDefs": [{
                        "orderable": false,
                        "targets": [5]
                    } // Actions column
                ]
            });
        });

        // View member details
        function viewMember(id) {
            // Show loading spinner
            document.getElementById('memberModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading member details...</p>
                </div>
            `;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('memberModal'));
            modal.show();

            // Load member details via AJAX
            fetch(`ajax/get_member_details.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('memberModalBody').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('memberModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading member details. Please try again.
                        </div>
                    `;
                });
        }

        // Show overdue members modal
        function showOverdueMembers() {
            const modal = new bootstrap.Modal(document.getElementById('overdueMembersModal'));
            modal.show();
        }

        // Delete member function
        function deleteMember(id, name) {
            if (confirm(`Are you sure you want to delete the member "${name}"?\n\nThis action cannot be undone and will remove all related data.`)) {
                window.location.href = `members.php?action=delete&id=${id}`;
            }
        }

        // Export table functions
        function exportTable(format) {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('export', format);
            window.open(`members.php?${searchParams.toString()}`, '_blank');
        }

        // Form validation
        document.getElementById('memberForm')?.addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const gender = document.getElementById('gender').value;

            if (!fullName || !email || !gender) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }

            // Age validation if date of birth is provided
            const dateOfBirth = document.getElementById('date_of_birth').value;
            if (dateOfBirth) {
                const birthDate = new Date(dateOfBirth);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();

                if (age < 5 || age > 120) {
                    e.preventDefault();
                    alert('Please enter a valid date of birth.');
                    return false;
                }
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            submitBtn.disabled = true;

            // Re-enable button after 5 seconds in case of error
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
        });

        // Phone number formatting
        document.getElementById('phone')?.addEventListener('input', function() {
            // Remove non-digit characters
            let value = this.value.replace(/\D/g, '');

            // Limit to reasonable phone number length
            if (value.length > 15) {
                value = value.substring(0, 15);
            }

            this.value = value;
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N for new member
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'members.php?action=add';
            }

            // Escape to go back to list
            if (e.key === 'Escape' && window.location.search.includes('action=')) {
                window.location.href = 'members.php';
            }
        });

        // Real-time validation feedback
        document.querySelectorAll('input[required]').forEach(function(input) {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });

            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value.trim() !== '') {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });

        // Email validation
        document.getElementById('email')?.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
            } else if (this.value) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });

        // Age calculation and display
        document.getElementById('date_of_birth')?.addEventListener('change', function() {
            if (this.value) {
                const birthDate = new Date(this.value);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();

                let actualAge = age;
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    actualAge--;
                }

                // Show age next to the field
                let ageDisplay = document.getElementById('ageDisplay');
                if (!ageDisplay) {
                    ageDisplay = document.createElement('small');
                    ageDisplay.id = 'ageDisplay';
                    ageDisplay.className = 'form-text';
                    this.parentNode.appendChild(ageDisplay);
                }

                if (actualAge >= 0 && actualAge <= 120) {
                    ageDisplay.textContent = `Age: ${actualAge} years old`;
                    ageDisplay.className = 'form-text text-muted';
                } else {
                    ageDisplay.textContent = 'Invalid date of birth';
                    ageDisplay.className = 'form-text text-danger';
                }
            }
        });

        // Confirm before leaving form with unsaved changes
        <?php if (in_array($action, ['add', 'edit'])): ?>
            let formChanged = false;
            document.querySelectorAll('#memberForm input, #memberForm select, #memberForm textarea').forEach(function(input) {
                input.addEventListener('change', function() {
                    formChanged = true;
                });
            });

            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });

            document.getElementById('memberForm').addEventListener('submit', function() {
                formChanged = false;
            });
        <?php endif; ?>

        // Auto-focus on first input when adding/editing
        <?php if ($action === 'add' || $action === 'edit'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('full_name').focus();
            });
        <?php endif; ?>

        // Search with enter key
        document.getElementById('search')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });

        // Status change confirmation
        function confirmStatusChange(action, memberName) {
            const messages = {
                'suspend': `Are you sure you want to suspend ${memberName}?\n\nThis will prevent them from borrowing books.`,
                'activate': `Are you sure you want to activate ${memberName}?\n\nThis will allow them to borrow books again.`
            };

            return confirm(messages[action] || 'Are you sure?');
        }

        // Quick member creation from other pages (if needed)
        function quickAddMember() {
            window.location.href = 'members.php?action=add';
        }

        // Print member details
        function printMemberDetails(memberId) {
            window.open(`print_member.php?id=${memberId}`, '_blank');
        }
    </script>
</body>

</html>