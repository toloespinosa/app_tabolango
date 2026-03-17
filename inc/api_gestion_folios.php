<?php
require_once 'auth.php';
// api_gestion_folios.php - V6.3: LECTURA ROBUSTA DE XMLs Y RUTAS ABSOLUTAS

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
ini_set('display_errors', 0);
error_reporting(E_ALL);

// 🔥 DETECCIÓN DE ENTORNO Y RUTAS CRÍTICAS 🔥
$host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ngrok') !== false || strpos($host, '.local') !== false);

// Usamos DOCUMENT_ROOT para apuntar a la raíz del servidor
$ruta_public = $_SERVER['DOCUMENT_ROOT']; 
$ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';

// --- CONFIGURACIÓN DE RUTAS ---
$CERT_PATH = $ruta_base_uploads . "certificados/certificado.pfx";
$DIR_CAF   = $ruta_base_uploads . "certificados/";

// --- CONFIGURACIÓN API ---
$API_KEY     = "7165-N580-6393-2899-7690"; 
$RUT_EMPRESA = "77121854-7";
$RUT_CERT    = "8201627-9";
$CERT_PASS   = "Sofia2020";

try {
    // Verificación de conexión
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Error de conexión a la BD heredada");
    }

    if (!is_dir($DIR_CAF)) {
        mkdir($DIR_CAF, 0755, true);
    }

    $action = $_GET['action'] ?? 'status';

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
        $tipo = $_GET['tipo'];

        if ($is_local) {
            echo json_encode(['status' => 'success', 'cantidad' => 999, 'message' => 'Simulación Local']);
            exit;
        }

        if (!file_exists($CERT_PATH)) {
            throw new Exception("Certificado no encontrado en: " . $CERT_PATH);
        }

        $url = "https://servicios.simpleapi.cl/api/folios/get/$tipo/";
        $payload = json_encode([
            "RutCertificado" => $RUT_CERT,
            "Password" => $CERT_PASS,
            "RutEmpresa" => $RUT_EMPRESA,
            "Ambiente" => 1
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'input' => $payload, 
            'files' => new CURLFile($CERT_PATH, 'application/x-pkcs12', 'certificado.pfx')
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $res = trim($res);
        if (is_numeric($res)) {
            echo json_encode(['status' => 'success', 'cantidad' => (int)$res]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "Respuesta SII: " . $res]);
        }

    } elseif ($action == 'descargar_caf') {
        $tipo = $_GET['tipo'];
        $cantidad = $_GET['cantidad'];

        if ($is_local) {
            echo json_encode(['status' => 'success', 'message' => "SIMULACIÓN LOCAL: Se simula descarga de $cantidad folios nuevos."]);
            exit;
        }
        
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
// FUNCIONES AUXILIARES INTELIGENTES
// =========================================================================

function obtenerUltimoFolioBD($conn, $tipo) {
    $cols = [33 => 'numero_factura', 52 => 'numero_guia', 61 => 'numero_nc'];
    $col = $cols[$tipo] ?? null;
    if (!$col) return 0;
    
    $res = $conn->query("SELECT MAX(CAST($col AS UNSIGNED)) as u FROM pedidos_activos WHERE $col > 0");
    if ($res && $row = $res->fetch_assoc()) {
        return $row['u'] ? (int)$row['u'] : 0;
    }
    return 0;
}

function analizarCAFsMultiples($dir, $tipo, $ultimoUsado) {
    // 1. Buscamos TODOS los XML en la carpeta, sin importar el nombre
    $archivos = glob($dir . "*.xml");
    if (!$archivos) return ['total_disponibles' => 0, 'rango_min' => 0, 'rango_max' => 0, 'ultima_fecha' => 'N/A'];
    
    $total_disponibles = 0;
    $rango_min = null;
    $rango_max = null;
    $ultima_fecha = "N/A";

    foreach ($archivos as $archivo) {
        $xml = @simplexml_load_file($archivo);
        if (!$xml) continue;

        // 2. Extraemos el nodo DA (Resuelve el problema si la raíz es <AUTORIZACION> o directamente <CAF>)
        $da = null;
        if (isset($xml->CAF->DA)) {
            $da = $xml->CAF->DA;
        } elseif (isset($xml->DA)) {
            $da = $xml->DA;
        }

        // 3. Verificamos que sea un CAF válido y del tipo que estamos iterando
        if ($da && isset($da->TD) && (int)$da->TD === (int)$tipo && isset($da->RNG)) {
            $desde = (int)$da->RNG->D;
            $hasta = (int)$da->RNG->H;
            $fecha = (string)$da->FA;

            // 4. Si el CAF ya se consumió completo, lo ignoramos
            if ($hasta <= $ultimoUsado) {
                continue; 
            }

            $folios_restantes = $hasta - max($ultimoUsado, $desde - 1);
            if ($folios_restantes > 0) {
                $total_disponibles += $folios_restantes;
                
                if (is_null($rango_min) || $desde < $rango_min) $rango_min = $desde;
                if (is_null($rango_max) || $hasta > $rango_max) $rango_max = $hasta;
                $ultima_fecha = $fecha; 
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