<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Include required classes
require_once 'classes/BaseModel.php';
require_once 'classes/Book.php';
require_once 'classes/Category.php';

$bookModel = new Book();
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

        if (isset($_POST['add_book'])) {
            // Add new book
            $data = [
                'title' => sanitize($_POST['title']),
                'author' => sanitize($_POST['author']),
                'isbn' => sanitize($_POST['isbn']),
                'category_id' => (int)$_POST['category_id'],
                'publisher' => sanitize($_POST['publisher']),
                'year_published' => (int)$_POST['year_published'],
                'pages' => (int)$_POST['pages'],
                'stock' => (int)$_POST['stock'],
                'description' => sanitize($_POST['description'])
            ];

            // Handle cover image upload
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $data['cover_image'] = uploadFile($_FILES['cover_image']);
                } catch (Exception $e) {
                    throw new Exception("Cover image upload failed: " . $e->getMessage());
                }
            }

            $book = $bookModel->createBook($data);
            $message = "Book '{$data['title']}' has been added successfully!";
            $messageType = 'success';
            logActivity('CREATE_BOOK', "Added new book: {$data['title']}");
        } elseif (isset($_POST['edit_book'])) {
            // Edit book
            $bookId = (int)$_POST['book_id'];
            $data = [
                'title' => sanitize($_POST['title']),
                'author' => sanitize($_POST['author']),
                'isbn' => sanitize($_POST['isbn']),
                'category_id' => (int)$_POST['category_id'],
                'publisher' => sanitize($_POST['publisher']),
                'year_published' => (int)$_POST['year_published'],
                'pages' => (int)$_POST['pages'],
                'stock' => (int)$_POST['stock'],
                'description' => sanitize($_POST['description'])
            ];

            // Handle cover image upload
            if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $data['cover_image'] = uploadFile($_FILES['cover_image']);
                } catch (Exception $e) {
                    throw new Exception("Cover image upload failed: " . $e->getMessage());
                }
            }

            $book = $bookModel->updateBook($bookId, $data);
            $message = "Book '{$data['title']}' has been updated successfully!";
            $messageType = 'success';
            logActivity('UPDATE_BOOK', "Updated book ID: {$bookId}");
        }

        // Redirect to prevent form resubmission
        header("Location: books.php?message=" . urlencode($message) . "&type=" . $messageType);
        exit();
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    try {
        $bookId = (int)$_GET['id'];
        $book = $bookModel->find($bookId);

        if ($book) {
            $bookModel->deleteBook($bookId);
            $message = "Book '{$book['title']}' has been deleted successfully!";
            $messageType = 'success';
            logActivity('DELETE_BOOK', "Deleted book: {$book['title']}");
        } else {
            throw new Exception("Book not found");
        }

        header("Location: books.php?message=" . urlencode($message) . "&type=" . $messageType);
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
$categoryFilter = (int)($_GET['category'] ?? 0);

// Get books with pagination
try {
    $books = $bookModel->getBooksWithPagination($page, $perPage, $search, $categoryFilter ?: null);
    $categories = $categoryModel->all([], 'name ASC');
} catch (Exception $e) {
    $message = "Error loading books: " . $e->getMessage();
    $messageType = 'error';
    $books = ['data' => [], 'total_pages' => 0, 'current_page' => 1];
    $categories = [];
}

// Get book for editing
$editBook = null;
if ($action === 'edit' && isset($_GET['id'])) {
    try {
        $editBook = $bookModel->find((int)$_GET['id']);
        if (!$editBook) {
            throw new Exception("Book not found");
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
    <title>Books Management - <?php echo SITE_NAME; ?></title>

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

        .cover-thumbnail {
            width: 60px;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
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
    </style>
</head>

<body>
    <!-- Navigation (same as dashboard) -->
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
                            <a class="nav-link text-white bg-white bg-opacity-25 rounded" href="books.php">
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
                                    <i class="fas fa-book me-2"></i>Books Management
                                </h1>
                                <p class="mb-0">Manage your library's book collection</p>
                            </div>
                            <div class="col-auto">
                                <?php if ($action !== 'add' && $action !== 'edit'): ?>
                                    <a href="books.php?action=add" class="btn btn-light">
                                        <i class="fas fa-plus me-2"></i>Add New Book
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

                <?php if ($action === 'add' || $action === 'edit'): ?>
                    <!-- Add/Edit Book Form -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?> me-2"></i>
                                <?php echo $action === 'add' ? 'Add New Book' : 'Edit Book'; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="bookForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="book_id" value="<?php echo $editBook['id']; ?>">
                                <?php endif; ?>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="title" class="form-label">Title *</label>
                                                <input type="text" class="form-control" id="title" name="title"
                                                    value="<?php echo htmlspecialchars($editBook['title'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="author" class="form-label">Author *</label>
                                                <input type="text" class="form-control" id="author" name="author"
                                                    value="<?php echo htmlspecialchars($editBook['author'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="isbn" class="form-label">ISBN</label>
                                                <input type="text" class="form-control" id="isbn" name="isbn"
                                                    value="<?php echo htmlspecialchars($editBook['isbn'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="category_id" class="form-label">Category *</label>
                                                <select class="form-select" id="category_id" name="category_id" required>
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>"
                                                            <?php echo ($editBook['category_id'] ?? '') == $category['id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="publisher" class="form-label">Publisher</label>
                                                <input type="text" class="form-control" id="publisher" name="publisher"
                                                    value="<?php echo htmlspecialchars($editBook['publisher'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="year_published" class="form-label">Year Published</label>
                                                <input type="number" class="form-control" id="year_published" name="year_published"
                                                    min="1000" max="<?php echo date('Y'); ?>"
                                                    value="<?php echo $editBook['year_published'] ?? ''; ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="pages" class="form-label">Pages</label>
                                                <input type="number" class="form-control" id="pages" name="pages"
                                                    min="1" value="<?php echo $editBook['pages'] ?? ''; ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="stock" class="form-label">Stock *</label>
                                                <input type="number" class="form-control" id="stock" name="stock"
                                                    min="1" value="<?php echo $editBook['stock'] ?? 1; ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Available Stock</label>
                                                <input type="text" class="form-control"
                                                    value="<?php echo $editBook['available_stock'] ?? 'Will be set automatically'; ?>"
                                                    readonly>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($editBook['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="cover_image" class="form-label">Cover Image</label>
                                            <input type="file" class="form-control" id="cover_image" name="cover_image" accept="image/*">
                                            <div class="form-text">Max file size: 5MB. Formats: JPG, JPEG, PNG, GIF</div>

                                            <?php if ($action === 'edit' && !empty($editBook['cover_image'])): ?>
                                                <div class="mt-3">
                                                    <p class="mb-2">Current Cover:</p>
                                                    <img src="<?php echo UPLOAD_PATH . $editBook['cover_image']; ?>"
                                                        alt="Current cover" class="img-thumbnail" style="max-width: 200px;">
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="<?php echo $action === 'add' ? 'add_book' : 'edit_book'; ?>" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        <?php echo $action === 'add' ? 'Add Book' : 'Update Book'; ?>
                                    </button>
                                    <a href="books.php" class="btn btn-secondary">
                                        <i class="fas fa-times me-2"></i>Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Books List -->

                    <!-- Search and Filter Form -->
                    <div class="search-form">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search Books</label>
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Search by title, author, ISBN..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"
                                            <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
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
                                <a href="books.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-redo me-2"></i>Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Books Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2"></i>Books List
                                <span class="badge bg-primary"><?php echo number_format($books['total_records']); ?></span>
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
                            <?php if (!empty($books['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0" id="booksTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Cover</th>
                                                <th>Title</th>
                                                <th>Author</th>
                                                <th>Category</th>
                                                <th>ISBN</th>
                                                <th>Stock</th>
                                                <th>Available</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($books['data'] as $book): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($book['cover_image'])): ?>
                                                            <img src="<?php echo UPLOAD_PATH . $book['cover_image']; ?>"
                                                                alt="Cover" class="cover-thumbnail">
                                                        <?php else: ?>
                                                            <div class="cover-thumbnail bg-light d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-book text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($book['title']); ?></strong>
                                                        <?php if ($book['year_published']): ?>
                                                            <br><small class="text-muted">(<?php echo $book['year_published']; ?>)</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($book['author']); ?></td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($book['category_name'] ?? 'Uncategorized'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($book['isbn']): ?>
                                                            <code><?php echo htmlspecialchars($book['isbn']); ?></code>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge bg-secondary"><?php echo $book['stock']; ?></span></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $book['available_stock'] > 0 ? 'success' : 'danger'; ?>">
                                                            <?php echo $book['available_stock']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <a href="books.php?action=edit&id=<?php echo $book['id']; ?>"
                                                                class="btn btn-sm btn-outline-primary btn-action"
                                                                title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-info btn-action"
                                                                onclick="viewBook(<?php echo $book['id']; ?>)"
                                                                title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <?php if (isAdmin()): ?>
                                                                <button type="button"
                                                                    class="btn btn-sm btn-outline-danger btn-action"
                                                                    onclick="deleteBook(<?php echo $book['id']; ?>, '<?php echo htmlspecialchars($book['title']); ?>')"
                                                                    title="Delete">
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
                                <?php if ($books['total_pages'] > 1): ?>
                                    <div class="card-footer">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <small class="text-muted">
                                                    Showing <?php echo (($books['current_page'] - 1) * $books['per_page']) + 1; ?>
                                                    to <?php echo min($books['current_page'] * $books['per_page'], $books['total_records']); ?>
                                                    of <?php echo number_format($books['total_records']); ?> results
                                                </small>
                                            </div>
                                            <div class="col-auto">
                                                <nav>
                                                    <ul class="pagination pagination-sm mb-0">
                                                        <?php if ($books['has_prev']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $books['prev_page']; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <i class="fas fa-chevron-left"></i>
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>

                                                        <?php
                                                        $start = max(1, $books['current_page'] - 2);
                                                        $end = min($books['total_pages'], $books['current_page'] + 2);

                                                        for ($i = $start; $i <= $end; $i++): ?>
                                                            <li class="page-item <?php echo $i == $books['current_page'] ? 'active' : ''; ?>">
                                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>&per_page=<?php echo $perPage; ?>">
                                                                    <?php echo $i; ?>
                                                                </a>
                                                            </li>
                                                        <?php endfor; ?>

                                                        <?php if ($books['has_next']): ?>
                                                            <li class="page-item">
                                                                <a class="page-link" href="?page=<?php echo $books['next_page']; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>&per_page=<?php echo $perPage; ?>">
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
                                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No books found</h5>
                                    <p class="text-muted">
                                        <?php if ($search || $categoryFilter): ?>
                                            Try adjusting your search criteria or <a href="books.php">view all books</a>.
                                        <?php else: ?>
                                            Start by <a href="books.php?action=add">adding your first book</a>.
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

    <!-- Book Details Modal -->
    <div class="modal fade" id="bookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Book Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bookModalBody">
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
            $('#booksTable').DataTable({
                "pageLength": <?php echo $perPage; ?>,
                "searching": false, // We use custom search
                "paging": false, // We use custom pagination
                "info": false, // We show custom info
                "ordering": true,
                "order": [
                    [1, 'asc']
                ], // Order by title
                "columnDefs": [{
                        "orderable": false,
                        "targets": [0, 7]
                    } // Cover and Actions columns
                ]
            });
        });

        // Delete book function
        function deleteBook(id, title) {
            if (confirm(`Are you sure you want to delete the book "${title}"?\n\nThis action cannot be undone.`)) {
                window.location.href = `books.php?action=delete&id=${id}`;
            }
        }

        // View book details
        function viewBook(id) {
            // Show loading spinner
            document.getElementById('bookModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading book details...</p>
                </div>
            `;

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('bookModal'));
            modal.show();

            // Load book details via AJAX
            fetch(`ajax/get_book_details.php?id=${id}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('bookModalBody').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('bookModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading book details. Please try again.
                        </div>
                    `;
                });
        }

        // Export table functions
        function exportTable(format) {
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('export', format);
            window.open(`books.php?${searchParams.toString()}`, '_blank');
        }

        // Form validation
        document.getElementById('bookForm')?.addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const author = document.getElementById('author').value.trim();
            const categoryId = document.getElementById('category_id').value;
            const stock = parseInt(document.getElementById('stock').value);

            if (!title || !author || !categoryId || !stock || stock < 1) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
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
    </script>
</body>

</html>