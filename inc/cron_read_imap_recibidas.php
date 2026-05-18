<?php
// cron_read_imap_recibidas.php
// Script blindado para escanear etiquetas de Gmail desde entornos locales LocalWP (Live Links).

require_once 'auth.php'; // Hereda la conexión $conn

// Desactivar límite de tiempo para evitar caídas en cargas masivas iniciales
set_time_limit(300);
ini_set('default_socket_timeout', 15); // Si se congela, se cae a los 15 segundos en vez de quedarse infinito

// --- 1. CONFIGURACIÓN DEL BUZÓN IMAP (Con banderas de compatibilidad local) ---
// 🔥 CAMBIO CLAVE: Agregamos banderas para evadir bloqueos SSL locales y apuntamos a la etiqueta con el prefijo de Gmail
$imap_host = '{imap.gmail.com:993/imap/ssl/novalidate-cert}INBOX/DTE_Recibidos'; 
$imap_user = 'jandres@tabolango.cl'; 
$imap_pass = 'ufyt omfq qnof rgfi';      

// --- 2. DETERMINAR RUTAS ABSOLUTAS CENTRALIZADAS ---
$host_actual = $_SERVER['HTTP_HOST'] ?? '';
$ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
    $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
} else {
    $ruta_public = $ruta_raiz; 
}

$ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
$carpeta_xml_recibidos = "recibidos_xml/";
$path_destino_xml = $ruta_base_uploads . $carpeta_xml_recibidos;

if (!is_dir($path_destino_xml)) {
    mkdir($path_destino_xml, 0755, true);
}

// --- 3. CONEXIÓN AL SERVIDOR IMAP ---
echo "Iniciando conexión con Google IMAP...<br>";
if (ob_get_level() > 0) ob_flush(); flush(); // Fuerza al Live Link a mostrar el texto ya mismo

$inbox = @imap_open($imap_host, $imap_user, $imap_pass);

if (!$inbox) {
    echo "<span style='color:red;'>❌ Error de conexión IMAP:</span> " . imap_last_error() . "<br><br>";
    echo "<b>Detalles técnicos acumulados:</b><pre>";
    print_r(imap_errors());
    print_r(imap_alerts());
    echo "</pre>";
    exit;
}

echo "✅ Conectado exitosamente a la etiqueta DTE_Recibidos.<br>";
echo "Buscando correos sin leer (UNSEEN)...<br>";
if (ob_get_level() > 0) ob_flush(); flush();

// Buscamos todos los correos NO LEÍDOS (UNSEEN) dentro de esa etiqueta específica
$emails = imap_search($inbox, 'UNSEEN');
$insertados = 0;

if ($emails) {
    echo "<b>Se encontraron " . count($emails) . " DTEs nuevos sin procesar.</b><br><br>";
    if (ob_get_level() > 0) ob_flush(); flush();
    
    $stmt = $conn->prepare("
        INSERT INTO facturas_recibidas (folio, fecha_emision, proveedor, rut_proveedor, total_bruto, url_xml, estado_acuse) 
        VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')
        ON DUPLICATE KEY UPDATE 
            total_bruto = VALUES(total_bruto),
            proveedor = VALUES(proveedor),
            fecha_emision = VALUES(fecha_emision),
            url_xml = IFNULL(facturas_recibidas.url_xml, VALUES(url_xml))
    ");

    foreach ($emails as $email_number) {
        $structure = imap_fetchstructure($inbox, $email_number);
        $attachments = [];

        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $attachments[$i] = [
                    'is_attachment' => false,
                    'filename' => '',
                    'attachment' => ''
                ];

                if ($structure->parts[$i]->ifdparameters) {
                    foreach ($structure->parts[$i]->dparameters as $object) {
                        if (strtolower($object->attribute) == 'filename') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }

                if ($structure->parts[$i]->ifparameters) {
                    foreach ($structure->parts[$i]->parameters as $object) {
                        if (strtolower($object->attribute) == 'name') {
                            $attachments[$i]['is_attachment'] = true;
                            $attachments[$i]['filename'] = $object->value;
                        }
                    }
                }

                if ($attachments[$i]['is_attachment']) {
                    $attachments[$i]['attachment'] = imap_fetchbody($inbox, $email_number, $i+1);
                    if ($structure->parts[$i]->encoding == 3) { 
                        $attachments[$i]['attachment'] = base64_decode($attachments[$i]['attachment']);
                    } elseif ($structure->parts[$i]->encoding == 4) { 
                        $attachments[$i]['attachment'] = quoted_printable_decode($attachments[$i]['attachment']);
                    }
                }
            }
        }

        foreach ($attachments as $attachment) {
            if ($attachment['is_attachment'] == false) continue;
            
            $filename = strtolower($attachment['filename']);
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'xml') continue;

            $xml_raw = $attachment['attachment'];

            // Limpieza radical de Namespaces
            $xml_clean = preg_replace('/xmlns="[^"]+"/', '', $xml_raw);
            $xml_clean = preg_replace('/xmlns:[a-zA-Z0-9_]+="[^"]+"/', '', $xml_clean);
            $xml_clean = preg_replace('/<[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '<$1', $xml_clean);
            $xml_clean = preg_replace('/<\/[a-zA-Z0-9_]+:([a-zA-Z0-9_]+)/', '</$1', $xml_clean);
            $xml_clean = preg_replace('/<Documento[^>]*>/', '<Documento>', $xml_clean);

            $xml_obj = @simplexml_load_string($xml_clean);
            if (!$xml_obj) continue;

            $nodos_encabezado = $xml_obj->xpath('//Encabezado');
            if (empty($nodos_encabezado)) continue;

            $encabezado = $nodos_encabezado[0];
            
            $tipo_dte = (int)($encabezado->IdDoc->TipoDTE ?? 0);
            if ($tipo_dte !== 33) continue; 

            $folio = (int)($encabezado->IdDoc->Folio ?? 0);
            $fecha_raw = (string)($encabezado->IdDoc->FchEmis ?? date('Y-m-d'));
            $fecha_emision = substr($fecha_raw, 0, 10);
            
            $proveedor = (string)($encabezado->Emisor->RznSoc ?? 'Proveedor Desconocido');
            $rut_proveedor = (string)($encabezado->Emisor->RUTEmisor ?? '');
            $total_bruto = (int)($encabezado->Totales->MntTotal ?? 0);

            if ($folio === 0 || empty($rut_proveedor)) continue;

            $nombre_archivo_xml = "REC_33_" . str_replace(['.', '-'], '', $rut_proveedor) . "_" . $folio . ".xml";
            $ruta_fisica_xml = $path_destino_xml . $nombre_archivo_xml;
            
            file_put_contents($ruta_fisica_xml, $xml_raw);

            $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
            $url_xml_web = $DOMINIO_LIMPIO . "uploads/" . $carpeta_xml_recibidos . $nombre_archivo_xml;

            $stmt->bind_param("isssis", $folio, $fecha_emision, $proveedor, $rut_proveedor, $total_bruto, $url_xml_web);
            $stmt->execute();

            echo "📥 Procesada con éxito: Factura #$folio - $proveedor<br>";
            if (ob_get_level() > 0) ob_flush(); flush();
            $insertados++;
        }

        // Marcamos el correo como leído
        imap_setflag_full($inbox, $email_number, "\\Seen");
    }
    
    echo "<br><b>[EXITO] Lector de Etiqueta finalizado. Documentos enlazados: $insertados</b>\n";
} else {
    echo "<br><b>[AVISO] No hay facturas nuevas sin leer en la etiqueta DTE_Recibidos.</b>\n";
}

imap_close($inbox);
?>