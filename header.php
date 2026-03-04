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
                        <a href="<?php echo home_url('/ingresar/'); ?>"><span role="img" class="emoji">📝</span> Nuevo Pedido</a>
                        <a href="<?php echo home_url('/clientes/'); ?>"><span role="img" class="emoji">🤝</span> Clientes</a>
                        <hr>
                        <a href="<?php echo home_url('/ajustes'); ?>"><span role="img" class="emoji">⚙️</span> Ajustes</a>
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
            // Obtenemos el usuario actual
            $current_user = wp_get_current_user();
            $is_logged_in = is_user_logged_in();
            $login_url = home_url( '/login/' );
            
            // Avatar (Intenta obtener avatar o usa uno por defecto)
            // 1. Buscamos la foto de Google que guardamos en functions.php
$google_avatar = get_user_meta( $current_user->ID, 'avatar_google', true );

// 2. Si tiene foto de Google, la usamos. Si no, usamos la de por defecto.
$avatar_url = !empty( $google_avatar ) ? $google_avatar : get_avatar_url( $current_user->ID );
            ?>
            
            <div class="user-profile-wrapper">
                <div class="user-avatar-trigger" id="userTrigger" 
                     data-logged="<?php echo $is_logged_in ? 'true' : 'false'; ?>" 
                     data-login-url="<?php echo esc_url($login_url); ?>">
                    
                    <?php if ($is_logged_in) : ?>
                        <span class="user-welcome-text">Hola, <?php echo esc_html($current_user->first_name ?: $current_user->display_name); ?>!</span>
                        <img src="<?php echo esc_url($avatar_url); ?>" alt="Perfil" class="custom-avatar">
                    <?php else : ?>
                         <span class="user-welcome-text">Iniciar Sesión</span>
                    <?php endif; ?>
                </div>

                <?php if ($is_logged_in) : ?>
                <div class="user-dropdown-menu" id="userDropdown">
                    <div class="dropdown-arrow"></div>
                    <div class="dropdown-header">
                        <p class="user-name-title"><?php echo esc_html($current_user->first_name . ' ' . $current_user->last_name); ?></p>
                        <p class="user-status-tag">Sesión Activa</p>
                    </div>
                    <div class="dropdown-content">
                        <a href="<?php echo home_url('/perfil/'); ?>"><span role="img" class="emoji">🙋</span> Mi Perfil</a>
                        <a href="<?php echo home_url('/productos/'); ?>"><span role="img" class="emoji">🍅</span> Productos</a>
                        <a href="<?php echo home_url('/precios/'); ?>"><span role="img" class="emoji">💸</span> Precios</a>
                        <a href="<?php echo home_url('/tus-autos/'); ?>"><span role="img" class="emoji">🚛</span> Mis Autos</a>
                        
                        <?php if (current_user_can('administrator')) : ?>
                            <a href="<?php echo home_url('/admin-usuarios/'); ?>"><span role="img" class="emoji">💻</span> Directorio</a>
                        <?php endif; ?>
                        
                        <hr class="dropdown-divider">
                        <a href="<?php echo wp_logout_url( home_url() ); ?>" class="logout-btn"><span role="img" class="emoji">🚪</span> Cerrar Sesión</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div></header>
<div id="main-content-wrapper">