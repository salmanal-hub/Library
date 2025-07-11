<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Include required classes
require_once 'classes/BaseModel.php';
require_once 'classes/User.php';

$userModel = new User();

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception("Invalid security token");
        }

        if (isset($_POST['update_profile'])) {
            // Update profile
            $data = [
                'full_name' => sanitize($_POST['full_name']),
                'email' => sanitize($_POST['email'])
            ];

            $user = $userModel->updateProfile($_SESSION['user_id'], $data);

            // Update session data
            $_SESSION['full_name'] = $data['full_name'];
            $_SESSION['email'] = $data['email'];

            $message = "Profile updated successfully!";
            $messageType = 'success';
            logActivity('UPDATE_PROFILE', "Updated profile information");
        } elseif (isset($_POST['change_password'])) {
            // Change password
            $currentPassword = $_POST['current_password'];
            $newPassword = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            // Validate passwords
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                throw new Exception("All password fields are required");
            }

            if ($newPassword !== $confirmPassword) {
                throw new Exception("New passwords do not match");
            }

            if (strlen($newPassword) < 6) {
                throw new Exception("Password must be at least 6 characters long");
            }

            $userModel->updatePassword($_SESSION['user_id'], $currentPassword, $newPassword);

            $message = "Password changed successfully!";
            $messageType = 'success';
            logActivity('CHANGE_PASSWORD', "Changed password");
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get current user data
try {
    $currentUser = $userModel->find($_SESSION['user_id']);
    if (!$currentUser) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    $message = "Error loading user data: " . $e->getMessage();
    $messageType = 'error';
    $currentUser = [
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

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
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 1rem;
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
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                                    <i class="fas fa-user me-2"></i>My Profile
                                </h1>
                                <p class="mb-0">Manage your account information and settings</p>
                            </div>
                            <div class="col-auto">
                                <a href="dashboard.php" class="btn btn-light">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                </a>
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

                <div class="row">
                    <!-- Profile Information -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="profile-avatar">
                                    <?php echo strtoupper(substr($currentUser['full_name'], 0, 2)); ?>
                                </div>
                                <h4><?php echo htmlspecialchars($currentUser['full_name']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                                <span class="badge bg-<?php echo $currentUser['role'] === 'admin' ? 'danger' : 'primary'; ?> fs-6">
                                    <?php echo ucfirst($currentUser['role']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-sm-6"><strong>Username:</strong></div>
                                    <div class="col-sm-6"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-6"><strong>Role:</strong></div>
                                    <div class="col-sm-6"><?php echo ucfirst($currentUser['role']); ?></div>
                                </div>
                                <div class="row mb-2">
                                    <div class="col-sm-6"><strong>Member Since:</strong></div>
                                    <div class="col-sm-6"><?php echo formatDate($currentUser['created_at'] ?? date('Y-m-d')); ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-sm-6"><strong>Last Login:</strong></div>
                                    <div class="col-sm-6"><?php echo date('M j, Y g:i A', $_SESSION['login_time'] ?? time()); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Forms -->
                    <div class="col-lg-8">
                        <!-- Update Profile Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit me-2"></i>Update Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="profileForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="full_name" class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name"
                                                value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email"
                                                value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="username_display" class="form-label">Username</label>
                                            <input type="text" class="form-control" id="username_display"
                                                value="<?php echo htmlspecialchars($currentUser['username']); ?>" readonly>
                                            <div class="form-text">Username cannot be changed</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="role_display" class="form-label">Role</label>
                                            <input type="text" class="form-control" id="role_display"
                                                value="<?php echo ucfirst($currentUser['role']); ?>" readonly>
                                            <div class="form-text">Role is managed by administrators</div>
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
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="passwordForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password *</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="current_password"
                                                name="current_password" required>
                                            <button class="btn btn-outline-secondary" type="button"
                                                onclick="togglePassword('current_password')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="new_password" class="form-label">New Password *</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="new_password"
                                                    name="new_password" minlength="6" required>
                                                <button class="btn btn-outline-secondary" type="button"
                                                    onclick="togglePassword('new_password')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                            <div class="form-text">Password must be at least 6 characters long</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="confirm_password"
                                                    name="confirm_password" minlength="6" required>
                                                <button class="btn btn-outline-secondary" type="button"