<?php
/**
 * Functions and definitions for Theme: Tabolango
 */

// 1. AUTO-CARGA DE LIBRERÍAS (COMPOSER) - Si usas PDF, etc.
if ( file_exists( get_template_directory() . '/vendor/autoload.php' ) ) {
    require_once get_template_directory() . '/vendor/autoload.php';
}

/**
 * 2. CARGA DE SCRIPTS Y ESTILOS (OPTIMIZADO)
 * Aquí conectamos el CSS nuevo y el JS Inteligente*/
function cargar_scripts_tabolango_app() {
    
    // 1. Cargar CSS Principal (Asegúrate de que la ruta sea correcta)
    $main_css_path = get_template_directory() . '/css/main.css';
    if (file_exists($main_css_path)) {
        wp_enqueue_style('tabolango-app-css', get_template_directory_uri() . '/css/main.css', array(), filemtime($main_css_path));
    } else {
        wp_enqueue_style('tabolango-style', get_stylesheet_uri()); 
    }

    // 2. Cargar Librería QR
    wp_enqueue_script('qrcode-lib', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0', true);

    // 3. Cargar global.js
    $global_js_path = get_template_directory() . '/js/global.js';
    if (file_exists($global_js_path)) {
        wp_enqueue_script('tabolango-global-js', get_template_directory_uri() . '/js/global.js', array('jquery', 'qrcode-lib'), filemtime($global_js_path), true);
        wp_localize_script('tabolango-global-js', 'wpData', array('siteUrl' => home_url(), 'themeUrl' => get_template_directory_uri()));
    }

    // 🔥 LA MAGIA: Carga dinámica por nombre de página (Slug) 🔥
    if ( is_page() || is_single() ) {
        global $post;
        $slug = $post->post_name; // Obtiene el nombre de la página (ej: 'pedidos', 'clientes')

        // A. Cargar CSS específico de la página (si existe)
        $page_css_path = get_template_directory() . '/css/' . $slug . '.css';
        if ( file_exists($page_css_path) ) {
            wp_enqueue_style(
                'tabolango-page-' . $slug . '-css', 
                get_template_directory_uri() . '/css/' . $slug . '.css', 
                array('tabolango-app-css'), // Se carga DESPUÉS del CSS principal
                filemtime($page_css_path)   // Cache-buster inteligente
            );
        }

        // B. Cargar JS específico de la página (si existe)
        $page_js_path = get_template_directory() . '/js/' . $slug . '.js';
        if ( file_exists($page_js_path) ) {
            wp_enqueue_script(
                'tabolango-page-' . $slug . '-js', 
                get_template_directory_uri() . '/js/' . $slug . '.js', 
                array('tabolango-global-js'), // Se carga DESPUÉS de global.js
                filemtime($page_js_path),     // Cache-buster inteligente
                true                          // Se carga en el footer para no bloquear la web
            );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'cargar_scripts_tabolango_app' );

/**
 * 3. SOPORTE DEL TEMA
 */
function mi_tema_pro_setup() {
    add_theme_support( 'custom-logo' );
    add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'mi_tema_pro_setup' );

/**
 * PROCESAR LOGIN DE GOOGLE Y VALIDAR AUTORIZACIÓN (100% BD)
 */
add_action('wp_ajax_login_nativo_tabolango', 'procesar_login_google_tabolango');
add_action('wp_ajax_nopriv_login_nativo_tabolango', 'procesar_login_google_tabolango');

function procesar_login_google_tabolango() {
    $token = $_POST['token'] ?? '';
    if (!$token) {
        wp_send_json(['status' => 'error', 'message' => 'No se recibió el token de autenticación.']);
    }

    // 1. Validar el Token directamente con los servidores de Google
    $response = wp_remote_get("https://oauth2.googleapis.com/tokeninfo?id_token=" . $token);
    
    if (is_wp_error($response)) {
        wp_send_json(['status' => 'error', 'message' => 'Error al comunicarse con Google.']);
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        wp_send_json(['status' => 'error', 'message' => 'Token de Google inválido o expirado.']);
    }

    // 2. Extraer datos del usuario de Google
    $email = strtolower(sanitize_email($body['email']));
    $nombre = sanitize_text_field($body['given_name'] ?? '');
    $apellido = sanitize_text_field($body['family_name'] ?? '');
    $picture = sanitize_url($body['picture'] ?? '');

    // 3. --- LÓGICA DE AUTORIZACIÓN (100% DEPENDIENTE DE LA BASE DE DATOS) ---
    global $wpdb;
    
    // Buscamos si el usuario existe en la tabla y está activo (sin prefijar la BD)
    $usuario_db = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM app_usuarios WHERE email = %s AND activo = 1", 
        $email
    ));

    // Si NO está en la base de datos o está inactivo -> ¡Rechazado!
    if (!$usuario_db) {
        wp_send_json([
            'status' => 'error', 
            'message' => 'Tu correo ('.$email.') no está autorizado en el sistema.'
        ]);
    }

    // 4. --- CREACIÓN O INICIO DE SESIÓN EN WORDPRESS ---
    $user = get_user_by('email', $email);

    if (!$user) {
        // Creamos la cuenta interna dinámicamente
        $random_password = wp_generate_password(12, false);
        $username = sanitize_user(explode('@', $email)[0], true); 
        
        $user_id = wp_create_user($username, $random_password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json(['status' => 'error', 'message' => 'Error al crear la cuenta de sesión interna.']);
        }
        
        wp_update_user(['ID' => $user_id, 'first_name' => $nombre, 'last_name' => $apellido]);
        $user = get_user_by('id', $user_id);
    }

    // 5. Iniciar la sesión de WordPress automáticamente
    clean_user_cache($user->ID);
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true); 
    update_user_caches($user);

    do_action('wp_login', $user->user_login, $user);

    // 6. Guardar la foto en WordPress
    update_user_meta($user->ID, 'avatar_google', $picture); 
    
    // 7. Actualizar la foto en tu tabla personalizada (Usando $wpdb de forma segura)
    if (!empty($picture)) {
        $wpdb->query($wpdb->prepare(
            "UPDATE app_usuarios SET foto_url = %s WHERE email = %s", 
            $picture, $email
        ));
    }

    wp_send_json(['status' => 'success', 'message' => 'Bienvenido']);
}

/**
 * BLINDAJE GLOBAL DE LA WEB APP (ZERO TRUST)
 */
add_action( 'template_redirect', 'tabolango_forzar_login_global' );
function tabolango_forzar_login_global() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ngrok') !== false || strpos($host, '.local') !== false) {
        return; 
    }

    if ( is_user_logged_in() || is_page( 'login' ) ) { return; }
    wp_redirect( home_url( '/login/' ) );
    exit;
}
/**
 * OBTENER EL ROL DEL USUARIO ACTUAL (COMPATIBLE CON TU WIDGET JS)
 */
function tabolango_get_user_role() {
    // 1. MODO DIOS: Leer la simulación que viene de tu global.js
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '.local') !== false);
    
    if ( $is_local && isset($_COOKIE['simular_rol_tabolango']) ) {
        return (int) $_COOKIE['simular_rol_tabolango']; // PHP obedece a tu widget
    }

    // 2. FLUJO NORMAL EN PRODUCCIÓN (Desde la Base de Datos)
    if ( ! is_user_logged_in() ) return 0;
    
    static $user_role = null; 
    if ( $user_role !== null ) return $user_role;

    global $wpdb;
    $email = wp_get_current_user()->user_email;
    $user_role = (int) $wpdb->get_var($wpdb->prepare("SELECT rol_id FROM app_usuario_roles WHERE usuario_email = %s LIMIT 1", $email));
    
    return $user_role;
}

/**
 * FUNCIÓN PARA BLOQUEAR PÁGINAS FÍSICAS (TOTALMENTE INTEGRADA AL DISEÑO)
 */
function tabolango_requerir_rol($roles_permitidos) {
    $rol_actual = tabolango_get_user_role();
    
    if ( ! in_array($rol_actual, $roles_permitidos) ) {
        // 1. Enviamos código 403 de acceso denegado (Buena práctica)
        status_header(403);
        
        // 2. Cargamos tu header.php (fondo, navbar, contenedor principal)
        get_header(); 
        
        // 3. Imprimimos el mensaje de error directamente en el main-content-wrapper
        ?>
        <div class="caja-blanca" style="text-align: center; max-width: 850px; width: 100%; padding: 50px 30px; margin: 0 auto;">
                <i class="fa-solid fa-shield-halved" style="font-size: 70px; margin-bottom: 20px; color: #e74c3c;"></i>
                <h2 style="font-size: 26px; font-weight: 900; margin-bottom: 15px; margin-top: 0; color: #333 !important;">Acceso Restringido</h2>
                <p style="font-size: 15px; color: #666 !important; margin: 0 auto 35px auto; line-height: 1.6; font-weight: 500;">No tienes los privilegios necesarios para ver o modificar esta sección del sistema.</p>
                
                <a href="<?php echo home_url('/'); ?>" class="btn-volver-denegado" style="background: #E98C00; color: white !important; border: none; padding: 14px 35px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; text-decoration: none; text-transform: uppercase; display: inline-block; transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                    <i class="fa-solid fa-house" style="margin-right:8px;"></i> Volver al Inicio
                </a>
            </div>
        </div>
        <?php
        
        // 4. Cargamos tu footer.php
        get_footer(); 
        
        // 5. ABORTAMOS la ejecución
        exit;
    }
}
/**
 * INYECCIÓN DE ESTADO GLOBAL DE LA APP (Identity Bridge)
 */
add_action('wp_head', 'inyectar_identidad_app', 5);
function inyectar_identidad_app() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ngrok') !== false || strpos($host, '.local') !== false);

    // MODO DIOS LOCAL
    if ($is_local) {
        echo "\n<script>
            window.APP_USER_DATA = { email: 'jaespinosaa@gmail.com', rol_id: 1, isAdmin: true, isEditor: true, isConductor: false, isVendedor: false };
            console.log('🛠️ MODO DIOS LOCAL ACTIVO');
        </script>\n";
        return;
    }

    // MODO PRODUCCIÓN
    if (!is_user_logged_in()) {
        echo "\n<script>window.APP_USER_DATA = { email: '', rol_id: 0, isAdmin: false, isEditor: false, isConductor: false, isVendedor: false };</script>\n";
        return;
    }

    $email = wp_get_current_user()->user_email;
    
    // Obtenemos el rol usando global $wpdb (Evita errores 500 por credenciales manuales)
    global $wpdb;
    $rol_id = (int) $wpdb->get_var($wpdb->prepare("SELECT rol_id FROM app_usuario_roles WHERE usuario_email = %s LIMIT 1", $email));

    $is_admin = ($rol_id === 1) ? 'true' : 'false';
    $is_editor = ($rol_id === 2) ? 'true' : 'false';
    $is_conductor = ($rol_id === 3) ? 'true' : 'false';
    $is_vendedor = ($rol_id === 4) ? 'true' : 'false';

    echo "\n<script>window.APP_USER_DATA = { email: '{$email}', rol_id: {$rol_id}, isAdmin: {$is_admin}, isEditor: {$is_editor}, isConductor: {$is_conductor}, isVendedor: {$is_vendedor} };</script>\n";
}

add_filter( 'show_admin_bar', '__return_false' );
?>