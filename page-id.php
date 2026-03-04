<?php
/* Template Name: ID */
get_header();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Cliente | Tabolango</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

    <div id="app-detalle" class="tabolango-view">
        <div id="loading" class="loader-text"><i class="fas fa-spinner fa-spin"></i> Cargando perfil...</div>

        <div id="main-content" style="display:none;">
            
            <div class="nav-admin-bar" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="/clientes" style="text-decoration: none;">
                        <button style="background: #f1f1f1; color: #666; border: none; padding: 10px 15px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-arrow-left"></i> VOLVER
                        </button>
                    </a>
                    <div id="wrapper-sucursales" style="display: none; position: relative;">
                        <button class="filter-btn" style="background: #f1f1f1; color: #666; border: none; padding: 10px 15px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px;" onclick="toggleDrop('menu-sucursales', event)">
                            <i class="fas fa-building" style="color: #E98C00;"></i> <span>SUCURSALES</span> <i class="fas fa-caret-down" style="opacity: 0.5;"></i>
                        </button>
                        <div id="menu-sucursales"></div>
                    </div>
                </div>
                <div id="admin-header" style="display:none; flex-direction: row; gap: 10px; align-items: center;">
                    <div id="admin-actions-status"></div>
                    <button id="btn-editar-master" onclick="toggleEdicion()" style="background:#333; color:white; border:none; padding:10px 15px; border-radius:10px; font-size:12px; font-weight:bold; cursor:pointer;">
                        <i class="fas fa-user-shield"></i> <span id="txt-edit">MODO EDICIÓN</span>
                    </button>
                </div>
            </div>

            <div class="card-ui">
                <div class="header-ui">
                    <div class="header-left">
                        <div id="ui-avatar" class="avatar-ui" onclick="if(editando) document.getElementById('input-logo').click()"></div>
                        <input type="file" id="input-logo" style="display:none" accept="image/*" onchange="cambiarLogo(this)">
                        <div class="header-info">
                            <span id="ui-tipo" class="badge-ui"></span>
                            <h2 id="ui-nombre" class="nombre-ui editable-field" data-key="cliente"></h2>
                            <div class="header-meta-info">
                                <span id="ui-id" class="id-ui"></span>
                                <div id="container-responsable" style="margin-top: 8px; display:flex; align-items:center;">
                                    <span class="label-mini">RESPONSABLE:</span>
                                    <span id="ui-responsable" class="btn-responsable-tag">Cargando...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="abrirModal('modal-factura')" class="btn-factura-header">
                        <i class="fas fa-file-invoice"></i> Datos Factura
                    </button>
                </div>

                <div class="grid-ui">
                    <div class="field-ui"><label>RUT Empresa</label><span id="ui-rut" class="editable-field" data-key="rut_cliente"></span></div>
                    <div class="field-ui"><label>Dirección Despacho</label><span id="ui-direccion" class="editable-field" data-key="direccion"></span></div>
                    
                    <div class="field-ui">
                        <label>Notificar a</label>
                        <span id="ui-contacto-wrapper"></span>
                        <input type="hidden" id="orig-nombre"><input type="hidden" id="orig-apellido">
                    </div>
                    
                    <div class="field-ui">
                        <label>Contacto Local</label>
                        <span id="ui-contacto-local" class="editable-field" data-key="contacto"></span>
                    </div>
                    
                    <div class="field-ui"><label>Email Contacto</label><span id="ui-email" class="editable-field" data-key="email"></span></div>
                    <div class="field-ui"><label>Teléfono Contacto</label><span id="ui-telefono" class="editable-field" data-key="telefono" onclick="copiarTelefonoFormateado()" style="cursor:pointer; color:#2ecc71; text-decoration:underline dotted;"></span></div>
                </div>
                
                <div style="display:none;"><input type="hidden" id="edit-latitud"><input type="hidden" id="edit-longitud"><input type="hidden" id="edit-comuna"></div>
                <button onclick="abrirMapa()" class="btn-ui-azul"><i class="fas fa-map-marked-alt"></i> Ver Ubicación de Despacho</button>
            </div>

            <div class="filter-wrapper">
                <div class="stats-filter-bar">
                    <div class="filtred-wrapper">
                        <button class="filter-btn active" id="btn-filtro-mes" onclick="toggleDrop('menu-meses', event)">MES</button>
                        <div id="menu-meses" class="mini-drop">
                            <div onclick="seleccionarOpcion('mes', '01', 'Enero')">Enero</div>
                            <div onclick="seleccionarOpcion('mes', '02', 'Febrero')">Febrero</div>
                            <div onclick="seleccionarOpcion('mes', '12', 'Diciembre')">Diciembre</div>
                        </div>
                    </div>
                    <div class="filtred-wrapper">
                        <button class="filter-btn" id="btn-filtro-año" onclick="toggleDrop('menu-años', event)">2026</button>
                        <div id="menu-años" class="mini-drop">
                            <div onclick="seleccionarOpcion('año', '2026', '2026')">2026</div>
                            <div onclick="seleccionarOpcion('año', '2025', '2025')">2025</div>
                        </div>
                    </div>
                    <div class="filtred-wrapper">
                        <button id="btn-filtro-total" class="filter-btn" onclick="resetFiltroTotal()">TOTAL</button>
                    </div>
                </div> 
                <div class="btn-reset-round" onclick="manejarClickSimple()"><i class="fas fa-undo-alt"></i></div>
            </div>

            <div id="render-stats-container">
                <div class="stats-container">
                    <div class="stat-item"><small>PEDIDOS ENTREGADOS</small><b id="ui-pedidos">0</b></div>
                    <div class="stat-item"><small>TOTAL INVERTIDO</small><b id="ui-monto">$0</b></div>
                    <div class="stat-item"><small>ÚLTIMO DESPACHO</small><b id="ui-ultimo-pedido">-</b></div>
                    <div class="stat-item"><small>PREDICCIÓN PRÓXIMA</small><b id="ui-prediccion" style="color: #E98C00;">-</b></div>
                </div>
            </div>

            <div id="ui-recurring-container" class="recurring-box" style="display:none;">
            </div>

            <div class="card-ui">
                <h3 class="title-section">🏆 Productos más comprados</h3>
                <div id="ui-productos"></div>
            </div>
            
            <div class="card-ui">
                <h3 class="title-section"><i class="fas fa-file-invoice"></i> Últimas 3 Facturas</h3>
                <table class="invoice-table">
                    <thead><tr><th>Folio</th><th>Fecha</th><th>Neto</th><th style="text-align:left;">Ver</th></tr></thead>
                    <tbody id="ui-facturas-body"><tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">Sin historial</td></tr></tbody>
                </table>
            </div>

        </div>
    </div>

    <div id="modal-factura" class="t-modal-overlay">
        <div class="t-modal-content">
            <div class="t-modal-header"><h3 class="modal-dark-title">Datos de Facturación SII</h3><span class="close" onclick="cerrarModal('modal-factura')">×</span></div>
            <div id="factura-body"></div>
        </div>
    </div>

    <div id="modal-mapa" class="t-modal-overlay">
        <div class="t-modal-content">
            <div class="t-modal-header"><h3 class="modal-dark-title">Ubicación de Despacho</h3><span class="close" onclick="cerrarModal('modal-mapa')">×</span></div>
            <div id="map-canvas" style="height: 300px; border-radius: 12px; margin-bottom: 15px; border: 1px solid #eee; overflow: hidden;"></div>
            <a id="btn-gmaps-externo" href="#" target="_blank" class="btn-ui-azul"><i class="fas fa-directions"></i> Abrir en Maps</a>
        </div>
    </div>

    <script src="app.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCbgo4XsKtuPO6riZd_9eQ3ErGXaI89J2M&libraries=places"></script>
</body>
</html>

<?php
get_footer();
?>
