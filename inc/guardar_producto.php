<?php
require_once 'auth.php';
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { http_response_code(200); exit; }

$servername = "localhost"; 
$username = "tabolang_app"; 
$password = 'm{Hpj.?IZL$Kz${S'; 
$dbname = "tabolang_pedidos";

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die(json_encode(["success" => false, "error" => "Conexión fallida"]));
}

// 1. Capturamos los datos básicos
$id_producto         = !empty($_POST['id_producto']) ? $_POST['id_producto'] : null;
$producto            = $_POST['producto'] ?? null;
$icono               = $_POST['icono'] ?? '';
$unidad              = $_POST['unidad'] ?? 'Bandeja';
$precio_actual       = (float)($_POST['precio_actual'] ?? 0);
$costo_actual        = (float)($_POST['costo_actual'] ?? 0);
$kg_por_unidad       = (float)($_POST['kg_por_unidad'] ?? 1.000);
$activo              = isset($_POST['activo']) ? (int)$_POST['activo'] : 1; 
$color_diferenciador = $_POST['color_diferenciador'] ?? '#0F4B29';

// 2. [NUEVO] Capturamos los campos nuevos
$variedad            = $_POST['variedad'] ?? '';
$calibre             = $_POST['calibre'] ?? '';
$formato             = $_POST['formato'] ?? '';

// RECALCULO
$precio_por_kilo = ($kg_por_unidad > 0) ? $precio_actual / $kg_por_unidad : 0;
$costo_por_kilo  = ($kg_por_unidad > 0) ? $costo_actual / $kg_por_unidad : 0;

try {
    if (empty($id_producto)) {
        // INSERTAR NUEVO (Agregados: variedad, calibre, formato)
        $stmt = $conn->prepare("INSERT INTO productos (producto, icono, unidad, precio_actual, costo_actual, precio_por_kilo, costo_por_kilo, kg_por_unidad, activo, color_diferenciador, variedad, calibre, formato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Tipos: s=string, d=double, i=integer
        // Estructura: sssdddddissss (13 variables)
        $stmt->bind_param("sssdddddissss", $producto, $icono, $unidad, $precio_actual, $costo_actual, $precio_por_kilo, $costo_por_kilo, $kg_por_unidad, $activo, $color_diferenciador, $variedad, $calibre, $formato);
        
    } else {
        // ACTUALIZAR EXISTENTE (Agregados: variedad, calibre, formato)
        $stmt = $conn->prepare("UPDATE productos SET producto=?, icono=?, unidad=?, precio_actual=?, costo_actual=?, precio_por_kilo=?, costo_por_kilo=?, kg_por_unidad=?, activo=?, color_diferenciador=?, variedad=?, calibre=?, formato=? WHERE id_producto=?");
        
        // Estructura: sssdddddissssi (13 variables + 1 ID al final)
        // NOTA: Asumo que id_producto es int ('i') por tu código anterior. Si fuera texto, cambia la última 'i' por 's'.
        $stmt->bind_param("sssdddddissssi", $producto, $icono, $unidad, $precio_actual, $costo_actual, $precio_por_kilo, $costo_por_kilo, $kg_por_unidad, $activo, $color_diferenciador, $variedad, $calibre, $formato, $id_producto);
    }

    if ($stmt->execute()) { 
        echo json_encode(["success" => true, "status" => "success"]); 
    } else { 
        echo json_encode(["success" => false, "error" => $stmt->error]); 
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

$conn->close();
?>