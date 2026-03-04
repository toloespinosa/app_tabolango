<?php
require_once 'auth.php';
// 1. Configuración de errores
error_reporting(0);
ini_set('display_errors', 0);

// 2. HEADERS CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$servername = "localhost";
$username = "tabolang_app";
$password = 'm{Hpj.?IZL$Kz${S';
$dbname = "tabolang_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Error de conexión BD"]));
}
$conn->set_charset("utf8mb4");

// 3. LEER INPUT
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

// --- ACCIÓN 1: PROXY CONSULTA RUT (V2) ---
if ($action === 'consultar_rut') {
    $rut_recibido = $input['rut'] ?? $_GET['rut'] ?? '';
    $rut_formateado = trim(str_replace('.', '', $rut_recibido));

    if (strlen($rut_formateado) < 3) {
        echo json_encode(["error" => "RUT vacío", "recibido" => $rut_recibido]);
        $conn->close(); exit;
    }

    $apiKey = '7165-N580-6393-2899-7690';
    $url = "https://rut.simpleapi.cl/v2/" . $rut_formateado;

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => array('Authorization: ' . $apiKey),
    ));

    $respuesta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $respuesta) {
        $json = json_decode($respuesta);
        echo $json ? $respuesta : json_encode(["error" => "Respuesta no es JSON", "raw" => $respuesta]);
    } else {
        echo json_encode(["error" => "Error API", "code" => $httpCode]);
    }
    $conn->close(); exit;
}

// --- ACCIÓN 2: OBTENER CATEGORÍAS ---
if ($action === 'get_categories') {
    $result = $conn->query("SELECT id_categoria as id, nombre_categoria as nombre FROM categorias_clientes ORDER BY nombre_categoria ASC");
    $categorias = [];
    if ($result) { while($row = $result->fetch_assoc()) { $categorias[] = $row; } }
    echo json_encode($categorias);
    $conn->close(); exit;
}

// ... (Encabezados y conexión igual que siempre) ...

// --- ACCIÓN 3: CREAR CLIENTE (ADD_CLIENT) ---
if ($action === 'add_client') {
    $cliente      = $input['cliente'] ?? '';
    $razon_social = $input['razon_social'] ?? '';
    $giro         = $input['giro'] ?? '';
    $tipo_cliente = $input['tipo_cliente'] ?? 3; 
    $rut          = $input['rut'] ?? '';
    
    // Contacto General / Despacho
    $telefono     = $input['telefono'] ?? '';
    $email        = $input['email'] ?? '';
    $direccion    = $input['direccion'] ?? '';
    $ciudad       = $input['ciudad'] ?? '';
    $comuna       = $input['comuna'] ?? '';
    $lat_despacho = $input['lat_despacho'] ?? '';
    $lng_despacho = $input['lng_despacho'] ?? '';
    $contacto     = $input['contacto'] ?? '';
    $responsable  = $input['responsable'] ?? '';
    
    // Datos Facturación
    $dir_factura  = $input['direccion_factura'] ?? '';
    $ciu_factura  = $input['ciudad_factura'] ?? '';
    $com_factura  = $input['comuna_factura'] ?? '';
    $email_factura= $input['email_factura'] ?? '';
    $tel_factura  = $input['telefono_factura'] ?? '';
    
    // CAMPOS EXISTENTES (NOMBRE Y APELLIDO)
    $nombre_val   = $input['nombre'] ?? '';
    $apellido_val = $input['apellido'] ?? '';

    if (empty($cliente) || empty($responsable)) {
        die(json_encode(["success" => false, "error" => "Nombre y Responsable son obligatorios"]));
    }

    // QUERY FINAL: 21 Campos + activo hardcoded
    $sql = "INSERT INTO clientes (
                cliente, razon_social, giro, tipo_cliente, rut_cliente, 
                telefono, email, direccion, ciudad, comuna, 
                lat_despacho, lng_despacho, contacto, responsable, activo,
                direccion_factura, ciudad_factura, comuna_factura, email_factura, telefono_factura, 
                nombre, apellido
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    
    // "sssissssssssssssssssss" (21 letras: 1 'i' y 20 's')
    $stmt->bind_param("sssisssssssssssssssss", 
        $cliente, $razon_social, $giro, $tipo_cliente, $rut, 
        $telefono, $email, $direccion, $ciudad, $comuna, 
        $lat_despacho, $lng_despacho, $contacto, $responsable,
        $dir_factura, $ciu_factura, $com_factura, $email_factura, $tel_factura,
        $nombre_val, $apellido_val
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "id" => $conn->insert_id]);
    } else {
        echo json_encode(["success" => false, "error" => "Error BD: " . $stmt->error]);
    }
    $stmt->close();
    $conn->close(); exit;
}
?>