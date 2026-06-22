<?php
// ============================================================
// API Proveedores / Cotizaciones de Compras
// ============================================================
require_once 'auth.php';

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

$action  = $_GET['action'] ?? '';
$input   = json_decode(file_get_contents('php://input'), true) ?? [];


// ────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────

/**
 * Calcula la ruta física y la URL base de uploads/proveedores/
 * según entorno (local o producción).
 */
function pathsProveedores() {
    $host    = $_SERVER['HTTP_HOST'] ?? '';
    $wp_root = substr(__DIR__, 0, strpos(__DIR__, 'wp-content'));

    if (strpos($host, 'tabolango.cl') !== false) {
        return [
            'fisica' => '/home/tabolang/public_html/uploads/proveedores/',
            'url'    => 'https://tabolango.cl/uploads/proveedores/',
        ];
    }
    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $script    = dirname($_SERVER['SCRIPT_NAME']);
    $base_url  = substr($script, 0, strpos($script, 'wp-content'));
    return [
        'fisica' => $wp_root . 'uploads/proveedores/',
        'url'    => $protocolo . '://' . $host . rtrim($base_url, '/') . '/uploads/proveedores/',
    ];
}

/**
 * Recibe una imagen como string base64 (con o sin prefijo data:URI),
 * la decodifica y guarda en uploads/proveedores/. Devuelve la URL final
 * o null si no hay imagen o falla.
 *
 * Este enfoque evita los problemas de multipart/form-data que tiene
 * LocalWP/nginx con uploads pequeños — todo va dentro del body JSON.
 */
function guardarFotoBase64($b64) {
    if (empty($b64) || !is_string($b64)) return null;

    // Quita prefijo "data:image/jpeg;base64," si viene
    if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,(.+)$/i', $b64, $m)) {
        $ext  = strtolower($m[1] === 'jpg' ? 'jpg' : $m[1]);
        $data = base64_decode($m[2], true);
    } else {
        $ext  = 'jpg';
        $data = base64_decode($b64, true);
    }
    if ($data === false || strlen($data) < 100) return null;

    $paths = pathsProveedores();
    if (!is_dir($paths['fisica'])) @mkdir($paths['fisica'], 0777, true);

    $nombre = 'cot_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (file_put_contents($paths['fisica'] . $nombre, $data) === false) {
        error_log('[api_proveedores] no se pudo escribir foto en ' . $paths['fisica']);
        return null;
    }
    return $paths['url'] . $nombre;
}

function fechaVencimiento($validez) {
    // diaria=hoy, semanal=+7, mensual=+30
    $hoy = new DateTime('today');
    switch ($validez) {
        case 'diaria':  /* hoy mismo */ break;
        case 'mensual': $hoy->modify('+30 days'); break;
        case 'semanal':
        default:        $hoy->modify('+7 days'); break;
    }
    return $hoy->format('Y-m-d');
}

function ok($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

function fail($msg, $code = 400) {
    http_response_code($code);
    // Logueamos info útil para depurar (solo se ve en el log, no en la respuesta).
    error_log('[api_proveedores] FAIL ' . $code . ': ' . $msg
        . ' | action=' . ($_GET['action'] ?? $_POST['action'] ?? '-')
        . ' | post_keys=' . implode(',', array_keys($_POST ?? []))
        . ' | files_keys=' . implode(',', array_keys($_FILES ?? []))
        . ' | content_length=' . ($_SERVER['CONTENT_LENGTH'] ?? '0'));
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ────────────────────────────────────────────────────────────
// PROVEEDORES
// ────────────────────────────────────────────────────────────
if ($action === 'list_proveedores') {
    // Trae proveedores activos + conteo de productos cotizados + último precio
    $sql = "
        SELECT p.id_proveedor, p.nombre, p.rut, p.contacto, p.telefono,
               p.email, p.ciudad, p.activo,
               (SELECT COUNT(*) FROM proveedor_productos pp
                 WHERE pp.id_proveedor = p.id_proveedor AND pp.activo = 1) AS n_productos,
               (SELECT MAX(pc.fecha_cotizacion)
                  FROM proveedor_cotizaciones pc
                  JOIN proveedor_productos pp ON pp.id = pc.id_proveedor_producto
                 WHERE pp.id_proveedor = p.id_proveedor) AS ultima_cotizacion
          FROM proveedores p
         WHERE p.activo = 1
         ORDER BY p.nombre ASC
    ";
    $res = $conn->query($sql);
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode($list);
    exit;
}

if ($action === 'get_proveedor') {
    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) fail("ID inválido");
    $stmt = $conn->prepare("SELECT * FROM proveedores WHERE id_proveedor = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $prov = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$prov) fail("Proveedor no encontrado", 404);
    echo json_encode($prov);
    exit;
}

if ($action === 'add_proveedor') {
    $nombre    = trim($input['nombre']    ?? '');
    $rut       = trim($input['rut']       ?? '');
    $contacto  = trim($input['contacto']  ?? '');
    $telefono  = trim($input['telefono']  ?? '');
    $email     = trim($input['email']     ?? '');
    $ciudad    = trim($input['ciudad']    ?? '');
    $direccion = trim($input['direccion'] ?? '');
    $notas     = trim($input['notas']     ?? '');

    if ($nombre === '') fail("El nombre del proveedor es obligatorio");

    $stmt = $conn->prepare(
        "INSERT INTO proveedores (nombre, rut, contacto, telefono, email, ciudad, direccion, notas, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->bind_param("ssssssss", $nombre, $rut, $contacto, $telefono, $email, $ciudad, $direccion, $notas);
    if (!$stmt->execute()) fail($conn->error, 500);
    $id = $conn->insert_id;
    $stmt->close();
    ok(['id_proveedor' => $id, 'nombre' => $nombre]);
}

if ($action === 'update_proveedor') {
    $id        = intval($input['id_proveedor'] ?? 0);
    $nombre    = trim($input['nombre']    ?? '');
    $rut       = trim($input['rut']       ?? '');
    $contacto  = trim($input['contacto']  ?? '');
    $telefono  = trim($input['telefono']  ?? '');
    $email     = trim($input['email']     ?? '');
    $ciudad    = trim($input['ciudad']    ?? '');
    $direccion = trim($input['direccion'] ?? '');
    $notas     = trim($input['notas']     ?? '');

    if ($id <= 0 || $nombre === '') fail("Datos incompletos");

    $stmt = $conn->prepare(
        "UPDATE proveedores
            SET nombre=?, rut=?, contacto=?, telefono=?, email=?, ciudad=?, direccion=?, notas=?
          WHERE id_proveedor=?"
    );
    $stmt->bind_param("ssssssssi", $nombre, $rut, $contacto, $telefono, $email, $ciudad, $direccion, $notas, $id);
    if (!$stmt->execute()) fail($conn->error, 500);
    $stmt->close();
    ok();
}

// ────────────────────────────────────────────────────────────
// PRODUCTOS DEL PROVEEDOR + COTIZACIONES
// ────────────────────────────────────────────────────────────
if ($action === 'list_productos') {
    // Productos de un proveedor, con su último precio y estado de vigencia.
    // Si hay varios registros con el mismo (producto + calibre), solo se
    // muestra el más reciente (último creado / con la cotización más nueva).
    $id_prov = intval($_GET['id_proveedor'] ?? 0);
    if ($id_prov <= 0) fail("Proveedor inválido");

    $sql = "
        SELECT pp.id, pp.producto_nombre, pp.variedad, pp.calibre, pp.unidad,
               pp.formato, pp.id_producto_link, pp.activo, pp.fecha_creacion,
               p.producto AS producto_link_nombre,
               p.icono    AS producto_link_icono,
               last_cot.precio, last_cot.validez, last_cot.valido_hasta,
               last_cot.notas AS cot_notas, last_cot.fecha_cotizacion,
               last_cot.foto_url AS cot_foto_url,
               last_cot.disponible_desde, last_cot.disponible_hasta,
               CASE
                   WHEN last_cot.valido_hasta IS NULL THEN 'sin_precio'
                   WHEN last_cot.valido_hasta < CURDATE() THEN 'vencido'
                   WHEN last_cot.valido_hasta = CURDATE() THEN 'vence_hoy'
                   ELSE 'vigente'
               END AS estado_vigencia,
               CASE
                   WHEN last_cot.disponible_desde IS NULL AND last_cot.disponible_hasta IS NULL THEN 'sin_info'
                   WHEN last_cot.disponible_desde IS NOT NULL AND last_cot.disponible_desde > CURDATE() THEN 'futuro'
                   WHEN last_cot.disponible_hasta IS NOT NULL AND last_cot.disponible_hasta < CURDATE() THEN 'terminado'
                   ELSE 'disponible'
               END AS estado_disponibilidad,
               (SELECT COUNT(*) FROM proveedor_cotizaciones pc2
                 WHERE pc2.id_proveedor_producto = pp.id) AS n_cotizaciones
          FROM proveedor_productos pp
          LEFT JOIN productos p ON pp.id_producto_link = p.id_producto
          LEFT JOIN (
              SELECT pc.*
                FROM proveedor_cotizaciones pc
                INNER JOIN (
                    SELECT id_proveedor_producto, MAX(fecha_cotizacion) AS maxf
                      FROM proveedor_cotizaciones
                  GROUP BY id_proveedor_producto
                ) ult ON pc.id_proveedor_producto = ult.id_proveedor_producto
                     AND pc.fecha_cotizacion       = ult.maxf
          ) last_cot ON last_cot.id_proveedor_producto = pp.id
         WHERE pp.id_proveedor = ? AND pp.activo = 1
         ORDER BY pp.producto_nombre ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_prov);
    $stmt->execute();
    $res = $stmt->get_result();
    $raw = [];
    while ($r = $res->fetch_assoc()) $raw[] = $r;
    $stmt->close();

    // Dedup por (producto_nombre + calibre), normalizado: nos quedamos con
    // el que tenga la cotización más reciente; si no, con el creado más nuevo.
    $dedup = [];
    foreach ($raw as $row) {
        $key = mb_strtolower(trim($row['producto_nombre']))
             . '|' . mb_strtolower(trim($row['calibre'] ?? ''));
        $rowActivity = $row['fecha_cotizacion'] ?? $row['fecha_creacion'] ?? '1900-01-01';
        if (!isset($dedup[$key])) {
            $dedup[$key] = $row;
            continue;
        }
        $cur = $dedup[$key];
        $curActivity = $cur['fecha_cotizacion'] ?? $cur['fecha_creacion'] ?? '1900-01-01';
        if ($rowActivity > $curActivity) $dedup[$key] = $row;
    }
    echo json_encode(array_values($dedup));
    exit;
}

if ($action === 'precio_en_fecha') {
    // Devuelve la cotización vigente del producto en una fecha dada
    // (la más reciente cuya fecha_cotizacion <= fecha consultada).
    $id_pp = intval($_GET['id_proveedor_producto'] ?? 0);
    $fecha = $_GET['fecha'] ?? '';
    if ($id_pp <= 0)                       fail("Producto inválido");
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) fail("Fecha inválida (YYYY-MM-DD)");

    $stmt = $conn->prepare(
        "SELECT precio, validez, valido_hasta, disponible_desde, disponible_hasta,
                notas, foto_url, registrado_por, fecha_cotizacion
           FROM proveedor_cotizaciones
          WHERE id_proveedor_producto = ?
            AND DATE(fecha_cotizacion) <= ?
          ORDER BY fecha_cotizacion DESC
          LIMIT 1"
    );
    $stmt->bind_param("is", $id_pp, $fecha);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$r) {
        echo json_encode(['encontrado' => false]);
        exit;
    }
    // estado: vigente / vencido en la fecha consultada
    $estado = ($r['valido_hasta'] >= $fecha) ? 'vigente' : 'vencido';
    echo json_encode(['encontrado' => true, 'estado' => $estado, 'cotizacion' => $r]);
    exit;
}

if ($action === 'add_producto') {
    // Crea un producto en el catálogo del proveedor + (opcional) primera cotización
    $id_prov         = intval($input['id_proveedor']      ?? 0);
    $id_link         = !empty($input['id_producto_link']) ? intval($input['id_producto_link']) : null;
    $nombre          = trim($input['producto_nombre']     ?? '');
    $variedad        = trim($input['variedad']            ?? '');
    $calibre         = trim($input['calibre']             ?? '');
    $unidad          = trim($input['unidad']              ?? '');
    $formato         = trim($input['formato']             ?? '');
    $precio_inicial  = isset($input['precio'])  ? floatval($input['precio']) : 0;
    $validez         = $input['validez'] ?? 'semanal';
    $notas_cot       = trim($input['notas']               ?? '');
    $disp_desde      = !empty($input['disponible_desde']) ? $input['disponible_desde'] : null;
    $disp_hasta      = !empty($input['disponible_hasta']) ? $input['disponible_hasta'] : null;

    if ($id_prov <= 0 || $nombre === '') fail("Proveedor y producto son obligatorios");

    $stmt = $conn->prepare(
        "INSERT INTO proveedor_productos
            (id_proveedor, id_producto_link, producto_nombre, variedad, calibre, unidad, formato, activo)
         VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
    );
    $stmt->bind_param("iisssss", $id_prov, $id_link, $nombre, $variedad, $calibre, $unidad, $formato);
    if (!$stmt->execute()) fail($conn->error, 500);
    $id_pp = $conn->insert_id;
    $stmt->close();

    // Si vino precio inicial, guarda la primera cotización (con foto opcional)
    if ($precio_inicial > 0) {
        $valido_hasta = fechaVencimiento($validez);
        $usuario      = $email_auth ?? '';
        $foto_url     = guardarFotoBase64($input['foto_b64'] ?? '');
        $stmt2 = $conn->prepare(
            "INSERT INTO proveedor_cotizaciones
                (id_proveedor_producto, precio, validez, valido_hasta, disponible_desde, disponible_hasta, notas, foto_url, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt2->bind_param("idsssssss", $id_pp, $precio_inicial, $validez, $valido_hasta, $disp_desde, $disp_hasta, $notas_cot, $foto_url, $usuario);
        $stmt2->execute();
        $stmt2->close();
    }
    ok(['id' => $id_pp]);
}

if ($action === 'add_cotizacion') {
    // Guarda un nuevo precio en el historial (con foto y disponibilidad opcionales)
    $id_pp      = intval($input['id_proveedor_producto'] ?? 0);
    $precio     = floatval($input['precio'] ?? 0);
    $validez    = $input['validez'] ?? 'semanal';
    $notas      = trim($input['notas'] ?? '');
    $disp_desde = !empty($input['disponible_desde']) ? $input['disponible_desde'] : null;
    $disp_hasta = !empty($input['disponible_hasta']) ? $input['disponible_hasta'] : null;

    if ($id_pp <= 0 || $precio <= 0) fail("Producto y precio son obligatorios");
    if (!in_array($validez, ['diaria','semanal','mensual'], true)) $validez = 'semanal';

    $valido_hasta = fechaVencimiento($validez);
    $usuario      = $email_auth ?? '';
    $foto_url     = guardarFotoBase64($input['foto_b64'] ?? '');

    $stmt = $conn->prepare(
        "INSERT INTO proveedor_cotizaciones
            (id_proveedor_producto, precio, validez, valido_hasta, disponible_desde, disponible_hasta, notas, foto_url, registrado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("idsssssss", $id_pp, $precio, $validez, $valido_hasta, $disp_desde, $disp_hasta, $notas, $foto_url, $usuario);
    if (!$stmt->execute()) fail($conn->error, 500);
    $stmt->close();
    ok(['valido_hasta' => $valido_hasta, 'foto_url' => $foto_url]);
}

if ($action === 'get_historial') {
    // Historial completo de un producto del proveedor (precios anteriores)
    $id_pp = intval($_GET['id_proveedor_producto'] ?? 0);
    if ($id_pp <= 0) fail("Producto inválido");
    $stmt = $conn->prepare(
        "SELECT id, precio, validez, valido_hasta, disponible_desde, disponible_hasta,
                notas, foto_url, registrado_por, fecha_cotizacion
           FROM proveedor_cotizaciones
          WHERE id_proveedor_producto = ?
          ORDER BY fecha_cotizacion DESC"
    );
    $stmt->bind_param("i", $id_pp);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) $list[] = $r;
    $stmt->close();
    echo json_encode($list);
    exit;
}

if ($action === 'delete_producto') {
    $id_pp = intval($input['id'] ?? 0);
    if ($id_pp <= 0) fail("Producto inválido");
    // Soft-delete (activo = 0). La cascada conserva el historial.
    $stmt = $conn->prepare("UPDATE proveedor_productos SET activo = 0 WHERE id = ?");
    $stmt->bind_param("i", $id_pp);
    if (!$stmt->execute()) fail($conn->error, 500);
    $stmt->close();
    ok();
}

// ────────────────────────────────────────────────────────────
// BÚSQUEDA INVERSA: por producto → todos los proveedores
// ────────────────────────────────────────────────────────────
if ($action === 'search_by_product') {
    // Agrupa por nombre normalizado de producto y muestra todos los
    // proveedores que lo ofrecen, ordenados por último precio (más barato arriba).
    $q = trim($_GET['q'] ?? '');

    $sql = "
        SELECT pp.id,
               pp.producto_nombre,
               pp.variedad, pp.calibre, pp.unidad, pp.formato,
               pr.id_proveedor, pr.nombre AS proveedor_nombre,
               pr.contacto AS proveedor_contacto, pr.telefono AS proveedor_telefono,
               last_cot.precio, last_cot.validez, last_cot.valido_hasta,
               last_cot.disponible_desde, last_cot.disponible_hasta,
               CASE
                   WHEN last_cot.valido_hasta IS NULL THEN 'sin_precio'
                   WHEN last_cot.valido_hasta < CURDATE() THEN 'vencido'
                   WHEN last_cot.valido_hasta = CURDATE() THEN 'vence_hoy'
                   ELSE 'vigente'
               END AS estado_vigencia,
               CASE
                   WHEN last_cot.disponible_desde IS NULL AND last_cot.disponible_hasta IS NULL THEN 'sin_info'
                   WHEN last_cot.disponible_desde IS NOT NULL AND last_cot.disponible_desde > CURDATE() THEN 'futuro'
                   WHEN last_cot.disponible_hasta IS NOT NULL AND last_cot.disponible_hasta < CURDATE() THEN 'terminado'
                   ELSE 'disponible'
               END AS estado_disponibilidad
          FROM proveedor_productos pp
          JOIN proveedores pr ON pr.id_proveedor = pp.id_proveedor AND pr.activo = 1
          LEFT JOIN (
              SELECT pc.*
                FROM proveedor_cotizaciones pc
                INNER JOIN (
                    SELECT id_proveedor_producto, MAX(fecha_cotizacion) AS maxf
                      FROM proveedor_cotizaciones
                  GROUP BY id_proveedor_producto
                ) ult ON pc.id_proveedor_producto = ult.id_proveedor_producto
                     AND pc.fecha_cotizacion       = ult.maxf
          ) last_cot ON last_cot.id_proveedor_producto = pp.id
         WHERE pp.activo = 1
    ";
    if ($q !== '') {
        $sql  .= " AND (pp.producto_nombre LIKE ? OR pp.variedad LIKE ?) ";
    }
    $sql .= " ORDER BY pp.producto_nombre ASC,
                       (last_cot.precio IS NULL), last_cot.precio ASC ";

    if ($q !== '') {
        $like = "%{$q}%";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $like, $like);
    } else {
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) $list[] = $r;
    $stmt->close();
    echo json_encode($list);
    exit;
}

// ────────────────────────────────────────────────────────────
// AUXILIAR: lista de productos del catálogo (para vincular)
// ────────────────────────────────────────────────────────────
if ($action === 'productos_catalogo') {
    $res = $conn->query("SELECT id_producto, producto, variedad, icono
                           FROM productos
                          WHERE activo = 1
                          ORDER BY producto ASC");
    $list = [];
    if ($res) while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode($list);
    exit;
}

fail("Acción no reconocida: " . htmlspecialchars($action));
