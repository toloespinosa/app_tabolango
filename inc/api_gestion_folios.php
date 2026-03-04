<?php
require_once 'auth.php';
// api_gestion_folios.php - V4: PROTOCOLO DE SEGURIDAD Y ENDPOINTS CORREGIDOS

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0);
error_reporting(E_ALL);

// --- CONFIGURACIÓN (REVISA ESTOS DATOS) ---
$API_KEY     = "7165-N580-6393-2899-7690"; 
$RUT_EMPRESA = "77121854-7"; // Tu RUT Empresa (Tabolango)
$RUT_CERT    = "8201627-9"; // RUT dueño de la firma
$CERT_PASS   = "Sofia2020";
$CERT_PATH   = __DIR__ . "/uploads/certificados/certificado.pfx";

$PATHS_CAF = [
    33 => __DIR__ . "/uploads/certificados/caf_33.xml",
    52 => __DIR__ . "/uploads/certificados/caf_52.xml",
    61 => __DIR__ . "/uploads/certificados/caf_61.xml"
];

$conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? 'status';

try {
    if ($action == 'status') {
        $status = [];
        $types = [33 => 'Factura Electrónica', 52 => 'Guía Despacho', 61 => 'Nota Crédito'];
        foreach ($types as $code => $name) {
            $cafData = leerRangoCAF($PATHS_CAF[$code]);
            $lastUsed = obtenerUltimoFolioBD($conn, $code);
            $disponibles = ($cafData['hasta'] > 0) ? ($cafData['hasta'] - max($lastUsed, $cafData['desde'] - 1)) : 0;
            $status[] = [
                'tipo' => $code, 'nombre' => $name, 'rango_desde' => $cafData['desde'],
                'rango_hasta' => $cafData['hasta'], 'ultimo_usado' => $lastUsed,
                'disponibles_local' => max(0, $disponibles), 'fecha_caf' => $cafData['fecha']
            ];
        }
        echo json_encode($status);

    } elseif ($action == 'check_sii') {
        $tipo = $_GET['tipo'];
        // URL de consulta de disponibles para timbraje
        $url = "https://servicios.simpleapi.cl/api/folios/get/$tipo/";
        $payload = json_encode([
            "RutCertificado" => $RUT_CERT,
            "Password" => $CERT_PASS,
            "RutEmpresa" => $RUT_EMPRESA,
            "Ambiente" => 1
        ]);
        $res = curlSimpleAPI($url, $payload, $CERT_PATH, $API_KEY);
        // Si el resultado es puramente numérico (ej: 500), devolvemos éxito
        echo json_encode(['status' => 'success', 'cantidad' => is_numeric(trim($res)) ? (int)$res : $res]);

    } elseif ($action == 'descargar_caf') {
        $tipo = $_GET['tipo'];
        $cantidad = $_GET['cantidad'];
        
        // Endpoint corregido para descarga de CAF
        $url = "https://servicios.simpleapi.cl/api/folios/get/$tipo/$cantidad";
        
        $payload = json_encode([
            "RutCertificado" => $RUT_CERT,
            "Password" => $CERT_PASS,
            "RutEmpresa" => $RUT_EMPRESA,
            "Ambiente" => 1
        ]);

        $xmlContent = curlSimpleAPI($url, $payload, $CERT_PATH, $API_KEY);

        if (strpos($xmlContent, '<?xml') !== false) {
            file_put_contents($PATHS_CAF[$tipo], $xmlContent);
            echo json_encode(['status' => 'success', 'message' => 'CAF actualizado correctamente.']);
        } else {
            // Intentar leer el error JSON si el SII rechazó
            $errorData = json_decode($xmlContent, true);
            $msg = $errorData['message'] ?? $errorData['error'] ?? "El SII rechazó la solicitud (Posible falta de folios o RUT incorrecto).";
            throw new Exception($msg);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// FUNCIONES AUXILIARES
function leerRangoCAF($path) {
    if (!file_exists($path)) return ['desde' => 0, 'hasta' => 0, 'fecha' => 'N/A'];
    $xml = @simplexml_load_file($path);
    if (!$xml) return ['desde' => 0, 'hasta' => 0, 'fecha' => 'Error XML'];
    return [
        'desde' => (int)$xml->CAF->DA->RNG->D,
        'hasta' => (int)$xml->CAF->DA->RNG->H,
        'fecha' => (string)$xml->CAF->DA->FA
    ];
}

function obtenerUltimoFolioBD($conn, $tipo) {
    $cols = [33 => 'numero_factura', 52 => 'numero_guia', 61 => 'numero_nc'];
    $col = $cols[$tipo];
    $res = $conn->query("SELECT MAX(CAST($col AS UNSIGNED)) as u FROM pedidos_activos WHERE $col > 0");
    $row = $res->fetch_assoc();
    return $row['u'] ? (int)$row['u'] : 0;
}

function curlSimpleAPI($url, $jsonInput, $certPath, $apiKey) {
    $ch = curl_init($url);
    $postFields = [
        'input' => $jsonInput,
        'files' => new CURLFile($certPath, 'application/x-pkcs12', 'certificado.pfx')
    ];
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $apiKey]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Aumentar timeout por si el SII demora
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}
?>