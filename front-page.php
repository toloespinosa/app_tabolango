<?php
/**
 * Template Name: Página de Inicio (Dashboard)
 */

get_header(); 

// 1. Obtenemos el usuario actual de WordPress
$current_user = wp_get_current_user();
$nombre_mostrar = $current_user->first_name ? $current_user->first_name : $current_user->display_name;

// Si no hay nadie logueado (caso raro en dashboard), ponemos "Invitado"
if ( ! is_user_logged_in() ) {
    $nombre_mostrar = "Invitado";
}
?>

<main class="site-main dashboard-container">
    
    <div class="tabolango-dashboard">
        <header class="welcome-section">
            <div class="welcome-text">
                <h1>Hola, <span><?php echo esc_html($nombre_mostrar); ?></span></h1>
                <p>SISTEMA DE GESTIÓN DE PEDIDOS</p>
            </div>
        </header>

        <div class="actions-grid">
            
            <a href="<?php echo home_url('/ingresar'); ?>" class="action-card card-verde">
                <div class="icon-circle bg-verde">
                    <span class="plus-icon"></span>
                </div>
                <div class="card-info">
                    <h3>Ingresar Pedido</h3>
                    <p>NUEVA VENTA</p>
                </div>
            </a>

            <a href="<?php echo home_url('/pedidos'); ?>" class="action-card card-azul">
                <div class="icon-circle bg-azul">📋</div>
                <div class="card-info">
                    <h3>Ver Pedidos</h3>
                    <p>GESTIÓN ACTIVA</p>
                </div>
            </a>


            <a href="<?php echo home_url('/entregados'); ?>" class="action-card card-admin">
                <div class="icon-circle bg-admin">🚚</div>
                <div class="card-info">
                    <h3>Entregado</h3>
                    <p>PEDIDOS ENTREGADOS</p>
                </div>
            </a>

            <a href="<?php echo home_url('/estadisticas'); ?>" class="action-card card-stats">
                <div class="icon-circle bg-stats">📊</div>
                <div class="card-info">
                    <h3>Estadísticas</h3>
                    <p>INTELIGENCIA DE VENTAS</p>
                </div>
            </a>

            <a href="<?php echo home_url('/gestion-facturas'); ?>" class="action-card card-naranja">
                <div class="icon-circle bg-naranja">💵</div>
                <div class="card-info">
                    <h3>Gestión de Facturas</h3>
                    <p>VER, ANULAR Y SOLICITAR CAF</p>
                </div>
            </a>
        </div></div></main>

<?php get_footer(); ?>