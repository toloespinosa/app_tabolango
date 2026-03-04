<?php
/*
Template Name: Pedidos Activos
*/
get_header(); 

$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
?>
<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div class="tabolango-orders-container">
    <h2 class="form-title">Pedidos Activos</h2>
  
    <div style="text-align: center; margin-bottom: 25px;">
        <button class="btn-comanda-principal" onclick="abrirModalComanda()">
            📋 GENERAR LISTA DE COMPRA
        </button>
    </div>

    <div id="orders-grid" class="orders-grid">
        <p style="color: white; text-align: center;">Cargando pedidos...</p>
    </div>
</div>

<div id="contenedor-modales-tabolango">
    <div id="modal-factura" class="modal-overlay" onclick="cerrarModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <span class="close-modal" onclick="cerrarModal()">×</span>
            <div id="modal-body-content" style="width: 100%;"></div>
        </div>
    </div>

    <div id="modal-detalle-pedido" class="modal-overlay" onclick="cerrarDetalle()">
        <div class="modal-content detalle-tabla-content" onclick="event.stopPropagation()">
            <button class="btn-close-round" onclick="cerrarDetalle()">✕</button>
            <div id="detalle-pedido-body" style="width: 100%;"></div>
        </div>
    </div>

    <div id="modal-editar-pedido" class="modal-overlay">
        <div class="m-card" style="max-width: 550px !important; margin: 0; position: relative;" onclick="event.stopPropagation()">
            <button type="button" onclick="cerrarEditor()" class="btn-close-editor-circle">✕</button>
            
          <div class="m-header" style="position: relative; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0;">
                <h2 style="font-size: 20px; color: #1a1a1a; margin: 0; font-weight: 900; letter-spacing: -0.5px;">Editar Pedido</h2>
                <div id="edit-subtitle" style="font-size: 12px; color: #888; margin-top: 4px; font-weight: 500;"></div>
                
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; background: #ffffff; padding: 12px 15px; border-radius: 10px; border: 1px solid #e8e8e8; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    
                    <div style="flex: 1; margin-right: 15px;">
                        <label style="font-size: 10px; color: #a0a0a0; font-weight: 800; display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                            📅 Fecha de Despacho
                        </label>
                        <div style="position: relative;">
                            <input type="date" id="editor-fecha-despacho" style="width: 100%; padding: 10px 12px; border: 2px solid #f0f0f0; border-radius: 8px; font-weight: 700; color: #2c3e50; font-size: 14px; background: #fcfcfc; transition: all 0.2s; outline: none; cursor: pointer;" onfocus="this.style.borderColor='#E98C00'; this.style.background='#fff';" onblur="this.style.borderColor='#f0f0f0'; this.style.background='#fcfcfc';">
                        </div>
                    </div>
                    
                    <div>
                        <button type="button" onclick="eliminarPedidoAPI()" title="Eliminar Pedido" style="background: #fff0f0; color: #e74c3c; border: 1px solid #fadbd8; padding: 10px 14px; border-radius: 8px; font-size: 16px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; height: 42px;" onmouseover="this.style.background='#e74c3c'; this.style.color='#fff';" onmouseout="this.style.background='#fff0f0'; this.style.color='#e74c3c';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        </button>
                    </div>

                </div>
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

    <div id="modal-selector-visual" class="modal-grid-overlay">
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
</div>
<div id="modal-vista-previa" class="modal-overlay" style="z-index: 9999999;">
    <div class="modal-content" style="max-width: 500px; padding: 0; background: #f4f6f9;">
        
        <div style="background: #2c3e50; color: white; padding: 20px; border-radius: 15px 15px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; font-size: 18px; font-weight: 800; text-transform: uppercase;">Vista Previa</h3>
                <div id="vp-tipo-doc" style="font-size: 12px; opacity: 0.8; margin-top: 4px;">DOCUMENTO BORRADOR</div>
            </div>
            <button onclick="cerrarVistaPrevia()" style="background:none; border:none; color:white; font-size:24px; cursor:pointer;">&times;</button>
        </div>

        <div style="padding: 20px;">
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #ddd;">
                
                <div style="border-bottom: 1px dashed #eee; padding-bottom: 15px; margin-bottom: 15px;">
                    <label style="font-size: 10px; color: #888; font-weight: bold; text-transform: uppercase;">Receptor</label>
                    <div id="vp-cliente" style="font-weight: 800; color: #333; font-size: 15px;">Nombre Cliente</div>
                    <div id="vp-rut" style="font-size: 12px; color: #555;">RUT: 11.111.111-1</div>
                </div>

                <div style="margin-bottom: 15px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #eee;">
                                <th style="text-align: left; font-size: 10px; color: #888; padding: 5px 0;">ITEM</th>
                                <th style="text-align: right; font-size: 10px; color: #888; padding: 5px 0;">CANT</th>
                                <th style="text-align: right; font-size: 10px; color: #888; padding: 5px 0;">TOTAL</th>
                            </tr>
                        </thead>
                        <tbody id="vp-items-container">
                            </tbody>
                    </table>
                </div>

                <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 5px;">
                        <span>Monto Neto</span>
                        <span id="vp-neto" style="font-weight: 600;">$0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 10px;">
                        <span>IVA (19%)</span>
                        <span id="vp-iva" style="font-weight: 600;">$0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 18px; color: #2c3e50; border-top: 1px solid #ddd; padding-top: 10px;">
                        <span style="font-weight: 800;">TOTAL</span>
                        <span id="vp-total" style="font-weight: 900;">$0</span>
                    </div>
                </div>

            </div>
        </div>

        <div style="padding: 20px; display: flex; gap: 10px;">
            <button onclick="cerrarVistaPrevia()" style="flex: 1; padding: 15px; background: #ecf0f1; color: #7f8c8d; border: none; border-radius: 10px; font-weight: 800; cursor: pointer;">CANCELAR</button>
            <button id="btn-confirmar-emision" onclick="" style="flex: 2; padding: 15px; background: #27ae60; color: white; border: none; border-radius: 10px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 0 #219150;">
                ✅ EMITIR AL SII
            </button>
        </div>
    </div>
</div>
    <div id="modal-comanda" class="modal-grid-overlay" style="display:none;">
        <div class="modal-grid-content" style="max-width: 600px; height: 90vh;">
            <div class="modal-grid-header">
                <div class="header-top">
                    <h3>📋 Lista de Preparación</h3>
                    <button type="button" class="btn-cerrar-modal" onclick="cerrarModalComanda()">✕</button>
                </div>
                <div style="background: #fdfdfd; padding: 15px; border-radius: 12px; border: 1px solid #eee; margin-bottom: 10px;">
                    <label style="font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase; display: block; margin-bottom: 5px;">Fecha de Despacho:</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="date" id="fecha-comanda" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-weight: bold; color: #333;">
                        <button onclick="generarListaComandaVisual()" style="background: #0F4B29; color: white; border: none; padding: 0 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">VER</button>
                    </div>
                </div>
            </div>

            <div id="lista-comanda-body" class="grid-container" style="display: block; padding: 10px; padding-bottom: 80px;">
                <div style="text-align: center; color: #999; margin-top: 50px;">Seleccione fecha para ver insumos.</div>
            </div>

            <div style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 15px; background: white; border-top: 1px solid #eee; display: flex; gap: 10px;">
                <button onclick="limpiarTicks()" style="flex: 1; padding: 12px; background: #fff; color: #e74c3c; border: 1px solid #e74c3c; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 11px;">REINICIAR TICKS</button>
                
                <button onclick="llamarPHPComanda()" style="flex: 2; padding: 12px; background: #0F4B29; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    📄 DESCARGAR PDF
                </button>
            </div>
        </div>
    </div>
</div>


<?php get_footer(); ?>