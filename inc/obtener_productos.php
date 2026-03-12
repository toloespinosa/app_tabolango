<?php
// inc/obtener_productos.php
require_once 'auth.php'; // Inyecta la conexión a BD ($conn) y las cabeceras CORS

try {
    // Consulta directa, trayendo todas las columnas
    $sql = "SELECT * FROM productos ORDER BY producto ASC";
    $result = $conn->query($sql);

    $productos = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            // Formateamos los números para que JS los reciba como Float/Int y no como String
            $row['precio_actual'] = (float)$row['precio_actual'];
            $row['costo_actual']  = (float)$row['costo_actual'];
            $row['kg_por_unidad'] = (float)$row['kg_por_unidad']; 
            $row['activo']        = (int)$row['activo'];
            
            $productos[] = $row;
        }
    }

    // Salida limpia
    echo json_encode($productos, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>