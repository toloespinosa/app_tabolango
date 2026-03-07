let charts = {};
let datosOriginales = null;

// URL DIRECTA A PRODUCCIÓN 
const URL_API_STATS = window.getApi('get_stats.php');

document.addEventListener('DOMContentLoaded', () => {
    const rolActual = window.APP_USER ? parseInt(window.APP_USER.rol_id) : 0;
    const esAdminGlobal = (window.APP_USER && window.APP_USER.isAdmin);
    const puedeVerPagina = (rolActual === 1 || rolActual === 2 || rolActual === 4 || esAdminGlobal);

    if (!puedeVerPagina) {
        document.getElementById('premium-dashboard').innerHTML = `
            <div style="text-align:center; padding:100px 20px; color:#334155; animation: fadeIn 0.5s;">
                <i class="fa-solid fa-chart-pie" style="font-size: 60px; margin-bottom: 20px; color: #e74c3c;"></i>
                <h2 style="font-size: 28px; font-weight: 900; color: #FF6600; margin-bottom: 10px;">Acceso Restringido</h2>
                <p style="font-size: 16px; color: #64748b;">No tienes los permisos necesarios para acceder al Panel de Inteligencia.</p>
                <button onclick="window.location.href='/'" style="margin-top: 30px; background: #FF6600; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer;">VOLVER AL INICIO</button>
            </div>`;
        return;
    }

    const mesActual = new Date().getMonth() + 1;
    document.getElementById('select-mes').value = mesActual;

    cargarEstadisticas();
});

async function cargarEstadisticas() {
    const año = document.getElementById('select-año').value;
    const url = `${URL_API_STATS}?year=${año}`;

    try {
        const resp = await fetch(url);
        const data = await resp.json();
        datosOriginales = data;

        filtrarPorMes();

    } catch (e) {
        console.error("Error al cargar datos:", e);
    }
}

function actualizarVista(kpis, empresas, productos, textoVentaLabel = "Venta Mes Actual") {
    const ventaActual = kpis.venta_mes || 0;
    const metaDinamica = kpis.meta_mensual || 25000000;

    // Cambiar título de la tarjeta
    document.querySelector('.kpi-card:nth-child(1) .label').innerText = textoVentaLabel;
    document.getElementById('kpi-venta-mes').innerText = formatearDinero(ventaActual);

    const porc = Math.min((ventaActual / metaDinamica) * 100, 100);
    const barra = document.getElementById('barra-mes');
    barra.style.width = porc + "%";

    const puntoEquilibrio = metaDinamica / 1.25;
    barra.style.backgroundColor = (ventaActual < puntoEquilibrio) ? "#ef4444" : "#10b981";

    document.querySelector('.progreso-text').innerText = `Meta (Equilibrio + Utilidad): ${formatearDinero(metaDinamica)}`;

    document.getElementById('kpi-margen-porc').innerText = (kpis.margen_porcentaje || 0) + "%";
    document.getElementById('kpi-margen-pesos').innerText = formatearDinero(kpis.margen_total_pesos) + " utilidad anual";
    document.getElementById('kpi-pendientes').innerText = kpis.pedidos_pendientes;

    renderTopEmpresas(empresas);
    renderTopProductos(productos);
}

function filtrarPorMes() {
    const mesSeleccionado = document.getElementById('select-mes').value;
    if (!datosOriginales || !datosOriginales.detallado_mes) return;

    // Limpiador de estado
    const normalizarEstado = (estado) => estado ? estado.trim().toLowerCase() : '';

    const datosReales = datosOriginales.detallado_mes.filter(d => normalizarEstado(d.estado) === 'entregado');
    const datosProyectados = datosOriginales.detallado_mes.filter(d => {
        const est = normalizarEstado(d.estado);
        return est !== 'entregado' && est !== 'cancelado';
    });

    if (mesSeleccionado === "todos") {
        // --- VISTA TODO EL AÑO ---
        const metaAnual = (datosOriginales.kpis.meta_mensual || 25000000) * 12;

        const rankingEmp = agruparYSumar(datosReales, 'cliente', 'total_venta').slice(0, 5);
        const rankingProd = agruparYSumar(datosReales, 'producto', 'total_venta').slice(0, 5).map(p => ({
            name: p.nombre,
            val: formatearDinero(p.total)
        }));

        actualizarVista({
            venta_mes: datosOriginales.kpis.venta_anual,
            meta_mensual: metaAnual,
            margen_total_pesos: datosOriginales.kpis.margen_total_pesos,
            margen_porcentaje: datosOriginales.kpis.margen_porcentaje,
            pedidos_pendientes: datosOriginales.kpis.pedidos_pendientes
        }, rankingEmp, rankingProd, "Venta Anual Acumulada");

        renderVentasMensuales(datosOriginales.mensual);
        renderVendedores(datosOriginales.vendedores);

        const rankingCatGlobal = agruparYSumar(datosReales, 'nombre_categoria', 'total_venta');
        const rankingComGlobal = agruparYSumar(datosReales, 'comuna', 'total_venta');
        renderTortaCategorias(rankingCatGlobal);
        renderTortaComunas(rankingComGlobal);

    } else {
        // --- VISTA MES ESPECÍFICO ---
        const realesMes = datosReales.filter(d => d.mes == mesSeleccionado);
        const proyectadosMes = datosProyectados.filter(d => d.mes == mesSeleccionado);
        const todosMes = datosOriginales.detallado_mes.filter(d => d.mes == mesSeleccionado);

        const ventaMes = realesMes.reduce((sum, d) => sum + d.total_venta, 0);
        const margenMes = realesMes.reduce((sum, d) => sum + d.margen, 0);

        const costosOperativos = 4633000;
        const ratioMargen = ventaMes > 0 ? (margenMes / ventaMes) : 0.17;
        const puntoEquilibrio = costosOperativos / ratioMargen;
        const metaDinamicaMes = puntoEquilibrio * 1.25;

        const rankingVendMes = agruparYSumar(realesMes, 'vendedor', 'total_venta');
        const dataVendedoresMes = {
            nombres: rankingVendMes.map(v => v.nombre),
            ventas: rankingVendMes.map(v => v.total)
        };

        const rankingEmp = agruparYSumar(realesMes, 'cliente', 'total_venta').slice(0, 5);
        const rankingProd = agruparYSumar(realesMes, 'producto', 'total_venta').slice(0, 5).map(p => ({
            name: p.nombre,
            val: formatearDinero(p.total)
        }));

        const año = document.getElementById('select-año').value;
        const diasEnMes = new Date(año, mesSeleccionado, 0).getDate();

        let lD = [], vD = [], pD = [], mD = [], mpD = [], conteoPedidos = [];

        for (let i = 1; i <= diasEnMes; i++) {
            lD.push(i);
            const rDia = realesMes.filter(d => d.dia == i);
            const pDia = proyectadosMes.filter(d => d.dia == i);
            const tDia = todosMes.filter(d => d.dia == i);

            vD.push(rDia.reduce((s, d) => s + d.total_venta, 0));
            pD.push(pDia.reduce((s, d) => s + d.total_venta, 0));
            mD.push(rDia.reduce((s, d) => s + d.margen, 0));
            mpD.push(pDia.reduce((s, d) => s + d.margen, 0));
            conteoPedidos.push(new Set(tDia.map(d => d.cliente)).size);
        }

        const rankingCat = agruparYSumar(realesMes, 'nombre_categoria', 'total_venta');
        const rankingCom = agruparYSumar(realesMes, 'comuna', 'total_venta');
        renderTortaCategorias(rankingCat);
        renderTortaComunas(rankingCom);

        actualizarVista({
            venta_mes: ventaMes,
            meta_mensual: metaDinamicaMes,
            margen_total_pesos: margenMes,
            margen_porcentaje: ventaMes > 0 ? ((margenMes / ventaMes) * 100).toFixed(1) : 0,
            pedidos_pendientes: datosOriginales.kpis.pedidos_pendientes
        }, rankingEmp, rankingProd, "Venta Mes Actual");

        renderVendedores(dataVendedoresMes);

        // 🔥 CORRECCIÓN AQUÍ: Pasamos los 6 parámetros en el orden correcto
        actualizarGraficoDiario(lD, vD, pD, mD, mpD, conteoPedidos);
    }
}

// 🔥 CORRECCIÓN AQUÍ: Recibimos los 6 parámetros (incluyendo margenesProy y conteos)
function actualizarGraficoDiario(labels, ventas, proyectadas, margenes, margenesProy, conteos) {
    const ctx = document.getElementById('chartVentasMensuales').getContext('2d');
    if (charts.mensual) charts.mensual.destroy();

    charts.mensual = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels.map(l => "Día " + l),
            datasets: [
                { label: 'Venta Real', data: ventas, borderColor: '#4f46e5', backgroundColor: 'rgba(79, 70, 229, 0.1)', fill: true, tension: 0.3 },
                { label: 'Venta Proyectada', data: proyectadas, borderColor: '#f59e0b', borderDash: [5, 5], tension: 0.3 },
                { label: 'Margen Real', data: margenes, borderColor: '#10b981', borderDash: [2, 2], tension: 0.3 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        afterBody: (context) => {
                            const index = context[0].dataIndex;
                            return `Entregas en ruta/completadas: ${conteos[index]}`;
                        },
                        label: function (context) {
                            let label = context.dataset.label || '';
                            let value = context.raw || 0;
                            let index = context.dataIndex;
                            let output = label + ': ' + formatearDinero(value);

                            if (label === 'Venta Proyectada') {
                                let mProy = margenesProy[index];
                                output += ' (Utilidad est: ' + formatearDinero(mProy) + ')';
                            }
                            return output;
                        }
                    }
                }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: v => formatearDinero(v) } } }
        }
    });
}

function renderVentasMensuales(data) {
    const ctx = document.getElementById('chartVentasMensuales').getContext('2d');
    if (charts.mensual) charts.mensual.destroy();

    charts.mensual = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.meses,
            datasets: [
                {
                    label: 'Ventas Reales',
                    data: data.ventas,
                    borderColor: '#4f46e5',
                    fill: true,
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Ventas Proyectadas',
                    data: data.proyectadas,
                    borderColor: '#f59e0b',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let label = context.dataset.label || '';
                            let value = context.raw || 0;
                            let index = context.dataIndex;
                            let output = label + ': ' + formatearDinero(value);

                            if (label === 'Ventas Proyectadas') {
                                let mProy = data.proyectadas_margen[index];
                                output += ' (Utilidad est: ' + formatearDinero(mProy) + ')';
                            }
                            return output;
                        }
                    }
                }
            },
            scales: { y: { beginAtZero: true, ticks: { callback: v => formatearDinero(v) } } }
        }
    });
}

function agruparYSumar(arr, claveNombre, claveValor) {
    const sumas = arr.reduce((acc, obj) => {
        const nombre = obj[claveNombre] || 'Sin Nombre';
        acc[nombre] = (acc[nombre] || 0) + (parseFloat(obj[claveValor]) || 0);
        return acc;
    }, {});

    return Object.keys(sumas)
        .map(nombre => ({ nombre: nombre, total: sumas[nombre] }))
        .sort((a, b) => b.total - a.total);
}

function renderVendedores(data) {
    const ctx = document.getElementById('chartVendedores').getContext('2d');
    if (charts.vendedores) charts.vendedores.destroy();

    charts.vendedores = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.nombres,
            datasets: [{
                label: 'Ventas por Vendedor',
                data: data.ventas,
                backgroundColor: '#4f46e5',
                borderRadius: 10
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { display: true, ticks: { callback: value => formatearDinero(value) } },
                y: { grid: { display: false }, ticks: { font: { weight: '600' } } }
            }
        }
    });
}

function renderTopEmpresas(empresas) {
    const lista = document.getElementById('top-empresas-lista');
    lista.innerHTML = empresas.length ? empresas.map(emp => `
        <div class="top-item">
            <span class="top-item-name">${emp.nombre}</span>
            <span class="top-item-val">${formatearDinero(emp.total)}</span>
        </div>
    `).join('') : '<p>Sin datos</p>';
}

function renderTopProductos(productos) {
    const lista = document.getElementById('top-productos-lista');
    lista.innerHTML = productos.length ? productos.map(prod => `
        <div class="top-item">
            <span class="top-item-name">${prod.name || prod.nombre}</span>
            <span class="top-item-val">${prod.val || formatearDinero(prod.total)}</span>
        </div>
    `).join('') : '<p>Sin datos</p>';
}

function formatearDinero(n) {
    return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n);
}

function renderTortaCategorias(data) {
    const ctx = document.getElementById('chartCategoriasVenta').getContext('2d');
    if (charts.categorias) charts.categorias.destroy();

    const totalVenta = data.reduce((sum, d) => sum + d.total, 0);

    charts.categorias = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.map(d => d.nombre),
            datasets: [{
                data: data.map(d => d.total),
                backgroundColor: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'],
                borderWidth: 2,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const valor = context.raw;
                            const porcentaje = ((valor / totalVenta) * 100).toFixed(1);
                            return ` ${context.label}: ${formatearDinero(valor)} (${porcentaje}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
}

function renderTortaComunas(data) {
    const ctx = document.getElementById('chartComunasVenta').getContext('2d');
    if (charts.comunas) charts.comunas.destroy();

    const totalVenta = data.reduce((sum, d) => sum + d.total, 0);

    charts.comunas = new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.map(d => d.nombre),
            datasets: [{
                data: data.map(d => d.total),
                backgroundColor: ['#6366f1', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#22d3ee'],
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const valor = context.raw;
                            const porcentaje = ((valor / totalVenta) * 100).toFixed(1);
                            return ` ${context.label}: ${formatearDinero(valor)} (${porcentaje}%)`;
                        }
                    }
                }
            }
        }
    });
}