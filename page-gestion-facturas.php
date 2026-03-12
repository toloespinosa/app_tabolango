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
            <div class="empty-state">
                <div class="icon-bg"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <h3>Próximamente</h3>
                <p>El módulo de facturas de compra estará disponible pronto.</p>
            </div>
        </div>

    </div>
</div>

<?php get_footer(); ?>