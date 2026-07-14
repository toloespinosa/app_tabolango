<?php
// ============================================================
// Reenvía UN DTE específico al SII (por id de dte_emitidos).
// Útil cuando una factura quedó marcada como ENVIADO pero nunca
// llegó al SII, o para reintentar un rechazo puntual.
//
// Uso:
//   https://erp.tabolango.cl/wp-content/themes/app_tabolango/inc/reenviar_dte_sii.php?id=123
//   https://erp.tabolango.cl/wp-content/themes/app_tabolango/inc/reenviar_dte_sii.php?folio=4521&tipo=33
// ============================================================
require_once 'auth.php';

header("Content-Type: application/json; charset=UTF-8");

if ($rol_final !== 1 && $rol_final !== 2) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Solo admin/editor puede reenviar DTEs al SII"]);
    exit;
}

try {
    $API_KEY               = "7165-N580-6393-2899-7690";
    $RUT_EMISOR_CLEAN      = "77121854-7";
    $RUT_CERTIFICADO       = "8201627-9";
    $PASS_CERTIFICADO      = "Sofia2020";
    $NUMERO_RESOLUCION_SII = 80;
    $FECHA_RESOLUCION_SII  = "2014-08-22";

    // Ruta certificado + uploads (mismo patrón que enviar_lote_sii.php)
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz;
    }
    if ($ruta_public === '') $ruta_public = dirname(__DIR__, 4);
    $ruta_base_uploads = rtrim($ruta_public, '/') . '/uploads/';
    $path_certificado  = $ruta_base_uploads . 'certificados/certificado.pfx';

    if (!file_exists($path_certificado)) {
        throw new Exception("Falta el certificado PFX en: " . $path_certificado);
    }

    // Cron/consola apuntan directo a la BD de producción; vía web usa auth.php.
    if (!isset($conn) || !$conn) {
        throw new Exception("Sin conexión a la BD (revisa auth.php)");
    }

    // ── Buscar el DTE por id o por (folio + tipo) ──
    $id_dte  = intval($_GET['id']    ?? 0);
    $folio_q = intval($_GET['folio'] ?? 0);
    $tipo_q  = trim($_GET['tipo']    ?? '');

    if ($id_dte > 0) {
        $stmt = $conn->prepare("SELECT id, id_pedido, tipo_documento, folio, url_xml, estado_envio
                                  FROM dte_emitidos WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id_dte);
    } elseif ($folio_q > 0 && $tipo_q !== '') {
        $stmt = $conn->prepare("SELECT id, id_pedido, tipo_documento, folio, url_xml, estado_envio
                                  FROM dte_emitidos
                                 WHERE folio = ? AND tipo_documento = ?
                              ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("is", $folio_q, $tipo_q);
    } else {
        throw new Exception("Pasa ?id=X o ?folio=X&tipo=33 en la URL");
    }
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doc) throw new Exception("No existe ese DTE en dte_emitidos.");
    if (empty($doc['url_xml'])) throw new Exception("El DTE #{$doc['id']} no tiene url_xml — no se puede reenviar.");

    // Ruta física del XML del DTE
    $ruta_fisica = str_replace('https://tabolango.cl/', rtrim($ruta_public, '/') . '/', $doc['url_xml']);
    if (!file_exists($ruta_fisica)) {
        throw new Exception("XML no encontrado físicamente en: " . $ruta_fisica);
    }

    // ═════════════════════════════════════════════════════════
    // 1. ENSOBRAR (envio/generar) — sobre con UN solo documento
    // ═════════════════════════════════════════════════════════
    $json_sobre = [
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO],
        "Caratula"    => [
            "RutEmisor"        => $RUT_EMISOR_CLEAN,
            "RutReceptor"      => "60803000-K",
            "FechaResolucion"  => $FECHA_RESOLUCION_SII,
            "NumeroResolucion" => $NUMERO_RESOLUCION_SII,
        ],
    ];
    $post_sobre = [
        'input'  => json_encode($json_sobre),
        'files'  => new CURLFile($path_certificado, 'application/x-pkcs12', 'certificado.pfx'),
        'files2' => new CURLFile($ruta_fisica,     'text/xml',              basename($ruta_fisica)),
    ];

    $ch_s = curl_init("https://api.simpleapi.cl/api/v1/envio/generar");
    curl_setopt($ch_s, CURLOPT_POST, 1);
    curl_setopt($ch_s, CURLOPT_POSTFIELDS, $post_sobre);
    curl_setopt($ch_s, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_s, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    curl_setopt($ch_s, CURLOPT_TIMEOUT, 60);
    $xml_sobre = curl_exec($ch_s);
    $err_sobre = curl_error($ch_s);
    $http_sob  = curl_getinfo($ch_s, CURLINFO_HTTP_CODE);
    curl_close($ch_s);

    if (strpos($xml_sobre, 'EnvioDTE') === false) {
        throw new Exception("Fallo al ensobrar | HTTP=$http_sob | " . strip_tags(substr($xml_sobre, 0, 400)));
    }

    // Guardar sobre temporal
    if (!is_dir($ruta_base_uploads)) {
        throw new Exception("No existe el directorio de uploads: " . $ruta_base_uploads);
    }
    $ruta_sobre_temp = $ruta_base_uploads . "sobre_reenvio_" . $doc['id'] . "_" . time() . ".xml";
    if (file_put_contents($ruta_sobre_temp, $xml_sobre) === false) {
        throw new Exception("No se pudo escribir el sobre temporal en: " . $ruta_sobre_temp);
    }

    // ═════════════════════════════════════════════════════════
    // 2. ENVIAR AL SII (envio/enviar)
    // ═════════════════════════════════════════════════════════
    $json_envio = [
        "Certificado" => ["Rut" => $RUT_CERTIFICADO, "Password" => $PASS_CERTIFICADO],
        "Ambiente"    => 1,
        "Tipo"        => 1,
    ];
    $post_envio = [
        'input'  => json_encode($json_envio),
        'files'  => new CURLFile($path_certificado, 'application/x-pkcs12', 'certificado.pfx'),
        'files2' => new CURLFile($ruta_sobre_temp,  'text/xml',              'sobre_lote.xml'),
    ];

    $ch_e = curl_init("https://api.simpleapi.cl/api/v1/envio/enviar");
    curl_setopt($ch_e, CURLOPT_POST, 1);
    curl_setopt($ch_e, CURLOPT_POSTFIELDS, $post_envio);
    curl_setopt($ch_e, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_e, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    curl_setopt($ch_e, CURLOPT_TIMEOUT, 60);
    $resp_sii  = curl_exec($ch_e);
    $err_sii   = curl_error($ch_e);
    $http_env  = curl_getinfo($ch_e, CURLINFO_HTTP_CODE);
    curl_close($ch_e);

    @unlink($ruta_sobre_temp);

    if ($resp_sii === false || $resp_sii === '') {
        throw new Exception("Respuesta vacía del SII | HTTP=$http_env | curl_err=$err_sii");
    }

    $json_resp = json_decode($resp_sii, true);
    if ($json_resp === null) {
        throw new Exception("Respuesta no-JSON del SII | HTTP=$http_env | RAW=" . substr($resp_sii, 0, 500));
    }
    if (isset($json_resp['ok']) && $json_resp['ok'] === false) {
        throw new Exception("Rechazo SII: " . $resp_sii);
    }

    $track_id = $json_resp['trackId'] ?? $json_resp['TrackId'] ?? null;
    if (!$track_id) {
        throw new Exception("Enviado sin TrackID | HTTP=$http_env | RAW=" . $resp_sii);
    }

    // ═════════════════════════════════════════════════════════
    // 3. ACTUALIZAR BD con el nuevo track_id
    // ═════════════════════════════════════════════════════════
    $log_msg = "Reenvío individual OK | TrackID: " . $track_id
             . " | por " . ($email_auth ?: 'sistema')
             . " | " . date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE dte_emitidos
                               SET estado_envio = 'ENVIADO',
                                   track_id      = ?,
                                   respuesta_api = ?
                             WHERE id = ?");
    $stmt->bind_param("ssi", $track_id, $log_msg, $doc['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode([
        "status"          => "success",
        "id"              => (int)$doc['id'],
        "tipo_documento"  => $doc['tipo_documento'],
        "folio"           => (int)$doc['folio'],
        "estado_anterior" => $doc['estado_envio'],
        "track_id_nuevo"  => $track_id,
        "mensaje"         => "DTE #" . $doc['id']
                           . " (tipo " . $doc['tipo_documento']
                           . ", folio " . $doc['folio']
                           . ") reenviado correctamente al SII.",
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
