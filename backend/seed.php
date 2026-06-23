<?php
/**
 * Fundación Bon Sens - Seed de datos de ejemplo
 *
 * Uso: php backend/seed.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Acceso denegado. Este script solo puede ejecutarse desde la terminal (CLI).\n";
    exit;
}

require_once __DIR__ . '/config.php';

echo "🌱 Sembrando datos...\n\n";

$db = getDB();

// Limpiar datos existentes
$db->exec("DELETE FROM news");
$db->exec("DELETE FROM contact_messages");
$db->exec("DELETE FROM subscribers");

// Insertar noticias de ejemplo
$news = [
    [
        'Operativo Solidario Junio 2026',
        'operativo-solidario-junio-2026',
        'Gran jornada de apoyo con 150 raciones de comida entregadas a personas en situación de calle en Talca.',
        '<p>El pasado sábado 10 de junio realizamos nuestro operativo solidario mensual en distintos puntos de la comuna de Talca.</p><p>Gracias a la participación de más de 25 voluntarios, logramos entregar 150 raciones de comida caliente, 80 kits de higiene y 50 frazadas para enfrentar las bajas temperaturas del invierno.</p><p>Agradecemos profundamente a cada persona que hizo posible esta jornada. ¡Seguimos uniendo manos y transformando vidas!</p>',
        'https://images.unsplash.com/photo-1593113598332-cd288d649433?auto=format&fit=crop&w=800&q=65',
        'operativos',
    ],
    [
        'Campaña Invierno con Abrigo 2026',
        'campana-invierno-con-abrigo-2026',
        'Recolectamos frazadas, ropa térmica y calzado para las personas en situación de calle de Talca.',
        '<p>Lanzamos nuestra campaña anual "Invierno con Abrigo" para reunir donaciones que nos permitan enfrentar la temporada más fría del año.</p><p>Necesitamos: frazadas en buen estado, chaquetas térmicas, calcetines, gorros de lana y calzado cerrado. Cada aporte cuenta para que nadie pase frío este invierno.</p><p>Puedes hacer tus donaciones en nuestro punto de acopio en Línea 9 Sur N°270, Talca, de lunes a viernes de 10:00 a 18:00 hrs.</p>',
        'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&w=800&q=65',
        'campañas',
    ],
    [
        'Jornada de Apoyo Hospitalario',
        'jornada-apoyo-hospitalario-junio',
        'Acompañamiento y entrega de insumos a pacientes y familiares en el Hospital Regional de Talca.',
        '<p>Realizamos una nueva jornada de apoyo hospitalario en el Hospital Regional de Talca, donde entregamos colaciones, kits de higiene y palabras de aliento a quienes esperan atenciones médicas.</p><p>Nuestro equipo de voluntarios recorrió las áreas de espera, compartiendo un momento de escucha y contención con pacientes y cuidadores.</p><p>La salud también se construye con solidaridad y presencia humana.</p>',
        'https://images.unsplash.com/photo-1559027615-cd4628902d4a?auto=format&fit=crop&w=800&q=65',
        'hospitalario',
    ],
    [
        'Canasta Familiar Solidaria',
        'canasta-familiar-solidaria-mayo',
        'Entrega de 60 canastas familiares a hogares vulnerables de la comuna de Talca.',
        '<p>Gracias al apoyo de nuestros colaboradores, logramos entregar 60 canastas familiares con alimentos no perecibles a familias en situación de vulnerabilidad de diversos sectores de Talca.</p><p>Cada canasta incluyó: arroz, fideos, legumbres, aceite, azúcar, leche y conservas. Una ayuda concreta que marca la diferencia en la mesa de muchas familias.</p><p>Seguiremos trabajando para ampliar esta red de apoyo solidario.</p>',
        'https://images.unsplash.com/photo-1509099836639-18ba1795216d?auto=format&fit=crop&w=800&q=65',
        'operativos',
    ],
];

$stmt = $db->prepare("INSERT INTO news (title, slug, excerpt, content, image_url, category, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($news as $i => $item) {
    $daysAgo = count($news) - $i;
    $publishedAt = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
    $stmt->execute([$item[0], $item[1], $item[2], $item[3], $item[4], $item[5], $publishedAt]);
    echo "  ✓ Noticia: {$item[0]}\n";
}

echo "\n✅ Seed completado. " . count($news) . " noticias creadas.\n";
