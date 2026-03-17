<?php
require_once 'auth.php';
// api_gestion_folios.php - V7: RUTAS 100% SINCRONIZADAS CON PROCESAR_FACTURACION

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0);
error_reporting(E_ALL);

// =========================================================================
// 1. SISTEMA DE RUTAS CENTRALIZADO (COPIADO DE PROCESAR_FACTURACION)
// =========================================================================
$host_actual = $_SERVER['HTTP_HOST'] ?? '';
$ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/'); // Ej: /home/tabolang/erp.tabolango.cl

// Si estamos ejecutando desde el subdominio de Producción
if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
    // En Hostinger, el dominio principal está en public_html
    $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
} else {
    // Si estamos en LocalWP 
    $ruta_public = $ruta_raiz;
}

$ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
$CERT_PATH         = $ruta_base_uploads . "certificados/certificado.pfx"; 
$DIR_CAF           = $ruta_base_uploads . "certificados/";
// =========================================================================

// --- CONFIGURACIÓN API ---
$API_KEY     = "7165-N580-6393-2899-7690"; 
$RUT_EMPRESA = "77121854-7";
$RUT_CERT    = "8201627-9";
$CERT_PASS   = "Sofia2020";

$action = $_GET['action'] ?? 'status';

try {
    // Heredamos $conn desde auth.php
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Error de conexión a la BD");
    }

    if ($action == 'status') {
        $status = [];
        $types = [33 => 'Factura Electrónica', 52 => 'Guía Despacho', 61 => 'Nota Crédito'];
        
        foreach ($types as $code => $name) {
            $lastUsed = obtenerUltimoFolioBD($conn, $code);
            $infoCAF = analizarCAFsMultiples($DIR_CAF, $code, $lastUsed);
            
            $status[] = [
                'tipo' => $code, 
                'nombre' => $name, 
                'rango_desde' => $infoCAF['rango_min'],
                'rango_hasta' => $infoCAF['rango_max'], 
                'ultimo_usado' => $lastUsed,
                'disponibles_local' => $infoCAF['total_disponibles'], 
                'fecha_caf' => $infoCAF['ultima_fecha']
            ];
        }
        echo json_encode($status);

    } elseif ($action == 'check_sii') {
        // Bloqueo de consultas a SimpleAPI en entorno local
        if ($es_local ?? false) {
            echo json_encode(['status' => 'success', 'cantidad' => 999, 'message' => 'Simulación Local']);
            exit;
        }

        $tipo = $_GET['tipo'];
        $url = "https://servicios.simpleapi.cl/api/folios/get/$tipo/";
        $payload = json_encode([
            "RutCertificado" => $RUT_CERT,
            "Password" => $CERT_PASS,
            "RutEmpresa" => $RUT_EMPRESA,
            "Ambiente" => 1
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['input' => $payload, 'files' => new CURLFile($CERT_PATH, 'application/x-pkcs12', 'certificado.pfx')]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $res = trim($res);
        if (is_numeric($res)) {
            echo json_encode(['status' => 'success', 'cantidad' => (int)$res]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Respuesta inesperada del SII: " . $res]);
        }

    } elseif ($action == 'descargar_caf') {
        // Bloqueo de descargas reales en entorno local
        if ($es_local ?? false) {
            echo json_encode(['status' => 'success', 'message' => "SIMULACIÓN LOCAL: Descarga bloqueada en entorno local."]);
            exit;
        }

        $tipo = $_GET['tipo'];
        $cantidad = $_GET['cantidad'];
        
        $url = "https://servicios.simpleapi.cl/api/folios/get/$tipo/$cantidad";
        $payload = json_encode([
            "RutCertificado" => $RUT_CERT,
            "Password" => $CERT_PASS,
            "RutEmpresa" => $RUT_EMPRESA,
            "Ambiente" => 1
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['input' => $payload, 'files' => new CURLFile($CERT_PATH, 'application/x-pkcs12', 'certificado.pfx')]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $xmlContent = curl_exec($ch);
        curl_close($ch);

        if (strpos($xmlContent, '<?xml') !== false) {
            if (!is_dir($DIR_CAF)) mkdir($DIR_CAF, 0755, true); // Asegurar carpeta por si acaso
            $nuevoNombre = "caf_" . $tipo . "_" . time() . ".xml";
            file_put_contents($DIR_CAF . $nuevoNombre, $xmlContent);
            
            echo json_encode(['status' => 'success', 'message' => "Se han descargado $cantidad folios nuevos exitosamente."]);
        } else {
            $errorData = json_decode($xmlContent, true);
            $msg = $errorData['message'] ?? $errorData['error'] ?? "El SII rechazó la solicitud.";
            throw new Exception($msg);
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// =========================================================================
// FUNCIONES AUXILIARES 
// =========================================================================

function obtenerUltimoFolioBD($conn, $tipo) {
    $cols = [33 => 'numero_factura', 52 => 'numero_guia', 61 => 'numero_nc'];
    $col = $cols[$tipo];
    $res = $conn->query("SELECT MAX(CAST($col AS UNSIGNED)) as u FROM pedidos_activos WHERE $col > 0");
    $row = $res->fetch_assoc();
    return $row['u'] ? (int)$row['u'] : 0;
}

function analizarCAFsMultiples($dir, $tipo, $ultimoUsado) {
    // Usamos la misma estructura de búsqueda exacta de tu V5 exitosa
    $archivos = glob($dir . "*caf_" . $tipo . "*.xml");
    
    $total_disponibles = 0;
    $rango_min = null;
    $rango_max = null;
    $ultima_fecha = "N/A";

    if ($archivos) {
        foreach ($archivos as $archivo) {
            $xml = @simplexml_load_file($archivo);
            if ($xml && isset($xml->CAF->DA->RNG)) {
                $desde = (int)$xml->CAF->DA->RNG->D;
                $hasta = (int)$xml->CAF->DA->RNG->H;
                $fecha = (string)$xml->CAF->DA->FA;

                // Limpieza automática si el rango ya fue superado por el último usado en BD
                if ($hasta <= $ultimoUsado) {
                    @unlink($archivo);
                    continue; 
                }

                $folios_restantes_en_este_archivo = $hasta - max($ultimoUsado, $desde - 1);
                if ($folios_restantes_en_este_archivo > 0) {
                    $total_disponibles += $folios_restantes_en_este_archivo;
                    
                    if (is_null($rango_min) || $desde < $rango_min) $rango_min = $desde;
                    if (is_null($rango_max) || $hasta > $rango_max) $rango_max = $hasta;
                    $ultima_fecha = $fecha; 
                }
            }
        }
    }

    return [
        'total_disponibles' => $total_disponibles,
        'rango_min' => is_null($rango_min) ? 0 : $rango_min,
        'rango_max' => is_null($rango_max) ? 0 : $rango_max,
        'ultima_fecha' => $ultima_fecha
    ];
}
?>