<?php
require_once 'auth.php';
/**
 * Ajustes de Notificaciones
 * Integrado con WordPress y base de datos local
 */

// ==============================================================================
// 1. BLOQUE MAESTRO DE CARGA (Autoload + WordPress)
// ==============================================================================
$theme_root = dirname(__DIR__); // Subimos un nivel desde /inc/

// A. Cargar Composer (si existe)
$autoload_path = $theme_root . '/vendor/autoload.php';
if (file_exists($autoload_path)) {
    require_once $autoload_path;
}

// B. Cargar el motor de WordPress
if (!defined('ABSPATH')) {
    $wp_load_path = $theme_root;
    while (!file_exists($wp_load_path . '/wp-load.php')) {
        $wp_load_path = dirname($wp_load_path);
        if ($wp_load_path == '/' || $wp_load_path == '.') break;
    }
    if (file_exists($wp_load_path . '/wp-load.php')) {
        require_once($wp_load_path . '/wp-load.php');
    }
}

// C. Verificar seguridad
if (!defined('ABSPATH')) {
    die("Error: No se pudo cargar WordPress.");
}

// ==============================================================================
// 2. LÓGICA DE NEGOCIO (Usando $wpdb)
// ==============================================================================
global $wpdb;

// Verificar si el usuario está logueado
if (!is_user_logged_in()) {
    wp_die('Debes iniciar sesión para ver tus ajustes.', 'Acceso Denegado');
}

// Obtener el usuario actual dinámicamente
$current_user = wp_get_current_user();
$email_usuario = $current_user->user_email; 

// Obtener preferencias actuales usando $wpdb
$tabla = 'app_fcm_tokens'; // Asegúrate que este sea el nombre correcto de tu tabla
$preferencias = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $tabla WHERE email = %s LIMIT 1", $email_usuario), 
    ARRAY_A
);

// Si no hay registro aún, creamos un array por defecto
if (!$preferencias) {
    $preferencias = [
        'notify_pedido_creado' => 1, 'notify_cambio_estado' => 1,
        'notify_pedido_entregado' => 1, 'notify_pedido_editado' => 1,
        'notify_doc_por_vencer' => 1, 'notify_doc_vencido' => 1
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes de Notificaciones - Tabolango</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* Estilos personalizados simples */
        body { font-family: 'Open Sans', sans-serif; }
        .form-check-input:checked { background-color: #0F4B29; border-color: #0F4B29; } /* Verde Tabolango */
        .list-group-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; }
        .card-header { background-color: #0F4B29 !important; }
    </style>
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width: 600px;">
    <div class="card shadow-sm">
        <div class="card-header text-white">
            <h5 class="mb-0">Preferencias de Notificación</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Configuración para: <strong><?php
require_once 'auth.php'; echo esc_html($email_usuario); ?></strong><br>
                Elige qué avisos quieres recibir en este dispositivo.
            </p>
            
            <ul class="list-group list-group-flush">
                <?php
require_once 'auth.php';
                $categorias = [
                    'notify_pedido_creado' => '📦 Pedido Creado',
                    'notify_cambio_estado' => '🔄 Cambio de Estado',
                    'notify_pedido_entregado' => '✅ Pedido Entregado',
                    'notify_pedido_editado' => '📝 Pedido Editado',
                    'notify_doc_por_vencer' => '⚠️ Documento por vencer',
                    'notify_doc_vencido' => '🚫 Documento vencido'
                ];

                foreach ($categorias as $columna => $label) {
                    // Verificamos si existe la clave, si no, asumimos 1 (activado)
                    $val = isset($preferencias[$columna]) ? $preferencias[$columna] : 1;
                    $checked = $val ? 'checked' : '';
                    
                    echo "
                    <li class='list-group-item'>
                        <span>$label</span>
                        <div class='form-check form-switch'>
                            <input class='form-check-input' type='checkbox' 
                                   onchange='actualizarPreferencia(\"$columna\", this.checked)' 
                                   $checked>
                        </div>
                    </li>";
                }
                ?>
            </ul>
        </div>
    </div>
</div>

<script>
function actualizarPreferencia(columna, estado) {
    const valor = estado ? 1 : 0;
    
    // IMPORTANTE: Asegúrate de que 'update_preference_ajax.php' también tenga el 
    // "Bloque Maestro" y use $wpdb, o esta llamada fallará.
    fetch('update_preference_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type: application/x-www-form-urlencoded' },
        body: `columna=${columna}&valor=${valor}`
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Error al guardar: ' + (data.message || 'Error desconocido'));
        } else {
            console.log('Preferencia guardada');
        }
    })
    .catch(err => console.error('Error de red:', err));
}
</script>

</body>
</html>