<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Include all model classes
require_once 'classes/BaseModel.php';
require_once 'classes/User.php';
require_once 'classes/Book.php';
require_once 'classes/Member.php';
require_once 'classes/Category.php';
require_once 'classes/Loan.php';

try {
    // Initialize models
    $bookModel = new Book();
    $memberModel = new Member();
    $loanModel = new Loan();
    $categoryModel = new Category();
    $userModel = new User();

    // Get dashboard statistics
    $stats = [
        'total_books' => $bookModel->count(),
        'total_members' => $memberModel->count(),
        'active_loans' => $loanModel->count(['status' => 'borrowed']),
        'overdue_loans' => $loanModel->getOverdueLoans(),
        'total_categories' => $categoryModel->count(),
        'returned_today' => $loanModel->getReturnedToday(),
        'new_members_month' => $memberModel->getNewMembersThisMonth()
    ];

    // Get recent activities
    $recentLoans = $loanModel->getRecentLoans(5);
    $recentMembers = $memberModel->getRecentMembers(5);

    // Get chart data for monthly loans
    $monthlyLoans = $loanModel->getMonthlyLoanStatistics();

    // Get category statistics for pie chart
    $categoryStats = [];
    try {
        $categories = $categoryModel->getCategoriesWithBookCount();
        foreach ($categories as $category) {
            if ($category['book_count'] > 0) {
                $categoryStats[] = [
                    'name' => $category['name'],
                    'count' => $category['book_count']
                ];
            }
        }
    } catch (Exception $e) {
        // Fallback data if method doesn't exist
        $categoryStats = [
            ['name' => 'Fiction', 'count' => 50],
            ['name' => 'Non-Fiction', 'count' => 30],
            ['name' => 'Science', 'count' => 25],
            ['name' => 'Technology', 'count' => 20]
        ];
    }
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
    // Set default values to prevent errors
    $stats = [
        'total_books' => 0,
        'total_members' => 0,
        'active_loans' => 0,
        'overdue_loans' => [],
        'total_categories' => 0,
        'returned_today' => 0,
        'new_members_month' => 0
    ];
    $recentLoans = [];
    $recentMembers = [];
    $monthlyLoans = [];
    $categoryStats = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0, 0, 0, .1);
        }

        .sidebar {
            min-height: calc(100vh - 56px);
            max-height: calc(100vh - 56px);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 2px 0 5px rgba(0, 0, 0, .1);
            position: sticky;
            top: 0;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            margin: 2px 0;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .main-content {
            padding: 20px;
            max-width: 100%;
            overflow-x: hidden;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
            transition: transform 0.3s ease;
            border: none;
            height: 120px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .chart-wrapper-small {
            position: relative;
            height: 250px;
            width: 100%;
        }

        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            border: none;
            margin-bottom: 2rem;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-container .card-header {
            border-radius: 15px 15px 0 0;
            border: none;
            padding: 1rem 1.5rem;
        }

        .badge-status {
            font-size: 0.8rem;
        }

        .quick-actions {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
        }

        .quick-actions .card-header {
            border-radius: 15px 15px 0 0;
            border: none;
            padding: 1rem 1.5rem;
        }

        /* Prevent infinite stretching */
        .container-fluid {
            max-width: 100%;
            padding-left: 0;
            padding-right: 0;
        }

        .row {
            margin-left: 0;
            margin-right: 0;
        }

        /* Fix for chart containers */
        canvas {
            max-width: 100% !important;
            height: auto !important;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-book-reader me-2"></i>
                <?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-2"></i>
                            <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if (isAdmin()): ?>
                                <li><a class="dropdown-item" href="users.php?action=profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="books.php">
                                <i class="fas fa-book me-2"></i>
                                Books
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="members.php">
                                <i class="fas fa-users me-2"></i>
                                Members
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="loans.php">
                                <i class="fas fa-handshake me-2"></i>
                                Loans
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fas fa-tags me-2"></i>
                                Categories
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="users.php">
                                    <i class="fas fa-user-cog me-2"></i>
                                    Users
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">
                <!-- Page Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('F j, Y'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Welcome Card -->
                <div class="welcome-card card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-sun me-2"></i>
                            Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
                        </h4>
                        <p class="card-text mb-0">
                            You're logged in as <strong><?php echo ucfirst($_SESSION['role']); ?></strong>.
                            Here's what's happening in your library today.
                        </p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Books
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_books']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Total Members
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['total_members']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #28a745, #20c997);">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Active Loans
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['active_loans']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #17a2b8, #6f42c1);">
                                            <i class="fas fa-handshake"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Overdue Loans
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format(count($stats['overdue_loans'])); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="stat-icon" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="chart-container">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-line me-2"></i>
                                Monthly Loan Statistics
                            </h5>
                            <div class="chart-wrapper">
                                <canvas id="monthlyLoansChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="chart-container">
                            <h5 class="mb-3">
                                <i class="fas fa-chart-pie me-2"></i>
                                Books by Category
                            </h5>
                            <div class="chart-wrapper-small">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities Row -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="table-container">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Recent Loans
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($recentLoans)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Member</th>
                                                    <th>Book</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentLoans as $loan): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($loan['member_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($loan['book_title']); ?></td>
                                                        <td><?php echo date('M d', strtotime($loan['loan_date'])); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = [
                                                                'borrowed' => 'bg-info',
                                                                'returned' => 'bg-success',
                                                                'overdue' => 'bg-danger'
                                                            ];
                                                            ?>
                                                            <span class="badge <?php echo $statusClass[$loan['status']] ?? 'bg-secondary'; ?> badge-status">
                                                                <?php echo ucfirst($loan['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No recent loans found
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="table-container">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-plus me-2"></i>
                                    Recent Members
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <?php if (!empty($recentMembers)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Code</th>
                                                    <th>Joined</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentMembers as $member): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($member['full_name']); ?></td>
                                                        <td><code><?php echo htmlspecialchars($member['member_code']); ?></code></td>
                                                        <td><?php echo date('M d', strtotime($member['member_since'])); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusClass = [
                                                                'active' => 'bg-success',
                                                                'inactive' => 'bg-secondary',
                                                                'suspended' => 'bg-danger'
                                                            ];
                                                            ?>
                                                            <span class="badge <?php echo $statusClass[$member['status']] ?? 'bg-secondary'; ?> badge-status">
                                                                <?php echo ucfirst($member['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 text-center text-muted">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No recent members found
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions card">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="books.php?action=add" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add New Book
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="members.php?action=add" class="btn btn-outline-success w-100">
                                    <i class="fas fa-user-plus me-2"></i>Add New Member
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="loans.php?action=add" class="btn btn-outline-info w-100">
                                    <i class="fas fa-handshake me-2"></i>New Loan
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="loans.php?status=overdue" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-exclamation-triangle me-2"></i>View Overdue
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js Configuration -->
    <script>
        // Monthly Loans Chart
        const monthlyLoansCtx = document.getElementById('monthlyLoansChart').getContext('2d');
        const monthlyLoansChart = new Chart(monthlyLoansCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyLoans, 'month')); ?>,
                datasets: [{
                    label: 'Loans',
                    data: <?php echo json_encode(array_column($monthlyLoans, 'count')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($categoryStats, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($categoryStats, 'count')); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#764ba2',
                        '#f093fb',
                        '#f5576c',
                        '#4facfe',
                        '#00f2fe',
                        '#43e97b',
                        '#38f9d7'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // Real-time clock update
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            const clockElement = document.getElementById('currentTime');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }

        setInterval(updateClock, 1000);
    </script>
</body>

</html>