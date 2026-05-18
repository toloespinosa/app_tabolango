<?php
// cron_read_imap_recibidas.php
// Escáner Híbrido: Procesa Facturas (DTE 33) y Guías de Combustible Copec (DTE 52).

require_once 'auth.php'; 

set_time_limit(300);
ini_set('default_socket_timeout', 15); 

// --- 1. CONFIGURACIÓN DEL BUZÓN IMAP ---
$imap_host = '{imap.gmail.com:993/imap/ssl/novalidate-cert}DTE_Recibidos'; 
$imap_user = 'jandres@tabolango.cl'; 
$imap_pass = 'ufyt omfq qnof rgfi';      

// --- 2. DETERMINAR RUTAS ---
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

// --- 3. CONEXIÓN IMAP ---
$inbox = @imap_open($imap_host, $imap_user, $imap_pass);

if (!$inbox) {
    die("❌ Error de conexión IMAP: " . imap_last_error() . "\n");
}

$emails = imap_search($inbox, 'ALL');
$insertados_fac = 0;
$insertados_com = 0;

if ($emails) {
    // Preparar Query para Facturas (DTE 33)
    $stmt_facturas = $conn->prepare("
        INSERT INTO facturas_recibidas (folio, fecha_emision, proveedor, rut_proveedor, total_bruto, url_xml, estado_acuse) 
        VALUES (?, ?, ?, ?, ?, ?, 'PENDIENTE')
        ON DUPLICATE KEY UPDATE 
            total_bruto = VALUES(total_bruto),
            proveedor = VALUES(proveedor),
            fecha_emision = VALUES(fecha_emision),
            url_xml = IFNULL(facturas_recibidas.url_xml, VALUES(url_xml))
    ");

    // Preparar Query para Combustible (DTE 52 Copec)
    $stmt_combustible = $conn->prepare("
        INSERT INTO consumo_combustible (folio, fecha_emision, rut_proveedor, patente, tipo_combustible, litros, precio_litro, monto_total, url_xml) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            patente = VALUES(patente),
            litros = VALUES(litros),
            monto_total = VALUES(monto_total),
            url_xml = VALUES(url_xml)
    ");

    foreach ($emails as $email_number) {
        $structure = imap_fetchstructure($inbox, $email_number);
        $attachments = [];

        if (isset($structure->parts) && count($structure->parts)) {
            for ($i = 0; $i < count($structure->parts); $i++) {
                $attachments[$i] = ['is_attachment' => false, 'filename' => '', 'attachment' => ''];

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
            $rut_proveedor = (string)($encabezado->Emisor->RUTEmisor ?? '');
            
            // ----------------------------------------------------
            // RUTA A: FACTURAS NORMALES (DTE 33)
            // ----------------------------------------------------
            if ($tipo_dte === 33) {
                $folio = (int)($encabezado->IdDoc->Folio ?? 0);
                $fecha_raw = (string)($encabezado->IdDoc->FchEmis ?? date('Y-m-d'));
                $fecha_emision = substr($fecha_raw, 0, 10);
                $proveedor = (string)($encabezado->Emisor->RznSoc ?? 'Proveedor Desconocido');
                $total_bruto = (int)($encabezado->Totales->MntTotal ?? 0);

                if ($folio === 0 || empty($rut_proveedor)) continue;

                $nombre_archivo_xml = "REC_33_" . str_replace(['.', '-'], '', $rut_proveedor) . "_" . $folio . ".xml";
                $ruta_fisica_xml = $path_destino_xml . $nombre_archivo_xml;
                file_put_contents($ruta_fisica_xml, $xml_raw);

                $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
                $url_xml_web = $DOMINIO_LIMPIO . "uploads/" . $carpeta_xml_recibidos . $nombre_archivo_xml;

                $stmt_facturas->bind_param("isssis", $folio, $fecha_emision, $proveedor, $rut_proveedor, $total_bruto, $url_xml_web);
                $stmt_facturas->execute();
                $insertados_fac++;
            }
            // ----------------------------------------------------
            // RUTA B: GUÍAS DE COMBUSTIBLE COPEC (DTE 52)
            // ----------------------------------------------------
            elseif ($tipo_dte === 52 && strpos($rut_proveedor, '99520000') !== false) {
                $folio = (int)($encabezado->IdDoc->Folio ?? 0);
                $fecha_raw = (string)($encabezado->IdDoc->FchEmis ?? date('Y-m-d'));
                $fecha_emision = substr($fecha_raw, 0, 10);
                $patente = (string)($encabezado->Transporte->Patente ?? 'S/P');
                $monto_total = (int)($encabezado->Totales->MntTotal ?? 0);

                // Buscamos el detalle de los litros
                $detalles = $xml_obj->xpath('//Detalle');
                $tipo_combustible = 'Combustible';
                $litros = 0;
                $precio_litro = 0;

                if (!empty($detalles)) {
                    $det = $detalles[0]; // Copec manda 1 item por guía
                    $tipo_combustible = (string)($det->NmbItem ?? 'Combustible');
                    $litros = (float)($det->QtyItem ?? 0);
                    $precio_litro = (float)($det->PrcItem ?? 0);
                }

                if ($folio === 0) continue;

                // Guardar XML físico
                $nombre_archivo_xml = "REC_52_" . str_replace(['.', '-'], '', $rut_proveedor) . "_" . $folio . ".xml";
                $ruta_fisica_xml = $path_destino_xml . $nombre_archivo_xml;
                file_put_contents($ruta_fisica_xml, $xml_raw);

                $protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                $DOMINIO_LIMPIO = $protocolo . "://" . $host_actual . "/wp-content/themes/Tabolango/";
                $url_xml_web = $DOMINIO_LIMPIO . "uploads/" . $carpeta_xml_recibidos . $nombre_archivo_xml;

                $stmt_combustible->bind_param("issssddis", $folio, $fecha_emision, $rut_proveedor, $patente, $tipo_combustible, $litros, $precio_litro, $monto_total, $url_xml_web);
                $stmt_combustible->execute();
                $insertados_com++;
            }
        }
        imap_setflag_full($inbox, $email_number, "\\Seen");
    }
}

imap_close($inbox);
echo "[EXITO] Lector finalizado. Facturas leídas: $insertados_fac | Guías de Combustible leídas: $insertados_com\n";
?>