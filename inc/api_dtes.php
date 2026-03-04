<?php
// api_dtes.php - Puente de lectura unificado (Tabla: dte_emitidos)

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
    $conn->set_charset("utf8mb4");

    // Traemos los documentos recientes (Excluimos los que terminan en 'S' - Simulaciones Locales)
    // Asumo que tienes una columna de fecha en la tabla dte_emitidos.
    // Si tu tabla no tiene un campo 'fecha_emision' explícito, avísame, pero generalmente se recomienda
    // tener un campo timestamp "created_at" o "fecha_emision". En este query asumo que usas 'fecha_emision'.
    
    $check_fecha = $conn->query("SHOW COLUMNS FROM dte_emitidos LIKE 'fecha_emision'");
    $campo_fecha = ($check_fecha && $check_fecha->num_rows > 0) ? "fecha_emision" : "id"; // Fallback por ID si no hay fecha

    $sql = "SELECT * FROM dte_emitidos 
            WHERE tipo_documento NOT LIKE '%S'
            ORDER BY $campo_fecha DESC 
            LIMIT 200"; 
            
    $res = $conn->query($sql);

    $dtes = [];
    while($row = $res->fetch_assoc()) {
        $dtes[] = $row;
    }

    echo json_encode($dtes);

} catch (Exception $e) {
    echo json_encode([]);
}
?>