<?php
// inc/auth.php - CORREGIDO PARA LOCALWP MAC

// Evitar que errores de PHP se mezclen con el JSON
ini_set('display_errors', 0); 
mysqli_report(MYSQLI_REPORT_OFF);

// Headers CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Detectar entorno
$whitelist_local = ['localhost', '127.0.0.1', 'tabolango-app.local', 'tabolango.local'];
$es_local = in_array($_SERVER['SERVER_NAME'], $whitelist_local);

// Variables de conexión
$socket = null;
$port = null;

if ($es_local) {
    // --- CONFIGURACIÓN LOCAL (MAC LOCALWP) ---
    $servername = "localhost";
    $username   = "root";
    $password   = "root";
    $dbname     = "local"; // <--- EN LOCALWP LA BD SE LLAMA "local" POR DEFECTO
    
    // Tu Socket Específico
    $socket = "/Users/juanandres/Library/Application Support/Local/run/o4oaY0jbM/mysql/mysqld.sock";

} else {
    // --- CONFIGURACIÓN PRODUCCIÓN ---
    $servername = "localhost";
    $username   = "tabolang_app";
    $password   = 'm{Hpj.?IZL$Kz${S'; 
    $dbname     = "tabolang_pedidos";
}

// Conexión Singleton
if (!isset($conn)) {
    try {
        // La firma es: host, user, pass, db, port, socket
        $conn = new mysqli($servername, $username, $password, $dbname, null, $socket);
        $conn->set_charset("utf8mb4"); 
        
        if ($conn->connect_error) {
            throw new Exception($conn->connect_error);
        }
    } catch (Exception $e) {
        // Devolvemos JSON válido con el error para que JS lo entienda
        echo json_encode([
            "status" => "error", 
            "message" => "Error DB: " . $e->getMessage()
        ]);
        exit;
    }
}

// 3. CAPTURAR EMAIL
$email_auth = $_REQUEST['wp_user'] ?? ''; 

// 4. ROLES
$rol_final = 0; 
$puede_editar = false;

if ($es_local && empty($email_auth)) {
    $email_auth = "jandres@tabolango.cl"; 
    $rol_final = 1;
    $puede_editar = true;
}

if (!empty($email_auth) && $rol_final === 0) {
    // Verificamos si la tabla existe para no romper si la BD está vacía
    $check = $conn->query("SHOW TABLES LIKE 'app_usuario_roles'");
    if ($check && $check->num_rows > 0) {
        $stmt_auth = $conn->prepare("SELECT rol_id FROM app_usuario_roles WHERE usuario_email = ? LIMIT 1");
        if ($stmt_auth) {
            $stmt_auth->bind_param("s", $email_auth);
            $stmt_auth->execute();
            $stmt_auth->bind_result($r_id);
            if ($stmt_auth->fetch()) {
                $rol_final = (int)$r_id;
                if ($rol_final === 1 || $rol_final === 2) $puede_editar = true;
            }
            $stmt_auth->close();
        }
    }
}

// Helper functions
if (!function_exists('verificarPermisoEscritura')) {
    function verificarPermisoEscritura() {
        global $puede_editar;
        if (!$puede_editar) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "No tienes permisos."]);
            exit;
        }
    }
}
?>