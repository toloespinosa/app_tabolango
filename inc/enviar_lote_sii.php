<?php
// enviar_lote_sii.php - EMPAQUETADOR CRONJOB + CORREO DUEMINT (V4 - Tabla dte_emitidos)

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

ini_set('display_errors', 0);
ini_set('memory_limit', '512M'); 
set_time_limit(300); // 5 minutos máximo

function enviarXMLDuemint($destinatario, $ruta_archivo, $folio) {
    if (!file_exists($ruta_archivo)) return false;
    $asunto = "Envio DTE Factura Folio " . $folio;
    $mensaje = "Adjunto XML de Factura Electronica Folio " . $folio;
    $nombre_archivo = basename($ruta_archivo);
    $content = chunk_split(base64_encode(file_get_contents($ruta_archivo)));
    $uid = md5(uniqid(time()));
    
    $header = "From: notificaciones@tabolango.cl\r\n";
    $header .= "Reply-To: admin@tabolango.cl\r\n";
    $header .= "MIME-Version: 1.0\r\n";
    $header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
    $body = "--".$uid."\r\n";
    $body .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $mensaje."\r\n\r\n";
    $body .= "--".$uid."\r\n";
    $body .= "Content-Type: text/xml; name=\"".$nombre_archivo."\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"".$nombre_archivo."\"\r\n\r\n";
    $body .= $content."\r\n\r\n";
    $body .= "--".$uid."--";
    
    return @mail($destinatario, $asunto, $body, $header);
}

try {
    $API_KEY = "7165-N580-6393-2899-7690"; 
    $RUT_EMISOR_CLEAN = "77121854-7";
    $RUT_CERTIFICADO = "8201627-9";
    $PASS_CERTIFICADO = "Sofia2020";
    $NUMERO_RESOLUCION_SII = 80; 
    $FECHA_RESOLUCION_SII  = "2014-08-22"; 

    // Ruta del certificado — mismo patrón que procesar_facturacion.php / api_gestion_folios.php.
    // El certificado vive en /home/tabolang/public_html/uploads/certificados/ en producción,
    // pero el subdominio erp.tabolango.cl apunta a una carpeta distinta, así que hay que
    // reemplazar "erp.tabolango.cl" por "public_html" en DOCUMENT_ROOT.
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if (strpos($host_actual, 'erp.tabolango.cl') !== false
        || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz;
    }
    // Fallback para ejecución vía CLI/cron donde DOCUMENT_ROOT no existe:
    // sube 4 niveles desde /inc hasta public_html (o equivalente local).
    if ($ruta_public === '') {
        $ruta_public = dirname(__DIR__, 4);
    }
    $ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
    $path_certificado  = $ruta_base_uploads . 'certificados/certificado.pfx';

    if (!file_exists($path_certificado)) {
        throw new Exception("Error: No se encuentra el certificado pfx. Buscado en: " . $path_certificado);
    }

    // NOTA: Como esto es un Cron, apuntamos directamente a la BD de Producción
    $conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
    $conn->set_charset("utf8mb4");

    // 1. BUSCAR DOCUMENTOS PENDIENTES EN dte_emitidos
    $sql = "SELECT id, id_pedido, tipo_documento, folio, url_xml FROM dte_emitidos WHERE estado_envio = 'PENDIENTE_SII'";
    $res = $conn->query($sql);

    if ($res->num_rows === 0) {
        echo json_encode(["status" => "info", "message" => "Todo al día. No hay DTEs pendientes de envío."]);
        exit;
    }

    $pendientes      = [];
    $faltantes_debug = []; // para diagnóstico si nada existe
    while ($row = $res->fetch_assoc()) {
        // Convierte la URL pública del XML a la ruta física en el servidor.
        // Mismo criterio que el path del certificado: usa $ruta_public, que apunta
        // a /home/tabolang/public_html (donde realmente viven los archivos).
        $ruta_fisica = str_replace(
            'https://tabolango.cl/',
            rtrim($ruta_public, '/') . '/',
            $row['url_xml']
        );
        if (file_exists($ruta_fisica)) {
            $row['ruta_fisica'] = $ruta_fisica;
            $pendientes[]       = $row;
        } else {
            $faltantes_debug[] = [
                'id_pedido' => $row['id_pedido'] ?? '?',
                'folio'     => $row['folio']     ?? '?',
                'url_xml'   => $row['url_xml']   ?? '?',
                'buscado'   => $ruta_fisica,
            ];
        }
    }

    if (count($pendientes) === 0) {
        // Devolvemos el detalle para saber exactamente qué archivos faltan.
        echo json_encode([
            "status"   => "error",
            "message"  => "Hay registros, pero los XML físicos no existen.",
            "faltantes"=> $faltantes_debug
        ]);
        exit;
    }

    // 2. ARMAR EL SOBRE MÚLTIPLE
    $json_sobre = [
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO],
        "Caratula" => ["RutEmisor" => $RUT_EMISOR_CLEAN, "RutReceptor" => "60803000-K", "FechaResolucion" => $FECHA_RESOLUCION_SII, "NumeroResolucion" => $NUMERO_RESOLUCION_SII]
    ];
    $post_sobre = ['input' => json_encode($json_sobre), 'files' => new CURLFile($path_certificado, 'application/x-pkcs12', 'certificado.pfx')];

    $file_index = 2;
    $ids_a_actualizar = [];
    $resumen_documentos = [];

    foreach ($pendientes as $doc) {
        $post_sobre['files' . $file_index] = new CURLFile($doc['ruta_fisica'], 'text/xml', basename($doc['ruta_fisica']));
        $ids_a_actualizar[] = $doc['id'];
        $resumen_documentos[] = "DTE " . $doc['tipo_documento'] . " | Folio: " . $doc['folio'];
        $file_index++;
    }

    // ENSOBRAR
    $ch_s = curl_init("https://api.simpleapi.cl/api/v1/envio/generar");
    curl_setopt($ch_s, CURLOPT_POST, 1);
    curl_setopt($ch_s, CURLOPT_POSTFIELDS, $post_sobre);
    curl_setopt($ch_s, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_s, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    $xml_sobre = curl_exec($ch_s); curl_close($ch_s);

    if (strpos($xml_sobre, 'EnvioDTE') === false) throw new Exception("Fallo al empaquetar. RAW: " . strip_tags($xml_sobre));

    $ruta_sobre_temp = __DIR__ . "/uploads/sobre_lote_diario_" . time() . ".xml";
    file_put_contents($ruta_sobre_temp, $xml_sobre);

    // 3. ENVIAR AL SII
    $json_envio = ["Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO], "Ambiente" => 1, "Tipo" => 1];
    $post_envio = ['input' => json_encode($json_envio), 'files' => new CURLFile($path_certificado, 'application/x-pkcs12', 'certificado.pfx'), 'files2' => new CURLFile($ruta_sobre_temp, 'text/xml', 'sobre_lote.xml')];

    $ch_e = curl_init("https://api.simpleapi.cl/api/v1/envio/enviar");
    curl_setopt($ch_e, CURLOPT_POST, 1);
    curl_setopt($ch_e, CURLOPT_POSTFIELDS, $post_envio);
    curl_setopt($ch_e, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_e, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    $resp_sii = curl_exec($ch_e); curl_close($ch_e);
    
    @unlink($ruta_sobre_temp); 

    $json_resp_sii = json_decode($resp_sii, true);
    if (isset($json_resp_sii['ok']) && $json_resp_sii['ok'] === false) throw new Exception("Rechazo SII: " . $resp_sii);

    $track_id = $json_resp_sii['trackId'] ?? $json_resp_sii['TrackId'] ?? null;
    if (!$track_id) throw new Exception("Enviado sin TrackID: " . $resp_sii);

    // 4. ACTUALIZAR BASE DE DATOS MASIVAMENTE Y ENVIAR A DUEMINT
    $ids_str = implode(",", $ids_a_actualizar);
    $log_msg = "Enviado Masivo OK | TrackID: " . $track_id;
    
    $conn->query("UPDATE dte_emitidos SET estado_envio = 'ENVIADO', track_id = '$track_id', respuesta_api = '$log_msg' WHERE id IN ($ids_str)");

    // Procesar envío de correos a Duemint por cada factura (33) exitosa
    $correos_enviados = 0;
    foreach ($pendientes as $doc) {
        if ((int)$doc['tipo_documento'] === 33) {
            if (enviarXMLDuemint("dte@duemint.com", $doc['ruta_fisica'], $doc['folio'])) {
                $correos_enviados++;
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "message" => "¡Lote enviado al SII!",
        "track_id" => $track_id,
        "cantidad_documentos" => count($pendientes),
        "enviados_duemint" => $correos_enviados,
        "detalle" => $resumen_documentos
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>