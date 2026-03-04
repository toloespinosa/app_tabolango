<?php
require_once 'auth.php';
// enviar_factura_email.php - V2: SOLO EMAIL FACTURACIÓN
// ESTRICTO: Solo envía a 'email_factura'. Ignora el email de contacto.

mb_internal_encoding("UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit;
}

// Cargar librerías
$rutas_posibles = [__DIR__ . '/vendor/autoload.php', __DIR__ . '/autoload.php'];
$loaded = false;
foreach ($rutas_posibles as $ruta) { 
    if (file_exists($ruta)) { require_once $ruta; $loaded = true; break; } 
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- RECEPCIÓN DE DATOS ---
$idPedido      = $_POST['id_pedido'] ?? '';
// Capturamos SOLO el email de facturación
$emailFactura  = trim($_POST['email_factura'] ?? ''); 
$urlPdf        = $_POST['url_pdf'] ?? '';
$nombreCliente = $_POST['cliente'] ?? 'Cliente';
$folio         = $_POST['folio'] ?? 'SN';

// Limpieza básica
$nombreCliente = mb_convert_encoding($nombreCliente, 'UTF-8', 'auto');

// --- LÓGICA PARA ENCONTRAR ARCHIVO FÍSICO (Para adjuntar) ---
function obtenerRutaFisica($url) {
    $rutaRelativa = str_replace(['https://tabolango.cl/', 'http://tabolango.cl/'], '', $url);
    $rutaRelativa = ltrim($rutaRelativa, '/');
    
    if (file_exists($rutaRelativa)) return $rutaRelativa;
    
    $nombreArchivo = basename($rutaRelativa);
    $posibles = [
        "uploads/facturas_api/" . $nombreArchivo,
        "uploads/" . $nombreArchivo
    ];
    
    foreach ($posibles as $p) {
        if (file_exists($p)) return $p;
    }
    return false;
}

$rutaAdjunto = obtenerRutaFisica($urlPdf);

try {
    if (!$loaded) throw new Exception("No se cargó PHPMailer (vendor/autoload.php).");
    
    // --- VALIDACIÓN ESTRICTA ---
    // Si no hay email_factura, se detiene el proceso con error.
    if (empty($emailFactura) || !filter_var($emailFactura, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Error: No se proporcionó un 'email_factura' válido. No se envió el correo.");
    }

    $mail = new PHPMailer(true);

    // --- CONFIGURACIÓN DEL SERVIDOR ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'notificaciones@tabolango.cl'; 
    $mail->Password   = 'ychh fnhy hhew stgw'; // Tu App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // --- REMITENTE Y DESTINATARIO ---
    $mail->setFrom('notificaciones@tabolango.cl', 'Tabolango Facturación');
    
    // Solo agregamos el email de facturación
    $mail->addAddress($emailFactura, $nombreCliente);

    // --- ADJUNTO ---
    if ($rutaAdjunto) {
        $mail->addAttachment($rutaAdjunto, "Factura_N{$folio}.pdf");
    }

    // --- CONTENIDO DEL CORREO ---
    $mail->isHTML(true);
    $mail->Subject = "📄 Factura Electrónica N° $folio - $nombreCliente";

    $htmlContent = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;'>
        <div style='background-color: #ffffff; padding: 20px; text-align: center; border-bottom: 4px solid #0F4B29;'>
            <img src='https://tabolango.cl/media/logo_tabolango.png' alt='Tabolango' style='max-width: 150px;'>
        </div>
        <div style='background-color: #0F4B29; padding: 10px; text-align: center;'>
            <h2 style='color: #ffffff; margin: 0; font-size: 20px;'>Documento Tributario</h2>
        </div>
        <div style='padding: 25px; background-color: #f9f9f9;'>
            <p style='color: #333; font-size: 16px;'>Hola <strong>$nombreCliente</strong>,</p>
            <p style='color: #555; line-height: 1.5;'>
                Adjunto encontrarás tu <strong>Factura Electrónica N° $folio</strong> correspondiente al pedido <strong>#$idPedido</strong>.
            </p>
            
            <div style='background-color: #fff; padding: 15px; border-left: 4px solid #27ae60; margin: 20px 0;'>
                <p style='margin: 5px 0; color: #555;'><strong>Folio:</strong> $folio</p>
                <p style='margin: 5px 0; color: #555;'><strong>Fecha Emisión:</strong> " . date('d/m/Y') . "</p>
                <p style='margin: 5px 0; color: #555; font-size: 12px;'><strong>Enviado a:</strong> $emailFactura</p>
            </div>

            <div style='text-align:center; margin-top:20px;'>
                <a href='$urlPdf' style='background-color: #0F4B29; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Ver Documento Online</a>
            </div>
            
            <p style='color: #777; font-size: 12px; margin-top: 20px;'>
                Si no puedes ver el archivo adjunto, haz clic en el botón de arriba.
            </p>
        </div>
        <div style='background-color: #eee; padding: 15px; text-align: center; font-size: 12px; color: #888;'>
            © " . date('Y') . " Tabolango.
        </div>
    </div>";

    $mail->Body    = $htmlContent;
    $mail->AltBody = "Hola $nombreCliente, adjunto enviamos tu Factura N° $folio. Puedes descargarla aquí: $urlPdf";

    $mail->send();

    echo json_encode([
        "status" => "ok", 
        "message" => "Email enviado exitosamente a $emailFactura"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
    echo json_encode(["status" => "error", "message" => "Error al enviar: " . $errorMsg]);
}
?>