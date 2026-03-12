<?php
// usuarios.php - VERSIÓN LIMPIA Y UNIFICADA
error_reporting(E_ALL);
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");

require_once 'auth.php'; // Trae $conn (tu BD) y $es_local automáticamente

// 1. INTEGRACIÓN SEGURA CON WORDPRESS
$wp_root = explode('wp-content', __FILE__)[0];
if (file_exists($wp_root . 'wp-load.php')) {
    require_once $wp_root . 'wp-load.php';
}

// 2. VALIDACIÓN DE SESIÓN (SIMULADOR VS PRODUCCIÓN)
$email_seguro = '';
$is_logged = function_exists('is_user_logged_in') && is_user_logged_in();

if (isset($es_local) && $es_local) {
    // Modo Simulador Local
    $email_seguro = $_GET['admin_email'] ?? 'jandres@tabolango.cl'; 
} else {
    // Modo Producción Búnker
    if (!$is_logged) {
        die(json_encode(["status" => "error", "message" => "Acceso denegado: Sesión no válida en WordPress."]));
    }
    $email_seguro = trim(strtolower(wp_get_current_user()->user_email));
}

// 3. VALIDACIÓN ESTRICTA DE ADMINISTRADOR
$es_admin_validado = false;

if (isset($es_local) && $es_local) {
    // Si estamos en local, el frontend ya verificó el rol del simulador, lo dejamos pasar.
    $es_admin_validado = true; 
} else {
    // En producción, consultamos la BD real (Solo ID 1 = Administrador)
    if (isset($conn) && !$conn->connect_error) {
        $stmt_check = $conn->prepare("SELECT r.id FROM app_roles r INNER JOIN app_usuario_roles ur ON r.id = ur.rol_id WHERE ur.usuario_email = ? AND r.id = 1");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $email_seguro);
            $stmt_check->execute();
            if ($stmt_check->get_result()->fetch_assoc()) {
                $es_admin_validado = true;
            }
            $stmt_check->close();
        }
    }
}

if (!$es_admin_validado) {
    die(json_encode(["status" => "error", "message" => "No tienes permisos de administrador reales."]));
}

// 4. RUTAS Y ACCIONES
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action == 'get_all_users_with_roles') {
    
    // Consulta agrupada compatible con LocalWP
    $sql = "SELECT u.email, u.nombre, u.apellido, 
            GROUP_CONCAT(r.id) as roles_ids
            FROM app_usuarios u
            LEFT JOIN app_usuario_roles ur ON u.email = ur.usuario_email
            LEFT JOIN app_roles r ON ur.rol_id = r.id
            WHERE u.activo = 1
            GROUP BY u.email, u.nombre, u.apellido"; 
            
    $res = $conn->query($sql);
    
    if (!$res) {
        die(json_encode(["status" => "error", "message" => "Fallo SQL: " . $conn->error]));
    }
    
    $usuarios = [];
    while($row = $res->fetch_assoc()) {
        $row['roles_ids'] = $row['roles_ids'] ? explode(',', $row['roles_ids']) : [];
        $usuarios[] = $row;
    }
    
    $res_roles = $conn->query("SELECT * FROM app_roles");
    $lista_roles = [];
    while($r = $res_roles->fetch_assoc()) {
        $lista_roles[] = $r;
    }

    echo json_encode(["status" => "success", "usuarios" => $usuarios, "maestro_roles" => $lista_roles]);
    exit;
}

// --- ACCIÓN: GUARDAR CAMBIOS ---
if ($action == 'save_user_roles') {
    // 1. Verificación de seguridad para escritura
    // En local permitimos siempre (Modo Dios), en producción solo al Admin real
    $autorizado = false;
    if (isset($es_local) && $es_local) {
        $autorizado = true;
    } else {
        // Validación real contra BD en producción
        $stmt_check_write = $conn_usuarios->prepare("SELECT r.id FROM app_roles r INNER JOIN app_usuario_roles ur ON r.id = ur.rol_id WHERE ur.usuario_email = ? AND r.id = 1");
        $stmt_check_write->bind_param("s", $email_seguro);
        $stmt_check_write->execute();
        if ($stmt_check_write->get_result()->fetch_assoc()) {
            $autorizado = true;
        }
        $stmt_check_write->close();
    }

    if (!$autorizado) {
        die(json_encode(["status" => "error", "message" => "No tienes permisos para modificar roles."]));
    }

    // 2. Proceso de guardado
    $email_target = $_POST['email_target'];
    $roles_ids = $_POST['roles_ids'] ?? [];

    // Iniciamos una transacción para asegurar que no queden datos huérfanos
    $conn_usuarios->begin_transaction();

    try {
        // Borramos roles actuales del usuario objetivo
        $stmt_del = $conn_usuarios->prepare("DELETE FROM app_usuario_roles WHERE usuario_email = ?");
        $stmt_del->bind_param("s", $email_target);
        $stmt_del->execute();
        $stmt_del->close();

        // Insertamos los nuevos roles seleccionados
        if (!empty($roles_ids)) {
            $stmt_ins = $conn_usuarios->prepare("INSERT INTO app_usuario_roles (usuario_email, rol_id) VALUES (?, ?)");
            foreach ($roles_ids as $rid) {
                $stmt_ins->bind_param("si", $email_target, $rid);
                $stmt_ins->execute();
            }
            $stmt_ins->close();
        }

        $conn_usuarios->commit();
        echo json_encode(["status" => "success", "message" => "Roles actualizados correctamente."]);
    } catch (Exception $e) {
        $conn_usuarios->rollback();
        echo json_encode(["status" => "error", "message" => "Error en la transacción: " . $e->getMessage()]);
    }
    exit;
}

$conn->close();
?>