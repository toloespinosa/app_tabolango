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
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Access-Control-Allow-Origin: *");


// 2. Usar la conexión global de WordPress
global $wpdb;
$tabla_usuarios = 'app_usuarios'; // Nombre de tu tabla

// Detectar acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// --- ACCIÓN 1: OBTENER PERFIL ---
if ($action == 'get_profile') {
    $email = sanitize_email($_GET['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode(['status' => 'error', 'message' => 'Email no proporcionado']);
        exit;
    }

    // Consulta usando WPDB (Protegido contra inyecciones SQL)
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT nombre, apellido, email, cargo, telefono, fecha_nacimiento, foto_url 
         FROM $tabla_usuarios WHERE email = %s LIMIT 1", 
        $email
    ), ARRAY_A);
    
    if ($row) {
        echo json_encode(['status' => 'success', 'data' => $row]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Usuario no encontrado']);
    }
    exit;
}

// --- ACCIÓN 2: ACTUALIZACIÓN MANUAL (Formulario de perfil) ---
if ($action == 'update_profile') {
    $email = sanitize_email($_POST['email'] ?? '');
    
    $datos = [
        'nombre'           => sanitize_text_field($_POST['nombre'] ?? ''),
        'apellido'         => sanitize_text_field($_POST['apellido'] ?? ''),
        'telefono'         => sanitize_text_field($_POST['telefono'] ?? ''),
        'fecha_nacimiento' => sanitize_text_field($_POST['fecha_nacimiento'] ?? ''),
        'foto_url'         => esc_url_raw($_POST['foto_url'] ?? '')
    ];

    if (empty($email)) {
        die(json_encode(["status" => "error", "message" => "Email no recibido"]));
    }

    // Verificar si existe
    $existe = $wpdb->get_var($wpdb->prepare("SELECT email FROM $tabla_usuarios WHERE email = %s", $email));

    if ($existe) {
        // UPDATE con WPDB
        $resultado = $wpdb->update($tabla_usuarios, $datos, ['email' => $email]);
    } else {
        // INSERT con WPDB
        $datos['email'] = $email;
        $datos['cargo'] = 'usuario';
        $resultado = $wpdb->insert($tabla_usuarios, $datos);
    }
    
    if ($resultado !== false) {
        echo json_encode(["status" => "success", "msg" => "Perfil actualizado manual"]);
    } else {
        echo json_encode(["status" => "error", "message" => $wpdb->last_error]);
    }
    exit;
}

// --- ACCIÓN 3: SINCRONIZAR CON GOOGLE (Login) ---
if ($action == 'google_sync') {
    $email    = sanitize_email($_POST['email'] ?? '');
    $nombre   = sanitize_text_field($_POST['nombre'] ?? '');
    $apellido = sanitize_text_field($_POST['apellido'] ?? '');
    $foto_url = esc_url_raw($_POST['foto_url'] ?? '');

    if (empty($email)) {
        die(json_encode(["status" => "error", "message" => "Email no recibido"]));
    }

    $existe = $wpdb->get_var($wpdb->prepare("SELECT email FROM $tabla_usuarios WHERE email = %s", $email));

    if ($existe) {
        $update_data = ['nombre' => $nombre, 'apellido' => $apellido];
        if (!empty($foto_url) && $foto_url !== 'undefined') {
            $update_data['foto_url'] = $foto_url;
        }
        $resultado = $wpdb->update($tabla_usuarios, $update_data, ['email' => $email]);
    } else {
        $resultado = $wpdb->insert($tabla_usuarios, [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'email'    => $email,
            'foto_url' => $foto_url,
            'cargo'    => 'usuario'
        ]);
    }
    
    if ($resultado !== false) {
        echo json_encode(["status" => "success", "message" => "Login Sincronizado"]);
    } else {
        echo json_encode(["status" => "error", "message" => $wpdb->last_error]);
    }
    exit;
}