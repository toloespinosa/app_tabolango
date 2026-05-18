<?php
// cron_sync_recibidas.php
// Script automático para sincronizar Facturas Recibidas (Compras) desde el SII vía SimpleAPI

require_once 'auth.php'; // Para usar $conn heredado

// --- 1. SISTEMA DE RUTAS CENTRALIZADAS (Homologado con Facturación) ---
$host_actual = $_SERVER['HTTP_HOST'] ?? '';
$ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

// Si estamos ejecutando desde el subdominio de Producción (ERP)
if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
    $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
} else {
    $ruta_public = $ruta_raiz; // LocalWP
}

$ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
$PATH_CERT = $ruta_base_uploads . "certificados/certificado.pfx"; 
// -----------------------------------------------------------------------

// --- 2. CREDENCIALES EXACTAS DE TABOLANGO ---
$url_base = 'https://servicios.simpleapi.cl/api/RCV/compras/';
$API_KEY = "7165-N580-6393-2899-7690"; 
$RUT_EMISOR = "77121854-7";       // RUT Tabolango SpA
$RUT_CERTIFICADO = "8201627-9";   // RUT Dueño del certificado
$PASS_CERTIFICADO = "Sofia2020";  // Clave
$AMBIENTE = 1;                    // 1 Producción, 0 Certificación

// Verificación rápida del certificado
if (!file_exists($PATH_CERT)) {
    die("[ERROR] No se encuentra el certificado en: " . $PATH_CERT . "\n");
}

// 3. Determinar el mes y año actual
$mes = date('m');
$anio = date('Y');
$endpoint = $url_base . $mes . '/' . $anio; // Ej: .../compras/05/2026

// 4. Armar el JSON de entrada según tu conexión original
$input_data = json_encode([
    "RutCertificado" => $RUT_CERTIFICADO,
    "RutEmpresa" => $RUT_EMISOR,
    "Ambiente" => $AMBIENTE,
    "Password" => $PASS_CERTIFICADO
], JSON_UNESCAPED_UNICODE);

// 5. Configurar la petición cURL
$curl = curl_init();
$cfile = new CURLFile($PATH_CERT, 'application/x-pkcs12', 'certificado.pfx');

curl_setopt_array($curl, array(
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 120, // Demora entre 40 y 120 seg según la doc
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
        'input' => $input_data,
        'files' => $cfile
    ),
    CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $API_KEY 
    ),
));

// Limpiamos pantalla para asegurar que el plugin de SweetAlert lea bien la respuesta
ob_clean();

$response = curl_exec($curl);
$httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("[ERROR cURL] $err\n");
}

if ($httpcode !== 200) {
    die("[ERROR API] Respondió con código $httpcode: $response\n");
}

$data = json_decode($response, true);

// 6. Procesar e Insertar en la Base de Datos Local
if (isset($data['compras']['detalleCompras']) && is_array($data['compras']['detalleCompras'])) {
    
    $facturas = $data['compras']['detalleCompras'];
    $insertadas = 0;
    $actualizadas = 0;

    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
    $carpeta_xml = "uploads/recibidos_xml/";

    $stmt = $conn->prepare("
        INSERT INTO facturas_recibidas (folio, fecha_emision, proveedor, rut_proveedor, total_bruto, url_xml, estado_acuse) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            total_bruto = VALUES(total_bruto),
            proveedor = VALUES(proveedor),
            fecha_emision = VALUES(fecha_emision),
            url_xml = IFNULL(facturas_recibidas.url_xml, VALUES(url_xml)),
            estado_acuse = IF(VALUES(estado_acuse) = 'ACEPTADA', 'ACEPTADA', facturas_recibidas.estado_acuse)
    ");

    foreach ($facturas as $fac) {
        $folio = (int)($fac['folio'] ?? 0);
        
        $fecha_raw = $fac['fechaEmision'] ?? date('Y-m-d');
        $fecha_emision = substr($fecha_raw, 0, 10); 
        
        $proveedor = $fac['razonSocial'] ?? 'Proveedor Desconocido';
        $rut_proveedor = $fac['rutProveedor'] ?? '';
        $total_bruto = (int)($fac['montoTotal'] ?? 0);

        if ($folio === 0 || empty($rut_proveedor)) continue;

        // 🔥 DETECCIÓN DE FORMA DE PAGO DESDE EL SII 🔥
        // 1: Contado | 2: Crédito | 3: Sin Costo (Gratuito)
        $fma_pago = isset($fac['formaPago']) ? (int)$fac['formaPago'] : (isset($fac['fmaPago']) ? (int)$fac['fmaPago'] : 2);
        
        // Si es Contado o Sin Costo, el SII asume que ya está aceptada
        $estado_inicial = ($fma_pago === 1 || $fma_pago === 3) ? 'ACEPTADA' : 'PENDIENTE';

        // Pre-calculamos el nombre del XML
        $rut_formateado = str_replace(['.', '-'], '', $rut_proveedor);
        $nombre_archivo_xml = "REC_33_" . $rut_formateado . "_" . $folio . ".xml";
        $url_xml_calculada = $DOMINIO_LIMPIO . $carpeta_xml . $nombre_archivo_xml;

        // Enviamos los 7 parámetros
        $stmt->bind_param("isssiss", $folio, $fecha_emision, $proveedor, $rut_proveedor, $total_bruto, $url_xml_calculada, $estado_inicial);
        $stmt->execute();

        if ($stmt->affected_rows === 1) {
            $insertadas++;
        } elseif ($stmt->affected_rows === 2) {
            $actualizadas++;
        }
    }
    
    echo "[EXITO] Proceso finalizado. Nuevas: $insertadas | Actualizadas/Sanadas: $actualizadas\n";

} else {
    echo "[AVISO] No hay facturas de compras registradas en este período.\n";
}
?>