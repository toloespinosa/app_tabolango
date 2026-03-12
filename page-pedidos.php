<?php
/*
Template Name: Pedidos Activos
*/

// 🔒 BLOQUEO DE SEGURIDAD (PHP)
tabolango_requerir_rol([1, 2, 3, 4]);

get_header(); 

$current_user = wp_get_current_user();
$user_email = $current_user->user_email;

// --- 1. DETECCIÓN DE ENTORNO ---
$whitelist_local = ['localhost', '127.0.0.1', 'tabolango-app.local', 'tabolango.local'];
$es_local = in_array($_SERVER['SERVER_NAME'], $whitelist_local);

// Fallback de email para pruebas en LocalWP
if ($es_local && empty($user_email)) {
    $user_email = "jandres@tabolango.cl"; 
}

// --- 2. OBTENER ROL (SIMULADOR VS REALIDAD) ---
$rol_id = 0;

// A) Primero: ¿Hay una Cookie del simulador activa y estamos en local?
if ($es_local && isset($_COOKIE['simular_rol_tabolango'])) {
    $rol_id = (int)$_COOKIE['simular_rol_tabolango'];
} 
// B) Segundo: Si no hay simulador o estamos en producción, ir a la Base de Datos
else {
    try {
        if ($es_local) {
            $conn = new mysqli("localhost", "root", "root", "local", null, "/Users/juanandres/Library/Application Support/Local/run/o4oaY0jbM/mysql/mysqld.sock");
        } else {
            $conn = new mysqli("localhost", "tabolang_app", 'm{Hpj.?IZL$Kz${S', "tabolang_pedidos");
        }
        $conn->set_charset("utf8mb4"); 
        
        if (!$conn->connect_error) {
            $stmt = $conn->prepare("SELECT rol_id FROM app_usuario_roles WHERE usuario_email = ? LIMIT 1");
            if($stmt){
                $stmt->bind_param("s", $user_email);
                $stmt->execute();
                $stmt->bind_result($r_id);
                if ($stmt->fetch()) {
                    $rol_id = (int)$r_id;
                }
                $stmt->close();
            }
            $conn->close();
        }
    } catch (Exception $e) {
        // Silencio en caso de error para no romper la vista
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div id="session-email-bridge" style="display:none !important;"><?php echo esc_html($user_email); ?></div>
<div id="session-rol-bridge" style="display:none !important;"><?php echo $rol_id; ?></div>

<div class="tabolango-orders-container">
    <h2 class="form-title">Pedidos Activos</h2>
  
    <?php if (in_array($rol_id, [1, 2, 4])) : ?>
    <div style="text-align: center; margin-bottom: 25px;">
        <button class="btn-comanda-principal" onclick="abrirModalComanda()">
            📋 GENERAR LISTA DE COMPRA
        </button>
    </div>
    <?php endif; ?>

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
            
            <div class="m-header">
                <h2>Editar Pedido</h2>
                <div class="m-line"></div>
                <div id="edit-subtitle" style="font-size: 13px; color: #888; margin-top: 5px;"></div>
            </div>

            <div style="background: #fdfdfd; padding: 12px 15px; border-radius: 12px; border: 1px solid #eee; margin-bottom: 15px;">
                <label style="font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase; display: block; margin-bottom: 5px;">📅 Fecha de Despacho:</label>
                <input type="date" id="editor-fecha-despacho" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-weight: bold; color: #333; box-sizing: border-box; font-family: inherit;">
            </div>
            
            <div class="m-products-area" style="max-height: 45vh; overflow-y: auto;">
                <div class="m-section-header">
                    <span>PRODUCTOS</span>
                    <button type="button" class="m-btn-add" onclick="agregarFilaEditor()">+ AÑADIR</button>
                </div>
                <div id="editor-productos-container"></div>
            </div>
            
            <div class="m-footer" style="display: flex; flex-direction: column; gap: 10px;">
                <button id="btn-guardar-edicion" onclick="guardarEdicionAPI()" class="m-btn-submit">GUARDAR CAMBIOS</button>
                <?php if (in_array($rol_id, [1])) : // Solo admin borra ?>
                <button type="button" onclick="eliminarPedidoAPI()" style="background: #fff; color: #e74c3c; border: 2px solid #ffebeb; width: 100%; padding: 14px; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; transition: 0.2s;">
                    🗑️ ELIMINAR ESTE PEDIDO
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="modal-confirmar-whatsapp" class="modal-overlay" style="z-index: 9999999;">
        <div class="modal-content" style="max-width: 420px; padding: 0; background: #e5ddd5; overflow: hidden; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.3);">
            
            <div style="background: #075E54; color: white; padding: 15px; display: flex; align-items: center; gap: 10px;">
                <div style="background: #25D366; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.885-.653-1.482-1.46-1.656-1.758-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.012c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"></path></svg>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 16px; font-weight: 600;">Vista Previa WhatsApp</h3>
                    <div style="font-size: 11px; opacity: 0.8;">Tabolango SpA</div>
                </div>
            </div>
            
            <div style="padding: 20px;">
                <div style="background: white; border-radius: 12px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                    <label style="font-size: 11px; color: #075E54; font-weight: bold; text-transform: uppercase;">Enviar a:</label>
                    <div id="wa-cliente-nombre" style="font-weight: 800; color: #333; font-size: 15px; margin-top: 4px; margin-bottom: 10px;">Cargando nombre...</div>
                    <div style="display: flex; align-items: center; gap: 10px; background: #f0f2f5; border-radius: 8px; padding: 5px 15px;">
                        <span style="font-size: 16px;">📱</span>
                        <input type="text" id="wa-telefono-input" style="flex: 1; padding: 8px 0; border: none; background: transparent; font-weight: bold; font-size: 15px; color: #111; outline: none;" placeholder="+56 9...">
                    </div>
                </div>

                <div style="background: #DCF8C6; padding: 15px; border-radius: 0 12px 12px 12px; font-family: 'Segoe UI', Helvetica, sans-serif; font-size: 14px; color: #111; line-height: 1.5; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.15);">
                    <div style="position: absolute; top: 0; left: -10px; width: 0; height: 0; border-top: 0px solid transparent; border-right: 15px solid #DCF8C6; border-bottom: 15px solid transparent;"></div>
                    
                    ¡Hola <b id="wa-preview-nombre"></b>! 🍅✨ Hemos ingresado tu pedido con éxito. 🎉 <br>
                    Tu número de orden es el <b>#<span id="wa-preview-id"></span></b>.<br><br>
                    
                    📄 <b>Te adjuntamos el documento PDF con el detalle exacto de tus productos.</b><br><br>
                    
                    💰 <i>Subtotal:</i> <b id="wa-preview-sub"></b><br>
                    🧾 <i>IVA:</i> <b id="wa-preview-iva"></b><br>
                    ✅ <i>Total a pagar:</i> <b id="wa-preview-tot" style="font-size: 16px; color:#0f4b29;"></b><br><br>
                    
                    ¡Gracias por preferir a Tabolango! 🌱
                </div>
            </div>

            <div style="padding: 15px; display: flex; gap: 10px; background: white; border-top: 1px solid #ddd;">
                <button onclick="document.getElementById('modal-confirmar-whatsapp').style.display='none'" style="flex: 1; padding: 12px; background: #f0f2f5; color: #555; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">CANCELAR</button>
                <button id="btn-enviar-wa-final" onclick="" style="flex: 2; padding: 12px; background: #25D366; color: white; border: none; border-radius: 8px; font-weight: 800; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 10px rgba(37, 211, 102, 0.3);">
                    ENVIAR AHORA
                </button>
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
                <div id="vp-tipo-doc" style="font-size: 12px; opacity: 0.8; margin-top: 4px;">BORRADOR GUIA</div>
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
                        <tbody id="vp-items-container"></tbody>
                    </table>
                </div>
                <div style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 5px;">
                        <span>Monto Neto</span><span id="vp-neto" style="font-weight: 600;">$0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 13px; color: #555; margin-bottom: 10px;">
                        <span>IVA (19%)</span><span id="vp-iva" style="font-weight: 600;">$0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 18px; color: #2c3e50; border-top: 1px solid #ddd; padding-top: 10px;">
                        <span style="font-weight: 800;">TOTAL</span><span id="vp-total" style="font-weight: 900;">$0</span>
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

<?php get_footer(); ?>