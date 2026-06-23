<?php
/**
 * Fundación Bon Sens - Router para servidor de desarrollo PHP
 *
 * Uso: php -S localhost:3000 router.php
 *
 * En producción usar Apache/Nginx con las reglas .htaccess incluidas.
 *
 * Carga las extensiones SQLite si se requieren en entornos temporales.
 */

// Cargar extensiones SQLite (necesario en este entorno)
$sqliteSo = '/tmp/php-sqlite-ext/usr/lib/php/modules/sqlite3.so';
$pdoSqliteSo = '/tmp/php-sqlite-ext/usr/lib/php/modules/pdo_sqlite.so';

if (file_exists($sqliteSo) && !extension_loaded('sqlite3')) {
    dl($sqliteSo);
}
if (file_exists($pdoSqliteSo) && !extension_loaded('pdo_sqlite')) {
    dl($pdoSqliteSo);
}

// Router
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

// Bloquear acceso directo a backend, router.php, archivos ocultos y de configuración
$blockedPatterns = [
    '#^/backend/#',
    '#^/router\.php$#',
    '#^/\.#', // Archivos ocultos (.env, .git, etc.)
    '#/(Dockerfile|docker-compose\.yml|fly\.toml|entrypoint\.sh|start\.sh)$#i',
    '#\.(env|db|sqlite|log|yml|toml|sh|md|json)$#i'
];

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $path)) {
        http_response_code(403);
        echo "Acceso denegado.\n";
        return true;
    }
}

// Servir archivos estáticos si existen
if ($path !== '/' && file_exists(__DIR__ . $path) && is_file(__DIR__ . $path)) {
    return false;
}

// API: redirigir al manejador de API
if (strpos($path, '/api/') === 0) {
    require __DIR__ . '/backend/api.php';
    return true;
}

// Admin SPA: servir index.html (las rutas se manejan via JS)
if (strpos($path, '/admin') === 0 && !file_exists(__DIR__ . $path)) {
    require __DIR__ . '/admin/index.html';
    return true;
}

// Mapear URLs limpias
$cleanUrls = [
    '/nosotros' => '/nosotros.html',
    '/que-hacemos' => '/que-hacemos.html',
    '/noticias' => '/noticias.html',
    '/como-ayudar' => '/como-ayudar.html',
    '/contacto' => '/contacto.html',
];

if (isset($cleanUrls[$path])) {
    require __DIR__ . $cleanUrls[$path];
    return true;
}

// Mapear detalle de noticias
if (preg_match('#^/noticias/([a-z0-9-]+)$#', $path)) {
    require __DIR__ . '/noticia.html';
    return true;
}

// Para todo lo demás, servir el archivo estático o index.html
$staticFile = __DIR__ . $path;
if ($path === '/' || !file_exists($staticFile)) {
    require __DIR__ . '/index.html';
} else {
    return false; // PHP built-in server sirve el archivo
}
