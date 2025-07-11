<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Include required classes
require_once 'classes/BaseModel.php';
require_once 'classes/Category.php';

$categoryModel = new Category();

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

        if (isset($_POST['add_category'])) {
            // Add new category
            $data = [
                'name' => sanitize($_POST['name']),
                'description' => sanitize($_POST['description'])
            ];

            $category = $categoryModel->createCategory($data);
            $message = "Category '{$data['name']}' has been added successfully!";
            $messageType = 'success';
            logActivity('CREATE_CATEGORY', "Added new category: {$data['name']}");
        } elseif (isset($_POST['edit_category'])) {
            // Edit category
            $categoryId = (int)$_POST['category_id'];
            $data = [
                'name' => sanitize($_POST['name']),
                'description' => sanitize($_POST['description'])
            ];

            $category = $categoryModel->updateCategory($categoryId, $data);
            $message = "Category '{$data['name']}' has been updated successfully!";
            $messageType = 'success';
            logActivity('UPDATE_CATEGORY', "Updated category ID: {$categoryId}");
        }

        // Redirect to prevent form resubmission
        header("Location: categories.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $categoryId = (int)$_GET['id'];
        $category = $categoryModel->find($categoryId);

        if ($category) {
            $categoryModel->deleteCategory($categoryId);
            $message = "Category '{$category['name']}' has been deleted successfully!";
            $messageType = 'success';
            logActivity('DELETE_CATEGORY', "Deleted category: {$category['name']}");
        } else {
            throw new Exception("Category not found");
        }

        header("Location: categories.php?message=" . urlencode($message) . "&type=" . $messageType);
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

// Get categories with pagination
try {
    $categories = $categoryModel->getCategoriesWithPagination($page, $perPage, $search);
    $categoryStats = $categoryModel->getCategoryStatistics();
    $popularCategories = $categoryModel->getPopularCategories(5);
    $emptyCategories = $categoryModel->getEmptyCategories();
} catch (Exception $e) {
    $message = "Error loading categories: " . $e->getMessage();
    $messageType = 'error';
    $categories = ['data' => [], 'total_pages' => 0, 'current_page' => 1];
    $categoryStats = ['total_categories' => 0, 'categories_with_books' => 0, 'empty_categories' => 0];
    $popularCategories = [];
    $emptyCategories = [];
}

// Get category for editing
$editCategory = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $editCategory = $categoryModel->find((int)$_GET['id']);
        if (!$editCategory) {
            throw new Exception("Category not found");
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
    <title>Categories Management - <?php echo SITE_NAME; ?></title>

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

        .category-card {
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .popular-categories {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .category-tag {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            margin: 0.2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.875rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .category-tag:hover {
            transform: scale(1.05);
            color: white;
        }

        .empty-categories-alert {
            background: linear-gradient(135deg, #ffa726 0%, #ff8a65 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
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
                            <a class="nav-link text-white bg-white bg-opacity-25 rounded" href="categories.php">
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
                                    <i class="fas fa-tags me-2"></i>Categories Management
                                </h1>
                                <p class="mb-0">Organize your library's book collection</p>
                            </div>
                            <div class="col-auto">
                                <?php if ($action !== 'add' && $action !== 'edit'): ?>
                                    <a href="categories.php?action=add" class="btn btn-light">
                                        <i class="fas fa-plus me-2"></i>Add New Category
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

                <!-- Empty Categories Warning -->
                <?php if (!empty($emptyCategories) && $action === 'list'): ?>
                    <div class="empty-categories-alert">
                        <div class="row align-items-center">
                            <div class="col">
                                <h6 class="mb-1">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Empty Categories Found
                                </h6>
                                <p class="mb-0">
                                    There are <strong><?php echo count($emptyCategories); ?></strong> categories with no books assigned.
                                    Consider adding books or removing unused categories.
                                </p>
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-light btn-sm" onclick="showEmptyCategories()">
                                    <i class="fas fa-eye me-2"></i>View Empty
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Category Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'add' ? 'Add New Category' : 'Edit Category'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="categoryForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="category_id" value="<?php echo $editCategory['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Category Name *</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>"
                                            required maxlength="100">
                                        <div class="form-text">Enter a unique category name</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Category Preview</label>
                                        <div class="form-control-plaintext">
                                            <span class="category-tag" id="categoryPreview">
                                                <i class="fas fa-tag me-1"></i>
                                                <?php echo htmlspecialchars($editCategory['name'] ?? 'Category Name'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description"
                                        rows="4" maxlength="500"><?php echo htmlspecialchars($editCategory['description'] ?? ''); ?></textarea>
                                    <div class="form-text">Optional description for this category (max 500 characters)</div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'add_category' : 'edit_category'; ?>" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'add' ? 'Add Category' : 'Update Category'; ?>
                                    </button>
                                    <a href="categories.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Categories List -->

                    <!-- Quick Statistics -->
                    <div class="quick-stats">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="stat-item primary">
                                    <h3 class="mb-1"><?php echo number_format($categoryStats['total_categories']); ?></h3>
                                    <p class="mb-0">Total Categories</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item success">
                                    <h3 class="mb-1"><?php echo number_format($categoryStats['categories_with_books']); ?></h3>
                                    <p class="mb-0">With Books</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item warning">
                                    <h3 class="mb-1"><?php echo number_format($categoryStats['empty_categories']); ?></h3>
                                    <p class="mb-0">Empty Categories</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stat-item info">
                                    <h3 class="mb-1">
                                        <?php echo $categoryStats['most_popular']['book_count'] ?? 0; ?>
                                    </h3>
                                    <p class="mb-0">Max Books</p>
                                    <?php if (isset($categoryStats['most_popular']['name'])): ?>
                                        <small class="opacity-75"><?php echo htmlspecialchars($categoryStats['most_popular']['name']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Categories -->
                    <?php if (!empty($popularCategories)): ?>
                        <div class="popular-categories">
                            <h6 class="mb-3">
                                <i class="fas fa-fire me-2 text-danger"></i>Popular Categories
                            </h6>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($popularCategories as $popular): ?>
                                    <a href="books.php?category=<?php echo $popular['id']; ?>" class="category-tag">
                                        <i class="fas fa-tag me-1"></i>
                                        <?php echo htmlspecialchars($popular['name']); ?>
                                        <span class="badge bg-light text-dark ms-1"><?php echo $popular['book_count']; ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Search and Filter Form -->
                    <div class="search-form">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search Categories</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Search by name or description..."
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
                                <a href="categories.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Categories Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Categories List
                                <span class="badge bg-primary"><?php echo number_format($categories['total_records']); ?></span>
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
                            <?php if (!empty($categories['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="categoriesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Books Count</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories['data'] as $category): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <span class="category-tag me-2">
                                                                <i class="fas fa-tag me-1"></i>
                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($category['description']): ?>
                                                            <span class="text-truncate d-inline-block" style="max-width: 300px;"
                                                                title="<?php echo htmlspecialchars($category['description']); ?>">
                                                                <?php echo htmlspecialchars($category['description']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">No description</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($category['book_count'] > 0): ?>
                                                            <a href="books.php?category=<?php echo $category['id']; ?>"
                                                                class="badge bg-success text-decoration-none">
                                                                <?php echo $category['book_count']; ?> books
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">0 books</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo date('d M Y', strtotime($category['created_at'])); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="categories.php?action=edit&id=<?php echo $category['id']; ?>"
                                                                class="btn btn-sm btn-outline-primary btn-action"
                                                                title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="books.php?category=<?php echo $category['id']; ?>"
                                                                class="btn btn-sm btn-outline-info btn-action"
                                                                title="View Books">
                                                                <i class="fas fa-book"></i>
                                                            </a>
                                                            <?php if (isAdmin()): ?>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-danger btn-action"
                                                                    onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['book_count']; ?>)"
                                                                    title="Delete"
                                                                    <?php echo $category['book_count'] > 0 ? 'disabled' : ''; ?>>
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
                                <?php if ($categories['total_pages'] > 1): ?>
                                    <div class="card-footer">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <small class="text-muted">
                                                    Showing <?php echo (($categories['current_page'] - 1) * $categories['per_page']) + 1; ?>
                                                    to <?php echo min($categories['current_page'] * $categories['per_page'], $categories['total_records']); ?>
                                                    of <?php echo number_format($categories['total_records']); ?> results
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <nav>
                                                    <ul class="pagination pagination-sm mb-0">
                                                        <?php if ($categories['has_prev']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $categories['prev_page']; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>">
                                                                    <i class="fas fa-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start = max(1, $categories['current_page'] - 2);
                                                        $end = min($categories['total_pages'], $categories['current_page'] + 2);

                                                        for ($i = $start; $i <= $end; $i++): ?>
                                                            <li class="page-item <?php echo $i == $categories['current_page'] ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <?php if ($categories['has_next']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $categories['next_page']; ?>&search=<?php echo urlencode($search); ?>&per_page=<?php echo $perPage; ?>">
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
                                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No categories found</h5>
                                    <p class="text-muted">
                                        <?php if ($search): ?>
                                            Try adjusting your search criteria or <a href="categories.php">view all categories</a>.
                                        <?php else: ?>
                                            Start by <a href="categories.php?action=add">creating your first category</a>.
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

    <!-- Empty Categories Modal -->
    <div class="modal fade" id="emptyCategoriesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Empty Categories
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        The following categories have no books assigned to them. Consider adding books or removing these categories.
                    </p>

                    <?php if (!empty($emptyCategories)): ?>
                        <div class="row">
                            <?php foreach ($emptyCategories as $empty): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card category-card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <span class="category-tag">
                                                    <i class="fas fa-tag me-1"></i>
                                                    <?php echo htmlspecialchars($empty['name']); ?>
                                                </span>
                                            </h6>
                                            <p class="card-text text-muted small">
                                                <?php echo $empty['description'] ? htmlspecialchars($empty['description']) : 'No description'; ?>
                                            </p>
                                            <div class="d-flex gap-2">
                                                <a href="categories.php?action=edit&id=<?php echo $empty['id']; ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </a>
                                                <a href="books.php?action=add&category=<?php echo $empty['id']; ?>"
                                                    class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-plus me-1"></i>Add Book
                                                </a>
                                                <?php if (isAdmin()): ?>
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteCategory(<?php echo $empty['id']; ?>, '<?php echo htmlspecialchars($empty['name']); ?>', 0)">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <p class="text-muted">All categories have books assigned!</p>
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
            $('#categoriesTable').DataTable({
                "pageLength": <?php echo $perPage; ?>,
                "searching": false, // We use custom search
                "paging": false, // We use custom pagination
                "info": false, // We show custom info
                "ordering": true,
                "order": [
                    [0, 'asc']
                ], // Order by name
                "columnDefs": [{
                        "orderable": false,
                        "targets": [4]
                    } // Actions column
                ]
            });
        });

        // Live preview for category name
        <?php if ($action === 'add' || $action === 'edit'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const nameInput = document.getElementById('name');
                const preview = document.getElementById('categoryPreview');

                function updatePreview() {
                    const value = nameInput.value.trim();
                    if (value) {
                        preview.innerHTML = '<i class="fas fa-tag me-1"></i>' + value;
                    } else {
                        preview.innerHTML = '<i class="fas fa-tag me-1"></i>Category Name';
                    }
                }

                nameInput.addEventListener('input', updatePreview);
                updatePreview(); // Initialize
            });
        <?php endif; ?>

        // Delete category function
        function deleteCategory(id, name, bookCount) {
            if (bookCount > 0) {
                alert(`Cannot delete category "${name}" because it has ${bookCount} books assigned to it.\n\nPlease reassign or remove the books first.`);
                return false;
            }

            if (confirm(`Are you sure you want to delete the category "${name}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `categories.php?action=delete&id=${id}`;
            }
        }

        // Show empty categories modal
        function showEmptyCategories() {
            const modal = new bootstrap.Modal(document.getElementById('emptyCategoriesModal'));
            modal.show();
        }

        // Export table functions
        function exportTable(format) {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('export', format);
            window.open(`categories.php?${searchParams.toString()}`, '_blank');
        }

        // Form validation
        document.getElementById('categoryForm')?.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();

            if (!name) {
                e.preventDefault();
                alert('Please enter a category name.');
                return false;
            }

            if (name.length > 100) {
                e.preventDefault();
                alert('Category name cannot exceed 100 characters.');
                return false;
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

        // Character counter for description
        document.getElementById('description')?.addEventListener('input', function() {
            const maxLength = 500;
            const currentLength = this.value.length;
            const remaining = maxLength - currentLength;

            // Find or create counter element
            let counter = document.getElementById('descriptionCounter');
            if (!counter) {
                counter = document.createElement('small');
                counter.id = 'descriptionCounter';
                counter.className = 'form-text';
                this.parentNode.appendChild(counter);
            }

            counter.textContent = `${remaining} characters remaining`;
            counter.className = remaining < 0 ? 'form-text text-danger' : 'form-text text-muted';
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
            // Ctrl+N for new category
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'categories.php?action=add';
            }

            // Escape to go back to list
            if (e.key === 'Escape' && window.location.search.includes('action=')) {
                window.location.href = 'categories.php';
            }
        });

        // Search with enter key
        document.getElementById('search')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });

        // Auto-focus on name field when adding/editing
        <?php if ($action === 'add' || $action === 'edit'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('name').focus();
            });
        <?php endif; ?>

        // Tooltip for truncated descriptions
        document.querySelectorAll('[title]').forEach(function(element) {
            new bootstrap.Tooltip(element);
        });

        // Confirm before leaving form with unsaved changes
        <?php if ($action === 'add' || $action === 'edit'): ?>
            let formChanged = false;
            document.querySelectorAll('#categoryForm input, #categoryForm textarea').forEach(function(input) {
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

            document.getElementById('categoryForm').addEventListener('submit', function() {
                formChanged = false;
            });
        <?php endif; ?>

        // Real-time validation
        document.getElementById('name')?.addEventListener('input', function() {
            const value = this.value.trim();
            const submitBtn = document.querySelector('#categoryForm button[type="submit"]');

            if (value.length === 0) {
                this.classList.add('is-invalid');
                if (submitBtn) submitBtn.disabled = true;
            } else if (value.length > 100) {
                this.classList.add('is-invalid');
                if (submitBtn) submitBtn.disabled = true;
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                if (submitBtn) submitBtn.disabled = false;
            }
        });

        // Category statistics refresh
        function refreshStats() {
            // This could be enhanced with AJAX to refresh stats without page reload
            window.location.reload();
        }

        // Bulk operations (future enhancement)
        function bulkDeleteEmptyCategories() {
            if (confirm('Are you sure you want to delete ALL empty categories?\n\nThis action cannot be undone.')) {
                // Implement bulk delete functionality
                console.log('Bulk delete empty categories');
            }
        }

        // Enhanced search suggestions
        document.getElementById('search')?.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            // Highlight matching categories in real-time
            document.querySelectorAll('#categoriesTable tbody tr').forEach(function(row) {
                const categoryName = row.querySelector('td:first-child').textContent.toLowerCase();
                const description = row.querySelector('td:nth-child(2)').textContent.toLowerCase();

                if (searchTerm === '' || categoryName.includes(searchTerm) || description.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>

</html>