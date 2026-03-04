<?php
require_once 'auth.php';
// webhook_respuesta.php - RESPUESTA INTELIGENTE CON BOTÓN
require_once 'vendor/autoload.php'; // Asegúrate que la ruta sea correcta
use Twilio\Rest\Client;

// 1. CREDENCIALES (Las mismas de enviar_factura.php)
$sid    = "TWILIO_ACCOUNT_SID"; // Tu SID real
$token  = "TWILIO_AUTH_TOKEN"; // Tu Token real
$twilio = new Client($sid, $token);

// 2. ID DE LA PLANTILLA (Cópialo de Twilio Content Template Builder)
// Debe empezar con HX...
$templateSid = "HX69b18ea7db16bd8aac36d108a52ccba8"; 

// 3. RECIBIR DATOS
$mensajeCliente = $_POST['Body'] ?? '';
$numeroCliente  = $_POST['From'] ?? ''; // Viene como whatsapp:+569...
$miNumeroTwilio = $_POST['To'] ?? '';   // Tu número de Twilio

// Evitar bucles: Si no hay mensaje o número, no hacemos nada
if (empty($mensajeCliente) || empty($numeroCliente)) {
    exit;
}

// 4. CEREBRO INTELIGENTE 🧠
// Convertimos a minúsculas para analizar mejor
$texto = strtolower(trim($mensajeCliente));

// Lista de palabras de agradecimiento o confirmación
$palabrasAgradecimiento = ['gracias', 'grx', 'muchas gracias', 'te pasaste', 'ok', 'vale', 'listo', 'bueno', 'recibido', 'genial'];

$respuestaTexto = "";

// Función para ver si alguna palabra clave está en el mensaje
function contienePalabra($texto, $lista) {
    foreach ($lista as $palabra) {
        if (strpos($texto, $palabra) !== false) return true;
    }
    return false;
}

if (contienePalabra($texto, $palabrasAgradecimiento)) {
    // CASO 1: El cliente dijo "Gracias", "Ok", etc.
    $respuestaTexto = "¡Gracias a ti! 🤝\n\nCualquier cosa me escribes en al Whatsapp de abajo 👇";
} else {
    // CASO 2: Dijo cualquier otra cosa ("Hola", "¿Precio?", "Ayuda")
    $respuestaTexto = "¡Hola! 👋 Soy el asistente virtual de facturación.\n\nLamentablemente solo soy un Robot 🤖, pero si presionas el boton de abajo puedes hablar directamente con Sofía.";
}

// 5. ENVIAR LA PLANTILLA CON EL TEXTO DINÁMICO
try {
    $message = $twilio->messages->create(
        $numeroCliente, // Destino
        [
            "from" => $miNumeroTwilio, // Origen
            "contentSid" => $templateSid, // ID de la plantilla HX...
            "contentVariables" => json_encode([
                "1" => $respuestaTexto // Aquí inyectamos el texto inteligente en {{1}}
            ])
        ]
    );
} catch (Exception $e) {
    // Opcional: Guardar error en un log
    file_put_contents("error_log_webhook.txt", $e->getMessage());
}
?>