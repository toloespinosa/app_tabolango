<?php
/* Template Name: Panel de Facturacion */
tabolango_requerir_rol([1, 2]);
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div id="premium-dashboard">
    <div class="dash-header">
        <div>
            <h2 class="dash-title">Panel de Facturación</h2>
            <p class="dash-subtitle">Gestión integral de DTEs y Folios SII.</p>
        </div>
        <button class="btn-refresh" onclick="location.reload()">
            <i class="fa-solid fa-rotate"></i> Recargar
        </button>
    </div>

    <div class="nav-tabs">
        <button class="tab-link active" onclick="switchTab('emitidas')">
            <i class="fa-solid fa-paper-plane"></i> Emitidas
        </button>
        <button class="tab-link" onclick="switchTab('folios')">
            <i class="fa-solid fa-barcode"></i> Administración Folios/CAF
        </button>
        <button class="tab-link" onclick="switchTab('recibidas')">
            <i class="fa-solid fa-inbox"></i> Recibidas
        </button>
        <button class="tab-link" onclick="switchTab('combustible')">
            <i class="fa-solid fa-gas-pump"></i> Combustible Copec
        </button>
    </div>

    <div class="content-wrapper">
        
        <div id="view-emitidas" class="view-section">
            <div class="table-responsive">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th width="10%">FOLIO</th>
                            <th width="15%">FECHA</th>
                            <th width="35%">CLIENTE</th>
                            <th width="15%">TOTAL</th>
                            <th width="25%" style="text-align: right;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-facturas">
                        <tr><td colspan="5" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando documentos...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="view-folios" class="view-section hidden" style="padding: 25px;">
            <h3 style="margin-top:0; color:#0f172a;">Estado de Folios Locales</h3>
            <p style="color:#64748b; font-size:13px; margin-bottom:20px;">Aquí puedes ver cuántos folios te quedan en el servidor y descargar nuevos desde el SII.</p>
            
            <div id="folios-grid" class="folios-grid">
                <div class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Analizando CAFs...</div>
            </div>
        </div>

        <div id="view-recibidas" class="view-section hidden">
            <div style="padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                    <i class="fa-solid fa-magnifying-glass" style="color: #94a3b8; font-size: 16px;"></i>
                    <input type="text" id="filtro-proveedor" placeholder="Buscar por Nombre o RUT de Proveedor..." 
                           style="flex: 1; border: none !important; background: transparent !important; box-shadow: none !important; font-size: 15px; font-weight: 600; outline: none; color: #334155;"
                           onkeyup="filtrarRecibidasDebounce()">
                </div>
                <button class="p-btn" style="background: #0F4B29; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; white-space: nowrap;" 
                        onclick="forzarSincronizacionRecibidas()">
                    <i class="fa-solid fa-rotate"></i> Sincronizar SII
                </button>
            </div>

            <div class="table-responsive" id="scroll-recibidas" style="max-height: 600px; overflow-y: auto;">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th width="10%">FOLIO</th>
                            <th width="15%">FECHA</th>
                            <th width="35%">PROVEEDOR</th>
                            <th width="15%">TOTAL</th>
                            <th width="25%" style="text-align: right;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-recibidas">
                        <tr><td colspan="5" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Inicializando módulo...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

       <div id="view-combustible" class="view-section hidden" style="padding: 20px;">
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <div class="folio-card" style="border-left: 4px solid #0056b3;">
                    <h4 style="margin:0; color:#64748b;">Saldo Disponible</h4>
                    <h2 id="wallet-saldo" style="margin:10px 0; color:#0056b3; font-size:28px;">$0</h2>
                    <button class="p-btn" style="width:100%; background:#0f4b29; color:white; border:none; padding:8px; border-radius:6px; cursor:pointer;" onclick="registrarAbonoCombustible()">
                        <i class="fa-solid fa-plus"></i> Ingresar Abono
                    </button>
                </div>
                <div class="folio-card" style="border-left: 4px solid #22c55e;">
                    <h4 style="margin:0; color:#64748b;">Abonos del Período</h4>
                    <h2 id="wallet-abonos" style="margin:10px 0; color:#334155;">$0</h2>
                </div>
                <div class="folio-card" style="border-left: 4px solid #ef4444;">
                    <h4 style="margin:0; color:#64748b;">Consumo del Período</h4>
                    <h2 id="wallet-consumos" style="margin:10px 0; color:#334155;">$0</h2>
                </div>
            </div>

            <?php 
                $mes_actual = date('m'); 
                $anio_actual = date('Y'); 
            ?>
            <div style="padding: 15px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-bottom: none; border-radius: 8px 8px 0 0; display: flex; align-items: center; flex-wrap: wrap; gap: 15px;">
                
                <div style="display: flex; align-items: center; flex: 1; min-width: 250px;">
                    <i class="fa-solid fa-magnifying-glass" style="color: #94a3b8; font-size: 16px; margin-right: 10px;"></i>
                    <input type="text" id="filtro-patente" placeholder="Buscar por Patente, Folio o Nota..." 
                           style="flex: 1; border: none !important; background: transparent !important; box-shadow: none !important; font-size: 15px; font-weight: 600; outline: none; color: #334155;"
                           onkeyup="filtrarCombustibleDebounce()">
                </div>

                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <select id="filtro-mes" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; color: #475569; outline: none; cursor: pointer;" onchange="cargarDashboardCombustible()">
                        <option value="">Todos los Meses</option>
                        <option value="01" <?php echo ($mes_actual == '01') ? 'selected' : ''; ?>>Enero</option>
                        <option value="02" <?php echo ($mes_actual == '02') ? 'selected' : ''; ?>>Febrero</option>
                        <option value="03" <?php echo ($mes_actual == '03') ? 'selected' : ''; ?>>Marzo</option>
                        <option value="04" <?php echo ($mes_actual == '04') ? 'selected' : ''; ?>>Abril</option>
                        <option value="05" <?php echo ($mes_actual == '05') ? 'selected' : ''; ?>>Mayo</option>
                        <option value="06" <?php echo ($mes_actual == '06') ? 'selected' : ''; ?>>Junio</option>
                        <option value="07" <?php echo ($mes_actual == '07') ? 'selected' : ''; ?>>Julio</option>
                        <option value="08" <?php echo ($mes_actual == '08') ? 'selected' : ''; ?>>Agosto</option>
                        <option value="09" <?php echo ($mes_actual == '09') ? 'selected' : ''; ?>>Septiembre</option>
                        <option value="10" <?php echo ($mes_actual == '10') ? 'selected' : ''; ?>>Octubre</option>
                        <option value="11" <?php echo ($mes_actual == '11') ? 'selected' : ''; ?>>Noviembre</option>
                        <option value="12" <?php echo ($mes_actual == '12') ? 'selected' : ''; ?>>Diciembre</option>
                    </select>

                    <select id="filtro-anio" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; color: #475569; outline: none; cursor: pointer;" onchange="cargarDashboardCombustible()">
                        <option value="">Todos los Años</option>
                        <option value="2024" <?php echo ($anio_actual == '2024') ? 'selected' : ''; ?>>2024</option>
                        <option value="2025" <?php echo ($anio_actual == '2025') ? 'selected' : ''; ?>>2025</option>
                        <option value="2026" <?php echo ($anio_actual == '2026') ? 'selected' : ''; ?>>2026</option>
                    </select>

                    <button class="p-btn" style="background: #ea580c; color: white; border: none; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; white-space: nowrap;" onclick="sincronizarCorreosCopec()">
                        <i class="fa-solid fa-envelope-open-text"></i> Sincronizar Cargas
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th width="10%">GUÍA</th>
                            <th width="15%">FECHA</th>
                            <th width="15%">PATENTE</th>
                            <th width="30%">DETALLE</th>
                            <th width="20%">TOTAL</th>
                            <th width="10%" style="text-align: right;">XML</th>
                        </tr>
                    </thead>
                    <tbody id="tbody-combustible">
                        <tr><td colspan="6" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Inicializando billetera...</td></tr>
                    </tbody>
                </table>
            </div>
        </div> </div>
</div>

<?php get_footer(); ?>