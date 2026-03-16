<?php
// test-fcm.php - Script de prueba para Firebase en Local

// 1. Evitamos caché y mostramos errores en pantalla para depurar rápido
header("Cache-Control: no-cache, must-revalidate");
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h3>🚀 Iniciando prueba de FCM en Local...</h3>";

// 2. Cargamos tus dependencias
require_once 'auth.php'; // Asegura la conexión a la BD local
require_once 'notifications.php'; // Carga la lógica optimizada

// 3. Definimos los datos de prueba
$email_prueba = 'jandres@tabolango.cl'; // Tu correo (según la BD)
$titulo = '🔔 ¡Prueba Exitosa!';
$mensaje = 'El service-account.json fue leído correctamente en LocalWP.';
$ruta = '/'; // URL fallback a la raíz

echo "<p>Intentando enviar notificación a: <strong>$email_prueba</strong></p>";

// 4. Ejecutamos la función
enviarNotificacionFCM($email_prueba, $titulo, $mensaje, $ruta, 'notify_pedido_creado');

echo "<p>✅ Proceso finalizado. Por favor, revisa:</p>";
echo "<ul>
        <li>La pantalla de tu dispositivo/navegador (¿Llegó el push?).</li>
        <li>El archivo <strong>error_log.txt</strong> en esta misma carpeta para ver la respuesta exacta de Google (Código 200 es éxito).</li>
      </ul>";
?>