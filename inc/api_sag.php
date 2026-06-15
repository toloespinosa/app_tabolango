<?php
// ============================================================
// API SAG — gestiona los 2 documentos de Autorización SAG
// usados en la sección "Mis Vehículos".
// ============================================================
require_once 'auth.php'; // expone $conn, $email_auth, $rol_final

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// Admin(1) y Editor(2) pueden subir; cualquiera puede leer.
$is_admin_editor = ($rol_final === 1 || $rol_final === 2);

function ok($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ────────────────────────────────────────────────────────────
// Helper: rutas físicas y URL pública según entorno.
// Mismo patrón usado por procesar_facturacion.php / api_acuse_recibo.php.
// ────────────────────────────────────────────────────────────
function pathsSag() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $raiz = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');

    if (strpos($host, 'erp.tabolango.cl') !== false
        || strpos($raiz, 'erp.tabolango.cl') !== false) {
        $public = str_replace('erp.tabolango.cl', 'public_html', $raiz);
    } else {
        $public = $raiz;
    }
    if ($public === '') {
        // Fallback para CLI / cron
        $public = dirname(__DIR__, 4);
    }

    $rutaFisica = rtrim($public, '/') . '/uploads/sag/';

    if (strpos($host, 'tabolango.cl') !== false) {
        $urlBase = 'https://tabolango.cl/uploads/sag/';
    } else {
        $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $script    = dirname($_SERVER['SCRIPT_NAME']);
        $base_url  = substr($script, 0, strpos($script, 'wp-content'));
        $urlBase   = $protocolo . '://' . $host . rtrim($base_url, '/') . '/uploads/sag/';
    }

    return ['fisica' => $rutaFisica, 'url' => $urlBase];
}

// ────────────────────────────────────────────────────────────
// Acción: obtener URLs actuales de los 2 documentos
// ────────────────────────────────────────────────────────────
if ($action === 'get_docs') {
    $res = $conn->query("SELECT doc_1_url, doc_2_url, updated_at, updated_by
                           FROM sag_documentos WHERE id = 1 LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) {
        ok(['doc_1_url' => null, 'doc_2_url' => null, 'updated_at' => null, 'updated_by' => null]);
    }
    ok($row);
}

// ────────────────────────────────────────────────────────────
// Acción: subir un documento (admin/editor solamente)
// Recibe en JSON: { slot: 1|2, foto_b64: "data:application/pdf;base64,..." | "data:image/..." }
// ────────────────────────────────────────────────────────────
if ($action === 'upload_doc') {
    if (!$is_admin_editor) fail("Sin permisos para subir", 403);

    $slot = intval($input['slot'] ?? 0);
    if ($slot !== 1 && $slot !== 2) fail("Slot inválido (debe ser 1 o 2)");

    $b64 = $input['archivo_b64'] ?? '';
    if (empty($b64) || !is_string($b64)) fail("No se recibió archivo");

    // Detectar tipo y decodificar. Aceptamos PDF y formatos de imagen comunes.
    if (preg_match('/^data:([\w\-\.\/\+]+);base64,(.+)$/i', $b64, $m)) {
        $mime = strtolower($m[1]);
        $data = base64_decode($m[2], true);
    } else {
        $mime = 'application/octet-stream';
        $data = base64_decode($b64, true);
    }
    if ($data === false || strlen($data) < 100) fail("Archivo inválido o vacío");

    $extPorMime = [
        'application/pdf'                                                            => 'pdf',
        'image/jpeg'                                                                 => 'jpg',
        'image/jpg'                                                                  => 'jpg',
        'image/png'                                                                  => 'png',
        'image/webp'                                                                 => 'webp',
        'application/msword'                                                         => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'    => 'docx',
    ];
    $ext = $extPorMime[$mime] ?? 'pdf';

    $paths = pathsSag();
    if (!is_dir($paths['fisica'])) @mkdir($paths['fisica'], 0777, true);

    $nombre = 'sag_doc' . $slot . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (file_put_contents($paths['fisica'] . $nombre, $data) === false) {
        fail("No se pudo escribir el archivo en el servidor", 500);
    }

    $url = $paths['url'] . $nombre;
    $col = $slot === 1 ? 'doc_1_url' : 'doc_2_url';
    $stmt = $conn->prepare("UPDATE sag_documentos
                               SET $col = ?, updated_by = ?
                             WHERE id = 1");
    $stmt->bind_param("ss", $url, $email_auth);
    if (!$stmt->execute()) fail($conn->error, 500);
    $stmt->close();

    ok(['url' => $url, 'slot' => $slot]);
}

fail("Acción no reconocida: " . htmlspecialchars($action));
