// 🔥 FORZAMOS RUTAS A PRODUCCIÓN PARA VER DATOS REALES EN LOCAL 🔥
const URL_API_LISTA = window.getApi('api_lista_facturas.php');
const URL_API_NC = window.getApi('procesar_nota_credito.php');
const URL_API_FOLIOS = window.getApi('api_gestion_folios.php');

// 🔥 VARIABLES PARA SCROLL INFINITO 🔥
let offsetActual = 0;
const LIMITE_POR_PAGINA = 25;
let cargandoMas = false;
let hayMasFacturas = true;

document.addEventListener('DOMContentLoaded', () => {
    // 🔥 BLINDAJE DE FRONT-END (Zero Trust) 🔥
    const rolActual = window.APP_USER ? parseInt(window.APP_USER.rol_id) : 0;
    const esAdminGlobal = (window.APP_USER && window.APP_USER.isAdmin);

    // Solo permitimos roles 1 (Admin), 2 (Editor), y 4 (Vendedor)
    const puedeVerPagina = (rolActual === 1 || rolActual === 2 || rolActual === 4 || esAdminGlobal);

    if (!puedeVerPagina) {
        document.getElementById('premium-dashboard').innerHTML = `
            <div style="text-align:center; padding:100px 20px; color:#334155; animation: fadeIn 0.5s;">
                <i class="fa-solid fa-shield-halved" style="font-size: 60px; margin-bottom: 20px; color: #e74c3c;"></i>
                <h2 style="font-size: 28px; font-weight: 900; color: #0f4b29; margin-bottom: 10px;">Acceso Restringido</h2>
                <p style="font-size: 16px; color: #64748b;">No tienes los permisos necesarios para acceder al Panel de Facturación.</p>
                <button onclick="window.location.href='/'" style="margin-top: 30px; background: #0F4B29; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer;">VOLVER AL INICIO</button>
            </div>`;
        return; // Bloqueamos la ejecución del resto del script
    }

    // Si tiene permiso, cargamos el primer lote de facturas
    cargarFacturasPremium(true);

    // Inicializamos el evento para detectar el final de la tabla
    initInfiniteScroll();
});

function switchTab(tab) {
    document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.view-section').forEach(el => el.classList.add('hidden'));

    const btns = document.querySelectorAll('.tab-link');
    if (tab === 'emitidas') btns[0].classList.add('active');
    if (tab === 'folios') btns[1].classList.add('active');
    if (tab === 'recibidas') btns[2].classList.add('active');

    document.getElementById(`view-${tab}`).classList.remove('hidden');

    if (tab === 'folios') cargarFolios();
}

// --- LÓGICA DE SCROLL INFINITO ---
function initInfiniteScroll() {
    const contenedorTabla = document.querySelector('.table-responsive');

    if (contenedorTabla) {
        contenedorTabla.addEventListener('scroll', () => {
            const isTabEmitidasVisible = !document.getElementById('view-emitidas').classList.contains('hidden');

            if (isTabEmitidasVisible && !cargandoMas && hayMasFacturas) {
                // Detecta si estamos a 50px del fondo del contenedor
                if (contenedorTabla.scrollTop + contenedorTabla.clientHeight >= contenedorTabla.scrollHeight - 50) {
                    cargarFacturasPremium(false); // false = es una carga secundaria (paginación)
                }
            }
        });
    }
}

// --- EMITIDAS (CON PAGINACIÓN) ---
async function cargarFacturasPremium(esCargaInicial = true) {
    const tbody = document.getElementById('tbody-facturas');

    if (esCargaInicial) {
        offsetActual = 0;
        hayMasFacturas = true;
        tbody.innerHTML = `<tr><td colspan="5" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando documentos...</td></tr>`;
    } else {
        // Fila temporal mientras carga más datos
        tbody.insertAdjacentHTML('beforeend', `<tr id="tr-loader-scroll"><td colspan="5" class="loading-row" style="text-align:center;"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando historial...</td></tr>`);
    }

    cargandoMas = true;

    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const sep = URL_API_LISTA.includes('?') ? '&' : '?';

        // Petición con límite y offset
        const res = await fetch(`${URL_API_LISTA}${sep}action=emitidas&wp_user=${encodeURIComponent(emailActual)}&limit=${LIMITE_POR_PAGINA}&offset=${offsetActual}`);

        if (!res.ok) throw new Error("Error servidor");
        const data = await res.json();

        // Limpiar loaders
        if (esCargaInicial) tbody.innerHTML = '';
        const loaderScroll = document.getElementById('tr-loader-scroll');
        if (loaderScroll) loaderScroll.remove();

        if (!Array.isArray(data) || data.length === 0) {
            hayMasFacturas = false;
            if (esCargaInicial) {
                tbody.innerHTML = `<tr><td colspan="5" class="empty-state"><p>No hay facturas emitidas.</p></td></tr>`;
            } else {
                tbody.insertAdjacentHTML('beforeend', `<tr><td colspan="5" style="text-align:center; color:#94a3b8; font-size:12px; padding:15px;">No hay más facturas en el historial.</td></tr>`);
            }
            cargandoMas = false;
            return;
        }

        // Si la BD devolvió menos de las 25 que le pedimos, ya no hay más para la próxima
        if (data.length < LIMITE_POR_PAGINA) {
            hayMasFacturas = false;
        }

        offsetActual += LIMITE_POR_PAGINA;

        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const isAdmin = (window.APP_USER && window.APP_USER.isAdmin);

        data.forEach(item => {
            let accionHTML = '';
            let puedeAnular = true;

            if (item.fecha_despacho) {
                const partesFecha = item.fecha_despacho.split('-');
                if (partesFecha.length === 3) {
                    const fechaDesp = new Date(partesFecha[0], partesFecha[1] - 1, partesFecha[2]);
                    fechaDesp.setHours(0, 0, 0, 0);

                    const diffTime = hoy.getTime() - fechaDesp.getTime();
                    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

                    if (diffDays >= 5 && !isAdmin) {
                        puedeAnular = false;
                    }
                }
            }

            if (item.estado_nota_credito === 'EMITIDA') {
                const url_nc = item.url_nc ? item.url_nc : 'null';
                accionHTML = `<button class="status-nc clickeable" onclick="verDocumento('${url_nc}', 'NC')" title="Ver Nota de Crédito"><i class="fa-solid fa-ban"></i> ANULADA</button>`;
            } else if (!puedeAnular) {
                accionHTML = `<span style="color:#94a3b8; font-size:11px; font-weight:bold; background:#f1f5f9; padding:6px 10px; border-radius:6px;">Plazo Expirado</span>`;
            } else {
                accionHTML = `<button class="p-btn p-btn-danger" onclick="confirmarAnulacion('${item.id_pedido}', ${item.numero_factura}, '${item.cliente.replace(/'/g, "")}')"><i class="fa-solid fa-file-circle-xmark"></i> Anular</button>`;
            }

            // Agregamos las filas al final de la tabla
            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td><span class="folio-tag">#${item.numero_factura}</span></td>
                    <td><b>${item.fecha_fmt}</b></td>
                    <td><span class="client-name">${item.cliente}</span><span style="font-size:11px; color:#94a3b8;">ID: ${item.id_pedido}</span></td>
                    <td><span class="amount-txt">${item.total_fmt}</span></td>
                    <td><div class="actions-group"><button class="p-btn p-btn-view" onclick="verDocumento('${item.url_factura}', ${item.numero_factura})"><i class="fa-regular fa-eye"></i></button>${accionHTML}</div></td>
                </tr>`);
        });
    } catch (e) {
        const loaderScroll = document.getElementById('tr-loader-scroll');
        if (loaderScroll) loaderScroll.remove();
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">Error cargando datos de facturación.</td></tr>`;
        console.error(e);
    } finally {
        cargandoMas = false;
    }
}

// --- FOLIOS ---
async function cargarFolios() {
    const grid = document.getElementById('folios-grid');
    grid.innerHTML = '<div class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Consultando estado...</div>';
    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const sep = URL_API_FOLIOS.includes('?') ? '&' : '?';
        const res = await fetch(`${URL_API_FOLIOS}${sep}action=status&wp_user=${encodeURIComponent(emailActual)}`);
        const data = await res.json();

        grid.innerHTML = '';

        data.forEach(f => {
            const totalCaf = f.rango_hasta - f.rango_desde + 1;
            let pct = 0;
            if (totalCaf > 0) pct = (f.disponibles_local / totalCaf) * 100;
            if (pct < 0) pct = 0; if (pct > 100) pct = 100;

            let colorBar = '#22c55e';
            if (pct < 50) colorBar = '#f59e0b';
            if (pct < 20) colorBar = '#ef4444';

            let estadoTxt = 'Operativo';
            if (f.disponibles_local <= 0) estadoTxt = 'CRÍTICO: SIN FOLIOS';
            else if (f.disponibles_local < 10) estadoTxt = 'BAJO STOCK';

            grid.innerHTML += `
                <div class="folio-card">
                    <h4>${f.nombre} <small style="color:#64748b; font-weight:400;">Cód. ${f.tipo}</small></h4>
                    <div class="folio-stat"><span>Último Usado:</span> <span class="stat-val">${f.ultimo_usado}</span></div>
                    <div class="folio-stat"><span>Rango CAF:</span> <span class="stat-val">${f.rango_desde} - ${f.rango_hasta}</span></div>
                    <div class="folio-stat"><span>Disponibles Local:</span> <span class="stat-val" style="color:${colorBar}">${f.disponibles_local}</span></div>
                    <div class="progress-bg"><div class="progress-fill" style="width:${pct}%; background-color:${colorBar};"></div></div>
                    <div style="font-size:11px; text-align:right; margin-bottom:10px; color:${colorBar}; font-weight:bold;">${estadoTxt}</div>
                    <div class="folio-actions">
                        <button class="btn-folio" onclick="consultarSII(${f.tipo})">🔍 Verificar SII</button>
                        <button class="btn-folio primary" onclick="solicitarCAF(${f.tipo})">📥 Bajar Nuevos</button>
                    </div>
                </div>`;
        });
    } catch (e) { grid.innerHTML = 'Error cargando folios.'; console.error(e); }
}

async function consultarSII(tipo) {
    const btn = event.target;
    const originalTxt = btn.innerText;
    btn.innerText = "⏳..."; btn.disabled = true;
    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const sep = URL_API_FOLIOS.includes('?') ? '&' : '?';
        const res = await fetch(`${URL_API_FOLIOS}${sep}action=check_sii&tipo=${tipo}&wp_user=${encodeURIComponent(emailActual)}`);
        const data = await res.json();

        if (data.status === 'success') {
            Swal.fire('Info SII', `El SII indica que tienes <b>${data.cantidad}</b> folios disponibles para descargar.`, 'info');
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Fallo conexión SII', 'error'); }
    finally { btn.innerText = originalTxt; btn.disabled = false; }
}

async function solicitarCAF(tipo) {
    const { value: cantidad } = await Swal.fire({
        title: 'Solicitar Folios',
        input: 'number',
        inputLabel: 'Cantidad a descargar',
        inputValue: 50,
        showCancelButton: true,
        confirmButtonColor: '#0f4b29',
        confirmButtonText: 'Descargar'
    });

    if (cantidad) {
        Swal.fire({ title: 'Descargando...', didOpen: () => Swal.showLoading() });
        try {
            let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
            const sep = URL_API_FOLIOS.includes('?') ? '&' : '?';
            const res = await fetch(`${URL_API_FOLIOS}${sep}action=descargar_caf&tipo=${tipo}&cantidad=${cantidad}&wp_user=${encodeURIComponent(emailActual)}`);
            const data = await res.json();

            if (data.status === 'success') {
                await Swal.fire('Éxito', 'CAF actualizado correctamente.', 'success');
                cargarFolios();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (e) { Swal.fire('Error', 'No se pudo descargar el CAF', 'error'); }
    }
}

// --- UTILIDADES ---
function verDocumento(url, folio) {
    if (!url || url === 'null') { Swal.fire('Aviso', 'Documento no generado', 'info'); return; }
    Swal.fire({ html: `<iframe src="${url}" style="width:100%; height:75vh; border:none;"></iframe>`, width: '850px', showConfirmButton: false, showCloseButton: true });
}

async function confirmarAnulacion(id, folio, cliente) {
    const result = await Swal.fire({
        title: 'Anulación Factura #' + folio,
        text: '¿Qué tipo de anulación desea realizar?',
        icon: 'question',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-file-circle-xmark"></i> Anulación Total',
        denyButtonText: '<i class="fa-solid fa-file-pen"></i> Anulación Parcial',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0f4b29',
        denyButtonColor: '#0f172a',
        cancelButtonColor: '#94a3b8',
        reverseButtons: true
    });

    if (!result.isConfirmed && !result.isDenied) return;

    const tipoAnulacion = result.isConfirmed ? 'total' : 'parcial';
    let montoNetoManual = null;

    if (tipoAnulacion === 'parcial') {
        const { value: monto } = await Swal.fire({
            title: 'Monto Neto a Anular',
            text: 'Ingrese el valor NETO (sin IVA) a rebajar de la factura:',
            input: 'number',
            inputPlaceholder: 'Ej: 15000',
            showCancelButton: true,
            confirmButtonText: 'Procesar Parcial',
            confirmButtonColor: '#0f172a',
            cancelButtonText: 'Cancelar'
        });

        if (!monto || monto <= 0) {
            Swal.fire('Aviso', 'Debe ingresar un monto válido mayor a 0.', 'warning');
            return;
        }
        montoNetoManual = monto;
    }

    Swal.fire({
        title: 'Procesando al SII...',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });

    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const res = await fetch(URL_API_NC, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_pedido: id,
                tipo_nc: tipoAnulacion,
                monto_neto_parcial: montoNetoManual,
                wp_user: emailActual
            })
        });

        const d = await res.json();
        if (d.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: '¡Emitida!',
                text: d.message,
                confirmButtonText: 'Ver Documento NC',
                confirmButtonColor: '#0f4b29'
            }).then(() => {
                if (d.url_pdf) window.open(d.url_pdf, '_blank');
                // Al anular, recargamos la tabla desde cero para refrescar el estado
                cargarFacturasPremium(true);
            });
        } else {
            Swal.fire('Error del Servidor', d.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Fallo de conexión: ' + e.message, 'error');
    }
}