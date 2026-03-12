<?php
require_once 'auth.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    // 🔥 USAMOS LA CONEXIÓN DINÁMICA DE auth.php 🔥
    // Verificamos si existe un socket definido y no está vacío
$socket = (defined('APP_DB_SOCKET') && APP_DB_SOCKET !== '') ? APP_DB_SOCKET : null;

// En mysqli, el orden es: host, user, pass, db, puerto (3306), socket
$conn = new mysqli(APP_DB_HOST, APP_DB_USER, APP_DB_PASSWORD, APP_DB_NAME, 3306, $socket);
    $conn->set_charset("utf8mb4");

    $action = $_GET['action'] ?? 'emitidas';

    if ($action == 'emitidas') {
        // Verificar columna estado_nota_credito
        $cols = $conn->query("SHOW COLUMNS FROM pedidos_activos LIKE 'estado_nota_credito'");
        $col_nc = ($cols && $cols->num_rows > 0) ? "estado_nota_credito" : "'' as estado_nota_credito";

        // Verificar columna url_nc (NUEVO)
        $cols_url = $conn->query("SHOW COLUMNS FROM pedidos_activos LIKE 'url_nc'");
        $col_url_nc = ($cols_url && $cols_url->num_rows > 0) ? "url_nc" : "'' as url_nc";

        // Consulta actualizada con $col_url_nc
        $sql = "SELECT 
                    id_pedido, numero_factura, fecha_despacho, cliente, url_factura, $col_nc, $col_url_nc,
                    SUM(precio_unitario * cantidad) as neto_calculado
                FROM pedidos_activos 
                WHERE numero_factura > 0 
                GROUP BY numero_factura 
                ORDER BY fecha_despacho DESC, numero_factura DESC";
                
        $res = $conn->query($sql);
        $data = [];
        
        while($row = $res->fetch_assoc()) {
            // LÓGICA CORRECTA: Neto * 1.19
            $neto = (float)$row['neto_calculado'];
            $bruto = round($neto * 1.19);
            
            $row['total_fmt'] = "$" . number_format($bruto, 0, ',', '.');
            $row['fecha_fmt'] = (!empty($row['fecha_despacho']) && $row['fecha_despacho'] !== '0000-00-00') 
                ? date("d/m/Y", strtotime($row['fecha_despacho'])) : "S/F";
            
            $data[] = $row;
        }
        
        echo json_encode($data);
    } else {
        echo json_encode([]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>