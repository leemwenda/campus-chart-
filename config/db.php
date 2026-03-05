<?php
ob_start(); // Prevent "headers already sent" errors
// ============================================================
// KCA CHART - Database Configuration
// Edit DB_PASS to match your XAMPP MySQL password (usually empty)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // Change if you set a MySQL password
define('DB_NAME', 'kcachart');
define('DB_PORT', 3306);

define('SITE_NAME', 'KCA Chat');

// Auto-detect SITE_URL so it works on any XAMPP path or server
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Walk up from /config to project root, then build URL
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $docRoot    = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    $rootDir    = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $subPath    = str_replace($docRoot, '', $rootDir);
    define('SITE_URL', rtrim($protocol . '://' . $host . $subPath, '/'));
}

define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');
define('MAX_UPLOAD_MB', 5);

// Session config
define('SESSION_LIFETIME', 86400); // 24 hours

class DB {
    private static ?PDO $instance = null;

    public static function connect(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed. Check config/db.php and ensure XAMPP MySQL is running.']));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function row(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function rows(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::connect()->lastInsertId();
    }

    public static function count(string $sql, array $params = []): int {
        return (int) self::query($sql, $params)->fetchColumn();
    }
}
