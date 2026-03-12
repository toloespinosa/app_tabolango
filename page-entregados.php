<?php
/* Template Name: Entregados */
tabolango_requerir_rol([1, 2, 4]);
get_header();
?>
<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div class="entregados-container">
    <div class="header-section">
        <h2>Historial de Entregas</h2>
        <p>Listado de pedidos finalizados y facturas firmadas.</p>
        
        <button onclick="abrirMenuMasivo()" class="btn-masivo-header">
            <i class="fa-brands fa-whatsapp" style="font-size: 1.2em;"></i>
            <span>ENVÍO DE FACTURAS MASIVO</span>
        </button>

        <div class="search-wrapper">
            <input type="text" id="buscador-entregados" placeholder="🔍 Buscar por cliente, producto o factura..." onkeyup="filtrarEntregas()">
        </div>
    </div>
    
    <div id="loading-entregados">
        <div class="spinner"></div>
        <p style="color: white; text-align: center;">Cargando historial...</p>
    </div>
    
    <div id="lista-entregados" class="cards-grid"></div>
    
    <div id="sentinel" style="height: 50px; width: 100%; text-align: center; color: white; margin-top: 20px;">
        <span id="loading-txt" style="display:none;">
            <div class="spinner" style="width:25px; height:25px; border-width:3px; display:inline-block; vertical-align:middle;"></div> 
        </span>
    </div>
</div>

<div id="modal-factura" class="modal-global-fix" onclick="cerrarModal()">
    <div class="modal-ventana" id="ventana-dinamica" onclick="event.stopPropagation()">
        <button class="btn-x-circular" onclick="cerrarModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="modal-render">
            <iframe id="frame-modal" src=""></iframe>
            <img id="img-modal" src="" alt="Documento">
        </div>
    </div>
</div>

<div id="modal-editar-pedido" class="modal-overlay-editor" style="display:none;">
    <div class="m-card" onclick="event.stopPropagation()">
        <button type="button" onclick="cerrarEditor()" class="btn-close-editor-circle">✕</button>
        <div class="m-header">
            <h2>Editar Pedido Entregado</h2>
            <div class="m-line"></div>
            <div id="edit-subtitle" style="font-size: 13px; color: #888; margin-top: 5px;"></div>
        </div>
        <div class="m-products-area" style="max-height: 55vh; overflow-y: auto;">
            <div class="m-section-header">
                <span>PRODUCTOS</span>
                <button type="button" class="m-btn-add" onclick="agregarFilaEditor()">+ AÑADIR</button>
            </div>
            <div id="editor-productos-container"></div>
        </div>
        <div class="m-footer">
            <button id="btn-guardar-edicion" onclick="guardarEdicionAPI()" class="m-btn-submit">GUARDAR CAMBIOS</button>
        </div>
    </div>
</div>

<div id="modal-selector-visual" class="modal-grid-overlay" style="display:none;">
    <div class="modal-grid-content" onclick="event.stopPropagation()">
        <div class="modal-grid-header">
            <div class="header-top">
                <h3 id="titulo-selector-visual">Productos</h3>
                <button type="button" class="btn-cerrar-modal" onclick="document.getElementById('modal-selector-visual').style.display='none'">✕</button>
            </div>
            <div class="header-search">
                <input type="text" placeholder="🔍 Buscar producto..." onkeyup="filtrarGridVisual(this.value)">
            </div>
        </div>
        <div id="grid-visual-productos" class="grid-container"></div>
    </div>
</div>


<?php
get_footer();
?>