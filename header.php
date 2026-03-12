<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?php echo get_stylesheet_uri(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php 
$is_logged_in = is_user_logged_in();
$current_user_bridge = $is_logged_in ? wp_get_current_user() : null;

// 🔥 ÚNICA LLAMADA AL ROL: Depende 100% de tu functions.php y la BD
$rol_id = function_exists('tabolango_get_user_role') ? tabolango_get_user_role() : 0;
?>

<?php if ( $is_logged_in ) : ?>
    <div id="session-email-bridge" style="display:none;" data-email="<?php echo esc_attr($current_user_bridge->user_email); ?>">
        <?php echo esc_html($current_user_bridge->user_email); ?>
    </div>
<?php endif; ?>

<header id="masthead" class="site-header">
    <div class="header-container">
        
        <div class="header-left">
            <div class="custom-ham-wrapper">
                <div class="ham-trigger" id="hamTrigger">
                    <div class="ham-icon">
                        <span></span><span></span><span></span>
                    </div>
                    <span class="ham-label">MENÚ</span>
                </div>
                
                <div class="ham-dropdown" id="hamDropdown">
                    <div class="ham-arrow"></div>
                    <div class="ham-header">NAVEGACIÓN</div>
                    <div class="ham-body">
                        <a href="<?php echo home_url('/'); ?>"><span role="img" class="emoji">🏠</span> Inicio</a>
                        
                        <?php if (in_array($rol_id, [1, 2, 4])) : // Solo Admin(1), Editor(2) y Vendedor(4) ?>
                            <a href="<?php echo home_url('/ingresar/'); ?>"><span role="img" class="emoji">📝</span> Nuevo Pedido</a>
                            <a href="<?php echo home_url('/clientes/'); ?>"><span role="img" class="emoji">🤝</span> Clientes</a>
                        <?php endif; ?>
                        
                        <?php if (in_array($rol_id, [1, 2])) : // Solo Admin(1) y Editor(2) ?>
                            <hr>
                            <a href="<?php echo home_url('/ajustes'); ?>"><span role="img" class="emoji">⚙️</span> Ajustes</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="header-center">
            <div class="site-branding">
                <?php 
                if ( has_custom_logo() ) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . home_url() . '">' . get_bloginfo( 'name' ) . '</a>';
                }
                ?>
            </div>
        </div>

        <div class="header-right">
            <?php 
            $login_url = home_url( '/login/' );
            $avatar_url = $is_logged_in ? get_avatar_url( $current_user_bridge->ID ) : ''; 

            // --- BUSCAR FOTO DIRECTO EN LA BASE DE DATOS EXTERNA ---
            if ( $is_logged_in ) {
                global $wpdb;
                $foto_bd = $wpdb->get_var( $wpdb->prepare( "SELECT foto_url FROM app_usuarios WHERE email = %s", $current_user_bridge->user_email ) );
                if ( !empty( $foto_bd ) ) {
                    $avatar_url = $foto_bd;
                }
            }
            ?>
            
            <div class="user-profile-wrapper">
                <div class="user-avatar-trigger" id="userTrigger" 
                     data-logged="<?php echo $is_logged_in ? 'true' : 'false'; ?>" 
                     data-login-url="<?php echo esc_url($login_url); ?>">
                    
                    <?php if ($is_logged_in) : ?>
                        <span class="user-welcome-text">Hola, <?php echo esc_html($current_user_bridge->first_name ?: $current_user_bridge->display_name); ?>!</span>
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="Perfil" class="custom-avatar">
                    <?php else : ?>
                         <span class="user-welcome-text">Iniciar Sesión</span>
                    <?php endif; ?>
                </div>

                <?php if ($is_logged_in) : ?>
                <div class="user-dropdown-menu" id="userDropdown">
                    <div class="dropdown-arrow"></div>
                    <div class="dropdown-header">
                        <p class="user-name-title"><?php echo esc_html($current_user_bridge->first_name . ' ' . $current_user_bridge->last_name); ?></p>
                        <p class="user-status-tag">Sesión Activa</p>
                    </div>
                    <div class="dropdown-content">
                        <a href="<?php echo home_url('/perfil/'); ?>"><span role="img" class="emoji">🙋</span> Mi Perfil</a>
                        
                        <?php if (in_array($rol_id, [1, 2, 4])) : // Admin(1), Editor(2), Vendedor(4) ?>
                            <a href="<?php echo home_url('/productos/'); ?>"><span role="img" class="emoji">🍅</span> Productos</a>
                            <a href="<?php echo home_url('/precios/'); ?>"><span role="img" class="emoji">💸</span> Precios</a>
                        <?php endif; ?>
                        
                        <?php if (in_array($rol_id, [1, 2, 3])) : // Admin(1), Editor(2), Conductor(3) ?>
                            <a href="<?php echo home_url('/tus-autos/'); ?>"><span role="img" class="emoji">🚛</span> Mis Autos</a>
                        <?php endif; ?>
                        
                        <?php if ($rol_id === 1) : // SOLO Admin(1) ?>
                            <a href="<?php echo home_url('/admin-usuarios/'); ?>"><span role="img" class="emoji">💻</span> Directorio</a>
                        <?php endif; ?>
                        
                        <hr class="dropdown-divider">
                        <a href="<?php echo wp_logout_url( home_url() ); ?>" class="logout-btn"><span role="img" class="emoji">🚪</span> Cerrar Sesión</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</header>
<div id="main-content-wrapper">