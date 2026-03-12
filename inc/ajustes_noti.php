<?php
// ajustes_noti.php - VERSIÓN LIMPIA (Sin bloques duplicados)
require_once 'auth.php'; 

// --- INTEGRACIÓN SEGURA CON LA SESIÓN DE WORDPRESS ---
$wp_root = explode('wp-content', __FILE__)[0];
if (file_exists($wp_root . 'wp-load.php')) {
    require_once $wp_root . 'wp-load.php';
}

$email_seguro = '';
$is_logged = function_exists('is_user_logged_in') && is_user_logged_in();

if (isset($es_local) && $es_local) {
    // 🔥 MODO SIMULADOR LOCAL 🔥
    $input = json_decode(file_get_contents('php://input'), true);
    $email_seguro = $_GET['email'] ?? $input['email'] ?? 'jandres@tabolango.cl';
} else {
    // 🛡️ PRODUCCIÓN: Búnker de seguridad
    if (!$is_logged) {
        die(json_encode(["error" => "Acceso denegado: Sesión no válida."]));
    }
    $email_seguro = trim(strtolower(wp_get_current_user()->user_email));
}

// --- VERIFICAR SI EL QUE EJECUTA ES ADMIN (Solo 1 vez) ---
$es_admin = false;

if (isset($es_local) && $es_local) {
    // En local, el Frontend es el que dicta si eres admin o no, el Backend confía.
    $es_admin = true; 
} else {
    // En Producción verificamos contra la BD
    $stmt_admin = $conn->prepare("SELECT r.id FROM app_roles r INNER JOIN app_usuario_roles ur ON r.id = ur.rol_id WHERE ur.usuario_email = ? AND r.id = 1");
    if ($stmt_admin) {
        $stmt_admin->bind_param("s", $email_seguro);
        $stmt_admin->execute();
        if ($stmt_admin->get_result()->fetch_assoc()) {
            $es_admin = true;
        }
        $stmt_admin->close();
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// --- GET: OBTENER DATOS DE NOTIFICACIONES ---
if ($method === 'GET') {
    // Si viene un 'target_email' y eres admin, consultas a ese. Si no, a ti mismo.
    $target = $_GET['target_email'] ?? '';
    $email_a_gestionar = ($es_admin && !empty($target)) ? $target : $email_seguro;

    $stmt = $conn->prepare("SELECT * FROM app_fcm_tokens WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $email_a_gestionar);
    $stmt->execute();
    $result = $stmt->get_result();
    $prefs = $result->fetch_assoc();

    if (!$prefs) {
        $response = [
            'notify_pedido_creado' => 1,
            'notify_cambio_estado' => 1,
            'notify_pedido_entregado' => 1,
            'notify_pedido_editado' => 1,
            'notify_doc_por_vencer' => 1,
            'notify_doc_vencido' => 1
        ];
    } else {
        unset($prefs['id'], $prefs['token'], $prefs['dispositivo_id'], $prefs['fecha_registro'], $prefs['updated_at']);
        $response = array_map('intval', $prefs);
    }

    $response['is_admin'] = $es_admin;
    
    echo json_encode($response);
    $stmt->close();
    exit;
} 

// --- POST: GUARDAR PREFERENCIAS ---
elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400); 
        die(json_encode(['error' => 'Datos inválidos'])); 
    }

    $target = $input['target_email'] ?? '';
    $email_a_gestionar = ($es_admin && !empty($target)) ? $target : $email_seguro;
    
    $p_creado   = !empty($input['notify_pedido_creado']) ? 1 : 0;
    $p_estado   = !empty($input['notify_cambio_estado']) ? 1 : 0;
    $p_entrega  = !empty($input['notify_pedido_entregado']) ? 1 : 0;
    $p_editado  = !empty($input['notify_pedido_editado']) ? 1 : 0;
    $d_vencer   = !empty($input['notify_doc_por_vencer']) ? 1 : 0;
    $d_vencido  = !empty($input['notify_doc_vencido']) ? 1 : 0;

    $check = $conn->prepare("SELECT id FROM app_fcm_tokens WHERE email = ?");
    $check->bind_param("s", $email_a_gestionar);
    $check->execute();
    $existe = $check->get_result()->num_rows > 0;
    $check->close();

    if ($existe) {
        $stmt = $conn->prepare("UPDATE app_fcm_tokens SET notify_pedido_creado=?, notify_cambio_estado=?, notify_pedido_entregado=?, notify_pedido_editado=?, notify_doc_por_vencer=?, notify_doc_vencido=? WHERE email=?");
        $stmt->bind_param("iiiiiis", $p_creado, $p_estado, $p_entrega, $p_editado, $d_vencer, $d_vencido, $email_a_gestionar);
    } else {
        $token_vacio = "";
        $stmt = $conn->prepare("INSERT INTO app_fcm_tokens (email, token, notify_pedido_creado, notify_cambio_estado, notify_pedido_entregado, notify_pedido_editado, notify_doc_por_vencer, notify_doc_vencido) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiiiiii", $email_a_gestionar, $token_vacio, $p_creado, $p_estado, $p_entrega, $p_editado, $d_vencer, $d_vencido);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Preferencias actualizadas']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Error al guardar motor DB']);
    }
    $stmt->close();
}
?>