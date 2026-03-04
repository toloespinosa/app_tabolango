<?php
require_once 'auth.php';
// auth.php ya maneja las cabeceras CORS y crea la variable $conn

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$admin_email = $_GET['admin_email'] ?? ''; 

// --- VALIDACIÓN DE SEGURIDAD (Solo un admin puede usar este archivo) ---
$stmt_check = $conn->prepare("
    SELECT r.nombre_rol 
    FROM app_roles r
    INNER JOIN app_usuario_roles ur ON r.id = ur.rol_id
    WHERE ur.usuario_email = ? AND r.nombre_rol = 'administrador'
");
$stmt_check->bind_param("s", $admin_email);
$stmt_check->execute();
if (!$stmt_check->get_result()->fetch_assoc()) {
    echo json_encode(["status" => "error", "message" => "No tienes permisos de administrador"]);
    exit;
}

// --- ACCIÓN: LISTAR USUARIOS Y ROLES ---
if ($action == 'get_all_users_with_roles') {
    $sql = "SELECT u.email, u.nombre, u.apellido, 
            GROUP_CONCAT(r.id) as roles_ids
            FROM app_usuarios u
            LEFT JOIN app_usuario_roles ur ON u.email = ur.usuario_email
            LEFT JOIN app_roles r ON ur.rol_id = r.id
            WHERE u.activo = 1
            GROUP BY u.email";
    
    $res = $conn->query($sql);
    $usuarios = [];
    while($row = $res->fetch_assoc()) {
        $row['roles_ids'] = $row['roles_ids'] ? explode(',', $row['roles_ids']) : [];
        $usuarios[] = $row;
    }
    
    $res_roles = $conn->query("SELECT * FROM app_roles");
    $lista_roles = [];
    while($r = $res_roles->fetch_assoc()) $lista_roles[] = $r;

    echo json_encode(["usuarios" => $usuarios, "maestro_roles" => $lista_roles]);
    exit;
}

// --- ACCIÓN: GUARDAR CAMBIOS ---
if ($action == 'save_user_roles') {
    $email_target = $_POST['email_target'];
    $roles_ids = $_POST['roles_ids'] ?? [];

    $stmt_del = $conn->prepare("DELETE FROM app_usuario_roles WHERE usuario_email = ?");
    $stmt_del->bind_param("s", $email_target);
    $stmt_del->execute();

    if (!empty($roles_ids)) {
        $stmt_ins = $conn->prepare("INSERT INTO app_usuario_roles (usuario_email, rol_id) VALUES (?, ?)");
        foreach ($roles_ids as $rid) {
            $stmt_ins->bind_param("si", $email_target, $rid);
            $stmt_ins->execute();
        }
    }
    echo json_encode(["status" => "success"]);
    exit;
}

$conn->close();
?>