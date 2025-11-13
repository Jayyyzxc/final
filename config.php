<?php
// ===========================
// Barangay System Configuration
// ===========================

// Start session once at the very beginning
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Error Reporting (for development)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Constants
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'barangay_system');

define('APP_NAME', 'Barangay Demographic Profiling System');

// ===========================
// Database Connection (mysqli)
// ===========================
if (!function_exists('get_db_connection')) {
    function get_db_connection(): mysqli {
        static $conn = null;
        if ($conn === null) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                die('Database connection failed: ' . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');
        }
        return $conn;
    }
}

// Create global connection instance
$conn = get_db_connection();

// ===========================
// Authentication Helpers
// ===========================
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return !empty($_SESSION['user']);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header("Location: login.php");
            exit();
        }
    }
}

// Adjust this to match roles you actually use, e.g. 'super_admin' or 'official'
if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin() {
        requireLogin();
        if (empty($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'super_admin') {
            header("Location: dashboard.php");
            exit();
        }
    }
}

// ===========================
// Utility Functions
// ===========================
if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header("Location: $url");
        exit();
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput(string $input): string {
        global $conn;
        return htmlspecialchars(trim($conn->real_escape_string($input)), ENT_QUOTES, 'UTF-8');
    }
}

// ===========================
// Data Retrieval Functions (mysqli)
// ===========================
if (!function_exists('getResidentCount')) {
    function getResidentCount(): int {
        global $conn;
        $result = $conn->query("SELECT COUNT(*) FROM residents");
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getHouseholdCount')) {
    function getHouseholdCount(): int {
        global $conn;
        $result = $conn->query("SELECT COUNT(*) FROM households");
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getUpcomingEventsCount')) {
    function getUpcomingEventsCount(): int {
        global $conn;
        $result = $conn->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
        return $result ? (int)$result->fetch_row()[0] : 0;
    }
}

if (!function_exists('getAgeDistribution')) {
    function getAgeDistribution(): array {
        global $conn;
        $data = [];
        $query = "
            SELECT 
                CASE 
                    WHEN age < 18 THEN '0-17'
                    WHEN age BETWEEN 18 AND 24 THEN '18-24'
                    WHEN age BETWEEN 25 AND 34 THEN '25-34'
                    WHEN age BETWEEN 35 AND 44 THEN '35-44'
                    WHEN age BETWEEN 45 AND 59 THEN '45-59'
                    ELSE '60+'
                END AS age_group,
                COUNT(*) as count
            FROM residents
            GROUP BY age_group
            ORDER BY age_group
        ";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getGenderDistribution')) {
    function getGenderDistribution(): array {
        global $conn;
        $data = [];
        $result = $conn->query("SELECT gender, COUNT(*) as count FROM residents GROUP BY gender");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getEmploymentStatus')) {
    function getEmploymentStatus(): array {
        global $conn;
        $data = [];
        $result = $conn->query("SELECT employment_status, COUNT(*) as count FROM residents GROUP BY employment_status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

if (!function_exists('getUpcomingEvents')) {
    function getUpcomingEvents(int $limit = 5): array {
        global $conn;
        $data = [];
        $stmt = $conn->prepare("SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT ?");
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}

// ===========================
// CSRF Protection
// ===========================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ===========================
// Error Handling
// ===========================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false; // Let the PHP internal handler handle it
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
?>
