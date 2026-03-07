<?php
require_once 'auth.php';
// api_dtes.php - Puente de lectura para la tabla maestra DTE
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
    $conn->set_charset("utf8mb4");

    // Traemos los documentos recientes ordenados por fecha
    $sql = "SELECT * FROM dte_emitidos WHERE fecha_emision >= DATE_SUB(NOW(), INTERVAL 60 DAY) ORDER BY id ASC";
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