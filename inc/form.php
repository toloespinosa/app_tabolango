<?php
// 1. CARGAR AUTH (Maneja conexión DB, Socket, CORS y sesión usuario)
require_once 'auth.php';

// Configuración de errores para JSON limpio
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Carga de notificaciones (Ruta Absoluta y Segura)
$ruta_notis = __DIR__ . '/notifications.php';
if (file_exists($ruta_notis)) {
    include_once($ruta_notis);
} else {
    error_log("TABOLANGO ALERT: No se encontró notifications.php en " . __DIR__);
}

// --------------------------------------------------
// LÓGICA DE USUARIO (Resolver Nombre Real)
// --------------------------------------------------
$usuario = $email_auth; 
$nombre_final = $usuario; 
$es_admin = false;
$user_data = null;

if (!empty($usuario)) {
    $stmt_u = $conn->prepare("SELECT nombre, apellido, cargo FROM app_usuarios WHERE (email = ? OR user_login = ?) AND activo = 1 LIMIT 1");
    if ($stmt_u) {
        $stmt_u->bind_param("ss", $usuario, $usuario);
        $stmt_u->execute();
        $user_data = $stmt_u->get_result()->fetch_assoc();
        $stmt_u->close();

        if ($user_data) {
            $full_name = trim($user_data['nombre'] . ' ' . $user_data['apellido']);
            if (!empty($full_name)) {
                $nombre_final = $full_name;
            }
            $es_admin = (strtolower($user_data['cargo'] ?? '') === 'administrador' || strtolower($user_data['cargo'] ?? '') === 'admin');
        }
    }
}

$action = $_GET['action'] ?? '';

// =================================================================================
// ENDPOINTS
// =================================================================================

// --- 1. INFO USUARIO ---
if ($action === 'get_user_info') {
    echo json_encode([
        "status" => $user_data ? "success" : "error",
        "nombre" => $nombre_final,
        "email"  => $usuario,
        "is_admin" => $es_admin
    ]);
    exit;
}

// --- 2. CONSULTAR PRECIO (GET) ---
if ($action === 'get_price_by_client') {
    $cliente_id  = $_GET['cliente'] ?? '';
    $producto_id = $_GET['producto'] ?? '';

    if (!$cliente_id || !$producto_id) {
        echo json_encode(["precio" => 0, "tramos" => []]);
        exit;
    }

    $precio_final = 0;
    $origen = "base";

    // A. Obtener Tipo de Cliente
    $stmt = $conn->prepare("SELECT tipo_cliente FROM clientes WHERE id_interno = ? LIMIT 1");
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $cli = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // B. Buscar Precio Diferenciado (Categoría)
    if ($cli && !empty($cli['tipo_cliente'])) {
        $stmt2 = $conn->prepare("SELECT precio FROM productos_precios_categorias WHERE id_producto = ? AND id_categoria_cliente = ? ORDER BY fecha_actualizacion DESC LIMIT 1");
        $stmt2->bind_param("si", $producto_id, $cli['tipo_cliente']);
        $stmt2->execute();
        $res_cat = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($res_cat && $res_cat['precio'] > 0) {
            $precio_final = floatval($res_cat['precio']);
            $origen = "categoria";
        }
    }

    // C. Buscar Precio Base (Producto) si no hay especial
    if ($precio_final == 0) {
        $stmt3 = $conn->prepare("SELECT precio_actual, precio_por_kilo FROM productos WHERE id_producto = ? LIMIT 1");
        $stmt3->bind_param("s", $producto_id);
        $stmt3->execute();
        $p = $stmt3->get_result()->fetch_assoc();
        $stmt3->close();

        $precio_final = ($p && $p['precio_actual'] > 0) ? $p['precio_actual'] : ($p['precio_por_kilo'] ?? 0);
    }

    // D. Buscar Tramos de Descuento (NUEVO)
    $tramos = [];
    $stmt_tr = $conn->prepare("SELECT cantidad_minima, porcentaje_descuento FROM productos_tramos_descuento WHERE id_producto = ? ORDER BY cantidad_minima DESC");
    if ($stmt_tr) {
        $stmt_tr->bind_param("s", $producto_id);
        $stmt_tr->execute();
        $res_tr = $stmt_tr->get_result();
        while ($row = $res_tr->fetch_assoc()) {
            $tramos[] = [
                "min" => floatval($row['cantidad_minima']), 
                "pct" => floatval($row['porcentaje_descuento'])
            ];
        }
        $stmt_tr->close();
    }

    echo json_encode(["precio" => floatval($precio_final), "origen" => $origen, "tramos" => $tramos]);
    exit;
}

// --- 3. GUARDAR PEDIDO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $productos = explode(' | ', $_POST['producto'] ?? '');
    $cantidades = explode(' | ', $_POST['cantidad'] ?? '');
    $cliente_id = $_POST['cliente'] ?? '';
    $fecha = $_POST['fecha_entrega'] ?? '';
    $obs = $_POST['observaciones'] ?? '';

    // Validar Cliente
    $stmt_c = $conn->prepare("SELECT cliente, tipo_cliente FROM clientes WHERE id_interno = ? LIMIT 1");
    $stmt_c->bind_param("i", $cliente_id);
    $stmt_c->execute();
    $cli = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();

    if (!$cli) {
        echo json_encode(["status"=>"error","message"=>"Cliente inválido"]);
        exit;
    }

    $cliente_nombre = $cli['cliente'];
    $tipo_cliente = $cli['tipo_cliente'];
    $qr_token = bin2hex(random_bytes(16));
    $creado_por = $nombre_final;

    // Generar ID Pedido
    $res_max = $conn->query("SELECT id_interno FROM pedidos_activos ORDER BY id_interno DESC LIMIT 1");
    $next_id = ($res_max && $row = $res_max->fetch_assoc()) ? ($row['id_interno'] + 1) : 1;
    $id_pedido = "TBL-" . date("dmy") . "-" . str_pad($next_id, 4, "0", STR_PAD_LEFT);

    $ok = 0;
    $total_pedido_acumulado = 0;

    // Preparamos INSERT con las nuevas columnas
    $stmt_ins = $conn->prepare("INSERT INTO pedidos_activos (id_pedido, creado_por, cliente, id_interno_cliente, producto, cantidad, precio_unitario, costo_unitario, total_venta, total_costo, margen, fecha_despacho, observaciones, qr_token, id_producto, precio_original, porcentaje_descuento, monto_descuento) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    foreach ($productos as $i => $id_producto) {
        $cant = floatval($cantidades[$i] ?? 0);
        if (empty($id_producto) || $cant <= 0) continue;

        // Datos del Producto
        $stmt_p = $conn->prepare("SELECT * FROM productos WHERE id_producto = ?");
        $stmt_p->bind_param("s", $id_producto);
        $stmt_p->execute();
        $prod = $stmt_p->get_result()->fetch_assoc();
        $stmt_p->close();

        if (!$prod) continue;

        // Determinar Precio Base
        $precio_u = ($prod['precio_actual'] > 0) ? $prod['precio_actual'] : $prod['precio_por_kilo'];
        
        if (!empty($tipo_cliente)) {
            $stmt_pr = $conn->prepare("SELECT precio FROM productos_precios_categorias WHERE id_producto = ? AND id_categoria_cliente = ? ORDER BY fecha_actualizacion DESC LIMIT 1");
            $stmt_pr->bind_param("si", $id_producto, $tipo_cliente);
            $stmt_pr->execute();
            $pr = $stmt_pr->get_result()->fetch_assoc();
            $stmt_pr->close();

            if ($pr && $pr['precio'] > 0) $precio_u = $pr['precio'];
        }

        // --- CÁLCULO DE TRAMOS DE DESCUENTO ---
        $precio_original = $precio_u;
        $porcentaje_desc = 0;

        $stmt_tramos = $conn->prepare("SELECT porcentaje_descuento FROM productos_tramos_descuento WHERE id_producto = ? AND cantidad_minima <= ? ORDER BY cantidad_minima DESC LIMIT 1");
        if ($stmt_tramos) {
            $stmt_tramos->bind_param("sd", $id_producto, $cant);
            $stmt_tramos->execute();
            $res_tramo = $stmt_tramos->get_result()->fetch_assoc();
            
            if ($res_tramo) {
                $porcentaje_desc = floatval($res_tramo['porcentaje_descuento']);
                $precio_u = $precio_original * (1 - ($porcentaje_desc / 100)); // Aplica descuento
            }
            $stmt_tramos->close();
        }

        $monto_desc = ($precio_original - $precio_u) * $cant;
        // ---------------------------------------

        $costo_u = ($prod['costo_actual'] > 0) ? $prod['costo_actual'] : $prod['costo_por_kilo'];
        
        // Totales
        $total_v = $cant * $precio_u;
        $total_c = $cant * $costo_u;
        $margen = $total_v - $total_c;
        $nombre_prod = $prod['producto'];

        $total_pedido_acumulado += $total_v;

        // Ejecutar Insert
        $stmt_ins->bind_param("sssssddddddssssddd", 
            $id_pedido, $creado_por, $cliente_nombre, $cliente_id, $nombre_prod, 
            $cant, $precio_u, $costo_u, $total_v, $total_c, $margen, 
            $fecha, $obs, $qr_token, $id_producto,
            $precio_original, $porcentaje_desc, $monto_desc
        );

        if ($stmt_ins->execute()) $ok++;
    }
    $stmt_ins->close();

    if ($ok > 0) {
        if (function_exists('enviarNotificacionFCM')) {
            $fecha_fmt = date("d/m/Y", strtotime($fecha));
            $total_fmt = "$ " . number_format($total_pedido_acumulado, 0, ',', '.');
            $msg = "Cliente: $cliente_nombre\nTotal: $total_fmt\nDespacho: $fecha_fmt\n$creado_por";
            
            $destinatario = (trim(strtolower($cliente_nombre)) === 'prueba') ? 'jandres@tabolango.cl' : null;
            $prefijo = ($destinatario !== null) ? "🧪 [TEST] " : "🎉 ";

            enviarNotificacionFCM($destinatario, $prefijo . "Nuevo Pedido", $msg, "https://app.tabolango.cl/admin/pedidos", "notify_pedido_creado");
        }

        echo json_encode(["status"=>"success","pedido"=>$id_pedido]);
    } else {
        echo json_encode(["status"=>"error","message"=>"No se guardaron items"]);
    }
    exit;
}

$conn->close();
?>