<?php
// scan_local_xml.php
// Escanea la carpeta local de XMLs, extrae los datos y detecta si es al Contado para auto-aceptar.

require_once 'auth.php'; 

$host_actual = $_SERVER['HTTP_HOST'] ?? '';
$ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
    $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
} else {
    $ruta_public = $ruta_raiz; 
}

$carpeta_xml_recibidos = "uploads/recibidos_xml/";
$path_destino_xml = rtrim($ruta_public, '/') . '/' . $carpeta_xml_recibidos;

if (!is_dir($path_destino_xml)) {
    die("❌ La carpeta $path_destino_xml no existe. Crea la carpeta y pon los XML ahí primero.");
}

$archivos_xml = glob($path_destino_xml . '*.xml');
$insertados = 0;

if (empty($archivos_xml)) {
    die("⚠️ No hay archivos .xml en la carpeta. Descarga algunos de Gmail y ponlos ahí.");
}

echo "Buscando XMLs en tu Mac local...<br><br>";

// 🔥 MODIFICADO: Agregamos lógica al ON DUPLICATE para que si el XML dice ACEPTADA, sobreescriba el PENDIENTE
$stmt = $conn->prepare("
    INSERT INTO facturas_recibidas (folio, fecha_emision, proveedor, rut_proveedor, total_bruto, url_xml, estado_acuse) 
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        total_bruto = VALUES(total_bruto),
        proveedor = VALUES(proveedor),
        fecha_emision = VALUES(fecha_emision),
        url_xml = VALUES(url_xml),
        estado_acuse = IF(VALUES(estado_acuse) = 'ACEPTADA', 'ACEPTADA', facturas_recibidas.estado_acuse)
");

foreach ($archivos_xml as $ruta_archivo) {
    $xml_raw = file_get_contents($ruta_archivo);
    $nombre_archivo = basename($ruta_archivo);

    // Limpieza radical de Namespaces
    $xml_clean = preg_replace('/xmlns="[^"]+"/', '', $xml_raw);
    $xml_clean = preg_replace('/xmlns:[a-zA-Z0-9_]+="[^"]+"/', '', $xml_clean);
    $xml_clean = preg_replace('/<[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '<$1', $xml_clean);
    $xml_clean = preg_replace('/<\/[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '</$1', $xml_clean);
    $xml_clean = preg_replace('/<Documento[^>]*>/', '<Documento>', $xml_clean);

    $xml_obj = @simplexml_load_string($xml_clean);
    if (!$xml_obj) {
        echo "<span style='color:red;'>Error leyendo: $nombre_archivo</span><br>";
        continue;
    }

    $nodos_encabezado = $xml_obj->xpath('//Encabezado');
    if (empty($nodos_encabezado)) {
        echo "<span style='color:orange;'>Saltado (No es factura): $nombre_archivo</span><br>";
        continue;
    }

    $encabezado = $nodos_encabezado[0];
    $tipo_dte = (int)($encabezado->IdDoc->TipoDTE ?? 0);
    
    // Filtramos solo Facturas Electrónicas (33)
    if ($tipo_dte !== 33) continue; 

    $folio = (int)($encabezado->IdDoc->Folio ?? 0);
    $fecha_raw = (string)($encabezado->IdDoc->FchEmis ?? date('Y-m-d'));
    $fecha_emision = substr($fecha_raw, 0, 10);
    
    $proveedor = (string)($encabezado->Emisor->RznSoc ?? 'Proveedor Desconocido');
    $rut_proveedor = (string)($encabezado->Emisor->RUTEmisor ?? '');
    $total_bruto = (int)($encabezado->Totales->MntTotal ?? 0);

    // 🔥 DETECCIÓN DE FORMA DE PAGO 🔥
    // 1: Contado | 2: Crédito | 3: Gratuito. (Si no viene la etiqueta, asumimos 2 para proteger el IVA)
    $fma_pago = (int)($encabezado->IdDoc->FmaPago ?? 2);
    $estado_inicial = ($fma_pago === 1 || $fma_pago === 3) ? 'ACEPTADA' : 'PENDIENTE';

    if ($folio === 0 || empty($rut_proveedor)) continue;

    // Renombramos el archivo al formato estándar del ERP
    $nuevo_nombre = "REC_33_" . str_replace(['.', '-'], '', $rut_proveedor) . "_" . $folio . ".xml";
    $nueva_ruta = $path_destino_xml . $nuevo_nombre;
    
    if ($nombre_archivo !== $nuevo_nombre) {
        rename($ruta_archivo, $nueva_ruta);
    }

    $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
    $url_xml_web = $DOMINIO_LIMPIO . $carpeta_xml_recibidos . $nuevo_nombre;

    // Pasamos el estado_inicial a la consulta
    $stmt->bind_param("isssiss", $folio, $fecha_emision, $proveedor, $rut_proveedor, $total_bruto, $url_xml_web, $estado_inicial);
    $stmt->execute();

    $badge_estado = ($estado_inicial === 'ACEPTADA') ? "<span style='color:green;'>[Auto-Aceptada al Contado]</span>" : "<span style='color:blue;'>[Crédito - Esperando Acuse]</span>";
    echo "✅ Procesada: Factura #$folio - $proveedor $badge_estado<br>";
    $insertados++;
}

echo "<br><b>[EXITO] Sincronización Local finalizada. Facturas indexadas: $insertados</b>";
?>