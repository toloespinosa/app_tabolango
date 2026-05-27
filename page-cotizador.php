<?php
/* Template Name: Cotizador */
tabolango_requerir_rol([1, 2, 4]);
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<div style="display:none;" id="session-email-bridge">[user_email_js]</div>

<h4><i class="fa-solid fa-file-invoice-dollar"></i> Cotizador de Precios</h4>

<div id="contenedor-cotizador" class="cotizador-panel">

    <div class="cot-config-row">
        <div class="cot-campo">
            <label><i class="fa-solid fa-user-tie"></i> Cliente</label>
            <select id="cot-cliente">
                <option value="">Cargando clientes...</option>
            </select>
        </div>
        <div class="cot-campo">
            <label><i class="fa-solid fa-tags"></i> Base de precio</label>
            <div class="cot-cat-btns">
                <button class="btn-cat active" data-cat="lista">Lista</button>
                <button class="btn-cat" data-cat="1">Gran Dist. (P1)</button>
                <button class="btn-cat" data-cat="2">Mayorista (P2)</button>
                <button class="btn-cat" data-cat="4">V. Norte (P4)</button>
            </div>
        </div>
        <div class="cot-campo">
            <label><i class="fa-solid fa-magnifying-glass"></i> Buscar producto</label>
            <input type="text" id="cot-buscar" placeholder="Filtrar productos..." oninput="cotizador_filtrar()">
        </div>
    </div>

    <!-- Panel nuevo cliente -->
    <div id="panel-nuevo-cliente" class="panel-nuevo-cliente" style="display:none;">
        <div class="nuevo-cli-header">
            <i class="fa-solid fa-user-plus"></i> Nuevo cliente
        </div>
        <div class="nuevo-cli-grid">
            <div class="nuevo-cli-campo">
                <label>Nombre / Empresa <span class="req">*</span></label>
                <input type="text" id="nc-nombre" placeholder="Ej: Frutas del Valle SpA">
            </div>
            <div class="nuevo-cli-campo">
                <label>Responsable / Contacto <span class="req">*</span></label>
                <input type="text" id="nc-responsable" placeholder="Ej: Juan Pérez">
            </div>
            <div class="nuevo-cli-campo">
                <label>RUT</label>
                <input type="text" id="nc-rut" placeholder="12.345.678-9">
            </div>
            <div class="nuevo-cli-campo">
                <label>Teléfono</label>
                <input type="text" id="nc-telefono" placeholder="+56 9 1234 5678">
            </div>
            <div class="nuevo-cli-campo">
                <label>Email</label>
                <input type="email" id="nc-email" placeholder="contacto@empresa.cl">
            </div>
            <div class="nuevo-cli-campo">
                <label>Ciudad</label>
                <input type="text" id="nc-ciudad" placeholder="Santiago">
            </div>
        </div>
        <div class="nuevo-cli-footer">
            <button id="btnCancelarCliente" class="btn-cancelar-cli">
                <i class="fa-solid fa-xmark"></i> Cancelar
            </button>
            <button id="btnGuardarCliente" class="btn-guardar-cli">
                <i class="fa-solid fa-floppy-disk"></i> Guardar cliente
            </button>
        </div>
    </div>

    <div class="cot-tabla-wrap">
        <table class="cot-table">
            <thead>
                <tr>
                    <th width="40px">
                        <input type="checkbox" id="cot-check-all" title="Seleccionar todos">
                    </th>
                    <th>Producto</th>
                    <th width="80px" style="text-align:center;">Calibre</th>
                    <th width="80px" style="text-align:center;">Unidad</th>
                    <th width="100px" style="text-align:center;">Formato</th>
                    <th width="120px" style="text-align:right;">Precio Unit.</th>
                    <th width="80px" style="text-align:center;">Cant.</th>
                    <th width="110px" style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <tbody id="cot-tbody">
                <tr><td colspan="8" style="text-align:center; padding:40px; color:#aaa;">Cargando productos...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="cot-footer">
        <div class="cot-notas-wrap">
            <label><i class="fa-solid fa-note-sticky"></i> Notas (opcional)</label>
            <textarea id="cot-notas" rows="2" placeholder="Ej: Entrega en 2 días hábiles, incluye embalaje especial..."></textarea>
        </div>
        <div class="cot-totales">
            <div class="cot-total-row">
                <span class="cot-total-lbl">Neto</span>
                <span class="cot-total-val" id="cot-total-neto">$0</span>
            </div>
            <div class="cot-total-row cot-iva-row">
                <span class="cot-total-lbl">IVA (19%)</span>
                <span class="cot-total-val" id="cot-total-iva">$0</span>
            </div>
            <div class="cot-total-row cot-grand-total">
                <span class="cot-total-lbl">TOTAL</span>
                <span class="cot-total-val" id="cot-grand-total">$0</span>
            </div>
            <button id="btnGenerarCot" class="btn-generar-cot" disabled>
                <i class="fa-solid fa-file-pdf"></i> Generar Cotización PDF
            </button>
        </div>
    </div>

</div>

<form id="form-pdf-cot" method="POST" target="_blank" style="display:none;">
    <input type="hidden" name="cliente_id" id="inp-cliente-id">
    <input type="hidden" name="productos"  id="inp-productos">
    <input type="hidden" name="notas"      id="inp-notas">
    <input type="hidden" name="wp_user"    value="[user_email_js]">
</form>

<?php get_footer(); ?>
