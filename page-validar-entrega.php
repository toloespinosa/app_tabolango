<?php
/* Template Name: Validar Entrega */
tabolango_requerir_rol([1, 2, 3, 4]);
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div class="operario-container">
    <div id="loading-box">
        <div class="spinner"></div>
        <p>⌛ Cargando estado del pedido ...</p>
    </div>
    
    <div id="order-ui" style="display:none;">
        <div class="app-card" id="status-card">
            <div class="status-badge" id="view-estado">---</div>
            <h2 id="view-cliente">---</h2>

            <button id="btn-card-mapa" class="btn-card-action" style="display:none;" onclick="abrirGpsModal()">
                <i class="fa-solid fa-location-arrow"></i> IR AL MAPA
            </button>
            <div class="header-row">
                <span>Pedido:</span> <strong id="view-id">---</strong>
            </div>
            <div id="lista-productos-container" class="product-list"></div>
        </div>

        <p class="instruction" id="main-instruction" style="color: #ffffff !important;">Cargando instrucciones...</p>
        
        <input type="file" id="foto-input" accept="image/*" capture="camera" style="display: none;" onchange="fotoCapturadaUI()">
        
        <button class="btn-main" id="btn-accion-principal">CARGANDO...</button>

<div id="botones-despacho-container" style="display:none; gap: 10px; margin-top: 15px;">
    <button class="btn-foto" id="btn-solo-foto" onclick="accionFotoDirecta()">
        <i class="fa-solid fa-camera"></i> 
        <span id="txt-btn-foto">FOTO ENTREGA</span>
    </button>

    <button class="btn-firma" id="btn-solo-firma" onclick="verificarUbicacion()">
        <i class="fa-solid fa-file-signature"></i> 
        FIRMAR Y ENTREGAR
    </button>
</div>

<input type="file" id="foto-input" accept="image/*" capture="camera" style="display: none;" onchange="fotoCapturadaUI()">

        <div id="firma-modal" class="modal-overlay" style="display:none;">
          <div class="modal-content" style="max-width: 400px; padding: 25px;">
            <div class="modal-icon">✍️</div>
            <h3>Recepción conforme</h3>
            <p style="color:#555;">Datos de quien recibe:</p>

            <button onclick="verGuiaPdf()" style="margin-bottom:15px; background:#f39c12; color:white; border:none; padding:8px 15px; border-radius:5px; cursor:pointer; font-weight:bold; width: 100%;">
               <i class="fa-solid fa-file-pdf"></i> Ver Guía Original
            </button>
        
            <input id="input-nombre-rx" placeholder="Nombre receptor" style="width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin-bottom:10px; font-size:16px;">
            <input id="input-rut-rx" placeholder="RUT receptor" style="width:100%;padding:12px;border-radius:10px;border:1px solid #ccc;margin-bottom:10px; font-size:16px;" maxlength="12">
            
            <textarea id="input-obs-rx" placeholder="Observaciones (opcional)..." style="width:100%; padding:12px; border-radius:10px; border:1px solid #ccc; margin-bottom:15px; font-size:16px; font-family:inherit; resize: none; height: 60px;"></textarea>
        
            <button id="btn-abrir-pizarra" onclick="abrirPantallaFirma()" style="width:100%; padding:15px; background:#34495e; color:white; border:none; border-radius:10px; font-size:16px; font-weight:bold; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:10px;">
                <i class="fa-solid fa-pen-nib"></i> FIRMAR EN PANTALLA
            </button>
            
            <div id="indicador-firma" style="display:none; color:#27ae60; font-weight:bold; margin-bottom:15px; background:#e8f8f5; padding:10px; border-radius:8px;">
                ✅ Firma Guardada
            </div>
        
            <div class="modal-buttons">
              <button class="btn-cancel" onclick="document.getElementById('firma-modal').style.display='none'">Cancelar</button>
              <button class="btn-confirm" id="btn-pre-enviar" onclick="prepararEnvio()" disabled style="opacity: 0.6;">Continuar</button>
            </div>
          </div>
        </div>
    </div>

    <div id="email-modal" class="modal-overlay" style="display:none; z-index: 1000000;">
        <div class="modal-content">
            <div class="modal-icon">📧</div>
            <h3>Enviar Copia</h3>
            <p style="color:#555; margin-bottom: 10px;">Enviar respaldo PDF a:</p>
            
            <div id="email-display-mode" style="margin-bottom: 25px;">
                <div id="email-label-static" style="font-size: 18px; font-weight: 800; color: #333; margin-bottom: 5px;"></div>
                <span onclick="activarEdicionEmail()" style="font-size: 13px; color: #0F4B29; text-decoration: underline; cursor: pointer;">
                    Cambiar mail
                </span>
            </div>

            <div id="email-edit-mode" style="display:none; margin-bottom: 20px;">
                <input id="input-email-final" type="email" placeholder="correo@cliente.cl" style="width:100%; padding:12px; border-radius:10px; border:1px solid #27ae60; font-size:16px; text-align:center; font-weight:bold; color:#333;">
            </div>
            
            <div class="modal-buttons">
                <button class="btn-cancel" onclick="finalizarSinCorreo()" style="font-size:14px;">Omitir</button>
                <button class="btn-confirm" id="btn-enviar-final" onclick="finalizarConCorreo()">✅ ENVIAR</button>
            </div>
        </div>
    </div>

    <div id="full-screen-signature" style="display:none;">
        <div class="signature-header">
            <span>✍️ Firme en el recuadro blanco</span>
            <small style="display:block; color:#777; font-weight:normal; font-size:12px;">Gire el teléfono para mayor comodidad</small>
        </div>
        <div id="canvas-container">
    <canvas id="canvas-firma"></canvas>
    <div class="signature-guide">
        <span>x</span>
        <div class="linea"></div>
    </div>
</div>
        <div class="signature-footer">
            <button onclick="limpiarCanvasFirma()" class="btn-borrar">Borrar</button>
            <button onclick="guardarFirmaTemp()" class="btn-aceptar">ACEPTAR FIRMA</button>
        </div>
    </div>

    <div id="custom-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <div class="modal-icon" id="modal-icon">🚚</div>
            <h3 id="modal-title">¿Confirmar Cambio?</h3>
            <p id="modal-text">¿Estás seguro de avanzar?</p>
            <div class="modal-buttons">
                <button class="btn-cancel" onclick="cerrarModal()">Cancelar</button>
                <button class="btn-confirm" id="btn-confirmar-modal" onclick="procesarEnvio()">Confirmar</button>
            </div>
        </div>
    </div>

    <div id="gps-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content" style="border-top: 13px solid #27ae60;">
        <div class="modal-icon">📍</div>
        <h3>¿Iniciar viaje?</h3>
        <p>Selecciona tu aplicación de mapas:</p>
        
        <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
            <button onclick="window.irAMapa('google')" style="display: flex; align-items: center; padding: 12px; border-radius: 12px; border: 1px solid #dadce0; background: #f8f9fa; color: #000000; font-weight: bold; cursor: pointer;">
                <img src="https://www.svgrepo.com/show/375444/google-maps-platform.svg" style="width: 24px; height: 24px; margin-right: 12px;">
                Google Maps
            </button>

           <button onclick="window.irAMapa('waze')" style="display: flex; align-items: center; padding: 12px; border-radius: 12px; border: none; background: #33CCFF; color: #000; font-weight: bold; cursor: pointer; width: 100%;">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 28px; height: 28px; margin-right: 12px;">
        
        <path d="M502.6 201.7c14.5 85.9-30.9 167.9-113.2 208.1 13 34.1-12.4 70.2-48.3 70.2-13.2 0-26-5.1-35.6-14.2s-15.3-21.6-16-34.8c-6.4 .2-64.2 0-76.3-.6-.3 6.8-1.9 13.5-4.7 19.6s-6.9 11.7-11.9 16.3-10.8 8.2-17.2 10.5-13.2 3.4-19.9 3.1c-33.9-1.4-58-34.8-47-67.9-37.2-13.1-72.5-34.9-99.6-70.8-13-17.3-.5-41.8 20.8-41.8 46.3 0 32.2-54.2 43.2-110.3 18.4-93.9 116.8-157.1 211.7-157.1 102.5 0 197.2 70.7 214.1 169.7z" fill="#FFFFFF"/>
        
        <path d="M502.6 201.7c14.5 85.9-30.9 167.9-113.2 208.1 13 34.1-12.4 70.2-48.3 70.2-13.2 0-26-5.1-35.6-14.2s-15.3-21.6-16-34.8c-6.4 .2-64.2 0-76.3-.6-.3 6.8-1.9 13.5-4.7 19.6s-6.9 11.7-11.9 16.3-10.8 8.2-17.2 10.5-13.2 3.4-19.9 3.1c-33.9-1.4-58-34.8-47-67.9-37.2-13.1-72.5-34.9-99.6-70.8-13-17.3-.5-41.8 20.8-41.8 46.3 0 32.2-54.2 43.2-110.3 18.4-93.9 116.8-157.1 211.7-157.1 102.5 0 197.2 70.7 214.1 169.7zM373.9 388.3c42-19.2 81.3-56.7 96.3-102.1 40.5-123.1-64.2-228-181.7-228-83.4 0-170.3 55.4-186.1 136-9.5 48.9 5 131.4-68.7 131.4 24.9 33.1 58.3 52.6 93.7 64 24.7-21.8 63.9-15.5 79.8 14.3 14.2 1 79.2 1.2 87.9 .8 3.5-6.9 8.5-12.9 14.7-17.5s13.2-7.9 20.8-9.5 15.4-1.4 22.9 .4 14.5 5.3 20.5 10.2zM205.5 187.1c0-34.7 50.8-34.7 50.8 0s-50.8 34.7-50.8 0zm116.6 0c0-34.7 50.9-34.7 50.9 0s-50.9 34.8-50.9 0zM199.5 257.8c-3.4-16.9 22.2-22.2 25.6-5.2l.1 .3c4.1 21.4 29.8 44 64.1 43.1 35.7-.9 59.3-22.2 64.1-42.8 4.5-16.1 28.6-10.4 25.5 6-5.2 22.2-31.2 62-91.5 62.9-42.6 0-80.9-27.8-87.9-64.2l0 0z" fill="#000000"/>

    </svg>
    Waze
</button>

            <button onclick="window.irAMapa('apple')" style="display: flex; align-items: center; padding: 12px; border-radius: 12px; border: none; background: #000; color: #fff; font-weight: bold; cursor: pointer;">
                <svg viewBox="0 0 640 640" style="width: 24px; height: 24px; margin-right: 12px;"><path d="M447.1 332.7C446.9 296 463.5 268.3 497.1 247.9C478.3 221 449.9 206.2 412.4 203.3C376.9 200.5 338.1 224 323.9 224C308.9 224 274.5 204.3 247.5 204.3C191.7 205.2 132.4 248.8 132.4 337.5C132.4 363.7 137.2 390.8 146.8 418.7C159.6 455.4 205.8 545.4 254 543.9C279.2 543.3 297 526 329.8 526C361.6 526 378.1 543.9 406.2 543.9C454.8 543.2 496.6 461.4 508.8 424.6C443.6 393.9 447.1 334.6 447.1 332.7zM390.5 168.5C417.8 136.1 415.3 106.6 414.5 96C390.4 97.4 362.5 112.4 346.6 130.9C329.1 150.7 318.8 175.2 321 202.8C347.1 204.8 370.9 191.4 390.5 168.5z" fill="currentColor"/></svg>
                Apple Maps
            </button>
        </div>

        <button class="btn-cancel" onclick="cerrarGpsModal()" style="margin-top: 15px; background: none; text-decoration: underline; color: #666;">No, después</button>
    </div>
</div>

    <div id="success-screen" style="display:none;">
        <div class="check-icon">✅</div>
        <h2 id="success-title">¡Listo!</h2>
        <p id="success-msg">Proceso finalizado correctamente.</p>
        <button class="btn-secondary" onclick="location.reload()">Escanear otro</button>
    </div> 
</div>


<?php
get_footer();
?>
