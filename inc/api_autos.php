<?php
// inc/api_autos.php
require_once 'auth.php'; // Inyecta $conn, $email_auth y $rol_final

// 1. Verificación de Identidad. El email ahora lo manda JS y auth.php lo atrapa.
if (empty($email_auth)) {
    echo json_encode(["autos" => [], "is_admin" => false, "message" => "Sesión no detectada"]);
    exit;
}

// 2. Definir privilegios: Rol 1 (Admin) o 2 (Editor) pueden gestionar la flota
$is_admin_or_editor = ($rol_final === 1 || $rol_final === 2);

try {
    // 3. Mantenimiento: Actualizar semáforos de documentos vencidos en tiempo real
    $conn->query("UPDATE vehiculos SET 
        estado_permiso = IF(venc_permiso < CURRENT_DATE, 0, estado_permiso),
        estado_soap = IF(venc_soap < CURRENT_DATE, 0, estado_soap),
        estado_revision = IF(venc_revision < CURRENT_DATE, 0, estado_revision)");

    // 4. Consultas Inteligentes basadas en Rol
    if ($is_admin_or_editor) {
        // ADMIN / EDITOR: Ven toda la flota y un array de quién la conduce
        $sql = "SELECT v.*, 
                GROUP_CONCAT(vu.user_email) as conductores_asignados 
                FROM vehiculos v
                LEFT JOIN vehiculo_usuarios vu ON v.patente = vu.patente_vehiculo
                GROUP BY v.patente";
        $stmt = $conn->prepare($sql);
    } else {
        // CONDUCTOR: Solo ve la flota donde él esté registrado en 'vehiculo_usuarios'
        $sql = "SELECT v.*, NULL as conductores_asignados 
                FROM vehiculos v
                INNER JOIN vehiculo_usuarios vu ON v.patente = vu.patente_vehiculo
                WHERE vu.user_email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email_auth);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $autos = [];
    while ($row = $result->fetch_assoc()) {
        // Parsear el string de MySQL a un Array puro de JS para los checkboxes
        if(!empty($row['conductores_asignados'])) {
            $row['lista_conductores'] = explode(',', $row['conductores_asignados']);
        } else {
            $row['lista_conductores'] = [];
        }
        $autos[] = $row;
    }

    // Le devolvemos 'is_admin' al JS para que dibuje o esconda los botones de Editar
    echo json_encode([
        "autos" => $autos, 
        "is_admin" => $is_admin_or_editor
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>