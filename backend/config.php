<?php
/**
 * Fundación Bon Sens - Configuración de Base de Datos
 *
 * Proporciona una conexión PDO a SQLite con schema completo.
 * Crea automáticamente las tablas al inicializar.
 *
 * Modo de uso:
 *   require_once __DIR__ . '/config.php';
 *   $db = getDB();
 *   $rows = $db->query("SELECT * FROM ...")->fetchAll();
 */

define('DB_PATH', getenv('DB_PATH') ?: __DIR__ . '/../data/bonsens.db');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost:3000');

// Config admin desde variables de entorno
define('ADMIN_USER', getenv('ADMIN_USERNAME') ?: 'admin');
define('ADMIN_PASS', getenv('ADMIN_PASSWORD') ?: 'admin123');
define('SECRET_KEY', getenv('SESSION_SECRET') ?: 'bonsens_secret_dev_2026');

/**
 * Obtiene (y crea si es necesario) la conexión a la base de datos.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dbUrl = getenv('DATABASE_URL');
        if ($dbUrl) {
            // PostgreSQL Connection
            $parsedUrl = parse_url($dbUrl);
            $host = $parsedUrl['host'];
            $port = $parsedUrl['port'] ?? 5432;
            $user = $parsedUrl['user'];
            $pass = $parsedUrl['pass'] ?? '';
            $dbname = ltrim($parsedUrl['path'], '/');
            
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            // SQLite Connection
            $dir = dirname(DB_PATH);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec('PRAGMA journal_mode=WAL');
            $pdo->exec('PRAGMA foreign_keys=ON');
        }

        createTables($pdo);
        seedAdmin($pdo);
    }
    return $pdo;
}

/**
 * Crea todas las tablas del sistema.
 */
function createTables(PDO $pdo): void {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'pgsql') {
        // PostgreSQL DDL
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id SERIAL PRIMARY KEY,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_messages (
                id SERIAL PRIMARY KEY,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT DEFAULT '',
                subject TEXT DEFAULT '',
                message TEXT NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subscribers (
                id SERIAL PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                name TEXT DEFAULT '',
                is_active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS news (
                id SERIAL PRIMARY KEY,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                excerpt TEXT DEFAULT '',
                content TEXT DEFAULT '',
                image_url TEXT DEFAULT '',
                category TEXT DEFAULT 'general',
                is_published INTEGER DEFAULT 1,
                published_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS donations (
                id SERIAL PRIMARY KEY,
                donor_name TEXT DEFAULT '',
                donor_email TEXT DEFAULT '',
                amount REAL NOT NULL,
                currency TEXT DEFAULT 'CLP',
                payment_method TEXT DEFAULT 'webpay',
                status TEXT DEFAULT 'pending',
                transaction_id TEXT DEFAULT '',
                message TEXT DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_sessions (
                id SERIAL PRIMARY KEY,
                token TEXT NOT NULL UNIQUE,
                username TEXT NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } else {
        // SQLite DDL (Original)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at DATETIME DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contact_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT DEFAULT '',
                subject TEXT DEFAULT '',
                message TEXT NOT NULL,
                is_read INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subscribers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                name TEXT DEFAULT '',
                is_active INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS news (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                excerpt TEXT DEFAULT '',
                content TEXT DEFAULT '',
                image_url TEXT DEFAULT '',
                category TEXT DEFAULT 'general',
                is_published INTEGER DEFAULT 1,
                published_at DATETIME DEFAULT (datetime('now')),
                created_at DATETIME DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS donations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                donor_name TEXT DEFAULT '',
                donor_email TEXT DEFAULT '',
                amount REAL NOT NULL,
                currency TEXT DEFAULT 'CLP',
                payment_method TEXT DEFAULT 'webpay',
                status TEXT DEFAULT 'pending',
                transaction_id TEXT DEFAULT '',
                message TEXT DEFAULT '',
                created_at DATETIME DEFAULT (datetime('now'))
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                token TEXT NOT NULL UNIQUE,
                username TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT (datetime('now'))
            )
        ");
    }
}

/**
 * Crea el usuario admin por defecto si no existe.
 */
function seedAdmin(PDO $pdo): void {
    $count = $pdo->query("SELECT COUNT(*) as c FROM admin_users")->fetch()['c'];
    if ($count == 0) {
        $hash = password_hash(ADMIN_PASS, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([ADMIN_USER, $hash]);
        error_log("[BonSens] Admin creado por defecto: " . ADMIN_USER);
    }
}

/**
 * Envía una respuesta JSON y termina.
 */
function jsonResponse(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envía una respuesta de error.
 */
function jsonError(string $message, int $status = 400): never {
    jsonResponse(['success' => false, 'error' => $message], $status);
}

/**
 * Valida un email.
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitiza texto contra XSS básico.
 */
function sanitize(?string $str): string {
    if ($str === null) return '';
    return htmlspecialchars(trim($str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Obtiene el body JSON de la request.
 */
function getJsonBody(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/**
 * Genera el slug a partir de un título.
 */
function slugify(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = str_replace(
        ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'],
        ['a','e','i','o','u','u','n','a','e','i','o','u','u','n'],
        $text
    );
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

/**
 * Formatea fecha para mostrar (hace X tiempo).
 */
function timeAgo(string $dateStr): string {
    $time = strtotime($dateStr);
    if (!$time) return '';
    $diff = time() - $time;
    if ($diff < 60) return 'Ahora';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('d/m/Y', $time);
}
