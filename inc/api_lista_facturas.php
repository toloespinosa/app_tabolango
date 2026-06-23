<?php
// api_facturas_emitidas.php
require_once 'auth.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    if (!isset($conn) || $conn->connect_error) throw new Exception("Error de conexión");

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    // Detectamos si existen las columnas de NC para no romper el SQL
    $cols = $conn->query("SHOW COLUMNS FROM pedidos_activos LIKE 'estado_nota_credito'");
    $col_nc = ($cols && $cols->num_rows > 0) ? "estado_nota_credito" : "'' as estado_nota_credito";
    $cols_url = $conn->query("SHOW COLUMNS FROM pedidos_activos LIKE 'url_nc'");
    $col_url_nc = ($cols_url && $cols_url->num_rows > 0) ? "url_nc" : "'' as url_nc";

    // ── Fuente 1: facturas generadas desde pedidos (flujo automático) ──
    $sql_pedidos = "SELECT
                MAX(id_pedido) as id_pedido,
                numero_factura,
                MAX(fecha_despacho) as fecha_despacho,
                MAX(cliente) as cliente,
                MAX(url_factura) as url_factura,
                MAX($col_nc) as estado_nota_credito,
                MAX($col_url_nc) as url_nc,
                SUM(precio_unitario * cantidad) as neto_calculado,
                0 as es_manual,
                '33' as tipo_documento
            FROM pedidos_activos
            WHERE numero_factura > 0
            GROUP BY numero_factura";

    // ── Fuente 2: DTEs emitidos manualmente (sin pedido asociado) ──
    // Solo Facturas Afectas (33). Las Facturas de Compra (46) son COMPRAS,
    // por lo que aparecen en el gestor de Recibidas, no aquí.
    $sql_manuales = "SELECT
                CONCAT('MAN-', id) as id_pedido,
                folio as numero_factura,
                DATE(fecha_emision) as fecha_despacho,
                IFNULL(cliente_razon_social, '— Sin cliente —') as cliente,
                url_pdf as url_factura,
                '' as estado_nota_credito,
                '' as url_nc,
                IFNULL(monto_neto, 0) as neto_calculado,
                1 as es_manual,
                tipo_documento
            FROM dte_emitidos
            WHERE es_manual = 1
              AND tipo_documento = '33'
              AND folio > 0";

    $sql = "($sql_pedidos) UNION ALL ($sql_manuales)
            ORDER BY fecha_despacho DESC, CAST(numero_factura AS UNSIGNED) DESC
            LIMIT $limit OFFSET $offset";

    $res = $conn->query($sql);
    if (!$res) throw new Exception("Error SQL: " . $conn->error);

    $data = [];
    while($row = $res->fetch_assoc()) {
        $neto = (float)$row['neto_calculado'];
        // Para los manuales el monto_neto ya es entero; para los de pedidos
        // se calcula como suma de precio*cantidad y se suma IVA aparte.
        $totalConIva = $row['es_manual']
            ? round($neto * 1.19)        // manual: neto sin IVA, le sumamos 19%
            : round($neto * 1.19);       // automático: igual fórmula histórica
        $row['total_fmt'] = "$" . number_format($totalConIva, 0, ',', '.');
        $row['fecha_fmt'] = (!empty($row['fecha_despacho']) && $row['fecha_despacho'] !== '0000-00-00')
            ? date("d/m/Y", strtotime($row['fecha_despacho'])) : "S/F";
        $data[] = $row;
    }
    echo json_encode($data);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}