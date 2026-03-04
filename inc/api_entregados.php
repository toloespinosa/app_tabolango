<?php
// api_entregados.php - V68: CON DATOS DE FACTURACI�N (CORREGIDO)
ob_start(); 

ini_set('display_errors', 0);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    ob_end_clean(); http_response_code(200); exit;
}

require_once 'auth.php'; 

$limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 9;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$fecha  = isset($_GET['fecha']) ? $_GET['fecha'] : ''; 

// --- PASO A: IDs (Sin cambios) ---
$sql_ids = "SELECT P.id_pedido 
            FROM pedidos_activos P 
            LEFT JOIN productos PR ON P.id_producto = PR.id_producto 
            WHERE P.estado = 'Entregado'";

$params = [];
$types = "";

if (!empty($fecha)) {
    $sql_ids .= " AND DATE(P.fecha_despacho) = ?";
    array_push($params, $fecha);
    $types .= "s";
}

if (!empty($search)) {
    // Agregamos busqueda por folio tambi�n
    $sql_ids .= " AND (P.cliente LIKE ? OR P.id_pedido LIKE ? OR PR.producto LIKE ? OR P.numero_factura LIKE ?)";
    $term = "%" . $search . "%";
    array_push($params, $term, $term, $term, $term);
    $types .= "ssss";
}

$sql_ids .= " GROUP BY P.id_pedido, P.fecha_despacho ORDER BY P.fecha_despacho DESC, P.id_pedido DESC LIMIT ? OFFSET ?";

array_push($params, $limit, $offset);
$types .= "ii";

$stmt = $conn->prepare($sql_ids);
if(!$stmt) {
    ob_end_clean(); echo json_encode(["error" => "Error SQL Paso A: " . $conn->error]); exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res_ids = $stmt->get_result();

$ids_encontrados = [];
while($row = $res_ids->fetch_assoc()) {
    $ids_encontrados[] = "'" . $conn->real_escape_string($row['id_pedido']) . "'";
}

if (empty($ids_encontrados)) {
    ob_end_clean(); echo json_encode([]); exit;
}

// --- PASO B: DATOS COMPLETOS (AHORA INCLUYE FACTURACI�N) ---
$ids_string = implode(",", $ids_encontrados);

$sql_full = "SELECT P.*, 
                P.whatsapp_enviado,
                PR.unidad AS unidad_real, 
                PR.calibre, 
                PR.formato, 
                PR.Variedad, 
                PR.color_diferenciador,
                
                -- DATOS NORMALES
                C.telefono AS telefono_real,
                C.nombre AS nombre_persona,      
                C.apellido AS apellido_persona,
                C.razon_social AS razon_social_real,
                C.email AS email_real,
                C.direccion AS direccion_real,
                C.comuna AS comuna_real,
                C.ciudad AS ciudad_real,

                -- DATOS FACTURACION (LOS NUEVOS)
                C.email_factura,
                C.telefono_factura,
                C.direccion_factura,
                C.comuna_factura,
                C.ciudad_factura
                
        FROM pedidos_activos P
        LEFT JOIN productos PR ON P.id_producto = PR.id_producto
        LEFT JOIN clientes C ON P.id_interno_cliente = C.id_interno 
        
        WHERE P.id_pedido IN ($ids_string)
        ORDER BY P.fecha_despacho DESC, P.id_pedido DESC"; 

$res_full = $conn->query($sql_full);

if (!$res_full) {
    ob_end_clean(); echo json_encode(["error" => "Error SQL Paso B: " . $conn->error]); exit;
}

$data = [];

while($row = $res_full->fetch_assoc()) {
    // Ajustes visuales de producto
    $row['unidad'] = (!empty($row['unidad_real'])) ? $row['unidad_real'] : 'Un';
    $row['color_diferenciador'] = $row['color_diferenciador'] ?: '#0F4B29';
    
    if (empty($row['whatsapp_enviado']) || $row['whatsapp_enviado'] == '0000-00-00 00:00:00') {
        $row['whatsapp_enviado'] = null;
    }
    
    // Nombres
    $nombrePersonaFull = trim(($row['nombre_persona'] ?? '') . ' ' . ($row['apellido_persona'] ?? ''));
    $row['saludo_whatsapp'] = !empty($row['nombre_persona']) ? $row['nombre_persona'] : '';
    $row['contacto_completo'] = !empty($nombrePersonaFull) ? $nombrePersonaFull : '';
    $row['nombre_contacto']   = $row['contacto_completo'];
    $row['razon_social'] = !empty($row['razon_social_real']) ? $row['razon_social_real'] : 'S/R Social';

    // --- 1. EMAILS ---
    $row['email_cliente'] = trim($row['email_real'] ?? ''); // Contacto
    $row['email_factura'] = trim($row['email_factura'] ?? ''); // Facturaci�n (PRIORIDAD)

    // --- 2. TEL�FONOS ---
    $row['telefono'] = trim($row['telefono_real'] ?? ''); // Contacto
    $row['telefono_factura'] = trim($row['telefono_factura'] ?? ''); // Facturaci�n (PRIORIDAD)

    // --- 3. DIRECCI�N (LOGICA "TODO O NADA") ---
    $dir_fac = trim($row['direccion_factura'] ?? '');
    $com_fac = trim($row['comuna_factura'] ?? '');
    $ciu_fac = trim($row['ciudad_factura'] ?? '');

    $usar_facturacion = (!empty($dir_fac) && !empty($com_fac) && !empty($ciu_fac));

    if ($usar_facturacion) {
        $row['direccion_final'] = $dir_fac;
        $row['comuna_final']    = $com_fac;
        $row['ciudad_final']    = $ciu_fac;
        $row['origen_direccion']= 'factura';
    } else {
        $row['direccion_final'] = trim($row['direccion_real'] ?? '');
        $row['comuna_final']    = trim($row['comuna_real'] ?? '');
        $row['ciudad_final']    = trim($row['ciudad_real'] ?? '');
        $row['origen_direccion']= 'contacto';
    }

    $data[] = $row;
}

$conn->close();
ob_end_clean();
echo json_encode($data);
exit;
?>