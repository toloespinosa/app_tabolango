<?php
/**
 * Template Name: Ingresar Pedido
 */

get_header(); 

// Obtenemos el email del usuario actual de forma segura
$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
?>
<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div class="minimal-wrapper">
    <div class="m-card">
        <div class="m-header">
            <h2>Nuevo Pedido</h2>
            <div class="m-line"></div>
        </div>
        
        <form id="orderForm" novalidate>
            <div class="m-section-top">
    <div class="m-group">
        <label>Cliente</label>
        <div id="display_cliente" class="display-trigger">Seleccione cliente...</div>
        <input type="hidden" id="cliente_hidden">
    </div>
    <div class="m-group">
        <label>Fecha Despacho</label>
        <input type="date" id="fecha_entrega">
    </div>
</div>

            <div class="m-products-area">
                <div class="m-section-header">
                    <span>PRODUCTOS</span>
                    <button type="button" class="m-btn-add" onclick="agregarFilaProducto()">+ AÑADIR</button>
                </div>
                <div id="productos-container"></div>
            </div>

            <div class="m-footer">
                <div class="m-group">
                    <label>Observaciones</label>
                    <textarea id="observaciones" rows="2" placeholder="Notas..."></textarea>
                </div>
                <div class="m-total-box">
                    <span class="m-total-label">TOTAL</span>
                    <div id="total_pedido_display">$0</div>
                </div>
            </div>

            <button type="submit" class="m-btn-submit" id="btnEnviar">
                <span id="btnText">REGISTRAR PEDIDO</span>
            </button>
        </form>
    </div>
</div>

<?php get_footer(); ?>