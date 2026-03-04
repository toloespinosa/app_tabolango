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
 * API DE LOGIN NATIVO DE GOOGLE PARA TABOLANGO
 * Reemplaza a Nextend Social Login
 */
add_action('wp_ajax_login_nativo_tabolango', 'procesar_login_google_nativo');
add_action('wp_ajax_nopriv_login_nativo_tabolango', 'procesar_login_google_nativo');

function procesar_login_google_nativo() {
    $token = isset($_POST['token']) ? $_POST['token'] : '';

    if (empty($token)) {
        wp_send_json(['status' => 'error', 'message' => 'Token no recibido']);
    }

    // 1. Validar el token directamente con Google (Evita usar librerías pesadas)
    $google_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . $token;
    $response = wp_remote_get($google_url);

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        wp_send_json(['status' => 'error', 'message' => 'Firma de Google inválida o expirada']);
    }

    $payload = json_decode(wp_remote_retrieve_body($response), true);

    // 2. Extraer datos del usuario
    $email = strtolower($payload['email']);
    $first_name = isset($payload['given_name']) ? $payload['given_name'] : '';
    $last_name = isset($payload['family_name']) ? $payload['family_name'] : '';
    $picture = isset($payload['picture']) ? $payload['picture'] : ''; // 🔥 LÍNEA NUEVA: Atrapamos la foto
    $dominio = substr(strrchr($email, "@"), 1);

    // 3. Lógica de Negocio: Verificar si existe el usuario
    $user = get_user_by('email', $email);

    if (!$user) {
        // El usuario NO existe. ¿Es de @tabolango.cl?
        if ($dominio === 'tabolango.cl') {
            // Sí es del equipo. Creamos la cuenta en WordPress automáticamente.
            $random_password = wp_generate_password(12, false);
            $user_id = wp_create_user($email, $random_password, $email);
            
            if (is_wp_error($user_id)) {
                wp_send_json(['status' => 'error', 'message' => 'Error al crear la cuenta interna']);
            }

            // Actualizar nombre y apellido
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $first_name,
                'last_name' => $last_name
            ]);
            
            $user = get_user_by('id', $user_id);
        } else {
            // NO es de Tabolango y NO existe. Lo echamos.
            wp_send_json(['status' => 'error', 'message' => 'Tu correo no pertenece a @tabolango.cl y no tienes autorización previa.']);
        }
    }

    // 4. Iniciar sesión forzada y segura en WordPress
    clean_user_cache($user->ID);
    wp_clear_auth_cookie();
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true); // true = recordar sesión
    
    // Disparamos la acción por si algún otro plugin necesita saber que alguien entró
    do_action('wp_login', $user->user_login, $user);
// 1. Guardar o actualizar la foto en la memoria rápida de WordPress
    update_user_meta($user->ID, 'avatar_google', $picture); 
    
    // 2. 🔥 NUEVO: Guardar la foto en tu Base de Datos externa (app_usuarios)
    $app_db = new wpdb( APP_DB_USER, APP_DB_PASSWORD, APP_DB_NAME, APP_DB_HOST );
    if ( empty( $app_db->error ) && !empty($picture) ) {
        // Hacemos un UPDATE en tu tabla. 
        // OJO: Asumo que la columna del correo se llama 'usuario_email' como en tu otra tabla. 
        // Si se llama 'email', cámbialo en la línea de abajo.
        $app_db->query( $app_db->prepare(
            "UPDATE app_usuarios SET foto_url = %s WHERE email = %s", 
            $picture, $email
        ));
    }
    wp_send_json(['status' => 'success', 'message' => 'Bienvenido']);
}

/**
 * BLINDAJE GLOBAL DE LA WEB APP (ZERO TRUST)
 * Redirige a todo usuario no logueado directamente a /login/
 */
add_action( 'template_redirect', 'tabolango_forzar_login_global' );

function tabolango_forzar_login_global() {
    // 1. Si el usuario YA está logueado, lo dejamos navegar en paz.
    if ( is_user_logged_in() ) {
        return;
    }

    // 2. Excepción crítica: Evitar el bucle infinito. 
    // Si ya está intentando ver la página "login", no lo redirigimos de nuevo.
    if ( is_page( 'login' ) ) {
        return;
    }

    // 3. Si no está logueado y está intentando ver cualquier otra cosa, lo echamos al login.
    wp_redirect( home_url( '/login/' ) );
    exit;
}




/**
 * INYECCIÓN DE ESTADO GLOBAL DE LA APP (Identity Bridge)
 * Define globalmente quién es el usuario, su avatar y qué permisos tiene.
 * Conectado a la Base de Datos externa de la App.
 */
add_action('wp_head', 'inyectar_identidad_app', 5);

function inyectar_identidad_app() {
    // 1. Si no está logueado, inyectamos datos vacíos
    if (!is_user_logged_in()) {
        echo "\n\n";
        echo "<script>window.APP_USER_DATA = { email: '', avatar: '', rol_id: 0, isAdmin: false, isEditor: false, isConductor: false, isVendedor: false };</script>\n";
        return;
    }

    $current_user = wp_get_current_user();
    $email = $current_user->user_email;
    $rol_id = 0; // 0 = Sin acceso por defecto
    
   // 2. Conexión a la Base de Datos Externa de la App
    $app_db = new wpdb( APP_DB_USER, APP_DB_PASSWORD, APP_DB_NAME, APP_DB_HOST );
    $avatar_url = ''; // Variable vacía para empezar
    
    // Verificamos si hubo un error al conectar
    if ( !empty( $app_db->error ) ) {
        error_log("Error conectando a BD App: " . $app_db->error->get_error_message());
    } else {
        // A) Buscar el ROL
        $query_rol = $app_db->prepare("SELECT rol_id FROM app_usuario_roles WHERE usuario_email = %s ORDER BY rol_id ASC LIMIT 1", $email);
        $rol_obtenido = $app_db->get_var($query_rol);
        if ($rol_obtenido !== null) {
            $rol_id = (int)$rol_obtenido;
        }

        // B) Buscar la FOTO
        $query_foto = $app_db->prepare("SELECT foto_url FROM app_usuarios WHERE email = %s LIMIT 1", $email);
        $foto_obtenida = $app_db->get_var($query_foto);
        if (!empty($foto_obtenida)) {
            $avatar_url = $foto_obtenida;
        }
    }

    // 3. Diccionario de Roles para el Log
    $nombres_roles = [
        0 => 'Sin Acceso',
        1 => 'Administrador',
        2 => 'Editor',
        3 => 'Conductor',
        4 => 'Vendedor',
        5 => 'Cliente'
    ];
    $nombre_rol = isset($nombres_roles[$rol_id]) ? $nombres_roles[$rol_id] : 'Desconocido';

    // 4. Traducir el ID numérico a variables booleanas (True/False)
    $is_admin     = ($rol_id === 1) ? 'true' : 'false';
    $is_editor    = ($rol_id === 2) ? 'true' : 'false';
    $is_conductor = ($rol_id === 3) ? 'true' : 'false';
    $is_vendedor  = ($rol_id === 4) ? 'true' : 'false';

    // 5. Imprimimos el comentario HTML invisible y el Script con el Log
    echo "\n\n";
    echo "<script>
        window.APP_USER_DATA = {
            email: '{$email}',
            avatar: '{$avatar_url}',
            rol_id: {$rol_id},
            isAdmin: {$is_admin},
            isEditor: {$is_editor},
            isConductor: {$is_conductor},
            isVendedor: {$is_vendedor}
        };
        
        // Console Log estilizado (Solo visible si eres Admin por el silenciador de consola)
        console.log('%c 🚀 TABOLANGO APP | USUARIO: {$email} | ROL: {$nombre_rol} ({$rol_id}) ', 'background: #0F4B29; color: #E98C00; font-size: 11px; font-weight: bold; padding: 4px 8px; border-radius: 4px;');
    </script>\n";
}
/**
 * OCULTAR LA BARRA NEGRA DE WORDPRESS
 * Oculta la barra superior para todos en el frontend, manteniendo el diseño de la App limpio.
 */
add_filter( 'show_admin_bar', '__return_false' );
?>