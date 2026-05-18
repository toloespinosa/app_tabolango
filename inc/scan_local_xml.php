<?php
// scan_local_xml.php (MODO DEBUG ACTIVADO)

// 1. Forzar a PHP a mostrar todos los errores en la pantalla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300); // Darle 5 minutos de tiempo por si es un archivo muy pesado

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
    die("❌ La carpeta $path_destino_xml no existe.");
}

$archivos_xml = glob($path_destino_xml . '*.xml');
$insertados_fac = 0;
$insertados_com = 0;

if (empty($archivos_xml)) {
    die("⚠️ No hay archivos .xml en la carpeta local.");
}

echo "Buscando XMLs en tu Mac local...<br><br>";
if (ob_get_level() > 0) ob_flush(); flush(); // Forzar a imprimir en pantalla AHORA

// Preparamos Query para Facturas
$stmt_facturas = $conn->prepare("
    INSERT INTO facturas_recibidas (folio, fecha_emision, proveedor, rut_proveedor, total_bruto, url_xml, estado_acuse) 
    VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')
    ON DUPLICATE KEY UPDATE 
        total_bruto = VALUES(total_bruto),
        proveedor = VALUES(proveedor),
        fecha_emision = VALUES(fecha_emision),
        url_xml = VALUES(url_xml),
        estado_acuse = IF(VALUES(estado_acuse) = 'ACEPTADA', 'ACEPTADA', facturas_recibidas.estado_acuse)
");

// Preparamos Query para Combustible (Copec)
$stmt_combustible = $conn->prepare("
    INSERT INTO consumo_combustible (folio, fecha_emision, rut_proveedor, patente, tipo_combustible, litros, precio_litro, monto_total, url_xml) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        patente = VALUES(patente),
        litros = VALUES(litros),
        monto_total = VALUES(monto_total),
        url_xml = VALUES(url_xml)
");

foreach ($archivos_xml as $ruta_archivo) {
    $nombre_archivo = basename($ruta_archivo);
    
    // 🔥 IMPRIMIMOS EL NOMBRE ANTES DE TOCARLO PARA VER DÓNDE SE PEGA 🔥
    echo "<i>Analizando: $nombre_archivo...</i> ";
    if (ob_get_level() > 0) ob_flush(); flush();

    if (strpos($nombre_archivo, 'REC_') === 0) {
        echo "<span style='color:gray;'>Ya procesado, saltando.</span><br>";
        continue;
    }

    try {
        // 🔥 ESCUDO ANTI-BLOQUEOS 🔥
        // 1. Si el archivo pesa más de 1 MB, no es una factura normal, lo saltamos.
        if (filesize($ruta_archivo) > 1024 * 1024) {
            echo "<span style='color:red;'>Archivo demasiado pesado (Más de 1MB), saltando.</span><br>";
            continue;
        }

        $xml_raw = file_get_contents($ruta_archivo);

        // 2. Si ni siquiera menciona el número 33 (Factura) o 52 (Guía), lo saltamos rapidísimo
        if (strpos($xml_raw, '>33<') === false && strpos($xml_raw, '>52<') === false) {
            echo "<span style='color:orange;'>Ignorado (No contiene DTE 33 ni 52).</span><br>";
            continue;
        }

        // Limpieza radical de Namespaces (Ahora es seguro hacerlo)
        $xml_clean = preg_replace('/xmlns="[^"]+"/', '', $xml_raw);
        $xml_clean = preg_replace('/xmlns:[a-zA-Z0-9_]+="[^"]+"/', '', $xml_clean);
        $xml_clean = preg_replace('/<[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '<$1', $xml_clean);
        $xml_clean = preg_replace('/<\/[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '</$1', $xml_clean);
        $xml_clean = preg_replace('/<Documento[^>]*>/', '<Documento>', $xml_clean);

        // Evitar que SimpleXML tire un Fatal Error y mate el script
        libxml_use_internal_errors(true);
        $xml_obj = simplexml_load_string($xml_clean);
        
        if (!$xml_obj) {
            echo "<span style='color:red;'>Error XML corrupto.</span><br>";
            libxml_clear_errors();
            continue;
        }

        $nodos_encabezado = $xml_obj->xpath('//Encabezado');
        if (empty($nodos_encabezado)) {
            echo "<span style='color:orange;'>No es DTE válido.</span><br>";
            continue;
        }

        $encabezado = $nodos_encabezado[0];
        $tipo_dte = (int)($encabezado->IdDoc->TipoDTE ?? 0);
        $rut_proveedor = (string)($encabezado->Emisor->RUTEmisor ?? '');
        
        // RUTA A: FACTURAS NORMALES (DTE 33)
        if ($tipo_dte === 33) {
            $folio = (int)($encabezado->IdDoc->Folio ?? 0);
            $fecha_raw = (string)($encabezado->IdDoc->FchEmis ?? date('Y-m-d'));
            $fecha_emision = substr($fecha_raw, 0, 10);
            
            $proveedor = (string)($encabezado->Emisor->RznSoc ?? 'Proveedor Desconocido');
            $total_bruto = (int)($encabezado->Totales->MntTotal ?? 0);
            $fma_pago = (int)($encabezado->IdDoc->FmaPago ?? 2);
            $estado_inicial = ($fma_pago === 1 || $fma_pago === 3) ? 'ACEPTADA' : 'PENDIENTE';

            if ($folio === 0 || empty($rut_proveedor)) {
                echo "<span style='color:red;'>Sin folio/RUT.</span><br>";
                continue;
            }

            $nuevo_nombre = "REC_33_" . str_replace(['.', '-'], '', $rut_proveedor) . "_" . $folio . ".xml";
            rename($ruta_archivo, $path_destino_xml . $nuevo_nombre);

            $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
            $url_xml_web = $DOMINIO_LIMPIO . $carpeta_xml_recibidos . $nuevo_nombre;

            $stmt_facturas->bind_param("isssiss", $folio, $fecha_emision, $proveedor, $rut_proveedor, $total_bruto, $url_xml_web, $estado_inicial);
            $stmt_facturas->execute();

            echo "<b>✅ Factura #$folio OK</b><br>";
            $insertados_fac++;
        }
        // RUTA B: GUÍAS DE COMBUSTIBLE COPEC (DTE 52)
        elseif ($tipo_dte === 52 && strpos($rut_proveedor, '99520000') !== false) {
            $folio = (int)($encabezado->IdDoc->Folio ?? 0);
            $fecha_raw = (string)($encabezado->IdDoc->FchEmis ?? date('Y-m-d'));
            $fecha_emision = substr($fecha_raw, 0, 10);
            $patente = (string)($encabezado->Transporte->Patente ?? 'S/P');
            $monto_total = (int)($encabezado->Totales->MntTotal ?? 0);

            $detalles = $xml_obj->xpath('//Detalle');
            $tipo_combustible = 'Combustible';
            $litros = 0;
            $precio_litro = 0;

            if (!empty($detalles)) {
                $det = $detalles[0]; 
                $tipo_combustible = (string)($det->NmbItem ?? 'Combustible');
                $litros = (float)($det->QtyItem ?? 0);
                $precio_litro = (float)($det->PrcItem ?? 0);
            }

            if ($folio === 0) {
                 echo "<span style='color:red;'>Sin folio.</span><br>";
                 continue;
            }

            $nuevo_nombre = "REC_52_" . str_replace(['.', '-'], '', $rut_proveedor) . "_" . $folio . ".xml";
            rename($ruta_archivo, $path_destino_xml . $nuevo_nombre);

            $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
            $url_xml_web = $DOMINIO_LIMPIO . $carpeta_xml_recibidos . $nuevo_nombre;

            $stmt_combustible->bind_param("issssddis", $folio, $fecha_emision, $rut_proveedor, $patente, $tipo_combustible, $litros, $precio_litro, $monto_total, $url_xml_web);
            $stmt_combustible->execute();

            echo "<b>⛽ Guía Copec #$folio OK</b><br>";
            $insertados_com++;
        } else {
            echo "<span style='color:orange;'>Ignorado (No es 33 ni 52 Copec).</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>Fallo crítico: " . $e->getMessage() . "</span><br>";
    }
}

echo "<br><b>[EXITO] Sincronización Local finalizada. Facturas: $insertados_fac | Guías Combustible: $insertados_com</b>";
?>