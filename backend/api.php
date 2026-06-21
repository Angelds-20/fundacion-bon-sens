<?php
/**
 * Fundación Bon Sens - API Router Principal
 *
 * Punto de entrada único para todas las rutas /api/*
 * Actúa como front controller: parsea la URL, autentica si es necesario,
 * y delega al handler correspondiente.
 *
 * Soporta:
 *   GET, POST, PUT, PATCH, DELETE
 *   Autenticación via Bearer token
 *   Rate limiting básico
 *   CORS
 *   JSON input/output
 */

// ─── Inicialización ──────────────────────────────────────────────
require_once __DIR__ . '/config.php';

// ─── CORS ────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Logger básico ───────────────────────────────────────────────
error_log(sprintf("[BonSens API] %s %s", $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']));

// ─── Parsear ruta ─────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);
// Normalizar: eliminar /api del inicio
$path = preg_replace('#^/api#', '', $path);
$path = rtrim($path, '/') ?: '/';

// ─── Autenticación ───────────────────────────────────────────────
function authenticate(): ?array {
    // Intentar obtener el header Authorization (Apache a veces no lo expone)
    $auth = $_SERVER['HTTP_AUTHORIZATION']
          ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
          ?? '';
    // Fallback: leer desde un header personalizado
    if (empty($auth) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    if (str_starts_with($auth, 'Bearer ')) {
        $token = substr($auth, 7);
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admin_sessions WHERE token = ? AND expires_at > ?");
        $stmt->execute([$token, date('Y-m-d H:i:s')]);
        return $stmt->fetch() ?: null;
    }
    return null;
}

function requireAuth(): array {
    $session = authenticate();
    if (!$session) {
        jsonError('No autorizado. Inicia sesión primero.', 401);
    }
    return $session;
}

// ─── Rate Limiting simple (en memoria) ───────────────────────────
$rateLimitFile = sys_get_temp_dir() . '/bonsens_ratelimit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'local');
$rateLimitWindow = 3600; // 1 hora
$rateLimitMax = 200;    // 200 requests/hora

function checkRateLimit(string $file, int $max, int $window): void {
    $now = time();
    $data = @file_get_contents($file);
    $requests = $data ? unserialize($data) : [];
    // Limpiar viejos
    $requests = array_filter($requests, fn($t) => $t > $now - $window);
    if (count($requests) >= $max) {
        jsonError('Demasiadas solicitudes. Intenta de nuevo en unos minutos.', 429);
    }
    $requests[] = $now;
    file_put_contents($file, serialize($requests));
}
checkRateLimit($rateLimitFile, $rateLimitMax, $rateLimitWindow);

// ─── Routing ─────────────────────────────────────────────────────
try {
    switch (true) {
        // ==========================================
        // ADMIN
        // ==========================================
        case $path === '/admin/login' && $method === 'POST':
            handleAdminLogin();
            break;

        case $path === '/admin/me' && $method === 'GET':
            $session = requireAuth();
            jsonResponse(['success' => true, 'admin' => ['username' => $session['username'] ?? ADMIN_USER]]);
            break;

        case $path === '/admin/dashboard' && $method === 'GET':
            requireAuth();
            handleDashboard();
            break;

        // ==========================================
        // CONTACT
        // ==========================================
        case $path === '/contact' && $method === 'POST':
            handleContactSubmit();
            break;

        case $path === '/contact' && $method === 'GET':
            requireAuth();
            handleContactList();
            break;

        case preg_match('#^/contact/(\d+)/read$#', $path, $m) && $method === 'PATCH':
            requireAuth();
            handleContactRead((int)$m[1]);
            break;

        case preg_match('#^/contact/(\d+)$#', $path, $m) && $method === 'DELETE':
            requireAuth();
            handleContactDelete((int)$m[1]);
            break;

        // ==========================================
        // NEWS
        // ==========================================
        case $path === '/news' && $method === 'GET':
            handleNewsList();
            break;

        case $path === '/news' && $method === 'POST':
            requireAuth();
            handleNewsCreate();
            break;

        case preg_match('#^/news/(\d+)$#', $path, $m) && $method === 'PUT':
            requireAuth();
            handleNewsUpdate((int)$m[1]);
            break;

        case preg_match('#^/news/(\d+)$#', $path, $m) && $method === 'DELETE':
            requireAuth();
            handleNewsDelete((int)$m[1]);
            break;

        case preg_match('#^/news/([a-z0-9-]+)$#', $path, $m) && $method === 'GET':
            handleNewsDetail($m[1]);
            break;

        // ==========================================
        // SUBSCRIBE
        // ==========================================
        case $path === '/subscribe' && $method === 'POST':
            handleSubscribe();
            break;

        case $path === '/subscribe' && $method === 'GET':
            requireAuth();
            handleSubscriberList();
            break;

        case preg_match('#^/subscribe/(\d+)$#', $path, $m) && $method === 'DELETE':
            requireAuth();
            handleSubscriberDelete((int)$m[1]);
            break;

        // ==========================================
        // DONATIONS
        // ==========================================
        case $path === '/donations' && $method === 'POST':
            handleDonationCreate();
            break;

        case $path === '/donations' && $method === 'GET':
            requireAuth();
            handleDonationList();
            break;

        case preg_match('#^/donations/pay/(\d+)$#', $path, $m) && $method === 'GET':
            handleDonationPay((int)$m[1]);
            break;

        case $path === '/donations/confirm' && $method === 'GET':
            handleDonationConfirm();
            break;

        // ==========================================
        // HEALTH / STATS
        // ==========================================
        case $path === '/health' && $method === 'GET':
            jsonResponse([
                'success' => true,
                'status' => 'ok',
                'timestamp' => date('c'),
                'php_version' => PHP_VERSION,
            ]);
            break;

        case $path === '/stats' && $method === 'GET':
            handlePublicStats();
            break;

        // ==========================================
        // 404
        // ==========================================
        default:
            jsonError('Endpoint no encontrado: ' . $method . ' ' . $path, 404);
    }
} catch (PDOException $e) {
    error_log("[BonSens DB Error] " . $e->getMessage());
    jsonError('Error interno del servidor.', 500);
} catch (Exception $e) {
    error_log("[BonSens Error] " . $e->getMessage());
    jsonError('Error interno del servidor.', 500);
}

// ================================================================
//  HANDLERS - ADMIN
// ================================================================

function handleAdminLogin(): void {
    $data = getJsonBody();
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $db = getDB();
        $token = bin2hex(random_bytes(32));
        $stmt = $db->prepare("INSERT INTO admin_sessions (token, username, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$token, $username, date('Y-m-d H:i:s', strtotime('+24 hours'))]);

        jsonResponse([
            'success' => true,
            'token' => $token,
            'admin' => ['username' => $username],
        ]);
    } else {
        jsonError('Credenciales inválidas.', 401);
    }
}

function handleDashboard(): void {
    $db = getDB();

    $totalMessages = $db->query("SELECT COUNT(*) as c FROM contact_messages")->fetch()['c'];
    $unreadMessages = $db->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0")->fetch()['c'];
    $totalSubscribers = $db->query("SELECT COUNT(*) as c FROM subscribers")->fetch()['c'];
    $totalNews = $db->query("SELECT COUNT(*) as c FROM news")->fetch()['c'];
    $publishedNews = $db->query("SELECT COUNT(*) as c FROM news WHERE is_published = 1")->fetch()['c'];
    $donationStats = $db->query("SELECT COUNT(*) as count, IFNULL(SUM(amount), 0) as total FROM donations WHERE status = 'completed'")->fetch();

    $recentMessages = $db->query("SELECT id, name, email, created_at, is_read FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();

    jsonResponse([
        'success' => true,
        'stats' => [
            'total_messages' => (int)$totalMessages,
            'unread_messages' => (int)$unreadMessages,
            'total_subscribers' => (int)$totalSubscribers,
            'total_news' => (int)$totalNews,
            'published_news' => (int)$publishedNews,
            'total_donations' => (int)$donationStats['count'],
            'total_donated' => (float)$donationStats['total'],
            'recent_messages' => $recentMessages,
        ],
    ]);
}

// ================================================================
//  HANDLERS - CONTACT
// ================================================================

function handleContactSubmit(): void {
    $data = getJsonBody();
    $name = sanitize($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $message = sanitize($data['message'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $subject = sanitize($data['subject'] ?? '');

    $errors = [];
    if (strlen($name) < 2) $errors[] = 'El nombre debe tener al menos 2 caracteres';
    if (!isValidEmail($email)) $errors[] = 'Email inválido';
    if (strlen($message) < 10) $errors[] = 'El mensaje debe tener al menos 10 caracteres';
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, $phone, $subject, $message]);
    $id = $db->lastInsertId();

    error_log("[BonSens] Nuevo contacto: {$name} <{$email}>");

    jsonResponse([
        'success' => true,
        'message' => 'Mensaje recibido correctamente. Te contactaremos pronto.',
        'data' => ['id' => (int)$id],
    ], 201);
}

function handleContactList(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $total = $db->query("SELECT COUNT(*) as c FROM contact_messages")->fetch()['c'];
    $unread = $db->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0")->fetch()['c'];
    $messages = $db->prepare("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $messages->execute([$limit, $offset]);

    jsonResponse([
        'success' => true,
        'messages' => $messages->fetchAll(),
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
        'unread' => (int)$unread,
    ]);
}

function handleContactRead(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}

function handleContactDelete(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Mensaje eliminado.']);
}

// ================================================================
//  HANDLERS - NEWS
// ================================================================

function handleNewsList(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 12)));
    $offset = ($page - 1) * $limit;
    $all = ($_GET['all'] ?? '') === '1';

    $where = $all ? '' : 'WHERE is_published = 1';
    $total = $db->query("SELECT COUNT(*) as c FROM news {$where}")->fetch()['c'];
    $news = $db->prepare("SELECT * FROM news {$where} ORDER BY published_at DESC LIMIT ? OFFSET ?");
    $news->execute([$limit, $offset]);

    jsonResponse([
        'success' => true,
        'news' => $news->fetchAll(),
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
    ]);
}

function handleNewsDetail(string $slugOrId): void {
    $db = getDB();
    if (is_numeric($slugOrId)) {
        $stmt = $db->prepare("SELECT * FROM news WHERE id = ?");
        $stmt->execute([(int)$slugOrId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM news WHERE slug = ?");
        $stmt->execute([$slugOrId]);
    }
    $news = $stmt->fetch();

    if (!$news) {
        jsonError('Noticia no encontrada.', 404);
    }

    // Noticias relacionadas
    $rel = $db->prepare("SELECT id, title, slug, excerpt, image_url FROM news WHERE category = ? AND id != ? AND is_published = 1 ORDER BY published_at DESC LIMIT 3");
    $rel->execute([$news['category'], $news['id']]);

    jsonResponse([
        'success' => true,
        'data' => $news,
        'related' => $rel->fetchAll(),
    ]);
}

function handleNewsCreate(): void {
    $data = getJsonBody();
    $title = sanitize($data['title'] ?? '');
    $slug = slugify($data['slug'] ?? ($data['title'] ?? ''));
    $excerpt = sanitize($data['excerpt'] ?? '');
    $content = $data['content'] ?? '';
    $image_url = sanitize($data['image_url'] ?? '');
    $category = sanitize($data['category'] ?? 'general');

    if (strlen($title) < 3) jsonError('El título debe tener al menos 3 caracteres');
    if (empty($slug)) jsonError('Slug inválido');

    $db = getDB();

    // Verificar slug único
    $check = $db->prepare("SELECT id FROM news WHERE slug = ?");
    $check->execute([$slug]);
    if ($check->fetch()) {
        jsonError('El slug ya está en uso.', 409);
    }

    $stmt = $db->prepare("INSERT INTO news (title, slug, excerpt, content, image_url, category) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $slug, $excerpt, $content, $image_url, $category]);
    $id = $db->lastInsertId();

    $news = $db->prepare("SELECT * FROM news WHERE id = ?");
    $news->execute([$id]);

    jsonResponse(['success' => true, 'data' => $news->fetch()], 201);
}

function handleNewsUpdate(int $id): void {
    $db = getDB();
    $check = $db->prepare("SELECT * FROM news WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        jsonError('Noticia no encontrada.', 404);
    }

    $data = getJsonBody();
    $allowed = ['title', 'slug', 'excerpt', 'content', 'image_url', 'category', 'is_published'];
    $updates = [];
    $values = [];

    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $updates[] = "{$field} = ?";
            $values[] = $field === 'is_published' ? (int)$data[$field] : sanitize((string)$data[$field]);
        }
    }

    if (empty($updates)) {
        jsonError('No hay campos para actualizar.');
    }

    $values[] = $id;
    $db->prepare("UPDATE news SET " . implode(', ', $updates) . " WHERE id = ?")->execute($values);

    $news = $db->prepare("SELECT * FROM news WHERE id = ?");
    $news->execute([$id]);
    jsonResponse(['success' => true, 'data' => $news->fetch()]);
}

function handleNewsDelete(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Noticia eliminada.']);
}

// ================================================================
//  HANDLERS - SUBSCRIBE
// ================================================================

function handleSubscribe(): void {
    $data = getJsonBody();
    $email = trim($data['email'] ?? '');
    $name = sanitize($data['name'] ?? '');

    if (!isValidEmail($email)) {
        jsonError('Email inválido.');
    }

    $db = getDB();

    // Verificar si ya existe
    $check = $db->prepare("SELECT id, is_active FROM subscribers WHERE email = ?");
    $check->execute([$email]);
    $existing = $check->fetch();

    if ($existing) {
        if (!$existing['is_active']) {
            $stmt = $db->prepare("UPDATE subscribers SET is_active = 1, name = ? WHERE id = ?");
            $stmt->execute([$name, $existing['id']]);
            jsonResponse(['success' => true, 'message' => '¡Has reactivado tu suscripción!'], 201);
        }
        jsonError('Este email ya está suscrito.', 409);
    }

    $stmt = $db->prepare("INSERT INTO subscribers (email, name) VALUES (?, ?)");
    $stmt->execute([$email, $name]);
    error_log("[BonSens] Nuevo suscriptor: {$email}");

    jsonResponse(['success' => true, 'message' => '¡Gracias por suscribirte!'], 201);
}

function handleSubscriberList(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $total = $db->query("SELECT COUNT(*) as c FROM subscribers")->fetch()['c'];
    $subs = $db->prepare("SELECT * FROM subscribers ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $subs->execute([$limit, $offset]);

    jsonResponse([
        'success' => true,
        'subscribers' => $subs->fetchAll(),
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
    ]);
}

function handleSubscriberDelete(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM subscribers WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['success' => true, 'message' => 'Suscriptor eliminado.']);
}

// ================================================================
//  HANDLERS - DONATIONS
// ================================================================

function handleDonationCreate(): void {
    $data = getJsonBody();
    $amount = (float)($data['amount'] ?? 0);

    if ($amount <= 0) {
        jsonError('Monto inválido.');
    }

    $db = getDB();
    $stmt = $db->prepare("INSERT INTO donations (donor_name, donor_email, amount, currency, payment_method, message) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        sanitize($data['donor_name'] ?? ''),
        trim($data['donor_email'] ?? ''),
        $amount,
        sanitize($data['currency'] ?? 'CLP'),
        sanitize($data['payment_method'] ?? 'webpay'),
        sanitize($data['message'] ?? ''),
    ]);
    $id = $db->lastInsertId();

    jsonResponse([
        'success' => true,
        'message' => 'Donación registrada. Serás redirigido al portal de pago.',
        'data' => [
            'id' => (int)$id,
            'payment_url' => BASE_URL . "/api/donations/pay/{$id}",
        ],
    ], 201);
}

function handleDonationList(): void {
    $db = getDB();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $total = $db->query("SELECT COUNT(*) as c FROM donations")->fetch()['c'];
    $stats = $db->query("SELECT COUNT(*) as count, IFNULL(SUM(amount), 0) as total_amount FROM donations WHERE status = 'completed'")->fetch();
    $donations = $db->prepare("SELECT * FROM donations ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $donations->execute([$limit, $offset]);

    jsonResponse([
        'success' => true,
        'donations' => $donations->fetchAll(),
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'pages' => (int)ceil($total / $limit),
        'stats' => $stats,
    ]);
}

function handleDonationPay(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM donations WHERE id = ?");
    $stmt->execute([$id]);
    $donation = $stmt->fetch();

    if (!$donation) {
        http_response_code(404);
        echo '<h1>Donación no encontrada</h1>';
        exit;
    }

    $amountFormatted = number_format((float)$donation['amount'], 0, ',', '.');

    // Intentar Webpay Plus real
    $webpay = createWebpayTransaction($id, $donation['amount']);
    if ($webpay && isset($webpay['url']) && isset($webpay['token'])) {
        ?>
        <!DOCTYPE html>
        <html lang="es-CL">
        <head>
            <meta charset="UTF-8">
            <title>Redirigiendo a Webpay - Fundación Bon Sens</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center p-4" onload="document.forms['webpayForm'].submit();">
            <div class="bg-white rounded-2xl shadow-xl p-8 max-w-sm w-full text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-[#CC0000] mx-auto mb-4"></div>
                <h1 class="text-xl font-bold text-gray-800 mb-2">Redirigiendo a Webpay Seguro</h1>
                <p class="text-gray-500 text-sm mb-4">Estamos conectando con el portal de Transbank para tu pago a través de Banco Estado u otras entidades...</p>
                <p class="text-xs text-gray-400">Por favor, no cierres esta ventana.</p>

                <form name="webpayForm" action="<?= htmlspecialchars($webpay['url']) ?>" method="POST" class="hidden">
                    <input type="hidden" name="token_ws" value="<?= htmlspecialchars($webpay['token']) ?>" />
                    <button type="submit">Continuar</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // Fallback: si no hay internet o falla la API, usar portal de simulación local
    ?>
    <!DOCTYPE html>
    <html lang="es-CL">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Portal de Pago (Simulación) - Fundación Bon Sens</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
        <style>body{font-family:Inter,sans-serif}</style>
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md w-full text-center">
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Portal de Pago Simulado</h1>
            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-credit-card text-2xl text-blue-600"></i>
            </div>
            <p class="text-gray-600 mb-2">Donación de <strong>$<?= $amountFormatted ?></strong></p>
            <p class="text-sm text-gray-500 mb-6">Método: <?= htmlspecialchars($donation['payment_method']) ?></p>
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-yellow-800 text-sm mb-6">
                ⚠️ Conexión con Transbank falló (modo offline). Presiona el botón para simular una transacción aprobada de Banco Estado.
            </div>
            <div class="space-y-2">
                <a href="/api/donations/confirm?token_ws=mock_token_<?= $id ?>" class="block bg-green-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-green-700 transition-all text-center">Simular Pago Exitoso</a>
                <a href="/api/donations/confirm" class="block bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 transition-all text-center">Simular Pago Rechazado</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function handleDonationConfirm(): void {
    $token = $_GET['token_ws'] ?? $_GET['TBK_TOKEN'] ?? '';
    $db = getDB();

    $status = 'failed';
    $amount = 0;
    $donorName = '';
    $transactionId = '';
    $errorMessage = '';

    if (!empty($token)) {
        if (str_starts_with($token, 'mock_token_')) {
            // Simulación local exitosa
            $id = (int)str_replace('mock_token_', '', $token);
            $status = 'completed';
            $transactionId = 'MOCK-TX-' . rand(100000, 999999);

            // Actualizar DB
            $stmt = $db->prepare("UPDATE donations SET status = 'completed', transaction_id = ? WHERE id = ?");
            $stmt->execute([$transactionId, $id]);

            // Obtener monto y nombre
            $stmt = $db->prepare("SELECT amount, donor_name FROM donations WHERE id = ?");
            $stmt->execute([$id]);
            $donation = $stmt->fetch();
            $amount = $donation['amount'] ?? 0;
            $donorName = $donation['donor_name'] ?? 'Anónimo';
        } else {
            // Confirmación real con Transbank Webpay
            $webpay = confirmWebpayTransaction($token);
            if ($webpay && isset($webpay['status'])) {
                $buyOrder = $webpay['buy_order'] ?? ''; // BONSENS-ID
                $id = (int)str_replace('BONSENS-', '', $buyOrder);
                $amount = $webpay['amount'] ?? 0;
                $transactionId = $webpay['authorization_code'] ?? ($webpay['vci'] ?? 'MOCK-TX');

                if ($webpay['status'] === 'AUTHORIZED' && ($webpay['response_code'] ?? -1) === 0) {
                    $status = 'completed';
                    $stmt = $db->prepare("UPDATE donations SET status = 'completed', transaction_id = ? WHERE id = ?");
                    $stmt->execute([$transactionId, $id]);

                    // Obtener datos del donante
                    $stmt = $db->prepare("SELECT donor_name FROM donations WHERE id = ?");
                    $stmt->execute([$id]);
                    $donor = $stmt->fetch();
                    $donorName = $donor['donor_name'] ?? 'Anónimo';
                } else {
                    $status = 'failed';
                    $stmt = $db->prepare("UPDATE donations SET status = 'failed' WHERE id = ?");
                    $stmt->execute([$id]);
                    $errorMessage = 'Transacción rechazada por el banco.';
                }
            } else {
                $errorMessage = 'No se pudo confirmar el pago con Transbank.';
            }
        }
    } else {
        $errorMessage = 'La transacción fue cancelada por el usuario.';
    }

    // Renderizar página premium de confirmación
    ?>
    <!DOCTYPE html>
    <html lang="es-CL">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirmación de Donación | Fundación Bon Sens</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
        <style>
            body { font-family: 'Inter', sans-serif; }
            .bg-custom { background: linear-gradient(135deg, #CC0000 0%, #990000 100%); }
        </style>
    </head>
    <body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-md w-full">
            <div class="bg-custom p-6 text-white text-center">
                <img src="/logo2.PNG" alt="Fundación Bon Sens" class="h-12 mx-auto bg-white rounded-xl p-1 mb-4" />
                <h1 class="text-2xl font-bold">Estado de Transacción</h1>
            </div>

            <div class="p-8 text-center">
                <?php if ($status === 'completed'): ?>
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-circle-check text-4xl text-green-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">¡Donación Recibida!</h2>
                    <p class="text-gray-600 mb-6 font-medium">Muchas gracias por tu generosidad, <strong><?= htmlspecialchars($donorName) ?></strong>. Tu aporte nos ayuda a seguir apoyando en Talca.</p>

                    <div class="bg-gray-50 rounded-xl p-4 text-left text-sm text-gray-600 mb-6 space-y-2">
                        <p class="flex justify-between border-b border-gray-150 pb-2"><span>Monto:</span> <strong class="text-gray-900">$<?= number_format($amount, 0, ',', '.') ?> CLP</strong></p>
                        <p class="flex justify-between border-b border-gray-150 pb-2"><span>Código Autorización:</span> <strong class="text-gray-900"><?= htmlspecialchars($transactionId) ?></strong></p>
                        <p class="flex justify-between border-b border-gray-150 pb-2"><span>Portal de Pago:</span> <strong class="text-gray-900">Banco Estado (Webpay)</strong></p>
                        <p class="flex justify-between"><span>Fecha:</span> <strong class="text-gray-900"><?= date('d/m/Y H:i') ?></strong></p>
                    </div>
                <?php else: ?>
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fa-solid fa-circle-xmark text-4xl text-red-600"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-800 mb-2">Pago No Completado</h2>
                    <p class="text-gray-600 mb-6"><?= htmlspecialchars($errorMessage ?: 'Hubo un inconveniente al procesar tu pago.') ?></p>

                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-left text-sm text-yellow-800 mb-6">
                        <p class="font-semibold mb-1">¿Qué puedes hacer?</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>Verifica tu saldo o límites de tarjeta.</li>
                            <li>Revisa si tus datos de Banco Estado son correctos.</li>
                            <li>Intenta nuevamente seleccionando otro medio de pago.</li>
                        </ul>
                    </div>
                <?php endif; ?>

                <a href="/" class="inline-block bg-[#CC0000] text-white px-8 py-3 rounded-lg font-semibold hover:brightness-90 transition-all w-full text-center">Volver al Inicio</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

function createWebpayTransaction(int $donationId, float $amount): ?array {
    $commerceCode = '597055555532';
    $apiKeySecret = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
    $url = 'https://webpay3gint.transbank.cl/rs-commerce-integration/api/webpay/v1.2/transactions';

    $body = json_encode([
        'buy_order' => 'BONSENS-' . $donationId,
        'session_id' => 'SESS-' . uniqid(),
        'amount' => (int)$amount,
        'return_url' => BASE_URL . '/api/donations/confirm'
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Tbk-Api-Key-Id: ' . $commerceCode,
        'Tbk-Api-Key-Secret: ' . $apiKeySecret,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return null;
}

function confirmWebpayTransaction(string $token): ?array {
    $commerceCode = '597055555532';
    $apiKeySecret = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';
    $url = 'https://webpay3gint.transbank.cl/rs-commerce-integration/api/webpay/v1.2/transactions/' . $token;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Tbk-Api-Key-Id: ' . $commerceCode,
        'Tbk-Api-Key-Secret: ' . $apiKeySecret,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return null;
}

// ================================================================
//  HANDLERS - PUBLIC STATS
// ================================================================

function handlePublicStats(): void {
    $db = getDB();
    $totalNews = $db->query("SELECT COUNT(*) as c FROM news WHERE is_published = 1")->fetch()['c'];
    $latestNews = $db->query("SELECT id, title, slug, excerpt, published_at FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3")->fetchAll();

    jsonResponse([
        'success' => true,
        'stats' => [
            'total_news' => (int)$totalNews,
            'latest_news' => $latestNews,
        ],
    ]);
}
