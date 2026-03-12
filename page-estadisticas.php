<?php
/* Template Name: Estadísticas */
tabolango_requerir_rol([1, 2, 4]);
get_header();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div class="stats-wrapper" id="premium-dashboard">
    <div class="stats-header">
        <div>
            <h1 style="color: #FF6600; font-family: sans-serif; font-weight: bold;">Panel de Inteligencia</h1>
            <p id="stats-subtitle">Análisis de rendimiento global</p>
        </div>

        <div class="periodo-selector">
            <i class="fas fa-calendar-day"></i>
            <select id="select-mes" onchange="filtrarPorMes()">
                <option value="todos">Todo el año</option>
                <option value="1">Enero</option>
                <option value="2">Febrero</option>
                <option value="3">Marzo</option>
                <option value="4">Abril</option>
                <option value="5">Mayo</option>
                <option value="6">Junio</option>
                <option value="7">Julio</option>
                <option value="8">Agosto</option>
                <option value="9">Septiembre</option>
                <option value="10">Octubre</option>
                <option value="11">Noviembre</option>
                <option value="12">Diciembre</option>
            </select>
            <i class="fas fa-calendar-alt"></i>
            <select id="select-año" onchange="cargarEstadisticas()">
                <option value="2025">2025</option>
                <option value="2026" selected>2026</option>
            </select>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon color-1"><i class="fas fa-shopping-cart"></i></div>
            <div class="kpi-info">
                <span class="label">Venta Mes Actual</span>
                <div class="valor" id="kpi-venta-mes">$0</div>
                <div class="progreso-container">
                    <div class="progreso-meta"><div id="barra-mes" class="progreso-fill bg-1"></div></div>
                    <span class="progreso-text">Meta: 30M</span>
                </div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon color-2"><i class="fas fa-percentage"></i></div>
            <div class="kpi-info">
                <span class="label">Margen Anual (%)</span>
                <div class="valor" id="kpi-margen-porc">0%</div>
                <div class="badge-trend" id="kpi-margen-pesos" style="background:rgba(16, 185, 129, 0.1); color:#10b981; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:700;">$0 utilidad</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon color-3"><i class="fas fa-truck-loading"></i></div>
            <div class="kpi-info">
                <span class="label">Pedidos Pendientes</span>
                <div class="valor" id="kpi-pendientes">0</div>
                <span class="sub-label">Por gestionar</span>
            </div>
        </div>
    </div>

    <div class="charts-main-grid">
        <div class="chart-box large">
            <div class="chart-header">
                <h3><i class="fas fa-history"></i> Histórico de Ventas vs Meta</h3>
                <span class="chart-tag">Mensual</span>
            </div>
            <div class="canvas-holder">
                <canvas id="chartVentasMensuales"></canvas>
            </div>
        </div>
        
        <div class="chart-box">
            <div class="chart-header">
                <h3><i class="fas fa-user-tie"></i> Ranking Vendedores</h3>
            </div>
            <div class="canvas-holder">
                <canvas id="chartVendedores"></canvas>
            </div>
        </div>
    </div>

    <div class="charts-secondary-grid">
        <div class="chart-box">
            <div class="chart-header">
                <h3><i class="fas fa-building"></i> Top 5 Empresas (Ventas)</h3>
            </div>
            <div id="top-empresas-lista" class="top-list">
                <p style="color:#888; text-align:center; padding-top:20px;">Cargando...</p>
            </div>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h3><i class="fas fa-trophy"></i> Top Productos (Volumen)</h3>
            </div>
            <div id="top-productos-lista" class="top-list">
                <p style="color:#888; text-align:center; padding-top:20px;">Cargando...</p>
            </div>
        </div>
    </div>
    
    <div class="charts-secondary-grid" style="margin-top: 25px;">
        <div class="chart-box">
            <div class="chart-header">
                <h3><i class="fas fa-tags"></i> Ventas por Categoría</h3>
            </div>
            <div class="canvas-holder">
                <canvas id="chartCategoriasVenta"></canvas>
            </div>
        </div>
        <div class="chart-box">
            <div class="chart-header">
                <h3><i class="fas fa-map-marker-alt"></i> Ventas por Comuna</h3>
            </div>
            <div class="canvas-holder">
                <canvas id="chartComunasVenta"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
?>
