<?php
// perfil_data.php - VERSIÓN BLINDADA Y OPTIMIZADA
require_once 'auth.php';

error_reporting(E_ALL);
ini_set('display_errors', 0); 

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Access-Control-Allow-Origin: *");

// 🔥 INTEGRACIÓN SEGURA CON LA SESIÓN DE WORDPRESS 🔥
// Buscamos dinámicamente el wp-load.php sin importar dónde esté alojado el tema
$wp_root = explode('wp-content', __FILE__)[0];
if (file_exists($wp_root . 'wp-load.php')) {
    require_once $wp_root . 'wp-load.php';
}

// Bloqueo de seguridad: Si no hay sesión en WP, denegamos el acceso
if (!is_user_logged_in()) {
    http_response_code(403);
    die(json_encode(["status" => "error", "message" => "Acceso denegado: Sesión no válida."]));
}

// Forzamos que el email sea ESTRICTAMENTE el de la cookie de sesión del servidor
$current_user_wp = wp_get_current_user();
$email_seguro = trim(strtolower($current_user_wp->user_email));

if (empty($email_seguro)) {
    die(json_encode(['status' => 'error', 'message' => 'No se pudo recuperar el correo de la sesión']));
}

try {
    // DETECCIÓN AUTOMÁTICA DE ENTORNO
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $es_entorno_local = (strpos($host_actual, 'localhost') !== false || strpos($host_actual, '.local') !== false);

    if ($es_entorno_local) {
        $conn = new mysqli("localhost", "root", "root", "local"); // Asegurado nombre BD local
    } else {
        $conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
    }
    
    if ($conn->connect_error) {
        throw new Exception("Conexión fallida DB");
    }
    $conn->set_charset("utf8mb4");

    $tabla_usuarios = 'app_usuarios';
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // --- ACCIÓN 1: OBTENER PERFIL ---
    if ($action == 'get_profile') {
        // Usamos $email_seguro, ignoramos $_GET['email']
        $stmt = $conn->prepare("SELECT nombre, apellido, email, cargo, telefono, fecha_nacimiento, foto_url FROM $tabla_usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email_seguro);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['status' => 'success', 'data' => $row]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
        }
        $stmt->close();
        exit;
    }

    // --- ACCIÓN 2: GUARDAR / ACTUALIZAR PERFIL ---
    if ($action == 'update_profile') {
        // Usamos $email_seguro, ignoramos $_POST['email']
        $nombre = trim(strip_tags($_POST['nombre'] ?? ''));
        $apellido = trim(strip_tags($_POST['apellido'] ?? ''));
        $telefono = trim(strip_tags($_POST['telefono'] ?? ''));
        $fecha_nac = trim(strip_tags($_POST['fecha_nacimiento'] ?? ''));
        $foto_url = filter_var($_POST['foto_url'] ?? '', FILTER_SANITIZE_URL);

        $stmt_check = $conn->prepare("SELECT email FROM $tabla_usuarios WHERE email = ?");
        $stmt_check->bind_param("s", $email_seguro);
        $stmt_check->execute();
        $existe = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if ($existe) {
            $stmt = $conn->prepare("UPDATE $tabla_usuarios SET nombre=?, apellido=?, telefono=?, fecha_nacimiento=?, foto_url=? WHERE email=?");
            $stmt->bind_param("ssssss", $nombre, $apellido, $telefono, $fecha_nac, $foto_url, $email_seguro);
        } else {
            $cargo = 'usuario';
            $stmt = $conn->prepare("INSERT INTO $tabla_usuarios (email, nombre, apellido, telefono, fecha_nacimiento, foto_url, cargo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $email_seguro, $nombre, $apellido, $telefono, $fecha_nac, $foto_url, $cargo);
        }
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "msg" => "Perfil guardado con éxito"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error de base de datos"]);
        }
        $stmt->close();
        exit;
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>