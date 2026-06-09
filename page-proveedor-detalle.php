<?php
/* Template Name: Proveedor Detalle */
tabolango_requerir_rol([1, 2, 4]);
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display:none;" id="session-email-bridge">[user_email_js]</div>

<div id="contenedor-proveedor-detalle" class="pd-panel">

    <!-- Header del proveedor -->
    <div class="pd-header">
        <div class="pd-header-top">
            <a href="/proveedores/" class="pd-volver">
                <i class="fa-solid fa-arrow-left"></i> Volver
            </a>
            <button id="btnEditarProveedor" class="pd-btn-editar" style="display:none;">
                <i class="fa-solid fa-pen"></i> Editar
            </button>
        </div>
        <div id="pd-info" class="pd-info">
            <div class="pd-loading">Cargando proveedor...</div>
        </div>
    </div>

    <!-- Tabla de productos cotizados -->
    <div class="pd-section">
        <div class="pd-section-header">
            <h3><i class="fa-solid fa-box"></i> Productos cotizados</h3>
            <div class="pd-section-actions">
                <input type="text" id="pd-buscar" placeholder="Filtrar productos..." oninput="pd_filtrar()">
                <button id="btnAbrirNuevoProducto" class="pd-btn-primary">
                    <i class="fa-solid fa-plus"></i> Nueva cotización
                </button>
            </div>
        </div>

        <div class="pd-tabla-wrap">
            <table class="pd-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="text-align:center;">Calibre</th>
                        <th style="text-align:center;">Unidad</th>
                        <th style="text-align:center;">Formato</th>
                        <th style="text-align:right;">Último precio</th>
                        <th style="text-align:center;">Validez</th>
                        <th style="text-align:center;">Vence</th>
                        <th style="text-align:center;">Estado</th>
                        <th style="text-align:center;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="pd-tbody">
                    <tr><td colspan="9" style="text-align:center; padding:40px; color:#aaa;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ────────── Modal Nueva Cotización / Producto ────────── -->
<div id="modal-nueva-cotizacion" class="pd-modal-overlay" style="display:none;">
    <div class="pd-modal">
        <div class="pd-modal-header">
            <span><i class="fa-solid fa-tag"></i> <span id="modal-cot-titulo">Actualizar precio</span></span>
            <button class="pd-modal-close" onclick="pd_cerrarModal()">×</button>
        </div>
        <div class="pd-modal-body">

            <!-- Selector: actualizar precio existente o producto nuevo -->
            <div id="pd-modo-selector" class="pd-modo-selector">
                <button class="pd-modo-btn active" data-modo="existente">
                    <i class="fa-solid fa-rotate"></i> Actualizar precio existente
                </button>
                <button class="pd-modo-btn" data-modo="nuevo">
                    <i class="fa-solid fa-plus"></i> Producto nuevo
                </button>
            </div>

            <!-- Modo: producto existente (default) -->
            <div id="modo-existente">
                <div class="pd-campo">
                    <label>Selecciona el producto del proveedor</label>
                    <select id="pd-prod-existente">
                        <option value="">— Selecciona —</option>
                    </select>
                </div>
            </div>

            <!-- Modo: nuevo producto -->
            <div id="modo-nuevo" style="display:none;">
                <div class="pd-form-grid">
                    <div class="pd-campo" style="grid-column: 1 / -1;">
                        <label>Nombre del producto <span class="req">*</span></label>
                        <input type="text" id="nc-nombre" placeholder="Ej: Tomate larga vida">
                    </div>
                    <div class="pd-campo">
                        <label>Variedad</label>
                        <input type="text" id="nc-variedad" placeholder="Ej: cherry, italiano...">
                    </div>
                    <div class="pd-campo">
                        <label>Calibre</label>
                        <input type="text" id="nc-calibre" placeholder="Ej: 1ra, mediano">
                    </div>
                    <div class="pd-campo">
                        <label>Unidad</label>
                        <input type="text" id="nc-unidad" placeholder="Ej: caja, malla, kg">
                    </div>
                    <div class="pd-campo">
                        <label>Formato</label>
                        <input type="text" id="nc-formato" placeholder="Ej: 10kg, 20 und">
                    </div>
                    <div class="pd-campo" style="grid-column: 1 / -1;">
                        <label>Vincular a producto de mi catálogo (opcional)</label>
                        <select id="nc-link-producto">
                            <option value="">— Sin vincular —</option>
                        </select>
                    </div>
                </div>
            </div>

            <hr style="margin: 18px 0; border: none; border-top: 1px dashed #ddd;">

            <!-- Datos del precio (siempre visibles) -->
            <div class="pd-form-grid">
                <div class="pd-campo">
                    <label>Precio <span class="req">*</span></label>
                    <input type="number" id="nc-precio" min="0" step="1" placeholder="0">
                </div>
                <div class="pd-campo">
                    <label>Validez</label>
                    <select id="nc-validez">
                        <option value="diaria">Diaria (vence hoy)</option>
                        <option value="semanal" selected>Semanal (+7 días)</option>
                        <option value="mensual">Mensual (+30 días)</option>
                    </select>
                </div>
                <div class="pd-campo" style="grid-column: 1 / -1;">
                    <label>Notas (opcional)</label>
                    <textarea id="nc-notas" rows="2" placeholder="Ej: pago contado 5% dcto, incluye despacho..."></textarea>
                </div>

                <div class="pd-campo pd-foto-campo" style="grid-column: 1 / -1;">
                    <label>📷 Foto del producto (opcional)</label>
                    <div class="pd-foto-wrap">
                        <label class="pd-foto-btn" for="nc-foto">
                            <i class="fa-solid fa-camera"></i>
                            <span id="nc-foto-label">Tomar / Elegir foto</span>
                        </label>
                        <input type="file" id="nc-foto" accept="image/*" capture="environment" style="display:none;">
                        <button type="button" id="nc-foto-quitar" class="pd-foto-quitar" style="display:none;">
                            <i class="fa-solid fa-xmark"></i> Quitar
                        </button>
                    </div>
                    <div id="nc-foto-preview" style="display:none; margin-top:8px;">
                        <img id="nc-foto-img" src="" alt="Vista previa" style="max-width:160px; max-height:160px; border-radius:8px; border:1px solid #ddd;">
                    </div>
                </div>
            </div>
        </div>
        <div class="pd-modal-footer">
            <button class="pd-btn-cancelar" onclick="pd_cerrarModal()">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button id="btnGuardarCotizacion" class="pd-btn-guardar">
                <i class="fa-solid fa-floppy-disk"></i> Guardar
            </button>
        </div>
    </div>
</div>

<!-- ────────── Modal Consultar precio por fecha ────────── -->
<div id="modal-fecha" class="pd-modal-overlay" style="display:none;">
    <div class="pd-modal" style="max-width:480px;">
        <div class="pd-modal-header">
            <span><i class="fa-solid fa-calendar-day"></i> Precio en fecha — <span id="fec-producto"></span></span>
            <button class="pd-modal-close" onclick="pd_cerrarFecha()">×</button>
        </div>
        <div class="pd-modal-body">
            <div class="pd-campo">
                <label>Selecciona la fecha</label>
                <input type="date" id="fec-input">
            </div>
            <div id="fec-resultado" style="margin-top:18px; min-height:60px;"></div>
        </div>
        <div class="pd-modal-footer">
            <button class="pd-btn-cancelar" onclick="pd_cerrarFecha()">
                <i class="fa-solid fa-xmark"></i> Cerrar
            </button>
        </div>
    </div>
</div>

<!-- ────────── Modal Historial ────────── -->
<div id="modal-historial" class="pd-modal-overlay" style="display:none;">
    <div class="pd-modal" style="max-width: 820px;">
        <div class="pd-modal-header">
            <span><i class="fa-solid fa-clock-rotate-left"></i> Historial — <span id="hist-producto"></span></span>
            <button class="pd-modal-close" onclick="pd_cerrarHistorial()">×</button>
        </div>
        <div class="pd-modal-body">

            <!-- Filtro de rango de fechas para el gráfico -->
            <div class="pd-rango">
                <div class="pd-campo">
                    <label>Desde</label>
                    <input type="date" id="hist-desde">
                </div>
                <div class="pd-campo">
                    <label>Hasta</label>
                    <input type="date" id="hist-hasta">
                </div>
                <button id="btnRangoTodo" class="pd-btn-rango">Todo</button>
                <button id="btnRango90"   class="pd-btn-rango">90 días</button>
                <button id="btnRango30"   class="pd-btn-rango">30 días</button>
            </div>

            <!-- Gráfico de variación -->
            <div class="pd-grafico-wrap">
                <canvas id="hist-grafico"></canvas>
                <div id="hist-grafico-vacio" style="display:none; text-align:center; padding:30px; color:#aaa; font-size:13px;">
                    No hay datos en el rango seleccionado
                </div>
            </div>

            <h4 style="margin: 22px 0 10px; font-size:13px; font-weight:800; color:#555; text-transform:uppercase; letter-spacing:0.3px;">
                <i class="fa-solid fa-list"></i> Detalle de cotizaciones
            </h4>

            <table class="pd-table" style="min-width:auto;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th style="text-align:center;">Foto</th>
                        <th style="text-align:right;">Precio</th>
                        <th style="text-align:center;">Δ</th>
                        <th style="text-align:center;">Validez</th>
                        <th>Notas</th>
                        <th>Por</th>
                    </tr>
                </thead>
                <tbody id="hist-tbody">
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:#aaa;">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ────────── Lightbox foto ────────── -->
<div id="pd-lightbox" class="pd-lightbox" style="display:none;" onclick="pd_cerrarLightbox()">
    <img id="pd-lightbox-img" src="" alt="">
    <button class="pd-lightbox-close" type="button">×</button>
</div>

<!-- ────────── Modal Editar Proveedor ────────── -->
<div id="modal-editar-proveedor" class="pd-modal-overlay" style="display:none;">
    <div class="pd-modal">
        <div class="pd-modal-header">
            <span><i class="fa-solid fa-pen"></i> Editar proveedor</span>
            <button class="pd-modal-close" onclick="pd_cerrarEdicion()">×</button>
        </div>
        <div class="pd-modal-body">
            <div class="pd-form-grid">
                <div class="pd-campo">
                    <label>Nombre / Empresa <span class="req">*</span></label>
                    <input type="text" id="ep-nombre">
                </div>
                <div class="pd-campo">
                    <label>RUT</label>
                    <input type="text" id="ep-rut">
                </div>
                <div class="pd-campo">
                    <label>Contacto</label>
                    <input type="text" id="ep-contacto">
                </div>
                <div class="pd-campo">
                    <label>Teléfono</label>
                    <input type="text" id="ep-telefono">
                </div>
                <div class="pd-campo">
                    <label>Email</label>
                    <input type="email" id="ep-email">
                </div>
                <div class="pd-campo">
                    <label>Ciudad</label>
                    <input type="text" id="ep-ciudad">
                </div>
                <div class="pd-campo" style="grid-column: 1 / -1;">
                    <label>Dirección</label>
                    <input type="text" id="ep-direccion" placeholder="Av. Las Industrias 1234, bodega 5">
                </div>
                <div class="pd-campo" style="grid-column: 1 / -1;">
                    <label>Notas</label>
                    <textarea id="ep-notas" rows="2"></textarea>
                </div>
            </div>
        </div>
        <div class="pd-modal-footer">
            <button class="pd-btn-cancelar" onclick="pd_cerrarEdicion()">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button id="btnGuardarEdicion" class="pd-btn-guardar">
                <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
            </button>
        </div>
    </div>
</div>

<?php get_footer(); ?>
