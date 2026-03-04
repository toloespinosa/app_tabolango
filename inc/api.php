<?php
// 1. CARGAR AUTH (Maneja conexión DB, Socket, CORS y sesión usuario)
require_once 'auth.php';

// Configuración de errores para JSON limpio
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 3. Cabeceras CORS (Una sola vez al principio)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejo de solicitud OPTIONS (Pre-flight del navegador)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 4. Cargar Entorno (Autoload + WordPress + Auth)
$theme_root = dirname(__DIR__);
$autoload_path = $theme_root . '/vendor/autoload.php';

// Cargar Composer
if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    ob_clean(); // Limpiamos cualquier echo anterior
    die(json_encode(["status" => "error", "message" => "Falta autoload.php", "debug_path" => $autoload_path]));
}

// Cargar WordPress
if (!defined('ABSPATH')) {
    $wp_load_path = $theme_root;
    while (!file_exists($wp_load_path . '/wp-load.php')) {
        $wp_load_path = dirname($wp_load_path);
        if ($wp_load_path == '/' || $wp_load_path == '.') break;
    }
    if (file_exists($wp_load_path . '/wp-load.php')) {
        require_once($wp_load_path . '/wp-load.php');
    }
}

global $wpdb; // WordPress DB disponible

// Integración Auth (Tu archivo de autenticación)
// Asegúrate de que auth.php no tenga 'echo' o espacios en blanco fuera de <?php
require_once __DIR__ . '/auth.php'; 
// Variables disponibles: $conn, $rol_final, $email_auth

// Cargar sistema de notificaciones
require_once __DIR__ . '/notifications.php';


// --- VARIABLES GLOBALES ---
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$es_admin = ($rol_final === 1); 

// --- [ACCIÓN] INFO USUARIO ---
if ($action == 'get_user_info') {
    $stmt_u = $conn->prepare("SELECT nombre, apellido FROM app_usuarios WHERE email = ? LIMIT 1");
    $stmt_u->bind_param("s", $email_auth);
    $stmt_u->execute();
    $userData = $stmt_u->get_result()->fetch_assoc();
    
    $nombreShow = $userData ? trim($userData['nombre'] . ' ' . $userData['apellido']) : $email_auth;
    
    $response = [
        "status" => "success", 
        "nombre" => $nombreShow, 
        "is_admin" => $es_admin,
        "rol_id" => $rol_final
    ];
    
    ob_clean(); echo json_encode($response); exit;
}

// --- [ACCIÓN] GUARDAR TOKEN FCM ---
if ($action == 'save_fcm_token') {
    $email = $_POST['email'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($email) || empty($token)) {
        ob_clean(); echo json_encode(["status" => "error", "message" => "Datos incompletos"]); exit;
    }

    $stmt_check = $conn->prepare("SELECT id FROM app_fcm_tokens WHERE email = ? AND token = ?");
    $stmt_check->bind_param("ss", $email, $token);
    $stmt_check->execute();
    $existe = $stmt_check->get_result()->fetch_assoc();

    if ($existe) {
        $stmt_upd = $conn->prepare("UPDATE app_fcm_tokens SET fecha_registro = NOW() WHERE id = ?");
        $stmt_upd->bind_param("i", $existe['id']);
        $stmt_upd->execute();
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO app_fcm_tokens (email, token) VALUES (?, ?)");
        $stmt_ins->bind_param("ss", $email, $token);
        $stmt_ins->execute();
    }
    ob_clean(); echo json_encode(["status" => "success", "message" => "Dispositivo registrado"]); exit;
}

// --- [ACCIÓN] OBTENER PEDIDO POR TOKEN ---
if ($action == 'get_order_by_token') {
    $token = $_GET['token'] ?? '';
    
    $sql = "SELECT P.*, PR.calibre, PR.formato, PR.Variedad, PR.unidad AS unidad_real, 
                   PR.color_diferenciador, C.lat_despacho, C.lng_despacho, C.direccion
            FROM pedidos_activos P
            LEFT JOIN productos PR ON P.id_producto = PR.id_producto
            LEFT JOIN clientes C ON P.cliente = C.cliente
            WHERE P.qr_token = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $row['is_admin_user'] = $es_admin; 
        $data[] = $row;
    }
    
    ob_clean(); echo json_encode($data); exit;
}

// --- [ACCIÓN] GUARDAR NUEVO PEDIDO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $productos_raw = $_POST['producto'] ?? ''; 
    $cantidades_raw = $_POST['cantidad'] ?? '';
    $lista_prod = explode(' | ', $productos_raw);
    $lista_cant = explode(' | ', $cantidades_raw);
    $cliente_input = $_POST['cliente'] ?? '';
    $fecha_despacho = $_POST['fecha_entrega'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    $creado_por_email = $_POST['usuario_wp'] ?? 'Sistema'; // Email del usuario

    // 1. Obtener nombre real del cliente
    $nombre_cliente_final = $cliente_input; 
    $stmt_c = $conn->prepare("SELECT cliente FROM clientes WHERE id_interno = ? OR cliente = ? LIMIT 1");
    $stmt_c->bind_param("ss", $cliente_input, $cliente_input);
    $stmt_c->execute();
    $res_c = $stmt_c->get_result()->fetch_assoc();
    if ($res_c) { $nombre_cliente_final = $res_c['cliente']; }
    
    // 2. Obtener nombre real del usuario creador
    $creado_por_nombre = $creado_por_email;
    $stmt_u = $conn->prepare("SELECT nombre, apellido FROM app_usuarios WHERE email = ? LIMIT 1");
    $stmt_u->bind_param("s", $creado_por_email);
    $stmt_u->execute();
    $uData = $stmt_u->get_result()->fetch_assoc();
    if($uData) $creado_por_nombre = trim($uData['nombre'].' '.$uData['apellido']);

    // 3. Generar ID Pedido
    $qr_token = bin2hex(random_bytes(16));
    $res_max = $conn->query("SELECT id_interno FROM pedidos_activos ORDER BY id_interno DESC LIMIT 1");
    $row_max = $res_max->fetch_assoc();
    $nuevo_num = ($row_max) ? intval($row_max['id_interno']) + 1 : 1;
    $id_pedido_grupo = "TBL-" . date("dmy") . "-" . str_pad($nuevo_num, 4, "0", STR_PAD_LEFT);

    // 4. Insertar Productos
    $insertados = 0;
    foreach ($lista_prod as $index => $id_p) {
        $id_p = trim($id_p);
        $cant_p = isset($lista_cant[$index]) ? floatval(trim($lista_cant[$index])) : 0;
        if (empty($id_p) || $cant_p <= 0) continue;

        $stmt_p = $conn->prepare("SELECT * FROM productos WHERE id_producto = ?");
        $stmt_p->bind_param("s", $id_p);
        $stmt_p->execute();
        $info = $stmt_p->get_result()->fetch_assoc();

        if ($info) {
            $p_u = ($info['precio_actual'] > 0) ? $info['precio_actual'] : ($info['precio_por_kilo'] ?? 0);
            $c_u = ($info['costo_actual'] > 0) ? $info['costo_actual'] : ($info['costo_por_kilo'] ?? 0);
            $tot_v = $cant_p * $p_u; 
            $tot_c = $cant_p * $c_u; 
            $margen = $tot_v - $tot_c;

            $sql = "INSERT INTO pedidos_activos (id_pedido, creado_por, cliente, producto, cantidad, precio_unitario, costo_unitario, total_venta, total_costo, margen, fecha_despacho, observaciones, qr_token, id_producto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_ins = $conn->prepare($sql);
            $stmt_ins->bind_param("ssssddddddssss", $id_pedido_grupo, $creado_por_nombre, $nombre_cliente_final, $info['producto'], $cant_p, $p_u, $c_u, $tot_v, $tot_c, $margen, $fecha_despacho, $observaciones, $qr_token, $id_p);
            
            if ($stmt_ins->execute()) $insertados++;
        }
    }

    if ($insertados > 0) {
        // Notificar Admin
        $url_app = "https://app.tabolango.cl/admin/pedidos"; 
        enviarNotificacionFCM(
            'jandres@tabolango.cl', 
            "📦 Nuevo Pedido: $id_pedido_grupo", 
            "Cliente: $nombre_cliente_final. Por: $creado_por_nombre",
            $url_app
        );
        ob_clean(); echo json_encode(["status" => "success", "pedido" => $id_pedido_grupo]);
    } else {
        ob_clean(); echo json_encode(["status" => "error", "message" => "No se insertaron productos"]);
    }
    exit;
}

// --- [ACCIÓN] OBTENER CLIENTES ---
if ($action == 'get_clients') {
    $res = $conn->query("SELECT cliente, logo FROM clientes WHERE activo = 1 ORDER BY cliente ASC");
    $data = []; while($row = $res->fetch_assoc()) $data[] = $row;
    ob_clean(); echo json_encode($data); exit;
}

// --- [ACCIÓN] OBTENER PRODUCTOS ---
if ($action === 'get_products') {
        
        // 🔥 1. MATAR EL CACHÉ DEL NAVEGADOR
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        // 2. Identificar al usuario que hace la petición
        $email_solicitante = isset($_GET['wp_user']) ? trim($conn->real_escape_string($_GET['wp_user'])) : '';
        
        // 3. Por defecto, asumimos que NO puede ver precios
        $puede_ver_precios = false;
        
        // Variable para diagnóstico
        $rol_detectado = 0; 

        if (!empty($email_solicitante)) {
            // Buscamos el rol_id del usuario
            $sql_rol = "SELECT rol_id FROM app_usuario_roles WHERE usuario_email = '$email_solicitante' ORDER BY rol_id ASC LIMIT 1";
            $res_rol = $conn->query($sql_rol);
            
            if ($res_rol && $res_rol->num_rows > 0) {
                $row_rol = $res_rol->fetch_assoc();
                $rol_detectado = (int)$row_rol['rol_id'];
                
                // Permitir acceso al dinero a: Admin(1), Editor(2), Vendedor(4)
                if (in_array($rol_detectado, [1, 2, 4])) {
                    $puede_ver_precios = true;
                }
            }
        }

        // 4. Consultar los productos activos
        $sql_prod = "SELECT * FROM productos WHERE activo = 1";
        $res_prod = $conn->query($sql_prod);
        
        $productos = [];
        while ($row = $res_prod->fetch_assoc()) {
            
            // 🔥 BLINDAJE: Si NO tiene permisos, destruimos las variables de dinero
            if (!$puede_ver_precios) {
                unset($row['precio_actual']);
                unset($row['costo_actual']);
                unset($row['precio_por_kilo']);
                unset($row['costo_por_kilo']);
            }
            
            // LÍNEA DE DIAGNÓSTICO (Opcional, puedes borrarla después)
            // Agregamos esto para que en la pestaña Red veas exactamente qué rol leyó el servidor
            $row['debug_rol_servidor'] = $rol_detectado;

            $productos[] = $row;
        }

        // 5. Enviar JSON al navegador
        echo json_encode($productos);
        exit;
    }

// --- [ACCIÓN] LISTAR PEDIDOS ACTIVOS ---
if ($action == 'get_active_orders') {
    $sql = "SELECT P.*, PR.unidad AS unidad_real, PR.calibre, PR.formato, PR.Variedad, PR.color_diferenciador 
            FROM pedidos_activos P
            LEFT JOIN productos PR ON P.id_producto = PR.id_producto
            WHERE P.estado != 'Entregado' ORDER BY P.fecha_despacho ASC";
    
    $res = $conn->query($sql);
    $data = []; 
    while($row = $res->fetch_assoc()) { 
        $row['unidad'] = $row['unidad_db'] ?: 'Un'; 
        $row['color_diferenciador'] = $row['color_diferenciador'] ?: '#0F4B29'; 
        $row['is_admin_user'] = $rol_final; 
        $data[] = $row; 
    }
    
    ob_clean(); echo json_encode($data); exit;
}

// --- [ACCIÓN] OBTENER ENTREGADOS ---
if ($action == 'get_entregados') {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 9;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Aquí podrías implementar caché si tienes una función leerCache() disponible
    // Por ahora lo hacemos directo para asegurar funcionamiento

    // A. Construir consulta con filtros dinámicos
    $sqlBase = "FROM pedidos_activos P LEFT JOIN productos PR ON P.id_producto = PR.id_producto WHERE P.estado = 'Entregado'";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $term = "%" . $search . "%";
        $sqlBase .= " AND (P.cliente LIKE ? OR P.id_pedido LIKE ? OR PR.producto LIKE ? OR P.numero_factura LIKE ?)";
        array_push($params, $term, $term, $term, $term);
        $types .= "ssss";
    }

    // B. Obtener IDs únicos paginados
    $sqlIds = "SELECT P.id_pedido $sqlBase GROUP BY P.id_pedido ORDER BY P.fecha_despacho DESC LIMIT ? OFFSET ?";
    array_push($params, $limit, $offset);
    $types .= "ii";

    $stmtIds = $conn->prepare($sqlIds);
    if (!empty($params)) $stmtIds->bind_param($types, ...$params);
    $stmtIds->execute();
    $resIds = $stmtIds->get_result();
    
    $ids_pedidos = [];
    while($row = $resIds->fetch_assoc()) {
        $ids_pedidos[] = "'" . $conn->real_escape_string($row['id_pedido']) . "'";
    }

    if (empty($ids_pedidos)) { 
        ob_clean(); echo json_encode([]); exit; 
    }

    // C. Obtener Detalles
    $ids_string = implode(",", $ids_pedidos);
    $sqlFinal = "SELECT P.*, PR.calibre, PR.formato, PR.Variedad, PR.unidad AS unidad_real, PR.color_diferenciador 
                 FROM pedidos_activos P LEFT JOIN productos PR ON P.id_producto = PR.id_producto 
                 WHERE P.id_pedido IN ($ids_string) ORDER BY P.fecha_despacho DESC";

    $resFinal = $conn->query($sqlFinal);
    $data = [];
    while($row = $resFinal->fetch_assoc()) {
        $row['unidad'] = (!empty($row['unidad_real'])) ? $row['unidad_real'] : 'Un';
        $row['fecha_fmt'] = $row['fecha_despacho'] ? date("d/m/Y", strtotime($row['fecha_despacho'])) : 'S/F';
        $data[] = $row;
    }
    
    ob_clean(); echo json_encode($data); exit;
}

$conn->close();
?>