<?php

/**
 * Digital Library Management System
 * Database Configuration File
 * 
 * This file contains database connection settings and system configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'granvsam_registration');
define('DB_PASS', '@rgent@234$$');
define('DB_NAME', 'granvsam_digital_library');

// System Configuration
define('SITE_NAME', 'Digital Library Management System');
define('SITE_URL', 'http://localhost/digital_library');
define('ADMIN_EMAIL', 'admin@digitallibrary.com');

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// File Upload Configuration
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination Configuration
define('RECORDS_PER_PAGE', 10);

// Date/Time Configuration
date_default_timezone_set('Asia/Jakarta');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Database Connection Class using Singleton Pattern
 */
class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() {}
}

/**
 * Utility Functions
 */

/**
 * Sanitize input data
 */
function sanitize($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate random string for codes
 */
function generateCode($prefix, $length = 6)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $prefix . $randomString;
}

/**
 * Format date to Indonesian format
 */
function formatDate($date)
{
    $months = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];

    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[date('m', $timestamp)];
    $year = date('Y', $timestamp);

    return $day . ' ' . $month . ' ' . $year;
}

/**
 * Calculate days difference
 */
function daysDifference($date1, $date2)
{
    $datetime1 = new DateTime($date1);
    $datetime2 = new DateTime($date2);
    $interval = $datetime1->diff($datetime2);
    return $interval->days;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user has admin role
 */
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Redirect function
 */
function redirect($url)
{
    header("Location: $url");
    exit();
}

/**
 * Display success message
 */
function showSuccess($message)
{
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Display error message
 */
function showError($message)
{
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Display warning message
 */
function showWarning($message)
{
    return '<div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Display info message
 */
function showInfo($message)
{
    return '<div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
}

/**
 * Auto-load classes
 */
spl_autoload_register(function ($class_name) {
    $directories = ['classes/', 'models/', 'controllers/'];

    foreach ($directories as $directory) {
        $file = $directory . $class_name . '.php';
        if (file_exists($file)) {
            require_once $file;
            break;
        }
    }
});

/**
 * Start session with security measures
 */
function startSecureSession()
{
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (
        isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)
    ) {
        session_unset();
        session_destroy();
        redirect('login.php?timeout=1');
    }

    $_SESSION['last_activity'] = time();
}


/**
 * CSRF Protection
 */
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * File upload handler
 */
function uploadFile($file, $uploadDir = UPLOAD_PATH)
{
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $allowedTypes = ALLOWED_EXTENSIONS;
    $maxSize = MAX_FILE_SIZE;

    // Check file size
    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds maximum allowed size.');
    }

    // Check file type
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedTypes)) {
        throw new Exception('File type not allowed.');
    }

    // Generate unique filename
    $filename = uniqid() . '.' . $fileExtension;
    $targetPath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $filename;
    } else {
        throw new Exception('Failed to upload file.');
    }
}

/**
 * Log activity
 */
function logActivity($action, $description = '')
{
    if (!file_exists('logs/')) {
        mkdir('logs/', 0755, true);
    }

    $logFile = 'logs/activity_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Guest';
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $logEntry = "[{$timestamp}] User: {$username} (ID: {$userId}) | IP: {$ip} | Action: {$action} | Description: {$description}" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Initialize secure session
startSecureSession();
