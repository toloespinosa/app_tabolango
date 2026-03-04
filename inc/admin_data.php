<?php
require_once 'auth.php';
// 1. Definir la ruta raíz del tema (Sube un nivel desde /inc/)
$theme_root = dirname(__DIR__);

// 2. Cargar el Autoload de Composer (Librerías PDF, etc.)
$autoload_path = $theme_root . '/vendor/autoload.php';

if (file_exists($autoload_path)) {
    require_once $autoload_path;
} else {
    // Si falla, mostramos la ruta donde lo buscó para depurar
    die(json_encode([
        "status" => "error", 
        "message" => "Falta autoload.php", 
        "debug_path" => $autoload_path
    ]));
}

// 3. Cargar el motor de WordPress (Para usar la base de datos y usuario actual)
// Buscamos wp-load.php subiendo niveles hasta encontrarlo
if (!defined('ABSPATH')) {
    $wp_load_path = $theme_root;
    while (!file_exists($wp_load_path . '/wp-load.php')) {
        $wp_load_path = dirname($wp_load_path);
        if ($wp_load_path == '/' || $wp_load_path == '.') break; // Evitar bucle infinito
    }
    
    if (file_exists($wp_load_path . '/wp-load.php')) {
        require_once($wp_load_path . '/wp-load.php');
    }
}

// 4. Ahora ya puedes usar $wpdb y funciones de plugins
global $wpdb;

// --- AQUI EMPIEZA TU LÓGICA DEL ARCHIVO ---
header("Content-Type: application/json; charset=UTF-8");
// ... resto del código ...
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$host = "localhost";
$user = "tabolang_app";
$pass = 'm{Hpj.?IZL$Kz${S'; 
$db   = "tabolang_pedidos";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset("utf8mb4");

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // --- NUEVA ACCIÓN: SINCRONIZACIÓN DE GOOGLE ---
    if ($action === 'google_sync') {
        $email    = $_POST['email'] ?? '';
        $foto_url = $_POST['foto_url'] ?? '';
        $nombre   = $_POST['nombre'] ?? '';
        $apellido = $_POST['apellido'] ?? '';

        if (empty($email) || empty($foto_url)) {
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            exit;
        }

        // Actualizamos la foto y nombres, pero NO tocamos teléfono ni fecha de nacimiento
        $stmt = $conn->prepare("UPDATE app_usuarios SET foto_url=?, nombre=?, apellido=? WHERE email=?");
        $stmt->bind_param("ssss", $foto_url, $nombre, $apellido, $email);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Sincronización completada']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error al actualizar']);
        }
        exit;
    }

    // --- TUS ACCIONES EXISTENTES ---
    if ($action === 'get_users') {
        $query_users = "SELECT nombre, apellido, email, telefono, cargo, foto_url, fecha_nacimiento 
                        FROM app_usuarios 
                        WHERE email LIKE '%@%' 
                        ORDER BY nombre ASC";
        
        $res_users = $conn->query($query_users);
        $users_list = [];
        while ($row = $res_users->fetch_assoc()) {
            $users_list[] = $row;
        }

        $res_roles = $conn->query("SELECT nombre_rol FROM app_roles ORDER BY nombre_rol ASC");
        $roles_list = [];
        while ($r = $res_roles->fetch_assoc()) {
            $roles_list[] = $r['nombre_rol'];
        }

        echo json_encode([
            'status' => 'success', 
            'data' => $users_list,
            'available_roles' => $roles_list
        ]);
    }

    else if ($action === 'update_user_admin') {
        $email = $_POST['email'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $apellido = $_POST['apellido'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $cargo = $_POST['cargo'] ?? 'usuario';
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;

        $stmt = $conn->prepare("UPDATE app_usuarios SET nombre=?, apellido=?, telefono=?, cargo=?, fecha_nacimiento=? WHERE email=?");
        $stmt->bind_param("ssssss", $nombre, $apellido, $telefono, $cargo, $fecha_nacimiento, $email);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado']);
        } else {
            throw new Exception("Error al guardar cambios");
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}