<?php
require_once 'auth.php';
// enviar_factura.php - V86: SOLO TELEFONO FACTURACION
// ESTRICTO: Solo envía a 'telefono_factura'.

mb_internal_encoding("UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// Carga de librerías
$rutas_posibles = [__DIR__ . '/vendor/autoload.php', __DIR__ . '/autoload.php'];
foreach ($rutas_posibles as $ruta) { if (file_exists($ruta)) { require_once $ruta; break; } }

use Twilio\Rest\Client;

// Conexión BD
$conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
$conn->set_charset("utf8mb4");

// ==============================================================================
// 🟢 BLOQUE SOLO MARCAR (SIN CAMBIOS)
// ==============================================================================
if (isset($_POST['solo_marcar']) && $_POST['solo_marcar'] == '1') {
    $idPedido = isset($_POST['id_pedido']) ? $conn->real_escape_string($_POST['id_pedido']) : '';

    if (!empty($idPedido)) {
        $sql = "UPDATE pedidos_activos SET whatsapp_enviado = NOW() WHERE id_pedido = '$idPedido'";
        
        if ($conn->query($sql)) {
            echo json_encode(["status" => "ok", "message" => "Marcado manualmente: $idPedido"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error BD: " . $conn->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "ID Pedido no recibido"]);
    }
    exit; 
}
// ==============================================================================

// --- CONFIGURACIÓN TWILIO ---
$sid    = "TWILIO_ACCOUNT_SID";
$token  = "TWILIO_AUTH_TOKEN"; 
$twilio = new Client($sid, $token);
$templateSid = "HX0df30bf3bb6c43a5c2b0ff85b1f82fc8"; 

// --- RECEPCIÓN DE DATOS ---
// AQUI EL CAMBIO: Capturamos telefono_factura
$telefonoFactura = $_POST['telefono_factura'] ?? ''; 
$urlRecibida     = $_POST['url_pdf'] ?? ''; 
$idPedido        = $_POST['id_pedido'] ?? '0'; 
$nombreSaludo    = $_POST['nombre'] ?? '';  
$folioPost       = $_POST['folio'] ?? 'SN'; 

try {
    // 1. VALIDACIONES
    if (empty($urlRecibida)) throw new Exception("URL del PDF vacía.");
    
    // Validación estricta del teléfono de facturación
    if (empty($telefonoFactura)) {
        throw new Exception("Error: No se recibió 'telefono_factura'. No se enviará mensaje.");
    }

    // 2. PREPARACIÓN DE VARIABLES
    $nombreArchivo = basename($urlRecibida);
    $urlFinal = $urlRecibida . "?v=" . time(); // Anti-caché
    $variableSaludo = !empty(trim($nombreSaludo)) ? "" . trim($nombreSaludo) : "";

    // 3. ENVÍO A TWILIO (Usando $telefonoFactura)
    // Nota: Asegúrate de que el front envíe el formato +569...
    // Si el front no manda el +, podrías agregarlo aquí: 
    // if (strpos($telefonoFactura, '+') === false) $telefonoFactura = "+" . $telefonoFactura;

    $message = $twilio->messages->create("whatsapp:" . $telefonoFactura, [
        "from" => "whatsapp:+12178583230",
        "contentSid" => $templateSid, 
        "contentVariables" => json_encode([
            "1" => (string)$variableSaludo, 
            "2" => (string)$folioPost,
            "3" => (string)$nombreArchivo 
        ]),
        "mediaUrl" => [$urlFinal]
    ]);

    // 4. ACTUALIZAR BD
    if (!empty($idPedido) && $idPedido !== '0') {
        $conn->query("UPDATE pedidos_activos SET whatsapp_enviado = NOW() WHERE id_pedido = '$idPedido'");
    }

    echo json_encode([
        "status" => "ok", 
        "archivo_enviado" => $nombreArchivo,
        "enviado_a" => $telefonoFactura
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>