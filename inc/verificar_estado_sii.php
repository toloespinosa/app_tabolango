<?php
// ============================================================
// Consulta al SII (vía SimpleAPI) el estado real de un track_id.
//
// SimpleAPI devuelve trackId apenas recibe el sobre, pero el SII lo procesa
// después y puede rechazarlo. "ENVIADO" en la BD no significa "aceptado".
//
// Uso:
//   .../verificar_estado_sii.php?wp_user=jandres@tabolango.cl
//       → consulta los últimos 20 DTEs con estado_envio = ENVIADO
//
//   .../verificar_estado_sii.php?id=123&wp_user=jandres@tabolango.cl
//       → consulta uno específico
// ============================================================
require_once 'auth.php';

header("Content-Type: application/json; charset=UTF-8");

if ($rol_final !== 1 && $rol_final !== 2) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Solo admin/editor puede verificar estados"]);
    exit;
}

try {
    $API_KEY          = "7165-N580-6393-2899-7690";
    $RUT_EMISOR_CLEAN = "77121854-7";
    $RUT_CERTIFICADO  = "8201627-9";
    $PASS_CERTIFICADO = "Sofia2020";

    // Ruta certificado (mismo patrón que enviar_lote_sii.php)
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz;
    }
    if ($ruta_public === '') $ruta_public = dirname(__DIR__, 4);
    $path_certificado = rtrim($ruta_public, '/') . '/uploads/certificados/certificado.pfx';

    if (!file_exists($path_certificado)) {
        throw new Exception("Falta el certificado PFX en: " . $path_certificado);
    }

    // ── Traducciones de estados del SII ──
    // Códigos oficiales que devuelve el SII al consultar un trackId
    $estados_sii = [
        'EPR' => ['nombre' => 'Envío Procesado',           'ok' => true,  'estado_db' => 'ACEPTADO'],
        'SOK' => ['nombre' => 'Schema OK',                 'ok' => true,  'estado_db' => 'ENVIADO'],
        'FOK' => ['nombre' => 'Firma OK',                  'ok' => true,  'estado_db' => 'ENVIADO'],
        'CRT' => ['nombre' => 'Carátula OK',               'ok' => true,  'estado_db' => 'ENVIADO'],
        'PDR' => ['nombre' => 'Pendiente de revisión',     'ok' => true,  'estado_db' => 'ENVIADO'],
        'REC' => ['nombre' => 'Recibido — en proceso',     'ok' => true,  'estado_db' => 'ENVIADO'],
        'RCT' => ['nombre' => 'Rechazado por contenido',   'ok' => false, 'estado_db' => 'RECHAZADO'],
        'RSC' => ['nombre' => 'Rechazado por schema',      'ok' => false, 'estado_db' => 'RECHAZADO'],
        'RFR' => ['nombre' => 'Rechazado por firma',       'ok' => false, 'estado_db' => 'RECHAZADO'],
        'RCH' => ['nombre' => 'Rechazado',                 'ok' => false, 'estado_db' => 'RECHAZADO'],
        'RPR' => ['nombre' => 'Reparo',                    'ok' => false, 'estado_db' => 'REPARO'],
        'ANC' => ['nombre' => 'Anulado por SII',           'ok' => false, 'estado_db' => 'ANULADO'],
    ];

    // ── Buscar DTEs a consultar ──
    $id_dte = intval($_GET['id'] ?? 0);
    if ($id_dte > 0) {
        $stmt = $conn->prepare("SELECT id, tipo_documento, folio, track_id, estado_envio
                                  FROM dte_emitidos WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id_dte);
    } else {
        // Todos los que están en ENVIADO (o SOK/FOK) — últimos 20
        $stmt = $conn->prepare("SELECT id, tipo_documento, folio, track_id, estado_envio
                                  FROM dte_emitidos
                                 WHERE estado_envio IN ('ENVIADO','SOK','FOK','CRT','PDR','REC')
                                   AND track_id IS NOT NULL AND track_id != ''
                              ORDER BY fecha_emision DESC LIMIT 20");
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();

    if (empty($rows)) {
        echo json_encode([
            "status"  => "info",
            "message" => "No hay DTEs pendientes de verificar.",
        ]);
        exit;
    }

    $resultados = [];
    foreach ($rows as $doc) {
        $track = $doc['track_id'];

        // ── Payload a SimpleAPI: /api/v1/dte/estado ──
        // Documentado en https://documentacion.simpleapi.cl (ambiente 1 = producción)
        $payload = [
            "TrackId"     => $track,
            "RutEmpresa"  => $RUT_EMISOR_CLEAN,
            "Ambiente"    => 1,
            "Certificado" => [
                "Rut"      => $RUT_CERTIFICADO,
                "Password" => $PASS_CERTIFICADO,
            ],
        ];

        $post = [
            'input' => json_encode($payload),
            'files' => new CURLFile($path_certificado, 'application/x-pkcs12', 'certificado.pfx'),
        ];

        // SimpleAPI ha cambiado su URL para consulta de estado en el tiempo.
        // Probamos varias candidatas y usamos la primera que responda distinto de 404.
        $urls_candidatas = [
            'https://api.simpleapi.cl/api/v1/envio/getEstado',
            'https://api.simpleapi.cl/api/v1/dte/getEstadoEnvio',
            'https://api.simpleapi.cl/api/v1/envio/estado',
            'https://api.simpleapi.cl/api/v1/dte/estado',
        ];
        $resp = null; $err = ''; $http = 0; $url_usada = '';
        foreach ($urls_candidatas as $url_try) {
            $ch = curl_init($url_try);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $resp = curl_exec($ch);
            $err  = curl_error($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $url_usada = $url_try;
            // Si NO es 404 (endpoint existe) usamos esta respuesta
            if ($http !== 404) break;
        }

        // Parsear respuesta (JSON o XML según SimpleAPI)
        $data = json_decode($resp, true);
        $codigo = null;
        $glosa  = null;

        if (is_array($data)) {
            $codigo = $data['estado']   ?? $data['Estado']   ?? $data['codigo'] ?? $data['statusCode'] ?? null;
            $glosa  = $data['glosa']    ?? $data['Glosa']    ?? $data['mensaje'] ?? $data['message']   ?? null;
        }

        // Si viene como XML del SII (respuesta cruda)
        if ($codigo === null && strpos($resp, '<?xml') !== false) {
            $xml = @simplexml_load_string($resp);
            if ($xml) {
                $codArr = $xml->xpath('//*[local-name()="ESTADO"]');
                $glsArr = $xml->xpath('//*[local-name()="GLOSA_ESTADO"]');
                if (!empty($codArr)) $codigo = (string)$codArr[0];
                if (!empty($glsArr)) $glosa  = (string)$glsArr[0];
            }
        }

        $info      = $estados_sii[$codigo] ?? null;
        $nuevo     = $info ? $info['estado_db'] : $doc['estado_envio'];
        $ok        = $info ? $info['ok']        : false;
        $nombre    = $info ? $info['nombre']    : ($glosa ?: 'Estado desconocido');

        // Guardamos el estado y la glosa en la BD
        $log = "Verificado " . date('Y-m-d H:i')
             . " | SII: " . ($codigo ?: '?') . " (" . $nombre . ")"
             . " | HTTP=$http | URL=" . basename($url_usada)
             . ($err ? " | curl_err=$err" : "");
        $stmt2 = $conn->prepare("UPDATE dte_emitidos
                                    SET estado_envio  = ?,
                                        respuesta_api = ?
                                  WHERE id = ?");
        $stmt2->bind_param("ssi", $nuevo, $log, $doc['id']);
        $stmt2->execute();
        $stmt2->close();

        $resultados[] = [
            'id'                 => (int)$doc['id'],
            'tipo_documento'     => $doc['tipo_documento'],
            'folio'              => (int)$doc['folio'],
            'track_id'           => $track,
            'codigo_sii'         => $codigo,
            'glosa'              => $nombre,
            'estado_anterior_db' => $doc['estado_envio'],
            'estado_nuevo_db'    => $nuevo,
            'aceptado'           => $ok,
            'http_code'          => $http,
            'endpoint'           => $url_usada,
            'raw_snippet'        => substr((string)$resp, 0, 300),
        ];
    }

    echo json_encode([
        "status"     => "success",
        "verificados"=> count($resultados),
        "detalle"    => $resultados,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
