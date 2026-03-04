<?php
require_once 'auth.php';
// 1. Cabeceras de Permisos (CORS)
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 2. Configuración de conexión
$servername = "localhost";
$username = "tabolang_app";
$password = 'm{Hpj.?IZL$Kz${S';
$dbname = "tabolang_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);

// Forzar UTF-8
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die(json_encode(["error" => "Conexión fallida: " . $conn->connect_error]));
}

// 3. Consulta
// CORRECCIÓN: Usamos SELECT * para traer TODAS las columnas (incluyendo variedad y formato)
$sql = "SELECT * FROM productos ORDER BY producto ASC";
$result = $conn->query($sql);

$productos = [];
while($row = $result->fetch_assoc()) {
    // Formateamos los números para que el JS los reciba limpios
    $row['precio_actual'] = (float)$row['precio_actual'];
    $row['costo_actual']  = (float)$row['costo_actual'];
    $row['kg_por_unidad'] = (float)$row['kg_por_unidad']; 
    $row['activo']        = (int)$row['activo'];
    
    // Las columnas de texto (variedad, formato, calibre) pasan automáticamente
    // gracias al SELECT *
    
    $productos[] = $row;
}

// 4. Salida
echo json_encode($productos, JSON_UNESCAPED_UNICODE);
$conn->close();
?>