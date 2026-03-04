<?php
require_once 'auth.php';

// 🔥 1. CABECERAS ESTRICTAS ANTI-CACHÉ (Evita que el navegador guarde datos financieros)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// 2. CAPTURAR EL EMAIL
$email_recibido = $_GET['wp_user'] ?? $_POST['wp_user'] ?? '';

$puede_editar = false;
$tiene_acceso = false; 
$rol_detectado = 'Invitado';

if (!empty($email_recibido)) {
    // 3. VALIDACIÓN POR ID (Blindaje de Rol)
    $stmt_check = $conn->prepare("
        SELECT r.nombre_rol, ur.rol_id 
        FROM app_roles r
        INNER JOIN app_usuario_roles ur ON r.id = ur.rol_id
        WHERE ur.usuario_email = ?
    ");
    $stmt_check->bind_param("s", $email_recibido);
    $stmt_check->execute();
    $resultado = $stmt_check->get_result();
    
    while ($fila = $resultado->fetch_assoc()) {
        $rid = (int)$fila['rol_id'];
        $rol_detectado = $fila['nombre_rol']; 
        
        // IDs 1 (Admin) y 2 (Editor) tienen acceso a la Matriz
        if ($rid === 1 || $rid === 2) {
            $puede_editar = true;
            $tiene_acceso = true;
        }
    }
}

// 4. RESTRICCIÓN DE SEGURIDAD (ZERO TRUST)
if (!$tiene_acceso) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "Acceso restringido. Tu rol ($rol_detectado) no tiene permisos para ver o editar precios.",
        "rol" => $rol_detectado
    ]);
    exit; // 🔥 CORTE DE EJECUCIÓN ABSOLUTO 🔥
}

// 5. ACCIÓN POST: GUARDAR CAMBIOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$puede_editar) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "No tienes permiso para guardar"]);
        exit;
    }

    $id_producto  = $_POST['id_producto'] ?? 0;
    $id_categoria = $_POST['id_categoria'] ?? 0; 
    $precio       = $_POST['precio'] ?? 0;

    // --- LÓGICA DIFERENCIADA ---
    if ($id_categoria === 'lista') {
        // CASO A: Actualizar PRECIO LISTA (Tabla productos -> precio_actual)
        $sql_upd = "UPDATE productos SET precio_actual = ? WHERE id_producto = ?";
        $stmt_upd = $conn->prepare($sql_upd);
        // 'd' = double (precio), 'i' = integer (id)
        $stmt_upd->bind_param("di", $precio, $id_producto); 
        
    } else {
        // CASO B: Actualizar PRECIOS CATEGORÍAS (Tabla relacional)
        $sql_upd = "INSERT INTO productos_precios_categorias (id_producto, id_categoria_cliente, precio) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE precio = VALUES(precio)";
        $stmt_upd = $conn->prepare($sql_upd);
        // 'i' = integer, 'i' = integer, 'd' = double
        $stmt_upd->bind_param("iid", $id_producto, $id_categoria, $precio);
    }
    
    // Ejecutar la consulta
    if ($stmt_upd->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    exit;
}

// 6. ACCIÓN GET: LISTAR PRODUCTOS (Solo llega aquí si el usuario pasó el filtro de seguridad)
$sql_prod = "SELECT p.*, 
            MAX(CASE WHEN pc.id_categoria_cliente = 1 THEN pc.precio END) AS p1,
            MAX(CASE WHEN pc.id_categoria_cliente = 2 THEN pc.precio END) AS p2,
            MAX(CASE WHEN pc.id_categoria_cliente = 4 THEN pc.precio END) AS p4
            FROM productos p
            LEFT JOIN productos_precios_categorias pc ON p.id_producto = pc.id_producto
            GROUP BY p.id_producto 
            ORDER BY p.activo DESC, p.producto ASC";

$res = $conn->query($sql_prod);
$productos = [];
if ($res) {
    while($row = $res->fetch_assoc()) {
        $productos[] = $row;
    }
}

echo json_encode([
    "puede_editar" => $puede_editar,
    "rol" => $rol_detectado,
    "productos" => $productos
]);

$conn->close();
?>