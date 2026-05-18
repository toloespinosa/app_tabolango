<?php
// api_combustible.php
require_once 'auth.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    if (!isset($conn) || $conn->connect_error) throw new Exception("Error de conexión a la BD");

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';

    if ($method === 'GET' && $action === 'dashboard') {
        
        $filtro = isset($_GET['filtro']) ? $conn->real_escape_string($_GET['filtro']) : '';
        $mes = isset($_GET['mes']) ? $conn->real_escape_string($_GET['mes']) : '';
        $anio = isset($_GET['anio']) ? $conn->real_escape_string($_GET['anio']) : '';

        $where_abonos = "1=1";
        $where_consumos = "1=1";

        if (!empty($mes) && !empty($anio)) {
            $where_abonos .= " AND MONTH(fecha) = '$mes' AND YEAR(fecha) = '$anio'";
            $where_consumos .= " AND MONTH(fecha_emision) = '$mes' AND YEAR(fecha_emision) = '$anio'";
        } elseif (!empty($anio)) {
            $where_abonos .= " AND YEAR(fecha) = '$anio'";
            $where_consumos .= " AND YEAR(fecha_emision) = '$anio'";
        }

        // 1. SALDO HISTÓRICO GLOBAL
        $res_ab_hist = $conn->query("SELECT IFNULL(SUM(monto), 0) as total FROM abonos_combustible");
        $res_co_hist = $conn->query("SELECT IFNULL(SUM(monto_total), 0) as total FROM consumo_combustible");
        $saldo_historico = $res_ab_hist->fetch_assoc()['total'] - $res_co_hist->fetch_assoc()['total'];

        // 2. TOTALES DEL PERÍODO FILTRADO
        $res_ab_filt = $conn->query("SELECT IFNULL(SUM(monto), 0) as total FROM abonos_combustible WHERE $where_abonos");
        $abonos_periodo = $res_ab_filt->fetch_assoc()['total'];

        $res_co_filt = $conn->query("SELECT IFNULL(SUM(monto_total), 0) as total FROM consumo_combustible WHERE $where_consumos");
        $consumos_periodo = $res_co_filt->fetch_assoc()['total'];

        // 3. CARTOLA HISTÓRICA MIXTA (UNION ALL)
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        
        $sql = "
            (SELECT 
                'CONSUMO' as tipo_mov,
                folio as numero,
                fecha_emision as fecha,
                patente as identificador,
                tipo_combustible as detalle,
                litros,
                precio_litro,
                monto_total as monto,
                url_xml as documento,
                '' as nota
            FROM consumo_combustible 
            WHERE $where_consumos " . (!empty($filtro) ? " AND (patente LIKE '%$filtro%' OR folio LIKE '%$filtro%')" : "") . ")
            
            UNION ALL
            
            (SELECT 
                'ABONO' as tipo_mov,
                id as numero,
                DATE(fecha) as fecha,
                usuario as identificador,
                'Depósito / Abono manual' as detalle,
                0.000 as litros,
                0.00 as precio_litro,
                monto,
                '' as documento,
                nota
            FROM abonos_combustible
            WHERE $where_abonos " . (!empty($filtro) ? " AND (nota LIKE '%$filtro%' OR id LIKE '%$filtro%' OR usuario LIKE '%$filtro%')" : "") . ")
            
            ORDER BY fecha DESC, numero DESC LIMIT $limit
        ";
        
        $res_lista = $conn->query($sql);
        $cartola = [];
        
        while($row = $res_lista->fetch_assoc()) {
            $row['fecha_fmt'] = date("d/m/Y", strtotime($row['fecha']));
            $row['monto_fmt'] = "$" . number_format($row['monto'], 0, ',', '.');
            $row['litros_fmt'] = $row['litros'] > 0 ? number_format($row['litros'], 2, ',', '.') . " Lts" : '';
            $cartola[] = $row;
        }

        echo json_encode([
            "status" => "success",
            "billetera" => [
                "saldo" => $saldo_historico,
                "saldo_fmt" => "$" . number_format($saldo_historico, 0, ',', '.'),
                "abonos_fmt" => "$" . number_format($abonos_periodo, 0, ',', '.'),
                "consumos_fmt" => "$" . number_format($consumos_periodo, 0, ',', '.')
            ],
            "historial" => $cartola
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'abonar') {
        $input = json_decode(file_get_contents('php://input'), true);
        $monto = (int)($input['monto'] ?? 0);
        $nota = $conn->real_escape_string($input['nota'] ?? 'Abono manual');
        $usuario = $conn->real_escape_string($input['usuario'] ?? 'Administrador');

        if ($monto <= 0) throw new Exception("El monto debe ser mayor a cero.");

        $stmt = $conn->prepare("INSERT INTO abonos_combustible (monto, usuario, nota) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $monto, $usuario, $nota);
        $stmt->execute();

        echo json_encode(["status" => "success", "message" => "Abono registrado correctamente."]);
        exit;
    }

    throw new Exception("Acción no válida");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>