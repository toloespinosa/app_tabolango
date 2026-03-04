<?php
// api-edit.php - Con registro de auditoría, transacciones BD y Notificaciones Push
ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { 
    http_response_code(200); exit; 
}

require_once 'auth.php';
include_once('notifications.php'); 

$action = $_POST['action'] ?? '';

// ==========================================
// ELIMINAR PEDIDO COMPLETO
// ==========================================
if ($action == 'delete_order') {
    verificarPermisoEscritura();
    $id_pedido = $_POST['id_pedido'] ?? '';
    
    if (empty($id_pedido)) {
        echo json_encode(["status" => "error", "message" => "Falta ID Pedido"]); exit;
    }

    $stmt_del = $conn->prepare("DELETE FROM pedidos_activos WHERE id_pedido = ?");
    $stmt_del->bind_param("s", $id_pedido);
    
    if ($stmt_del->execute()) {
        echo json_encode(["status" => "success", "message" => "Pedido eliminado"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al eliminar en BD"]);
    }
    exit;
}

// ==========================================
// ACTUALIZAR PEDIDO (Y FECHA)
// ==========================================
if ($action == 'update_order_items') {
    
    verificarPermisoEscritura();

    $id_pedido = $_POST['id_pedido'] ?? '';
    $productos_raw = $_POST['producto'] ?? ''; 
    $cantidades_raw = $_POST['cantidad'] ?? '';
    $precios_raw = $_POST['precios_venta'] ?? ''; 
    $nueva_fecha_despacho = $_POST['fecha_despacho'] ?? ''; // <-- Recibimos la nueva fecha
    
    $email_editor = $_POST['wp_user'] ?? '';
    $nombre_editor = $email_editor; 

    if (!empty($email_editor)) {
        $stmt_u = $conn->prepare("SELECT nombre, apellido FROM app_usuarios WHERE email = ? LIMIT 1");
        $stmt_u->bind_param("s", $email_editor);
        $stmt_u->execute();
        $res_u = $stmt_u->get_result()->fetch_assoc();
        if ($res_u) {
            $nombre_editor = trim($res_u['nombre'] . ' ' . $res_u['apellido']);
        }
    }

    $fecha_edicion = date('Y-m-d H:i:s'); 
    $hora_notif = date('H:i'); 

    if (empty($id_pedido)) {
        echo json_encode(["status" => "error", "message" => "Falta ID Pedido"]); exit;
    }

    $stmt_meta = $conn->prepare("SELECT creado_por, cliente, id_interno_cliente, fecha_despacho, observaciones, qr_token, url_factura, url_guia, numero_factura, estado FROM pedidos_activos WHERE id_pedido = ? LIMIT 1");
    $stmt_meta->bind_param("s", $id_pedido);
    $stmt_meta->execute();
    $meta = $stmt_meta->get_result()->fetch_assoc();

    if (!$meta) {
        echo json_encode(["status" => "error", "message" => "Pedido no encontrado"]); exit;
    }

    // APLICAR LA NUEVA FECHA SI ES QUE LLEGÓ UNA
    if (!empty($nueva_fecha_despacho)) {
        $meta['fecha_despacho'] = $nueva_fecha_despacho;
    }

    $conn->begin_transaction();

    try {
        $stmt_del = $conn->prepare("DELETE FROM pedidos_activos WHERE id_pedido = ?");
        $stmt_del->bind_param("s", $id_pedido);
        if (!$stmt_del->execute()) {
            throw new Exception("Error al limpiar el pedido antiguo.");
        }

        $lista_prod = explode(' | ', $productos_raw);
        $lista_cant = explode(' | ', $cantidades_raw);
        $lista_precios = explode(' | ', $precios_raw);
        
        $insertados = 0;

        foreach ($lista_prod as $index => $id_p_item) {
            $id_p_item = trim($id_p_item);
            $cant_p = isset($lista_cant[$index]) ? floatval(trim($lista_cant[$index])) : 0;
            $precio_frontend = isset($lista_precios[$index]) ? floatval(trim($lista_precios[$index])) : 0;
            
            if (empty($id_p_item) || $cant_p <= 0) continue;

            $stmt_p = $conn->prepare("SELECT * FROM productos WHERE id_producto = ?");
            $stmt_p->bind_param("s", $id_p_item);
            $stmt_p->execute();
            $info = $stmt_p->get_result()->fetch_assoc();

            if ($info) {
                if ($precio_frontend > 0) {
                    $p_u = $precio_frontend;
                } else {
                    $p_u = ($info['precio_actual'] > 0) ? $info['precio_actual'] : ($info['precio_por_kilo'] ?? 0);
                }
                $c_u = ($info['costo_actual'] > 0) ? $info['costo_actual'] : ($info['costo_por_kilo'] ?? 0);
                
                $tot_v = $cant_p * $p_u; 
                $tot_c = $cant_p * $c_u; 
                $margen = $tot_v - $tot_c;

                $sql = "INSERT INTO pedidos_activos 
                        (id_pedido, creado_por, cliente, id_interno_cliente, producto, cantidad, precio_unitario, costo_unitario, total_venta, total_costo, margen, fecha_despacho, observaciones, qr_token, id_producto, url_factura, url_guia, numero_factura, estado, ultima_edicion, editado_por) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt_ins = $conn->prepare($sql);
                if (!$stmt_ins) {
                    throw new Exception("Error preparando INSERT: " . $conn->error);
                }

                $stmt_ins->bind_param("sssssddddddssssssssss", 
                    $id_pedido, 
                    $meta['creado_por'], 
                    $meta['cliente'],
                    $meta['id_interno_cliente'],
                    $info['producto'], 
                    $cant_p, 
                    $p_u, 
                    $c_u, 
                    $tot_v, 
                    $tot_c, 
                    $margen, 
                    $meta['fecha_despacho'], // ¡Aquí se guarda la nueva fecha automáticamente!
                    $meta['observaciones'], 
                    $meta['qr_token'], 
                    $id_p_item,
                    $meta['url_factura'],
                    $meta['url_guia'],
                    $meta['numero_factura'],
                    $meta['estado'],
                    $fecha_edicion,
                    $nombre_editor
                );
                
                if (!$stmt_ins->execute()) {
                    throw new Exception("Error ejecutando INSERT: " . $stmt_ins->error);
                }
                $insertados++;
            }
        }

        if ($insertados == 0) {
            throw new Exception("No hay productos válidos para guardar.");
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            "status" => "error", 
            "message" => "Fallo de conexión en BD. Tus datos están a salvo. Detalle: " . $e->getMessage()
        ]);
        exit;
    }

    if ($insertados > 0 && function_exists('enviarNotificacionFCM')) {
        $cliente_nom = $meta['cliente'];
        $destinatario = (trim(strtolower($cliente_nom)) === 'prueba') ? 'jandres@tabolango.cl' : null;
        $prefijo = ($destinatario !== null) ? "🧪 [EDIT-TEST] " : "✏️ ";

        enviarNotificacionFCM(
            $destinatario, 
            $prefijo . "Pedido Editado", 
            "Cliente: $cliente_nom\nPor: $nombre_editor\nHora: $hora_notif", 
            "", 
            "notify_pedido_editado"
        );
    }

    echo json_encode([
        "status" => "success", 
        "message" => "Pedido actualizado ($insertados items)",
        "editor" => $nombre_editor,
        "hora" => $fecha_edicion
    ]);
    exit;
}

$conn->close();
?>