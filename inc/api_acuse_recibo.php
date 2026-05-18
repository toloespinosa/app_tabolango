<?php
// api_acuse_recibo.php
require_once 'auth.php';
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método no permitido.");
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    $folio = $input['folio'] ?? '';
    $rut_proveedor = $input['rut_proveedor'] ?? '';
    $accion = $input['accion'] ?? ''; // 'ERM' (Aceptar) o 'RCD' (Rechazar)

    if (empty($folio) || empty($rut_proveedor) || empty($accion)) {
        throw new Exception("Faltan parámetros obligatorios (Folio, RUT o Acción).");
    }

    // Datos de la empresa (Tabolango)
    $API_KEY = "7165-N580-6393-2899-7690";
    $CERT_RUT = "8201627-9"; 
    $CERT_PASS = "Sofia2020";

    // Formatear RUT del proveedor (SimpleAPI exige: sin puntos, con guión)
    $rut_proveedor_clean = str_replace('.', '', $rut_proveedor);

    // Buscar el certificado físico en el servidor
    $host_actual = $_SERVER['HTTP_HOST'] ?? '';
    $ruta_raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
    if (strpos($host_actual, 'erp.tabolango.cl') !== false || strpos($ruta_raiz, 'erp.tabolango.cl') !== false) {
        $ruta_public = str_replace('erp.tabolango.cl', 'public_html', $ruta_raiz);
    } else {
        $ruta_public = $ruta_raiz; 
    }
    
    $path_certificado = rtrim($ruta_public, '/') . '/uploads/certificados/certificado.pfx';
    if (!file_exists($path_certificado)) {
        throw new Exception("No se encontró el certificado digital en el servidor.");
    }

    // Armar el Payload JSON según documentación
    $json_payload = [
        "Certificado" => [
            "Rut" => $CERT_RUT,
            "Password" => $CERT_PASS
        ],
        "Tipo" => 33, // 33 = Factura Electrónica
        "Folio" => (int)$folio,
        "Accion" => $accion,
        "RutEmpresa" => $rut_proveedor_clean,
        "Ambiente" => 1 // 1 = Producción
    ];

    // Llamada CURL a SimpleAPI
    $ch = curl_init("https://api.simpleapi.cl/api/v1/compras/aceptacionreclamo");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'input' => json_encode($json_payload, JSON_UNESCAPED_UNICODE),
        'files' => new CURLFile($path_certificado, 'application/x-pkcs12', basename($path_certificado))
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: " . $API_KEY]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $api_res = json_decode($response, true);

    // Validar respuesta del SII
    if ($http_code !== 200 || !isset($api_res['codRespuesta']) || $api_res['codRespuesta'] !== 0) {
        $msg_error = $api_res['descripcion'] ?? strip_tags($response);
        
        // 🔥 LÓGICA INTELIGENTE: Detectar si el SII ya la aceptó por plazo (8 días) o error del emisor
        $palabras_clave_aceptada = ['al contado', 'gratuito', 'ya se encuentra', 'plazo', 'tácito', 'vencido'];
        $ya_estaba_aceptada = false;
        
        foreach ($palabras_clave_aceptada as $palabra) {
            if (stripos($msg_error, $palabra) !== false) {
                $ya_estaba_aceptada = true;
                break;
            }
        }

        // Si intentabas ACEPTARLA ('ERM') y el SII dice que ya está aceptada/vencida, la forzamos localmente a ACEPTADA
        if ($ya_estaba_aceptada && $accion === 'ERM') {
            $stmt = $conn->prepare("UPDATE facturas_recibidas SET estado_acuse = 'ACEPTADA' WHERE folio = ? AND rut_proveedor = ?");
            $stmt->bind_param("is", $folio, $rut_proveedor);
            $stmt->execute();

            echo json_encode([
                "status" => "success", 
                "message" => "El SII indica que esta factura ya fue aceptada (por plazo de 8 días o condición). Se ha actualizado tu panel correctamente.",
                "sii_response" => $msg_error
            ]);
            exit; // Cortamos la ejecución para que envíe el success al frontend
        }

        // Si es otro error real, lo lanzamos
        throw new Exception("Rechazo del SII: " . $msg_error);
    }

    // Actualizar Base de Datos si el SII responde con Éxito rotundo
    $estado_db = ($accion === 'ERM') ? 'ACEPTADA' : 'RECHAZADA';
    
    $stmt = $conn->prepare("UPDATE facturas_recibidas SET estado_acuse = ? WHERE folio = ? AND rut_proveedor = ?");
    $stmt->bind_param("sis", $estado_db, $folio, $rut_proveedor);
    $stmt->execute();

    echo json_encode([
        "status" => "success", 
        "message" => "Documento procesado exitosamente en el SII.",
        "sii_response" => $api_res['descripcion']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>