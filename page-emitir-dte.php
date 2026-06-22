<?php
/* Template Name: Emitir DTE Manual */
tabolango_requerir_rol([1, 2]); // Solo Admin/Editor
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<div style="display:none;" id="session-email-bridge">[user_email_js]</div>

<div id="contenedor-emitir-dte" class="dte-panel">

    <div class="dte-titulo">Emitir DTE Manual</div>

    <div class="dte-layout">

        <!-- ────────── COLUMNA IZQ: FORMULARIO ────────── -->
        <div class="dte-form-col">

            <!-- Selector de tipo de DTE -->
            <div class="dte-section">
                <label class="dte-label">Tipo de documento</label>
                <div class="dte-tipo-chips">
                    <button class="dte-chip active" data-tipo="33">📄 Factura Afecta</button>
                    <button class="dte-chip"        data-tipo="61">↩️ Nota de Crédito</button>
                    <button class="dte-chip"        data-tipo="46">📥 Factura de Compra</button>
                    <button class="dte-chip"        data-tipo="52">🚚 Guía de Despacho</button>
                </div>
            </div>

            <!-- Folio + fecha emisión + forma pago -->
            <div class="dte-section dte-grid-3">
                <div>
                    <label class="dte-label">Folio</label>
                    <input type="number" id="dte-folio" placeholder="Auto (siguiente)" min="1">
                    <small class="dte-hint">Vacío = autoincremento por tipo</small>
                </div>
                <div>
                    <label class="dte-label">Fecha emisión</label>
                    <input type="date" id="dte-fecha">
                </div>
                <div id="dte-forma-pago-wrap">
                    <label class="dte-label">Forma de pago</label>
                    <select id="dte-forma-pago">
                        <option value="1">Contado</option>
                        <option value="2">Crédito</option>
                    </select>
                </div>
            </div>

            <div class="dte-section dte-grid-2" id="dte-credito-wrap" style="display:none;">
                <div>
                    <label class="dte-label">Días de crédito</label>
                    <input type="number" id="dte-dias-credito" value="30" min="0">
                </div>
            </div>

            <!-- Bloque receptor -->
            <div class="dte-section">
                <label class="dte-label" id="dte-label-receptor">Receptor (Cliente)</label>
                <div class="dte-grid-2">
                    <select id="dte-cliente-sel">
                        <option value="">— Selecciona cliente —</option>
                        <option value="manual">✏️ Ingresar manualmente</option>
                    </select>
                    <div style="font-size:11px; color:#888; align-self:center;">
                        O elige "Ingresar manualmente" para escribir los datos.
                    </div>
                </div>
                <div class="dte-grid-2" style="margin-top:8px;">
                    <input type="text" id="dte-rec-rut"          placeholder="RUT (76.123.456-7)" disabled>
                    <input type="text" id="dte-rec-razon"        placeholder="Razón social" disabled>
                    <input type="text" id="dte-rec-giro"         placeholder="Giro" disabled>
                    <input type="text" id="dte-rec-direccion"    placeholder="Dirección" disabled>
                    <input type="text" id="dte-rec-comuna"       placeholder="Comuna" disabled>
                    <input type="text" id="dte-rec-email"        placeholder="Email (opcional)" disabled>
                </div>
            </div>

            <!-- Referencia (solo Nota de Crédito) -->
            <div class="dte-section" id="dte-referencia-wrap" style="display:none;">
                <label class="dte-label">📎 Documento que se referencia</label>
                <div class="dte-grid-2">
                    <select id="dte-ref-dte">
                        <option value="">— Selecciona DTE original —</option>
                    </select>
                    <select id="dte-ref-codigo">
                        <option value="1">Anula documento</option>
                        <option value="2">Corrige texto</option>
                        <option value="3">Corrige monto</option>
                    </select>
                </div>
                <input type="text" id="dte-ref-razon" placeholder="Razón de la nota de crédito" style="margin-top:8px;">
            </div>

            <!-- Traslado (solo Guía de Despacho) -->
            <div class="dte-section" id="dte-traslado-wrap" style="display:none;">
                <label class="dte-label">🚚 Datos de traslado</label>
                <div class="dte-grid-2">
                    <select id="dte-traslado-tipo">
                        <option value="">Tipo de despacho</option>
                        <option value="1">Por cuenta del receptor</option>
                        <option value="2">Por cuenta del emisor a instalaciones del cliente</option>
                        <option value="3">Por cuenta del emisor a otras instalaciones</option>
                    </select>
                    <select id="dte-traslado-ind">
                        <option value="">Indicador de traslado</option>
                        <option value="1">Operación constituye venta</option>
                        <option value="2">Ventas por efectuar</option>
                        <option value="3">Consignaciones</option>
                        <option value="4">Entrega gratuita</option>
                        <option value="5">Traslados internos</option>
                        <option value="6">Otros traslados no venta</option>
                        <option value="7">Guía de devolución</option>
                    </select>
                </div>
            </div>

            <!-- Items -->
            <div class="dte-section">
                <label class="dte-label">📦 Ítems del documento</label>
                <table class="dte-items-tabla">
                    <thead>
                        <tr>
                            <th style="width:36%;">Nombre</th>
                            <th style="width:11%;">Cant.</th>
                            <th style="width:11%;">Unidad</th>
                            <th style="width:14%;">Precio</th>
                            <th style="width:14%;">Subtotal</th>
                            <th style="width:6%;">Ex.</th>
                            <th style="width:8%;"></th>
                        </tr>
                    </thead>
                    <tbody id="dte-items-tbody"></tbody>
                </table>
                <button id="dte-btn-add-item" type="button" class="dte-btn-add-item">
                    <i class="fa-solid fa-plus"></i> Agregar ítem
                </button>
            </div>

            <!-- Totales -->
            <div class="dte-section dte-totales-box">
                <div class="dte-total-row">
                    <span>Neto</span><span id="dte-total-neto">$0</span>
                </div>
                <div class="dte-total-row" id="dte-row-exento" style="display:none;">
                    <span>Exento</span><span id="dte-total-exento">$0</span>
                </div>
                <div class="dte-total-row">
                    <span>IVA (19%)</span><span id="dte-total-iva">$0</span>
                </div>
                <div class="dte-total-row dte-grand">
                    <span>TOTAL</span><span id="dte-total-grand">$0</span>
                </div>
            </div>

            <button id="dte-btn-generar" class="dte-btn-generar">
                <i class="fa-solid fa-rocket"></i> Generar DTE
            </button>
            <div id="dte-msg" style="margin-top:12px; min-height:20px;"></div>
        </div>

        <!-- ────────── COLUMNA DER: ÚLTIMOS DTE ────────── -->
        <div class="dte-recientes-col">
            <h3 class="dte-recientes-titulo">
                <i class="fa-solid fa-clock-rotate-left"></i> Últimos DTEs manuales
            </h3>
            <div id="dte-recientes-list">
                <div style="text-align:center; color:#aaa; padding:30px; font-size:12px;">
                    Cargando...
                </div>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>
