<?php
require_once 'auth.php';
ini_set('display_errors', 0); 
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Conexión
$servername = "localhost";
$username = "tabolang_app";
$password = 'm{Hpj.?IZL$Kz${S'; 
$dbname = "tabolang_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) { die(json_encode(["status"=>"error"])); }

$user_email = isset($_GET['email']) ? strtolower(trim($_GET['email'])) : '';

if (empty($user_email)) {
    echo json_encode(["autos" => []]);
    exit;
}

// 1. Verificar si es Admin (Rol ID 1)
$isAdmin = false;
$stmtRole = $conn->prepare("SELECT 1 FROM app_usuario_roles WHERE usuario_email = ? AND rol_id = 1");
$stmtRole->bind_param("s", $user_email);
$stmtRole->execute();
if ($stmtRole->get_result()->num_rows > 0) {
    $isAdmin = true;
}

try {
    // 2. Actualizar estados vencidos automáticamente (mantenimiento)
    $conn->query("UPDATE vehiculos SET 
        estado_permiso = IF(venc_permiso < CURRENT_DATE, 0, estado_permiso),
        estado_soap = IF(venc_soap < CURRENT_DATE, 0, estado_soap),
        estado_revision = IF(venc_revision < CURRENT_DATE, 0, estado_revision)");

    // 3. Consultas Diferenciadas
    if ($isAdmin) {
        // ADMIN: Ve TODO y necesita saber quiénes son los conductores (GROUP_CONCAT)
        $sql = "SELECT v.*, 
                GROUP_CONCAT(vu.user_email) as conductores_asignados 
                FROM vehiculos v
                LEFT JOIN vehiculo_usuarios vu ON v.patente = vu.patente_vehiculo
                GROUP BY v.patente";
        $stmt = $conn->prepare($sql);
    } else {
        // CONDUCTOR: Solo ve sus autos asignados
        // Nota: No necesitamos el GROUP_CONCAT aquí, pero lo mantenemos null para consistencia si se requiere
        $sql = "SELECT v.*, NULL as conductores_asignados 
                FROM vehiculos v
                INNER JOIN vehiculo_usuarios vu ON v.patente = vu.patente_vehiculo
                WHERE vu.user_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $user_email);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $autos = [];
    while ($row = $result->fetch_assoc()) {
        // Convertir string de conductores en array para JS
        if(isset($row['conductores_asignados']) && $row['conductores_asignados']) {
            $row['lista_conductores'] = explode(',', $row['conductores_asignados']);
        } else {
            $row['lista_conductores'] = [];
        }
        $autos[] = $row;
    }

    echo json_encode(["autos" => $autos, "is_admin" => $isAdmin]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>