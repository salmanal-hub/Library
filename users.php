<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    redirect('login.php');
}

if (!isAdmin()) {
    redirect('dashboard.php');
}

// Include required classes
require_once 'classes/BaseModel.php';
require_once 'classes/User.php';

$userModel = new User();

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

        if (isset($_POST['add_user'])) {
            // Add new user
            $data = [
                'username' => sanitize($_POST['username']),
                'password' => $_POST['password'], // Store plain text as per your model
                'email' => sanitize($_POST['email']),
                'full_name' => sanitize($_POST['full_name']),
                'role' => sanitize($_POST['role'])
            ];

            // Validate password confirmation
            if ($_POST['password'] !== $_POST['confirm_password']) {
                throw new Exception("Password confirmation does not match");
            }

            $user = $userModel->createUser($data);
            $message = "User '{$data['username']}' has been created successfully!";
            $messageType = 'success';
            logActivity('CREATE_USER', "Created new user: {$data['username']}");
        } elseif (isset($_POST['edit_user'])) {
            // Edit user
            $userId = (int)$_POST['user_id'];
            $data = [
                'email' => sanitize($_POST['email']),
                'full_name' => sanitize($_POST['full_name']),
                'role' => sanitize($_POST['role'])
            ];

            $user = $userModel->updateProfile($userId, $data);
            $message = "User has been updated successfully!";
            $messageType = 'success';
            logActivity('UPDATE_USER', "Updated user ID: {$userId}");
        } elseif (isset($_POST['change_password'])) {
            // Change user password
            $userId = (int)$_POST['user_id'];
            $newPassword = $_POST['new_password'];

            // Validate password confirmation
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("Password confirmation does not match");
            }

            // For admin changing other user's password, we don't require current password
            $userModel->update($userId, ['password' => $newPassword]);

            $message = "Password has been changed successfully!";
            $messageType = 'success';
            logActivity('CHANGE_PASSWORD', "Changed password for user ID: {$userId}");
        } elseif (isset($_POST['update_profile'])) {
            // Update current user's profile
            $userId = $_SESSION['user_id'];
            $data = [
                'email' => sanitize($_POST['email']),
                'full_name' => sanitize($_POST['full_name'])
            ];

            $user = $userModel->updateProfile($userId, $data);

            // Update session data
            $_SESSION['full_name'] = $data['full_name'];
            $_SESSION['email'] = $data['email'];

            $message = "Your profile has been updated successfully!";
            $messageType = 'success';
            logActivity('UPDATE_PROFILE', "Updated own profile");
        } elseif (isset($_POST['change_own_password'])) {
            // Change current user's password
            $userId = $_SESSION['user_id'];
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];

            // Validate password confirmation
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                throw new Exception("Password confirmation does not match");
            }

            $userModel->updatePassword($userId, $currentPassword, $newPassword);

            $message = "Your password has been changed successfully!";
            $messageType = 'success';
            logActivity('CHANGE_OWN_PASSWORD', "Changed own password");
        }

        // Redirect to prevent form resubmission
        header("Location: users.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $userId = (int)$_GET['id'];

        // Prevent user from deleting themselves
        if ($userId == $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account");
        }

        $user = $userModel->find($userId);

        if ($user) {
            $userModel->delete($userId);
            $message = "User '{$user['username']}' has been deleted successfully!";
            $messageType = 'success';
            logActivity('DELETE_USER', "Deleted user: {$user['username']}");
        } else {
            throw new Exception("User not found");
        }

        header("Location: users.php?message=" . urlencode($message) . "&type=" . $messageType);
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

// Get users with pagination
try {
    $users = $userModel->getUsersWithPagination($page, $perPage, $search);
    $userStats = $userModel->getStatistics();
} catch (Exception $e) {
    $message = "Error loading users: " . $e->getMessage();
    $messageType = 'error';
    $users = ['data' => [], 'total_pages' => 0, 'current_page' => 1];
    $userStats = ['total_users' => 0, 'by_role' => ['admin' => 0, 'staff' => 0], 'recent_users' => 0];
}

// Get user for editing
$editUser = null;
if (($action === 'edit' || $action === 'password') && isset($_GET['id'])) {
    try {
        $editUser = $userModel->find((int)$_GET['id']);
        if (!$editUser) {
            throw new Exception("User not found");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
        $action = 'list';
    }
}

// Get current user for profile
$currentUser = null;
if ($action === 'profile') {
    try {
        $currentUser = $userModel->find($_SESSION['user_id']);
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
    <title>Users Management - <?php echo SITE_NAME; ?></title>

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

        .stat-item.info {
            background: linear-gradient(135deg, #2196F3 0%, #21CBF3 100%);
            color: white;
        }

        .role-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
        }

        .role-admin {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .role-staff {
            background: linear-gradient(135deg, #4834d4 0%, #686de0 100%);
        }

        .user-avatar {
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

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s ease;
        }

        .strength-weak {
            background: #ff6b6b;
        }

        .strength-medium {
            background: #ffa726;
        }

        .strength-strong {
            background: #66bb6a;
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
                            <a class="nav-link text-white" href="members.php">
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
                        <li class="nav-item">
                            <a class="nav-link text-white bg-white bg-opacity-25 rounded" href="users.php">
                                <i class="fas fa-user-cog me-2"></i>Users
                            </a>
                        </li>
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
                                    <i class="fas fa-user-cog me-2"></i>
                                    <?php
                                    switch ($action) {
                                        case 'profile':
                                            echo 'My Profile';
                                            break;
                                        case 'add':
                                            echo 'Add New User';
                                            break;
                                        case 'edit':
                                            echo 'Edit User';
                                            break;
                                        case 'password':
                                            echo 'Change Password';
                                            break;
                                        default:
                                            echo 'Users Management';
                                            break;
                                    }
                                    ?>
                                </h1>
                                <p class="mb-0">
                                    <?php
                                    switch ($action) {
                                        case 'profile':
                                            echo 'Manage your account settings';
                                            break;
                                        case 'add':
                                            echo 'Create a new system user';
                                            break;
                                        case 'edit':
                                            echo 'Update user information';
                                            break;
                                        case 'password':
                                            echo 'Change user password';
                                            break;
                                        default:
                                            echo 'Manage system users and permissions';
                                            break;
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-auto">
                                <?php if ($action === 'list'): ?>
                                    <a href="users.php?action=add" class="btn btn-light">
                                        <i class="fas fa-plus me-2"></i>Add New User
                                    </a>
                                <?php elseif ($action !== 'profile'): ?>
                                    <a href="users.php" class="btn btn-light">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Users
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

                <?php if ($action === 'add'): ?>
                    <!-- Add New User Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-plus me-2"></i>Add New User
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="addUserForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username *</label>
                                        <input type="text" class="form-control" id="username" name="username" required maxlength="50">
                                        <div class="form-text">Unique username for login</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Role *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="staff">Staff</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" required maxlength="100">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required maxlength="100">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Password *</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <div class="form-text" id="passwordMatch"></div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="add_user" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Create User
                                    </button>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'edit' && $editUser): ?>
                    <!-- Edit User Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-edit me-2"></i>Edit User: <?php echo htmlspecialchars($editUser['username']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="editUserForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($editUser['username']); ?>" readonly>
                                        <div class="form-text">Username cannot be changed</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Role *</label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="staff" <?php echo $editUser['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                            <option value="admin" <?php echo $editUser['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name"
                                            value="<?php echo htmlspecialchars($editUser['full_name']); ?>" required maxlength="100">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email *</label>
                                        <input type="email" class="form-control" id="email" name="email"
                                            value="<?php echo htmlspecialchars($editUser['email']); ?>" required maxlength="100">
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="edit_user" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update User
                                    </button>
                                    <a href="users.php?action=password&id=<?php echo $editUser['id']; ?>" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </a>
                                    <a href="users.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'password' && $editUser): ?>
                    <!-- Change Password Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-key me-2"></i>Change Password for: <?php echo htmlspecialchars($editUser['username']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="changePasswordForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="new_password" class="form-label">New Password *</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="password-strength" id="passwordStrength"></div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirm_password" class="form-label">Confirm Password *</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <div class="form-text" id="passwordMatch"></div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="change_password" class="btn btn-warning">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                    <a href="users.php?action=edit&id=<?php echo $editUser['id']; ?>" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Edit
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'profile' && $currentUser): ?>
                    <!-- User Profile -->
                    <div class="row">
                        <div class="col-md-8">
                            <!-- Update Profile Form -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user me-2"></i>Profile Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="profileForm">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUser['username']); ?>" readonly>
                                                <div class="form-text">Username cannot be changed</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="role" class="form-label">Role</label>
                                                <input type="text" class="form-control" value="<?php echo ucfirst($currentUser['role']); ?>" readonly>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="full_name" class="form-label">Full Name *</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name"
                                                    value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required maxlength="100">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email *</label>
                                                <input type="email" class="form-control" id="email" name="email"
                                                    value="<?php echo htmlspecialchars($currentUser['email']); ?>" required maxlength="100">
                                            </div>
                                        </div>

                                        <button type="submit" name="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Profile
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Change Password Form -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="changeOwnPasswordForm">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Current Password *</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="new_password" class="form-label">New Password *</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                <div class="password-strength" id="passwordStrength"></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                <div class="form-text" id="passwordMatch"></div>
                                            </div>
                                        </div>

                                        <button type="submit" name="change_own_password" class="btn btn-warning">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <!-- Profile Summary -->
                            <div class="card">
                                <div class="card-body text-center">
                                    <div class="user-avatar mb-3 mx-auto" style="width: 80px; height: 80px; font-size: 2rem;">
                                        <?php echo strtoupper(substr($currentUser['full_name'], 0, 1)); ?>
                                    </div>
                                    <h5><?php echo htmlspecialchars($currentUser['full_name']); ?></h5>
                                    <p class="text-muted"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                                    <span class="badge role-<?php echo $currentUser['role']; ?> role-badge">
                                        <?php echo ucfirst($currentUser['role']); ?>
                                    </span>
                                    <hr>
                                    <small class="text-muted">
                                        Member since: <?php echo date('M Y', strtotime($currentUser['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Users List -->

                    <!-- Quick Statistics -->
                    <div class="quick-stats">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item primary">
                                    <h3 class="mb-1"><?php echo number_format($userStats['total_users']); ?></h3>
                                    <p class="mb-0">Total Users</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item success">
                                    <h3 class="mb-1"><?php echo number_format($userStats['by_role']['admin'] ?? 0); ?></h3>
                                    <p class="mb-0">Administrators</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item warning">
                                    <h3 class="mb-1"><?php echo number_format($userStats['by_role']['staff'] ?? 0); ?></h3>
                                    <p class="mb-0">Staff Members</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item info">
                                    <h3 class="mb-1"><?php echo number_format($userStats['recent_users']); ?></h3>
                                    <p class="mb-0">Recent Users</p>
                                    <small class="opacity-75">Last 30 days</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter Form -->
                    <div class="search-form">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search Users</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Search by username, name, or email..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
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
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Users List
                                <span class="badge bg-primary"><?php echo number_format($users['total_records']); ?></span>
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
                            <?php if (!empty($users['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="usersTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>User</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users['data'] as $user): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="user-avatar me-3">
                                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                                            </div>
                                                            <div>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                                    <small class="text-muted">(You)</small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <code><?php echo htmlspecialchars($user['username']); ?></code>
                                                    </td>
                                                    <td>
                                                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-decoration-none">
                                                            <?php echo htmlspecialchars($user['email']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <span class="badge role-<?php echo $user['role']; ?> role-badge">
                                                            <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'crown' : 'user'; ?> me-1"></i>
                                                            <?php echo ucfirst($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="users.php?action=edit&id=<?php echo $user['id']; ?>"
                                                                class="btn btn-sm btn-outline-primary btn-action"
                                                                title="Edit User">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="users.php?action=password&id=<?php echo $user['id']; ?>"
                                                                class="btn btn-sm btn-outline-warning btn-action"
                                                                title="Change Password">
                                                                <i class="fas fa-key"></i>
                                                            </a>
                                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-danger btn-action"
                                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                                    title="Delete User">
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
                                <?php if ($users['total_pages'] > 1): ?>
                                    <div class="card-footer">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <small class="text-muted">
                                                    Showing <?php echo (($users['current_page'] - 1) * $users['per_page']) + 1; ?>
                                                    to <?php echo min($users['current_page'] * $users['per_page'], $users['total_records']); ?>
                                                    of <?php echo number_format($users['total_records']); ?> results
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <nav>
                                                    <ul class="pagination pagination-sm mb-0">
                                                        <?php if ($users['has_prev']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $users['prev_page']; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>">
                                                                    <i class="fas fa-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start = max(1, $users['current_page'] - 2);
                                                        $end = min($users['total_pages'], $users['current_page'] + 2);

                                                        for ($i = $start; $i <= $end; $i++): ?>
                                                            <li class="page-item <?php echo $i == $users['current_page'] ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <?php if ($users['has_next']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $users['next_page']; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>">
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
                                    <h5 class="text-muted">No users found</h5>
                                    <p class="text-muted">
                                        <?php if ($search): ?>
                                            Try adjusting your search criteria or <a href="users.php">view all users</a>.
                                        <?php else: ?>
                                            Start by <a href="users.php?action=add">creating your first user</a>.
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.0/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.0/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTables for better functionality
        $(document).ready(function() {
            $('#usersTable').DataTable({
                "pageLength": <?php echo $perPage; ?>,
                "searching": false, // We use custom search
                "paging": false, // We use custom pagination
                "info": false, // We show custom info
                "ordering": true,
                "order": [
                    [1, 'asc']
                ], // Order by username
                "columnDefs": [{
                        "orderable": false,
                        "targets": [5]
                    } // Actions column
                ]
            });
        });

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            return strength;
        }

        // Password validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordFields = document.querySelectorAll('input[name="password"], input[name="new_password"]');
            const confirmFields = document.querySelectorAll('input[name="confirm_password"]');

            passwordFields.forEach(function(field) {
                field.addEventListener('input', function() {
                    const strength = checkPasswordStrength(this.value);
                    const strengthBar = document.getElementById('passwordStrength');

                    if (strengthBar) {
                        strengthBar.style.width = (strength / 6 * 100) + '%';

                        if (strength <= 2) {
                            strengthBar.className = 'password-strength strength-weak';
                        } else if (strength <= 4) {
                            strengthBar.className = 'password-strength strength-medium';
                        } else {
                            strengthBar.className = 'password-strength strength-strong';
                        }
                    }
                });
            });

            confirmFields.forEach(function(field) {
                field.addEventListener('input', function() {
                    const passwordField = this.closest('form').querySelector('input[name="password"], input[name="new_password"]');
                    const matchDiv = document.getElementById('passwordMatch');

                    if (matchDiv && passwordField) {
                        if (this.value === passwordField.value && this.value !== '') {
                            matchDiv.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> Passwords match</span>';
                        } else if (this.value !== '') {
                            matchDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> Passwords do not match</span>';
                        } else {
                            matchDiv.innerHTML = '';
                        }
                    }
                });
            });
        });

        // Delete user function
        function deleteUser(id, username) {
            if (confirm(`Are you sure you want to delete the user "${username}"?\n\nThis action cannot be undone and will remove all user data.`)) {
                window.location.href = `users.php?action=delete&id=${id}`;
            }
        }

        // Export table functions
        function exportTable(format) {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('export', format);
            window.open(`users.php?${searchParams.toString()}`, '_blank');
        }

        // Form validation
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const passwords = this.querySelectorAll('input[type="password"]');
                let valid = true;

                // Check if passwords are filled
                passwords.forEach(function(password) {
                    if (password.required && !password.value) {
                        valid = false;
                    }
                });

                // Check password match
                const password = this.querySelector('input[name="password"], input[name="new_password"]');
                const confirmPassword = this.querySelector('input[name="confirm_password"]');

                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    alert('Password confirmation does not match.');
                    valid = false;
                }

                // Check password strength
                if (password && password.value.length < 6) {
                    alert('Password must be at least 6 characters long.');
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                    return false;
                }

                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                    submitBtn.disabled = true;

                    // Re-enable button after 5 seconds in case of error
                    setTimeout(function() {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 5000);
                }
            });
        });

        // Username validation
        document.getElementById('username')?.addEventListener('input', function() {
            const value = this.value;
            const regex = /^[a-zA-Z0-9_]+$/;

            if (value && !regex.test(value)) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
            } else {
                this.setCustomValidity('');
            }
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
            // Ctrl+N for new user (only on list page)
            if (e.ctrlKey && e.key === 'n' && window.location.search === '') {
                e.preventDefault();
                window.location.href = 'users.php?action=add';
            }

            // Escape to go back to list
            if (e.key === 'Escape' && window.location.search.includes('action=')) {
                window.location.href = 'users.php';
            }
        });

        // Auto-focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('form input:not([readonly]):not([type="hidden"])');
            if (firstInput) {
                firstInput.focus();
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
        document.querySelectorAll('input[type="email"]').forEach(function(input) {
            input.addEventListener('blur', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (this.value && !emailRegex.test(this.value)) {
                    this.classList.add('is-invalid');
                } else if (this.value) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });

        // Confirm before leaving form with unsaved changes
        <?php if (in_array($action, ['add', 'edit', 'password', 'profile'])): ?>
            let formChanged = false;
            document.querySelectorAll('form input, form select, form textarea').forEach(function(input) {
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

            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', function() {
                    formChanged = false;
                });
            });
        <?php endif; ?>

        // Role change warning
        document.getElementById('role')?.addEventListener('change', function() {
            if (this.value === 'admin') {
                if (!confirm('Are you sure you want to grant administrator privileges?\n\nAdministrators have full access to all system functions.')) {
                    this.value = 'staff';
                }
            }
        });
    </script>
</body>

</html>