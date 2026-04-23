<?php
require_once 'auth.php';
// enviar_factura.php - V87: AUTOLOAD BLINDADO, WP-CONFIG Y LIMPIEZA DE TELÉFONO
// ESTRICTO: Solo envía a 'telefono_factura'.

mb_internal_encoding("UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// --- 1. BLINDAJE DEL AUTOLOAD ---
$rutas_posibles = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',       
    __DIR__ . '/../../vendor/autoload.php',    
    __DIR__ . '/autoload.php'
];
$autoload_encontrado = false;
foreach ($rutas_posibles as $ruta) { 
    if (file_exists($ruta)) { require_once $ruta; $autoload_encontrado = true; break; } 
}
if (!$autoload_encontrado) {
    echo json_encode(["status" => "error", "message" => "Falta autoload.php en enviar_factura"]);
    exit;
}

use Twilio\Rest\Client;

// --- 2. CARGAR WP-CONFIG PARA LAS CREDENCIALES ---
$wp_root = substr(__DIR__, 0, strpos(__DIR__, 'wp-content'));
if (file_exists($wp_root . 'wp-config.php')) {
    require_once $wp_root . 'wp-config.php';
} else {
    echo json_encode(["status" => "error", "message" => "No se encontró wp-config.php para leer las credenciales."]);
    exit;
}

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

// --- 4. CONFIGURACIÓN TWILIO (Desde wp-config.php) ---
$sid    = TWILIO_ACCOUNT_SID;
$token  = TWILIO_AUTH_TOKEN; 
$twilio = new Client($sid, $token);
$templateSid = "HX0df30bf3bb6c43a5c2b0ff85b1f82fc8"; 

// --- RECEPCIÓN DE DATOS ---
$telefonoFactura = $_POST['telefono_factura'] ?? ''; 
$urlRecibida     = $_POST['url_pdf'] ?? ''; 
$idPedido        = $_POST['id_pedido'] ?? '0'; 
$nombreSaludo    = $_POST['nombre'] ?? '';  
$folioPost       = $_POST['folio'] ?? 'SN'; 

// --- DETECCIÓN DE ENTORNO LOCAL ---
$host_req = $_SERVER['HTTP_HOST'] ?? '';
$es_entorno_local = (strpos($host_req, 'localhost') !== false || strpos($host_req, '127.0.0.1') !== false || strpos($host_req, '.local') !== false);

try {
    // 1. VALIDACIONES
    if (empty($urlRecibida)) throw new Exception("URL del PDF vacía.");
    
    // Validación estricta del teléfono de facturación
    if (empty($telefonoFactura)) {
        throw new Exception("Error: No se recibió 'telefono_factura'. No se enviará mensaje.");
    }

    // --- LIMPIEZA Y FORMATO DE TELÉFONO ---
    $telefonoFactura = preg_replace('/[^0-9+]/', '', $telefonoFactura);
    if (strpos($telefonoFactura, '+') === false && strlen($telefonoFactura) >= 11) {
        $telefonoFactura = "+" . $telefonoFactura;
    }

    // 2. PREPARACIÓN DE VARIABLES
    $nombreArchivo = basename($urlRecibida);
    $urlFinal = $urlRecibida . "?v=" . time(); // Anti-caché
    $variableSaludo = !empty(trim($nombreSaludo)) ? "" . trim($nombreSaludo) : "";

    // 3. ENVÍO A TWILIO (SOLO EN PRODUCCIÓN)
    if (!$es_entorno_local) {
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
    }

    // 4. ACTUALIZAR BD
    if (!empty($idPedido) && $idPedido !== '0') {
        $conn->query("UPDATE pedidos_activos SET whatsapp_enviado = NOW() WHERE id_pedido = '$idPedido'");
    }

    // Mensaje de éxito dinámico
    $msg_envio = $es_entorno_local ? "Simulación WhatsApp OK (Modo Local)" : "Enviado a " . $telefonoFactura;

    echo json_encode([
        "status" => "ok", 
        "archivo_enviado" => $nombreArchivo,
        "enviado_a" => $msg_envio
    ]);

} catch (Throwable $e) {
    http_response_code(400); // Lanzar 400 exacto para JS
    echo json_encode(["status" => "error", "message" => "CRÍTICO: " . $e->getMessage()]);
}
?>