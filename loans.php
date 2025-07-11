<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Include required classes
require_once 'classes/BaseModel.php';
require_once 'classes/Loan.php';
require_once 'classes/Book.php';
require_once 'classes/Member.php';

$loanModel = new Loan();
$bookModel = new Book();
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

        if (isset($_POST['create_loan'])) {
            // Create new loan
            $data = [
                'member_id' => (int)$_POST['member_id'],
                'book_id' => (int)$_POST['book_id'],
                'notes' => sanitize($_POST['notes'] ?? '')
            ];

            // Set custom dates if provided
            if (!empty($_POST['loan_date'])) {
                $data['loan_date'] = $_POST['loan_date'];
            }
            if (!empty($_POST['due_date'])) {
                $data['due_date'] = $_POST['due_date'];
            }

            $loan = $loanModel->createLoan($data);
            $message = "Loan created successfully with code: {$loan['loan_code']}";
            $messageType = 'success';
            logActivity('CREATE_LOAN', "Created loan: {$loan['loan_code']}");
        } elseif (isset($_POST['return_book'])) {
            // Return book
            $loanId = (int)$_POST['loan_id'];
            $returnDate = $_POST['return_date'] ?: null;
            $notes = sanitize($_POST['notes'] ?? '');

            $result = $loanModel->returnBook($loanId, $returnDate, $notes);

            $message = "Book returned successfully!";
            if ($result['fine_amount'] > 0) {
                $message .= " Fine amount: Rp " . number_format($result['fine_amount'], 0, ',', '.');
                $message .= " (Overdue: {$result['overdue_days']} days)";
            }
            $messageType = 'success';
            logActivity('RETURN_BOOK', "Returned book for loan ID: {$loanId}");
        } elseif (isset($_POST['extend_loan'])) {
            // Extend loan
            $loanId = (int)$_POST['loan_id'];
            $extensionDays = (int)($_POST['extension_days'] ?? 7);

            $loanModel->extendLoan($loanId, $extensionDays);
            $message = "Loan extended successfully by {$extensionDays} days!";
            $messageType = 'success';
            logActivity('EXTEND_LOAN', "Extended loan ID: {$loanId} by {$extensionDays} days");
        }

        // Redirect to prevent form resubmission
        header("Location: loans.php?message=" . urlencode($message) . "&type=" . $messageType);
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

// Get loans with pagination
try {
    $loans = $loanModel->getLoansWithPagination($page, $perPage, $search, $statusFilter ?: null);

    // Get available books and active members for forms
    $availableBooks = $bookModel->all(['available_stock' => ['>', 0]], 'title ASC');
    $activeMembers = $memberModel->all(['status' => 'active'], 'full_name ASC');

    // Get overdue loans count
    $overdueLoans = $loanModel->getOverdueLoans();
    $overdueCount = count($overdueLoans);
} catch (Exception $e) {
    $message = "Error loading loans: " . $e->getMessage();
    $messageType = 'error';
    $loans = ['data' => [], 'total_pages' => 0, 'current_page' => 1];
    $availableBooks = [];
    $activeMembers = [];
    $overdueCount = 0;
}

// Get loan for actions
$actionLoan = null;
if (($action === 'return' || $action === 'extend') && isset($_GET['id'])) {
    try {
        $loanData = $loanModel->getLoansWithDetails(['l.id' => (int)$_GET['id']]);
        $actionLoan = !empty($loanData) ? $loanData[0] : null;
        if (!$actionLoan) {
            throw new Exception("Loan not found");
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
    <title>Loans Management - <?php echo SITE_NAME; ?></title>

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
                            <a class="nav-link text-white bg-white bg-opacity-25 rounded" href="loans.php">
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
                                    <i class="fas fa-handshake me-2"></i>Loans Management
                                </h1>
                                <p class="mb-0">Manage book loans and returns</p>
                            </div>
                            <div class="col-auto">
                                <?php if ($action !== 'add' && $action !== 'return' && $action !== 'extend'): ?>
                                    <a href="loans.php?action=add" class="btn btn-light">
                                        <i class="fas fa-plus me-2"></i>New Loan
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
                                <h5 class="mb-1">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Overdue Alert
                                </h5>
                                <p class="mb-0">
                                    There are <strong><?php echo $overdueCount; ?></strong> overdue loans that need attention.
                                </p>
                            </div>
                            <div class="col-auto">
                                <a href="loans.php?status=overdue" class="btn btn-light btn-sm">
                                    <i class="fas fa-eye me-2"></i>View Overdue
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'add'): ?>
                    <!-- Create New Loan Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-plus me-2"></i>Create New Loan
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="loanForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="member_id" class="form-label">Member *</label>
                                        <select class="form-select" id="member_id" name="member_id" required>
                                            <option value="">Select Member</option>
                                            <?php foreach ($activeMembers as $member): ?>
                                                <option value="<?php echo $member['id']; ?>">
                                                    <?php echo htmlspecialchars($member['member_code'] . ' - ' . $member['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="book_id" class="form-label">Book *</label>
                                        <select class="form-select" id="book_id" name="book_id" required>
                                            <option value="">Select Book</option>
                                            <?php foreach ($availableBooks as $book): ?>
                                                <option value="<?php echo $book['id']; ?>">
                                                    <?php echo htmlspecialchars($book['title'] . ' - ' . $book['author']); ?>
                                                    (Available: <?php echo $book['available_stock']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="loan_date" class="form-label">Loan Date</label>
                                        <input type="date" class="form-control" id="loan_date" name="loan_date"
                                            value="<?php echo date('Y-m-d'); ?>">
                                        <div class="form-text">Leave empty to use today's date</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="due_date" class="form-label">Due Date</label>
                                        <input type="date" class="form-control" id="due_date" name="due_date"
                                            value="<?php echo date('Y-m-d', strtotime('+' . Loan::DEFAULT_LOAN_DAYS . ' days')); ?>">
                                        <div class="form-text">Default: <?php echo Loan::DEFAULT_LOAN_DAYS; ?> days from loan date</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"
                                        placeholder="Optional notes about this loan..."></textarea>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="create_loan" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Create Loan
                                    </button>
                                    <a href="loans.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'return' && $actionLoan): ?>
                    <!-- Return Book Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-undo me-2"></i>Return Book
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Loan Details -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Loan Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Loan Code:</strong></td>
                                            <td><?php echo $actionLoan['loan_code']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Member:</strong></td>
                                            <td><?php echo htmlspecialchars($actionLoan['member_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Book:</strong></td>
                                            <td><?php echo htmlspecialchars($actionLoan['book_title']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Author:</strong></td>
                                            <td><?php echo htmlspecialchars($actionLoan['book_author']); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted">Loan Dates</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Loan Date:</strong></td>
                                            <td><?php echo date('d M Y', strtotime($actionLoan['loan_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Due Date:</strong></td>
                                            <td><?php echo date('d M Y', strtotime($actionLoan['due_date'])); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Days Borrowed:</strong></td>
                                            <td>
                                                <?php
                                                $daysBorrowed = (strtotime(date('Y-m-d')) - strtotime($actionLoan['loan_date'])) / (60 * 60 * 24);
                                                echo ceil($daysBorrowed) . ' days';

                                                if (date('Y-m-d') > $actionLoan['due_date']) {
                                                    $overdueDays = (strtotime(date('Y-m-d')) - strtotime($actionLoan['due_date'])) / (60 * 60 * 24);
                                                    $estimatedFine = ceil($overdueDays) * Loan::FINE_PER_DAY;
                                                    echo '<br><span class="text-danger">Overdue: ' . ceil($overdueDays) . ' days</span>';
                                                    echo '<br><span class="text-danger">Estimated Fine: Rp ' . number_format($estimatedFine, 0, ',', '.') . '</span>';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="loan_id" value="<?php echo $actionLoan['id']; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="return_date" class="form-label">Return Date *</label>
                                        <input type="date" class="form-control" id="return_date" name="return_date"
                                            value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="notes" class="form-label">Return Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"
                                            placeholder="Optional notes about the return..."></textarea>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="return_book" class="btn btn-success">
                                        <i class="fas fa-check me-2"></i>Return Book
                                    </button>
                                    <a href="loans.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($action === 'extend' && $actionLoan): ?>
                    <!-- Extend Loan Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-calendar-plus me-2"></i>Extend Loan
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Loan Details -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted">Loan Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>Loan Code:</strong></td>
                                            <td><?php echo $actionLoan['loan_code']; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Member:</strong></td>
                                            <td><?php echo htmlspecialchars($actionLoan['member_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Book:</strong></td>
                                            <td><?php echo htmlspecialchars($actionLoan['book_title']); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Current Due Date:</strong></td>
                                            <td><?php echo date('d M Y', strtotime($actionLoan['due_date'])); ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="loan_id" value="<?php echo $actionLoan['id']; ?>">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="extension_days" class="form-label">Extension Days *</label>
                                        <select class="form-select" id="extension_days" name="extension_days" required>
                                            <option value="7" selected>7 days</option>
                                            <option value="14">14 days</option>
                                            <option value="21">21 days</option>
                                            <option value="30">30 days</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">New Due Date</label>
                                        <input type="text" class="form-control" id="new_due_date" readonly>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="extend_loan" class="btn btn-warning">
                                        <i class="fas fa-calendar-plus me-2"></i>Extend Loan
                                    </button>
                                    <a href="loans.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Loans List -->

                    <!-- Quick Statistics -->
                    <?php
                    try {
                        $stats = $loanModel->getLoanStatistics();
                    } catch (Exception $e) {
                        $stats = ['total_loans' => 0, 'active_loans' => 0, 'returned_loans' => 0, 'overdue_loans' => 0];
                    }
                    ?>
                    <div class="quick-stats">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item primary">
                                    <h3 class="mb-1"><?php echo number_format($stats['total_loans']); ?></h3>
                                    <p class="mb-0">Total Loans</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item success">
                                    <h3 class="mb-1"><?php echo number_format($stats['active_loans']); ?></h3>
                                    <p class="mb-0">Active Loans</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item warning">
                                    <h3 class="mb-1"><?php echo number_format($stats['returned_loans']); ?></h3>
                                    <p class="mb-0">Returned Books</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item danger">
                                    <h3 class="mb-1"><?php echo number_format($stats['overdue_loans']); ?></h3>
                                    <p class="mb-0">Overdue Loans</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter Form -->
                    <div class="search-form">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Loans</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Search by loan code, member, book..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="borrowed" <?php echo $statusFilter === 'borrowed' ? 'selected' : ''; ?>>Borrowed</option>
                                    <option value="returned" <?php echo $statusFilter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                    <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
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
                                <a href="loans.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Loans Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Loans List
                                <span class="badge bg-primary"><?php echo number_format($loans['total_records']); ?></span>
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
                            <?php if (!empty($loans['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="loansTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Loan Code</th>
                                                <th>Member</th>
                                                <th>Book</th>
                                                <th>Loan Date</th>
                                                <th>Due Date</th>
                                                <th>Return Date</th>
                                                <th>Status</th>
                                                <th>Fine</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($loans['data'] as $loan): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($loan['loan_code']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($loan['member_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($loan['member_code']); ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($loan['book_title']); ?></div>
                                                        <small class="text-muted">by <?php echo htmlspecialchars($loan['book_author']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php echo date('d M Y', strtotime($loan['loan_date'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $dueDate = date('d M Y', strtotime($loan['due_date']));
                                                        $isOverdue = ($loan['status'] !== 'returned' && date('Y-m-d') > $loan['due_date']);
                                                        echo $isOverdue ? '<span class="text-danger fw-bold">' . $dueDate . '</span>' : $dueDate;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($loan['return_date']): ?>
                                                            <?php echo date('d M Y', strtotime($loan['return_date'])); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $statusClass = '';
                                                        $statusIcon = '';
                                                        switch ($loan['status']) {
                                                            case 'borrowed':
                                                                $statusClass = 'bg-primary';
                                                                $statusIcon = 'fas fa-book-open';
                                                                break;
                                                            case 'returned':
                                                                $statusClass = 'bg-success';
                                                                $statusIcon = 'fas fa-check-circle';
                                                                break;
                                                            case 'overdue':
                                                                $statusClass = 'bg-danger';
                                                                $statusIcon = 'fas fa-exclamation-triangle';
                                                                break;
                                                        }
                                                        ?>
                                                        <span class="badge <?php echo $statusClass; ?> status-badge">
                                                            <i class="<?php echo $statusIcon; ?> me-1"></i>
                                                            <?php echo ucfirst($loan['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($loan['fine_amount'] > 0): ?>
                                                            <span class="text-danger fw-bold">
                                                                Rp <?php echo number_format($loan['fine_amount'], 0, ',', '.'); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-info btn-action"
                                                                onclick="viewLoan(<?php echo $loan['id']; ?>)"
                                                                title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>

                                                            <?php if ($loan['status'] === 'borrowed' || $loan['status'] === 'overdue'): ?>
                                                                <a href="loans.php?action=return&id=<?php echo $loan['id']; ?>"
                                                                    class="btn btn-sm btn-outline-success btn-action"
                                                                    title="Return Book">
                                                                    <i class="fas fa-undo"></i>
                                                                </a>
                                                                <a href="loans.php?action=extend&id=<?php echo $loan['id']; ?>"
                                                                    class="btn btn-sm btn-outline-warning btn-action"
                                                                    title="Extend Loan">
                                                                    <i class="fas fa-calendar-plus"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination -->
                                <?php if ($loans['total_pages'] > 1): ?>
                                    <div class="card-footer">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <small class="text-muted">
                                                    Showing <?php echo (($loans['current_page'] - 1) * $loans['per_page']) + 1; ?>
                                                    to <?php echo min($loans['current_page'] * $loans['per_page'], $loans['total_records']); ?>
                                                    of <?php echo number_format($loans['total_records']); ?> results
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <nav>
                                                    <ul class="pagination pagination-sm mb-0">
                                                        <?php if ($loans['has_prev']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $loans['prev_page']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <i class="fas fa-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start = max(1, $loans['current_page'] - 2);
                                                        $end = min($loans['total_pages'], $loans['current_page'] + 2);

                                                        for ($i = $start; $i <= $end; $i++): ?>
                                                            <li class="page-item <?php echo $i == $loans['current_page'] ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <?php if ($loans['has_next']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $loans['next_page']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $statusFilter; ?>&per_page=<?php echo $perPage; ?>">
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
                                    <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No loans found</h5>
                                    <p class="text-muted">
                                        <?php if ($search || $statusFilter): ?>
                                            Try adjusting your search criteria or <a href="loans.php">view all loans</a>.
                                        <?php else: ?>
                                            Start by <a href="loans.php?action=add">creating your first loan</a>.
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

    <!-- Loan Details Modal -->
    <div class="modal fade" id="loanModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Loan Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="loanModalBody">
                    <!-- Content will be loaded via AJAX -->
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
            $('#loansTable').DataTable({
                "pageLength": <?php echo $perPage; ?>,
                "searching": false, // We use custom search
                "paging": false, // We use custom pagination
                "info": false, // We show custom info
                "ordering": true,
                "order": [
                    [3, 'desc']
                ], // Order by loan date desc
                "columnDefs": [{
                        "orderable": false,
                        "targets": [8]
                    } // Actions column
                ]
            });
        });

        // Auto-calculate new due date when extending loan
        <?php if ($action === 'extend'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const extensionSelect = document.getElementById('extension_days');
                const newDueDateField = document.getElementById('new_due_date');
                const currentDueDate = new Date('<?php echo $actionLoan['due_date']; ?>');

                function updateNewDueDate() {
                    const extensionDays = parseInt(extensionSelect.value);
                    const newDate = new Date(currentDueDate);
                    newDate.setDate(newDate.getDate() + extensionDays);

                    const options = {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    };
                    newDueDateField.value = newDate.toLocaleDateString('en-US', options);
                }

                extensionSelect.addEventListener('change', updateNewDueDate);
                updateNewDueDate(); // Initialize
            });
        <?php endif; ?>

        // Auto-calculate due date when creating loan
        <?php if ($action === 'add'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const loanDateField = document.getElementById('loan_date');
                const dueDateField = document.getElementById('due_date');

                function updateDueDate() {
                    if (loanDateField.value) {
                        const loanDate = new Date(loanDateField.value);
                        loanDate.setDate(loanDate.getDate() + <?php echo Loan::DEFAULT_LOAN_DAYS; ?>);
                        dueDateField.value = loanDate.toISOString().split('T')[0];
                    }
                }

                loanDateField.addEventListener('change', updateDueDate);
            });
        <?php endif; ?>

        // View loan details
        function viewLoan(id) {
            // Show loading spinner
            document.getElementById('loanModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading loan details...</p>
                </div>
            `;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('loanModal'));
            modal.show();

            // Load loan details via AJAX
            fetch(`ajax/get_loan_details.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('loanModalBody').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('loanModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading loan details. Please try again.
                        </div>
                    `;
                });
        }

        // Export table functions
        function exportTable(format) {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('export', format);
            window.open(`loans.php?${searchParams.toString()}`, '_blank');
        }

        // Form validation
        document.getElementById('loanForm')?.addEventListener('submit', function(e) {
            const memberId = document.getElementById('member_id').value;
            const bookId = document.getElementById('book_id').value;

            if (!memberId || !bookId) {
                e.preventDefault();
                alert('Please select both member and book.');
                return false;
            }

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
            submitBtn.disabled = true;

            // Re-enable button after 5 seconds in case of error
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 5000);
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

        // Confirm actions
        function confirmAction(action, loanCode) {
            const messages = {
                'return': `Are you sure you want to return the book for loan ${loanCode}?`,
                'extend': `Are you sure you want to extend the loan ${loanCode}?`
            };

            return confirm(messages[action] || 'Are you sure?');
        }

        // Real-time search suggestions (can be enhanced with AJAX)
        document.getElementById('search')?.addEventListener('input', function() {
            // Implement real-time search if needed
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+N for new loan
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'loans.php?action=add';
            }

            // Escape to go back to list
            if (e.key === 'Escape' && window.location.search.includes('action=')) {
                window.location.href = 'loans.php';
            }
        });

        // Print functionality
        function printLoanDetails(loanId) {
            window.open(`print_loan.php?id=${loanId}`, '_blank');
        }

        // Enhanced date validation
        document.querySelectorAll('input[type="date"]').forEach(function(dateInput) {
            dateInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (this.id === 'return_date' && selectedDate < new Date(document.querySelector('input[name="loan_date"]')?.value || '1900-01-01')) {
                    alert('Return date cannot be before loan date');
                    this.value = '';
                }
            });
        });
    </script>
</body>

</html>