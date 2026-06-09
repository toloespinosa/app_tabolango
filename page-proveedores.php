<?php
/* Template Name: Proveedores */
tabolango_requerir_rol([1, 2, 4]);
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<div style="display:none;" id="session-email-bridge">[user_email_js]</div>

<div id="contenedor-proveedores" class="prov-panel">

    <div class="prov-titulo">Cotizaciones de Compras</div>

    <!-- Tabs + acciones globales -->
    <div class="prov-header">
        <div class="prov-tabs">
            <button class="prov-tab active" data-tab="proveedores">
                <i class="fa-solid fa-store"></i> Por proveedor
            </button>
            <button class="prov-tab" data-tab="productos">
                <i class="fa-solid fa-magnifying-glass-dollar"></i> Por producto
            </button>
        </div>
        <div class="prov-acciones">
            <input type="text" id="prov-buscar" placeholder="Buscar..." oninput="prov_filtrar()">
            <button id="btnAbrirNuevoProveedor" class="prov-btn-nuevo">
                <i class="fa-solid fa-plus"></i> Nuevo proveedor
            </button>
        </div>
    </div>

    <!-- Vista 1: por proveedor -->
    <div id="vista-proveedores" class="prov-vista">
        <div id="grid-proveedores" class="prov-grid">
            <div class="prov-loading">Cargando proveedores...</div>
        </div>
    </div>

    <!-- Vista 2: por producto (grid de cards) -->
    <div id="vista-productos" class="prov-vista" style="display:none;">
        <div id="grid-productos" class="prov-grid">
            <div class="prov-loading">Cargando productos...</div>
        </div>
    </div>

</div>

<!-- ────────── Modal: detalle de producto (todos los proveedores) ────────── -->
<div id="modal-prod-detalle" class="prov-modal-overlay" style="display:none;">
    <div class="prov-modal" style="max-width: 760px;">
        <div class="prov-modal-header">
            <span><i class="fa-solid fa-magnifying-glass-dollar"></i> <span id="prod-det-titulo">Producto</span></span>
            <button class="prov-modal-close" onclick="prov_cerrarDetalle()">×</button>
        </div>
        <div class="prov-modal-body">
            <div id="prod-det-resumen" style="margin-bottom:14px;"></div>
            <table class="prov-table" style="min-width:auto;">
                <thead>
                    <tr>
                        <th>Proveedor</th>
                        <th style="text-align:center;">Contacto</th>
                        <th style="text-align:right;">Precio</th>
                        <th style="text-align:center;">Validez</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:center;">Ir</th>
                    </tr>
                </thead>
                <tbody id="prod-det-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ────────── Modal Nuevo Proveedor ────────── -->
<div id="modal-nuevo-proveedor" class="prov-modal-overlay" style="display:none;">
    <div class="prov-modal">
        <div class="prov-modal-header">
            <span><i class="fa-solid fa-store"></i> Nuevo proveedor</span>
            <button class="prov-modal-close" onclick="prov_cerrarModal()">×</button>
        </div>
        <div class="prov-modal-body">
            <div class="prov-form-grid">
                <div class="prov-campo">
                    <label>Nombre / Empresa <span class="req">*</span></label>
                    <input type="text" id="np-nombre" placeholder="Ej: Frutas del Valle SpA">
                </div>
                <div class="prov-campo">
                    <label>RUT</label>
                    <input type="text" id="np-rut" placeholder="12.345.678-9">
                </div>
                <div class="prov-campo">
                    <label>Contacto</label>
                    <input type="text" id="np-contacto" placeholder="Persona de contacto">
                </div>
                <div class="prov-campo">
                    <label>Teléfono</label>
                    <input type="text" id="np-telefono" placeholder="+56 9 ...">
                </div>
                <div class="prov-campo">
                    <label>Email</label>
                    <input type="email" id="np-email" placeholder="contacto@empresa.cl">
                </div>
                <div class="prov-campo">
                    <label>Ciudad</label>
                    <input type="text" id="np-ciudad" placeholder="Santiago">
                </div>
                <div class="prov-campo" style="grid-column: 1 / -1;">
                    <label>Dirección</label>
                    <input type="text" id="np-direccion" placeholder="Av. Las Industrias 1234, bodega 5">
                </div>
                <div class="prov-campo" style="grid-column: 1 / -1;">
                    <label>Notas</label>
                    <textarea id="np-notas" rows="2" placeholder="Días de despacho, condiciones de pago, etc."></textarea>
                </div>
            </div>
        </div>
        <div class="prov-modal-footer">
            <button class="prov-btn-cancelar" onclick="prov_cerrarModal()">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button id="btnGuardarProveedor" class="prov-btn-guardar">
                <i class="fa-solid fa-floppy-disk"></i> Guardar proveedor
            </button>
        </div>
    </div>
</div>

<?php get_footer(); ?>
