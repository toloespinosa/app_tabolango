<?php
require_once 'auth.php';
// notifications.php - Versión Soporte Masivo por Categoría

function utf8ize($mixed) {
    if (is_array($mixed)) {
        foreach ($mixed as $key => $value) {
            $mixed[$key] = utf8ize($value);
        }
    } else if (is_string($mixed)) {
        return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
    }
    return $mixed;
}

// Ahora $email_destino puede ser NULL para enviar a TODOS los suscritos
function enviarNotificacionFCM($email_destino, $titulo, $mensaje, $url_destino = 'https://app.tabolango.cl/pedidos/', $categoria = 'notify_pedido_creado') {
    global $conn;
    
    $columnas_permitidas = ['notify_pedido_creado', 'notify_cambio_estado', 'notify_pedido_entregado', 'notify_pedido_editado', 'notify_doc_por_vencer', 'notify_doc_vencido'];
    if (!in_array($categoria, $columnas_permitidas)) {
        $categoria = 'notify_pedido_creado';
    }

    // LÓGICA INTELIGENTE:
    if ($email_destino === null) {
        // Opción A: Enviar a TODOS los que tengan la categoría activada (1)
        $stmt = $conn->prepare("SELECT id, token FROM app_fcm_tokens WHERE $categoria = 1");
        $stmt->execute();
    } else {
        // Opción B: Enviar a UNA persona específica (si tiene la categoría activada)
        $stmt = $conn->prepare("SELECT id, token FROM app_fcm_tokens WHERE email = ? AND $categoria = 1");
        $stmt->bind_param("s", $email_destino);
        $stmt->execute();
    }
    
    $res = $stmt->get_result();
    
    $url_fcm = 'https://fcm.googleapis.com/v1/projects/tabolangoapp/messages:send';
    $accessToken = obtenerTokenGoogle(); 

    if (!$accessToken) {
        file_put_contents(__DIR__ . '/error_log.txt', date("Y-m-d H:i:s") . " ERROR: No Access Token." . PHP_EOL, FILE_APPEND);
        return;
    }

    while($row = $res->fetch_assoc()) {
        $id_db = $row['id'];
        $token = $row['token'];
        
        $titulo_clean = utf8ize($titulo);
        $mensaje_clean = utf8ize($mensaje);

       $payload = [
            'message' => [
                'token' => $token,
                
                // --- AGREGAR ESTO (Bloque Notification) ---
                // Esto es lo que falta para que tu Service Worker no se quede callado.
                'notification' => [
                    'title' => $titulo_clean,
                    'body'  => $mensaje_clean,
                ],
                // ------------------------------------------

                'data' => [
                    'url' => (string)$url_destino,
                    'title' => $titulo_clean, 
                    'body' => $mensaje_clean
                ],
                'webpush' => [
                    'fcm_options' => [
                        'link' => (string)$url_destino
                    ]
                ]
            ]
        ];

        $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!$json_payload) {
            $payload = utf8ize($payload);
            $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_fcm);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // LOG para ver a quién se le envió
        file_put_contents(__DIR__ . '/error_log.txt', date("Y-m-d H:i:s") . " | Cat: $categoria | Token ID: $id_db | Resp: $httpCode" . PHP_EOL, FILE_APPEND);

        if ($httpCode == 404 || $httpCode == 410) {
            $conn->query("DELETE FROM app_fcm_tokens WHERE id = $id_db");
        }
    }
}

function obtenerTokenGoogle() {
    $ruta = __DIR__ . '/service-account.json';
    if (!file_exists($ruta)) return false;
    $json = json_decode(file_get_contents($ruta), true);
    $now = time();
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss' => $json['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]);
    $baseHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $basePayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    openssl_sign($baseHeader . "." . $basePayload, $signature, $json['private_key'], 'sha256WithRSAEncryption');
    $baseSig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $baseHeader . "." . $basePayload . "." . $baseSig;
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=$jwt");
    $res = json_decode(curl_exec($ch), true);
    return $res['access_token'] ?? false;
}
?>