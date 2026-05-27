<?php
require_once 'auth.php';

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

$action = $_GET['action'] ?? '';

if ($action === 'clientes') {
    $res = $conn->query("SELECT id_interno, cliente, razon_social, rut_cliente, email, telefono, direccion, ciudad, comuna FROM clientes WHERE activo = 1 ORDER BY cliente ASC");
    $clientes = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $clientes[] = $row;
    }
    echo json_encode($clientes);
    exit;
}

if ($action === 'add_client') {
    header("Content-Type: application/json; charset=UTF-8");
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $cliente     = trim($input['cliente']     ?? '');
    $responsable = trim($input['responsable'] ?? '');
    $rut         = trim($input['rut']         ?? '');
    $telefono    = trim($input['telefono']    ?? '');
    $email       = trim($input['email']       ?? '');
    $ciudad      = trim($input['ciudad']      ?? '');

    if (empty($cliente) || empty($responsable)) {
        http_response_code(400);
        echo json_encode(["success" => false, "error" => "Nombre y Responsable son obligatorios"]);
        exit;
    }

    // tipo_cliente=5 corresponde a "Minorista" en producción (default para clientes nuevos).
    // Ojo: en local la BD tiene las categorías corridas (id=3 es Minorista), pero seguimos la convención de prod.
    $stmt = $conn->prepare(
        "INSERT INTO clientes (cliente, responsable, rut_cliente, telefono, email, ciudad, tipo_cliente, activo)
         VALUES (?, ?, ?, ?, ?, ?, 5, 1)"
    );
    $stmt->bind_param("ssssss", $cliente, $responsable, $rut, $telefono, $email, $ciudad);

    if ($stmt->execute()) {
        $nuevo_id = $conn->insert_id;
        echo json_encode(["success" => true, "id_interno" => $nuevo_id, "cliente" => $cliente]);
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }
    $stmt->close();
    exit;
}

if ($action === 'productos') {
    $res = $conn->query("
        SELECT p.id_producto, p.producto, p.variedad, p.calibre, p.unidad, p.formato, p.icono,
               p.precio_actual,
               MAX(CASE WHEN pc.id_categoria_cliente = 1 THEN pc.precio END) AS p1,
               MAX(CASE WHEN pc.id_categoria_cliente = 2 THEN pc.precio END) AS p2,
               MAX(CASE WHEN pc.id_categoria_cliente = 4 THEN pc.precio END) AS p4
        FROM productos p
        LEFT JOIN productos_precios_categorias pc ON p.id_producto = pc.id_producto
        WHERE p.activo = 1
        GROUP BY p.id_producto
        ORDER BY p.producto ASC
    ");
    $productos = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) $productos[] = $row;
    }
    echo json_encode($productos);
    exit;
}

echo json_encode(["error" => "Acción no reconocida"]);
