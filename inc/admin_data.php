<?php
// inc/admin_data.php
require_once 'auth.php'; // Inyecta CORS, $conn dinámica y Roles

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// =======================================================================
// 0. ACCIÓN: SINCRONIZACIÓN DE GOOGLE (No requiere sesión estricta previa)
// =======================================================================
if ($action === 'google_sync') {
    $email    = $_POST['email'] ?? '';
    $foto_url = $_POST['foto_url'] ?? '';
    $nombre   = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';

    if (empty($email) || empty($foto_url)) {
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos para sincronizar.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE app_usuarios SET foto_url=?, nombre=?, apellido=? WHERE email=?");
    $stmt->bind_param("ssss", $foto_url, $nombre, $apellido, $email);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Sincronización completada']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al actualizar sincronización.']);
    }
    exit;
}

// =======================================================================
// A PARTIR DE AQUÍ: ACCIONES RESTRINGIDAS (Requieren estar logueado)
// =======================================================================
if (empty($email_auth)) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no detectada.']);
    exit;
}

// =======================================================================
// 1. OBTENER USUARIOS
// =======================================================================
if ($action === 'get_users') {
    try {
        $sql = "SELECT id, user_login, nombre, apellido, email, foto_url, telefono, cargo, activo, fecha_nacimiento 
                FROM app_usuarios 
                ORDER BY nombre ASC";
        $result = $conn->query($sql);
        
        $users = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => $users
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error SQL: ' . $e->getMessage()]);
    }
    exit;
}

// =======================================================================
// 2. ACTUALIZAR DATOS DE USUARIO (Solo Admin)
// =======================================================================
if ($action === 'update_user_admin') {
    
    if ($rol_final !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'Acceso denegado. Solo administradores pueden editar.']);
        exit;
    }

    $email_target = $_POST['email'] ?? '';
    if (!$email_target) {
        echo json_encode(['status' => 'error', 'message' => 'Falta el correo del usuario.']);
        exit;
    }

    $nombre    = trim($_POST['nombre'] ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $telefono  = trim($_POST['telefono'] ?? '');
    $fecha_nac = trim($_POST['fecha_nacimiento'] ?? '');
    if (empty($fecha_nac)) $fecha_nac = '0000-00-00';
    
    $cargo     = $_POST['cargo'] ?? null;
    $foto_url  = $_POST['foto_url'] ?? null;

    try {
        // Preparar actualización principal en app_usuarios
        $sql_update = "UPDATE app_usuarios SET nombre=?, apellido=?, telefono=?, fecha_nacimiento=?";
        $params = [$nombre, $apellido, $telefono, $fecha_nac];
        $types = "ssss";

        if ($cargo !== null) {
            $sql_update .= ", cargo=?";
            $params[] = $cargo;
            $types .= "s";
        }
        if ($foto_url !== null) {
            $sql_update .= ", foto_url=?";
            $params[] = $foto_url;
            $types .= "s";
        }

        $sql_update .= " WHERE email=?";
        $params[] = $email_target;
        $types .= "s";

        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado correctamente.']);
        } else {
            throw new Exception($stmt->error);
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar en BD: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Acción no válida solicitada a la API.']);
?>