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
 */
function cargar_scripts_tabolango_app() {
    $main_css_path = get_template_directory() . '/css/main.css';
    if (file_exists($main_css_path)) {
        wp_enqueue_style('tabolango-app-css', get_template_directory_uri() . '/css/main.css', array(), filemtime($main_css_path));
    } else {
        wp_enqueue_style('tabolango-style', get_stylesheet_uri()); 
    }

    wp_enqueue_script('qrcode-lib', 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js', array(), '1.0', true);

    $global_js_path = get_template_directory() . '/js/global.js';
    if (file_exists($global_js_path)) {
        wp_enqueue_script('tabolango-global-js', get_template_directory_uri() . '/js/global.js', array('jquery', 'qrcode-lib'), filemtime($global_js_path), true);
        wp_localize_script('tabolango-global-js', 'wpData', array('siteUrl' => home_url(), 'themeUrl' => get_template_directory_uri()));
    }

    if ( is_page() || is_single() ) {
        global $post;
        $slug = $post->post_name; 

        $page_css_path = get_template_directory() . '/css/' . $slug . '.css';
        if ( file_exists($page_css_path) ) {
            wp_enqueue_style('tabolango-page-' . $slug . '-css', get_template_directory_uri() . '/css/' . $slug . '.css', array('tabolango-app-css'), filemtime($page_css_path));
        }

        $page_js_path = get_template_directory() . '/js/' . $slug . '.js';
        if ( file_exists($page_js_path) ) {
            wp_enqueue_script('tabolango-page-' . $slug . '-js', get_template_directory_uri() . '/js/' . $slug . '.js', array('tabolango-global-js'), filemtime($page_js_path), true);
        }
    }
}
add_action( 'wp_enqueue_scripts', 'cargar_scripts_tabolango_app' );

function mi_tema_pro_setup() {
    add_theme_support( 'custom-logo' );
    add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'mi_tema_pro_setup' );

/**
 * HELPER: CONECTOR INTELIGENTE A LA BASE DE DATOS DE LA APP
 * Resuelve la separación de bases de datos en Producción
 */
function tabolango_get_app_db() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '.local') !== false);

    if ($is_local) {
        global $wpdb;
        return $wpdb;
    } else {
        static $app_db = null;
        if ($app_db === null) {
            // Usa tus credenciales de Hostinger
            $app_db = new wpdb('tabolang_app', 'm{Hpj.?IZL$Kz${S', 'tabolang_pedidos', 'localhost');
        }
        return $app_db;
    }
}

/**
 * PROCESAR LOGIN DE GOOGLE Y VALIDAR AUTORIZACIÓN (100% BD)
 */
add_action('wp_ajax_login_nativo_tabolango', 'procesar_login_google_tabolango');
add_action('wp_ajax_nopriv_login_nativo_tabolango', 'procesar_login_google_tabolango');

function procesar_login_google_tabolango() {
    $token = $_POST['token'] ?? '';
    if (!$token) wp_send_json(['status' => 'error', 'message' => 'No se recibió el token de autenticación.']);

    $response = wp_remote_get("https://oauth2.googleapis.com/tokeninfo?id_token=" . $token);
    if (is_wp_error($response)) wp_send_json(['status' => 'error', 'message' => 'Error al comunicarse con Google.']);

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (isset($body['error'])) wp_send_json(['status' => 'error', 'message' => 'Token de Google inválido.']);

    $email = strtolower(sanitize_email($body['email']));
    $nombre = sanitize_text_field($body['given_name'] ?? '');
    $apellido = sanitize_text_field($body['family_name'] ?? '');
    $picture = sanitize_url($body['picture'] ?? '');

    $app_db = tabolango_get_app_db();
    $es_tabolango = str_ends_with($email, '@tabolango.cl');
    
    $usuario_db = $app_db->get_row($app_db->prepare("SELECT * FROM app_usuarios WHERE email = %s", $email));

    if (!$usuario_db) {
        if ($es_tabolango) {
            $user_login = explode('@', $email)[0];
            $app_db->insert('app_usuarios', [
                'user_login' => $user_login, 'nombre' => $nombre, 'apellido' => $apellido, 
                'email' => $email, 'foto_url' => $picture, 'cargo' => 'Usuario', 'activo' => 1
            ]);
        } else {
            wp_send_json([
                'status' => 'not_registered', 
                'message' => "El correo <b>{$email}</b> no pertenece al sistema.",
                'userData' => ['email' => $email, 'nombre' => $nombre, 'apellido' => $apellido, 'picture' => $picture]
            ]);
        }
    } else {
        if ($usuario_db->activo == 0) {
            wp_send_json(['status' => 'error', 'message' => 'Tu cuenta ha sido registrada pero está <b>pendiente de aprobación</b> por un Administrador.']);
        }
    }

    $user = get_user_by('email', $email);
    if (!$user) {
        $random_password = wp_generate_password(12, false);
        $username = sanitize_user(explode('@', $email)[0], true); 
        $user_id = wp_create_user($username, $random_password, $email);
        wp_update_user(['ID' => $user_id, 'first_name' => $nombre, 'last_name' => $apellido]);
        $user = get_user_by('id', $user_id);
    }

    clean_user_cache($user->ID); wp_clear_auth_cookie();
    wp_set_current_user($user->ID); wp_set_auth_cookie($user->ID, true); 
    update_user_caches($user); do_action('wp_login', $user->user_login, $user);

    update_user_meta($user->ID, 'avatar_google', $picture); 
    if (!empty($picture)) {
        $app_db->query($app_db->prepare("UPDATE app_usuarios SET foto_url = %s WHERE email = %s", $picture, $email));
    }

    wp_send_json(['status' => 'success', 'message' => 'Bienvenido']);
}

/**
 * NUEVO: ENDPOINT PARA GUARDAR SOLICITUD DE ACCESO (Inactivo por defecto)
 */
add_action('wp_ajax_nopriv_solicitar_acceso_tabolango', 'crear_solicitud_acceso_tabolango');
function crear_solicitud_acceso_tabolango() {
    $app_db = tabolango_get_app_db();
    $email = sanitize_email($_POST['email'] ?? '');
    $nombre = sanitize_text_field($_POST['nombre'] ?? '');
    $apellido = sanitize_text_field($_POST['apellido'] ?? '');
    $picture = esc_url_raw($_POST['picture'] ?? '');

    if (!$email) wp_send_json(['status' => 'error', 'message' => 'Datos incompletos.']);

    $base_login = explode('@', $email)[0];
    $user_login = $base_login;
    $counter = 1;
    while ($app_db->get_var($app_db->prepare("SELECT id FROM app_usuarios WHERE user_login = %s", $user_login))) {
        $user_login = $base_login . $counter; $counter++;
    }

    $inserted = $app_db->insert('app_usuarios', [
        'user_login' => $user_login, 'nombre' => $nombre, 'apellido' => $apellido,
        'email' => $email, 'foto_url' => $picture, 'cargo' => 'Usuario', 'activo' => 0 
    ]);

    if ($inserted) {
        wp_send_json(['status' => 'success', 'message' => 'Solicitud enviada. Contacta a un administrador para que active tu cuenta.']);
    } else {
        wp_send_json(['status' => 'error', 'message' => 'Error al registrar la solicitud.']);
    }
}

/**
 * BLINDAJE GLOBAL DE LA WEB APP
 */
add_action( 'template_redirect', 'tabolango_forzar_login_global' );
function tabolango_forzar_login_global() {
    if ( ! is_user_logged_in() && ! is_page( 'login' ) ) { 
        wp_redirect( home_url( '/login/' ) );
        exit;
    }
}

/**
 * OBTENER EL ROL DEL USUARIO ACTUAL
 */
function tabolango_get_user_role() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, '.local') !== false);
    
    // Leemos la simulación visual si existe en local
    if ( $is_local && isset($_COOKIE['simular_rol_tabolango']) ) {
        return (int) $_COOKIE['simular_rol_tabolango'];
    }

    if ( ! is_user_logged_in() ) return 0;
    
    static $user_role = null; 
    if ( $user_role !== null ) return $user_role;

    $app_db = tabolango_get_app_db();
    $email = wp_get_current_user()->user_email;
    $user_role = (int) $app_db->get_var($app_db->prepare("SELECT rol_id FROM app_usuario_roles WHERE usuario_email = %s LIMIT 1", $email));
    
    return $user_role;
}

/**
 * FUNCIÓN PARA BLOQUEAR PÁGINAS FÍSICAS (DISEÑO CAJA BLANCA)
 */
function tabolango_requerir_rol($roles_permitidos) {
    $rol_actual = tabolango_get_user_role();
    
    if ( ! in_array($rol_actual, $roles_permitidos) ) {
        status_header(403);
        get_header(); 
        ?>
        <div style="padding: 10vh 20px; display: flex; justify-content: center; align-items: center; min-height: 60vh;">
            <div class="caja-blanca" style="text-align: center; max-width: 450px; width: 100%; padding: 40px 30px; animation: fadeIn 0.5s ease-out; margin: 0 auto;">
                <style>
                    @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
                    .btn-volver-denegado { background: #E98C00; color: white !important; border: none; padding: 14px 35px; border-radius: 8px; font-weight: 800; font-size: 14px; cursor: pointer; text-decoration: none; text-transform: uppercase; display: inline-block; transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin-top: 10px; }
                    .btn-volver-denegado:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(233, 140, 0, 0.4) !important; }
                </style>
                
                <i class="fa-solid fa-shield-halved" style="font-size: 65px; margin-bottom: 20px; color: #e74c3c;"></i>
                <h2 style="font-size: 24px; font-weight: 900; margin-bottom: 15px; margin-top: 0; color: #333 !important;">Acceso Restringido</h2>
                <p style="font-size: 15px; color: #666 !important; margin: 0 auto 25px auto; line-height: 1.6; font-weight: 500;">No tienes los privilegios necesarios para ver o modificar esta sección del sistema.</p>
                
                <a href="<?php echo home_url('/'); ?>" class="btn-volver-denegado">
                    <i class="fa-solid fa-house" style="margin-right:8px;"></i> Volver al Inicio
                </a>
            </div>
        </div>
        <?php
        get_footer(); 
        exit;
    }
}

/**
 * INYECCIÓN DE ESTADO GLOBAL DE LA APP (Identity Bridge Limpio)
 */
add_action('wp_head', 'inyectar_identidad_app', 5);
function inyectar_identidad_app() {
    if (!is_user_logged_in()) {
        echo "\n<script>window.APP_USER_DATA = { email: '', rol_id: 0, isAdmin: false, isEditor: false, isConductor: false, isVendedor: false };</script>\n";
        return;
    }

    $email = wp_get_current_user()->user_email;
    $rol_id = tabolango_get_user_role(); // Usa la función centralizada que respeta tu Cookie de simulación

    $is_admin = ($rol_id === 1) ? 'true' : 'false';
    $is_editor = ($rol_id === 2) ? 'true' : 'false';
    $is_conductor = ($rol_id === 3) ? 'true' : 'false';
    $is_vendedor = ($rol_id === 4) ? 'true' : 'false';

    echo "\n<script>window.APP_USER_DATA = { email: '{$email}', rol_id: {$rol_id}, isAdmin: {$is_admin}, isEditor: {$is_editor}, isConductor: {$is_conductor}, isVendedor: {$is_vendedor} };</script>\n";
}

add_filter( 'show_admin_bar', '__return_false' );
?>