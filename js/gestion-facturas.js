// 🔥 RUTAS DEFINITIVAS 🔥
const URL_API_EMITIDAS = window.getApi('api_lista_facturas.php');
const URL_API_RECIBIDAS = window.getApi('api_facturas_recibidas.php');
const URL_API_NC = window.getApi('procesar_nota_credito.php');
const URL_API_FOLIOS = window.getApi('api_gestion_folios.php');
const URL_API_ACUSE = window.getApi('api_acuse_recibo.php');
const URL_API_COMBUSTIBLE = window.getApi('api_combustible.php');

let timerCombustible = null;
let offsetActual = 0;
let offsetRecibidas = 0;
const LIMITE_POR_PAGINA = 25;
let cargandoMas = false;
let hayMasFacturas = true;
let cargandoMasRecibidas = false;
let hayMasRecibidas = true;
let timerFiltro = null;

document.addEventListener('DOMContentLoaded', () => {
    const rolActual = window.APP_USER ? parseInt(window.APP_USER.rol_id) : 0;
    const esAdminGlobal = (window.APP_USER && window.APP_USER.isAdmin);
    const puedeVerPagina = (rolActual === 1 || rolActual === 2 || rolActual === 4 || esAdminGlobal);

    if (!puedeVerPagina) {
        document.getElementById('premium-dashboard').innerHTML = `<div style="text-align:center; padding:100px 20px;"><h2>Acceso Restringido</h2></div>`;
        return;
    }

    cargarFacturasPremium(true); // Carga inicial de emitidas
    initInfiniteScroll();
});

function switchTab(tab) {
    document.querySelectorAll('.tab-link').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.view-section').forEach(el => el.classList.add('hidden'));

    const btns = document.querySelectorAll('.tab-link');
    // Asegúrate de que los índices coincidan con el orden de tus botones en el HTML
    if (tab === 'emitidas') { btns[0].classList.add('active'); cargarFacturasPremium(true); }
    if (tab === 'folios') { btns[1].classList.add('active'); cargarFolios(); }
    if (tab === 'recibidas') { btns[2].classList.add('active'); cargarFacturasRecibidas(true); }
    if (tab === 'combustible') { btns[3].classList.add('active'); cargarDashboardCombustible(); }

    document.getElementById(`view-${tab}`).classList.remove('hidden');
}

function initInfiniteScroll() {
    const contEmitidas = document.querySelector('#view-emitidas .table-responsive');
    const contRecibidas = document.querySelector('#scroll-recibidas');

    if (contEmitidas) {
        contEmitidas.addEventListener('scroll', () => {
            if (!document.getElementById('view-emitidas').classList.contains('hidden') && !cargandoMas && hayMasFacturas) {
                if (contEmitidas.scrollTop + contEmitidas.clientHeight >= contEmitidas.scrollHeight - 50) cargarFacturasPremium(false);
            }
        });
    }

    if (contRecibidas) {
        contRecibidas.addEventListener('scroll', () => {
            if (!document.getElementById('view-recibidas').classList.contains('hidden') && !cargandoMasRecibidas && hayMasRecibidas) {
                if (contRecibidas.scrollTop + contRecibidas.clientHeight >= contRecibidas.scrollHeight - 50) cargarFacturasRecibidas(false);
            }
        });
    }
}

// ==========================================
// MÓDULO 1: FACTURAS EMITIDAS
// ==========================================
async function cargarFacturasPremium(esCargaInicial = true) {
    const tbody = document.getElementById('tbody-facturas');

    if (esCargaInicial) {
        offsetActual = 0;
        hayMasFacturas = true;
        tbody.innerHTML = `<tr><td colspan="5" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando documentos...</td></tr>`;
    } else {
        tbody.insertAdjacentHTML('beforeend', `<tr id="tr-loader-scroll"><td colspan="5" class="loading-row" style="text-align:center;"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando historial...</td></tr>`);
    }

    cargandoMas = true;

    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const sep = URL_API_EMITIDAS.includes('?') ? '&' : '?';

        const res = await fetch(`${URL_API_EMITIDAS}${sep}wp_user=${encodeURIComponent(emailActual)}&limit=${LIMITE_POR_PAGINA}&offset=${offsetActual}`);

        if (!res.ok) throw new Error("Error servidor");
        const data = await res.json();

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

        if (data.length < LIMITE_POR_PAGINA) hayMasFacturas = false;
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
                    if (diffDays >= 5 && !isAdmin) puedeAnular = false;
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

// ==========================================
// MÓDULO 2: FACTURAS RECIBIDAS (CORREGIDO)
// ==========================================

function filtrarRecibidasDebounce() {
    clearTimeout(timerFiltro);
    timerFiltro = setTimeout(() => {
        cargarFacturasRecibidas(true);
    }, 500);
}

async function cargarFacturasRecibidas(esCargaInicial = true) {
    const tbody = document.getElementById('tbody-recibidas');
    const filtro = document.getElementById('filtro-proveedor').value.trim();

    if (esCargaInicial) {
        offsetRecibidas = 0;
        hayMasRecibidas = true;
        tbody.innerHTML = `<tr><td colspan="5" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Consultando documentos recibidos...</td></tr>`;
    } else {
        tbody.insertAdjacentHTML('beforeend', `<tr id="tr-loader-recibidas"><td colspan="5" class="loading-row" style="text-align:center;"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando historial...</td></tr>`);
    }

    cargandoMasRecibidas = true;

    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const sep = URL_API_RECIBIDAS.includes('?') ? '&' : '?';

        const res = await fetch(`${URL_API_RECIBIDAS}${sep}filtro=${encodeURIComponent(filtro)}&wp_user=${encodeURIComponent(emailActual)}&limit=${LIMITE_POR_PAGINA}&offset=${offsetRecibidas}`);

        if (!res.ok) throw new Error("Error servidor");
        const data = await res.json();

        if (esCargaInicial) tbody.innerHTML = '';
        const loaderScroll = document.getElementById('tr-loader-recibidas');
        if (loaderScroll) loaderScroll.remove();

        if (!Array.isArray(data) || data.length === 0) {
            hayMasRecibidas = false;
            if (esCargaInicial) {
                tbody.innerHTML = `<tr><td colspan="5" class="empty-state"><p>No se encontraron facturas recibidas.</p></td></tr>`;
            } else {
                tbody.insertAdjacentHTML('beforeend', `<tr><td colspan="5" style="text-align:center; color:#94a3b8; font-size:12px; padding:15px;">No hay más documentos.</td></tr>`);
            }
            cargandoMasRecibidas = false;
            return;
        }

        if (data.length < LIMITE_POR_PAGINA) hayMasRecibidas = false;
        offsetRecibidas += LIMITE_POR_PAGINA;

        data.forEach(item => {
            let accionHTML = '';
            if (item.estado_acuse === 'ACEPTADA') {
                accionHTML = `<span style="color:#22c55e; font-size:11px; font-weight:bold; background:#dcfce7; padding:6px 10px; border-radius:6px;"><i class="fa-solid fa-check-double"></i> Aceptada</span>`;
            } else if (item.estado_acuse === 'RECHAZADA') {
                accionHTML = `<span style="color:#ef4444; font-size:11px; font-weight:bold; background:#fee2e2; padding:6px 10px; border-radius:6px;"><i class="fa-solid fa-xmark"></i> Rechazada</span>`;
            } else {
                // 🔥 ENVIAMOS EL RUT AL SII EN VEZ DEL ID INTERNO
                accionHTML = `<button class="p-btn" style="background:#0056b3; color:white; border:none; padding:6px 12px; border-radius:6px; font-weight:bold; cursor:pointer;" onclick="confirmarAceptacion('${item.rut_proveedor}', '${item.folio}', '${item.proveedor}')"><i class="fa-solid fa-check"></i> Aceptar</button>`;
            }

            tbody.insertAdjacentHTML('beforeend', `
                <tr>
                    <td><span class="folio-tag" style="background:#f1f5f9; color:#334155;">#${item.folio}</span></td>
                    <td><b>${item.fecha_fmt}</b></td>
                    <td>
                        <span class="client-name">${item.proveedor}</span>
                        <span style="font-size:11px; color:#94a3b8;">RUT: ${item.rut_proveedor}</span>
                    </td>
                    <td><span class="amount-txt">${item.total_fmt}</span></td>
                    <td>
                        <div class="actions-group" style="display:flex; gap:8px; align-items:center;">
                            <button class="p-btn p-btn-view" onclick="verDocumento('${item.url_xml}', ${item.folio})" title="${item.items_hover}">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            ${accionHTML}
                        </div>
                    </td>
                </tr>`);
        });
    } catch (e) {
        const loaderScroll = document.getElementById('tr-loader-recibidas');
        if (loaderScroll) loaderScroll.remove();
        tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; color:red;">Error cargando datos de recibidas.</td></tr>`;
        console.error(e);
    } finally {
        cargandoMasRecibidas = false;
    }
}

// Acuse de Recibo en SII
async function confirmarAceptacion(rutProveedor, folio, proveedor) {
    const result = await Swal.fire({
        title: `Procesar Factura #${folio}`,
        html: `¿Qué acción deseas realizar con la factura de <b>${proveedor}</b>?<br><br><span style="font-size:12px; color:#e74c3c;">Esta acción es irreversible ante el SII y el proveedor.</span>`,
        icon: 'question',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonColor: '#0056b3',
        denyButtonColor: '#dc3545',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fa-solid fa-check-double"></i> Aceptar (Acuse Recibo)',
        denyButtonText: '<i class="fa-solid fa-xmark"></i> Rechazar (Reclamo)',
        cancelButtonText: 'Cancelar',
        customClass: window.swalConfig ? window.swalConfig.customClass : {}
    });

    if (result.isConfirmed || result.isDenied) {
        const accionSII = result.isConfirmed ? 'ERM' : 'RCD';
        const textoCarga = result.isConfirmed ? 'Enviando Acuse de Recibo...' : 'Enviando Rechazo al SII...';

        Swal.fire({ title: textoCarga, didOpen: () => Swal.showLoading(), allowOutsideClick: false });

        try {
            const res = await fetch(URL_API_ACUSE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    folio: folio,
                    rut_proveedor: rutProveedor,
                    accion: accionSII
                })
            });

            const data = await res.json();

            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: result.isConfirmed ? '¡Aceptada!' : '¡Rechazada!',
                    text: data.message,
                    customClass: window.swalConfig ? window.swalConfig.customClass : {}
                }).then(() => {
                    cargarFacturasRecibidas(true);
                });
            } else {
                Swal.fire('Error del SII', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        }
    }
}

// ==========================================
// MÓDULO 3: ADMINISTRACIÓN DE FOLIOS
// ==========================================
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

// ==========================================
// MÓDULO 4: UTILIDADES Y ANULACIONES
// ==========================================
function verDocumento(url, folio) {
    if (!url || url === 'null' || url === '#') {
        Swal.fire('Aviso', 'Documento no generado o no disponible.', 'info');
        return;
    }

    if (url.toLowerCase().endsWith('.xml')) {
        const baseRenderUrl = window.getApi('render_xml_recibido.php');
        const separador = baseRenderUrl.includes('?') ? '&' : '?';
        const iframeUrl = `${baseRenderUrl}${separador}xml_url=${encodeURIComponent(url)}`;

        Swal.fire({
            title: `Factura de Compra #${folio}`,
            html: `<iframe src="${iframeUrl}" style="width:100%; height:75vh; border:none; border-radius:8px;"></iframe>`,
            width: '850px',
            showConfirmButton: false,
            showCloseButton: true
        });
        return;
    }

    Swal.fire({
        html: `<iframe src="${url}" style="width:100%; height:75vh; border:none;"></iframe>`,
        width: '850px',
        showConfirmButton: false,
        showCloseButton: true
    });
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

    Swal.fire({ title: 'Procesando al SII...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : '';
        const res = await fetch(URL_API_NC, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: id, tipo_nc: tipoAnulacion, monto_neto_parcial: montoNetoManual, wp_user: emailActual })
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
                cargarFacturasPremium(true);
            });
        } else {
            Swal.fire('Error del Servidor', d.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Fallo de conexión: ' + e.message, 'error');
    }
}

// ==========================================
// MÓDULO 5: SINCRONIZADORES
// ==========================================
async function forzarSincronizacionRecibidas() {
    const result = await Swal.fire({
        title: 'Sincronizar con el SII',
        html: `Esta acción consumirá <b>1 consulta</b> de tu plan mensual de SimpleAPI.<br><br>¿Deseas continuar?`,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#0F4B29',
        cancelButtonColor: '#e74c3c',
        confirmButtonText: 'Sí, sincronizar',
        cancelButtonText: 'Cancelar',
        customClass: window.swalConfig.customClass
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Conectando con SimpleAPI...',
            html: 'Esto puede demorar hasta 60 segundos.<br>Por favor, no cierres esta ventana.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const cronUrl = wpData.themeUrl + '/inc/cron_sync_recibidas.php';
            const res = await fetch(cronUrl);
            const textResponse = await res.text();

            if (textResponse.includes('[EXITO]')) {
                const match = textResponse.match(/Nuevas: (\d+) \| Actualizadas.*? (\d+)/);
                const nuevas = match ? match[1] : 'varias';

                await Swal.fire({
                    icon: 'success',
                    title: 'Sincronización Completada',
                    text: `Se procesaron correctamente los datos. Se encontraron ${nuevas} facturas nuevas.`,
                    customClass: window.swalConfig.customClass
                });
                cargarFacturasRecibidas(true);
            } else {
                Swal.fire('Advertencia', 'El proceso terminó, pero revisa la consola para ver los detalles del SII.', 'warning');
            }
        } catch (e) {
            Swal.fire('Error', 'Hubo un fallo al intentar conectar con el script de sincronización.', 'error');
        }
    }
}

// Nuevo Sincronizador de Correos / Archivos Locales
async function descargarXmlDesdeCorreo() {
    Swal.fire({
        title: 'Procesando XMLs...',
        html: 'Extrayendo facturas. Por favor espera...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        // NOTA: Para producción cambiar a 'cron_read_imap_recibidas.php'
        const cronUrl = wpData.themeUrl + '/inc/scan_local_xml.php';
        const res = await fetch(cronUrl);
        const textResponse = await res.text();

        if (textResponse.includes('[EXITO]')) {
            Swal.fire('¡Éxito!', 'Los archivos XML se han descargado e indexado correctamente en el ERP.', 'success');
            cargarFacturasRecibidas(true);
        } else {
            console.error(textResponse);
            Swal.fire('Aviso', 'El proceso terminó. Revisa la consola para más detalles.', 'warning');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo conectar con el lector.', 'error');
    }
}
// ==========================================
// MÓDULO 6: COMBUSTIBLE (BILLETERA COPEC)
// ==========================================

function filtrarCombustibleDebounce() {
    clearTimeout(timerCombustible);
    timerCombustible = setTimeout(() => cargarDashboardCombustible(), 500);
}

async function cargarDashboardCombustible() {
    const tbody = document.getElementById('tbody-combustible');
    const filtro = document.getElementById('filtro-patente').value.trim();
    const mes = document.getElementById('filtro-mes').value;
    const anio = document.getElementById('filtro-anio').value;

    tbody.innerHTML = `<tr><td colspan="6" class="loading-row"><i class="fa-solid fa-circle-notch fa-spin"></i> Cargando movimientos...</td></tr>`;

    try {
        const sep = URL_API_COMBUSTIBLE.includes('?') ? '&' : '?';
        const res = await fetch(`${URL_API_COMBUSTIBLE}${sep}action=dashboard&filtro=${encodeURIComponent(filtro)}&mes=${mes}&anio=${anio}`);
        const data = await res.json();

        if (data.status === 'success') {
            document.getElementById('wallet-saldo').innerText = data.billetera.saldo_fmt;
            document.getElementById('wallet-abonos').innerText = data.billetera.abonos_fmt;
            document.getElementById('wallet-consumos').innerText = data.billetera.consumos_fmt;

            document.getElementById('wallet-saldo').style.color = data.billetera.saldo < 0 ? '#ef4444' : '#0056b3';

            tbody.innerHTML = '';
            if (data.historial.length === 0) {
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:20px; color:#94a3b8;">No hay registros de movimientos en este período.</td></tr>`;
                return;
            }

            data.historial.forEach(item => {
                if (item.tipo_mov === 'ABONO') {
                    // Filas de ABONO (Verdes)
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr style="background-color: #f0fdf4;">
                            <td><span class="folio-tag" style="background:#bbf7d0; color:#166534;">#VALE-${item.numero}</span></td>
                            <td><b>${item.fecha_fmt}</b></td>
                            <td><span style="font-weight:bold; color:#15803d;"><i class="fa-solid fa-circle-arrow-up"></i> ABONO</span></td>
                            <td>
                                <span style="display:block; font-weight:600; color:#166534;">${item.nota}</span>
                                <span style="font-size:11px; color:#64748b;">Registrado por: ${item.identificador}</span>
                            </td>
                            <td><span class="amount-txt" style="color:#166534; font-weight:bold;">+ ${item.monto_fmt}</span></td>
                            <td>
                                <button class="p-btn" style="background:#166534; color:white; border:none; padding:5px 8px; border-radius:4px;" onclick="verValeInterno(${item.numero}, '${item.fecha_fmt}', '${item.identificador}', ${item.monto}, '${item.nota}')" title="Ver Vale de Abono">
                                    <i class="fa-solid fa-file-invoice-dollar"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                } else {
                    // Filas de CONSUMO (Tradicionales)
                    tbody.insertAdjacentHTML('beforeend', `
                        <tr>
                            <td><span class="folio-tag" style="background:#f1f5f9; color:#334155;">#${item.numero}</span></td>
                            <td><b>${item.fecha_fmt}</b></td>
                            <td><span style="font-weight:bold; background:#e2e8f0; padding:4px 8px; border-radius:4px; letter-spacing:1px; color:#334155;"><i class="fa-solid fa-truck"></i> ${item.identificador}</span></td>
                            <td>
                                <span style="display:block;">${item.detalle}</span>
                                <span style="font-size:11px; color:#64748b;">${item.litros_fmt} a $${item.precio_litro}/L</span>
                            </td>
                            <td><span class="amount-txt" style="color:#ef4444;">- ${item.monto_fmt}</span></td>
                            <td>
                                <button class="p-btn p-btn-view" onclick="verDocumento('${item.documento}', ${item.numero})" title="Ver Guía Original">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `);
                }
            });
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="6" style="color:red; text-align:center;">Error cargando datos.</td></tr>`;
    }
}

// Ventana Emergente con Formato de Vale Imprimible
function verValeInterno(numero, fecha, usuario, monto, nota) {
    const montoFmt = "$" + Number(monto).toLocaleString('es-CL');

    Swal.fire({
        title: '',
        html: `
            <div id="print-vale-area" style="font-family: 'Courier New', Courier, monospace; text-align: left; padding: 10px; color: #000; background: #fff; border: 1px dashed #000; width: 290px; margin: 0 auto; font-size: 13px;">
                <div style="text-align: center; font-weight: bold; margin-bottom: 5px;">TABOLANGO SpA</div>
                <div style="text-align: center; font-size: 11px; margin-bottom: 15px;">COMPROBANTE INTERNO DE ABONO</div>
                <hr style="border-top: 1px dashed #000; margin: 5px 0;">
                <div><b>COMPROBANTE:</b> #VALE-${numero}</div>
                <div><b>FECHA:</b> ${fecha}</div>
                <div><b>RESPONSABLE:</b> ${usuario}</div>
                <hr style="border-top: 1px dashed #000; margin: 5px 0;">
                <div style="margin: 10px 0;">
                    <b>DETALLE:</b><br>
                    ${nota}
                </div>
                <hr style="border-top: 1px dashed #000; margin: 5px 0;">
                <div style="text-align: right; font-size: 16px; font-weight: bold; margin-top: 10px;">
                    TOTAL: ${montoFmt}
                </div>
                <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #555;">
                    --- PROCESADO EN ERP TABOLANGO ---
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#166534',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fa-solid fa-print"></i> Imprimir Vale',
        cancelButtonText: 'Cerrar'
    }).then((result) => {
        if (result.isConfirmed) {
            // Clonar contenido e imprimir solo el vale en limpio
            const contenidoHtml = document.getElementById('print-vale-area').innerHTML;
            const ventanaImpresion = window.open('', '_blank', 'width=400,height=600');
            ventanaImpresion.document.write(`
                <html>
                <head><title>Imprimir Vale</title></head>
                <body onload="window.print(); window.close();" style="margin:20px;">
                    <div style="font-family: 'Courier New', monospace; font-size:14px; width:300px;">
                        ${contenidoHtml}
                    </div>
                </body>
                </html>
            `);
            ventanaImpresion.document.close();
        }
    });
}
async function sincronizarCorreosCopec() {
    Swal.fire({
        title: 'Conectando con Gmail...',
        html: 'Buscando nuevas guías de despacho y facturas.<br>Por favor, no cierres esta ventana.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        // Apuntamos al script real de lectura IMAP
        const cronUrl = wpData.themeUrl + '/inc/cron_read_imap_recibidas.php';
        const res = await fetch(cronUrl);
        const textResponse = await res.text();

        // Si el PHP devuelve nuestra bandera de [EXITO]
        if (textResponse.includes('[EXITO]')) {
            let mensajeExito = 'Sincronización completada correctamente.';

            // Intentamos extraer cuántas guías se leyeron usando expresiones regulares desde la respuesta de PHP
            const matchGuias = textResponse.match(/Guías de Combustible leídas: (\d+)/);
            const matchFacturas = textResponse.match(/Facturas leídas: (\d+)/);

            if (matchGuias && matchFacturas) {
                mensajeExito = `Proceso finalizado.<br>• Guías Copec nuevas: <b>${matchGuias[1]}</b><br>• Facturas nuevas: <b>${matchFacturas[1]}</b>`;
            }

            await Swal.fire({
                icon: 'success',
                title: '¡Correos Sincronizados!',
                html: mensajeExito,
                confirmButtonColor: '#ea580c'
            });

            // Recargamos la cartola para ver los movimientos reflejados de inmediato
            cargarDashboardCombustible();
        } else {
            console.error(textResponse);
            Swal.fire('Aviso', 'El proceso terminó, pero la respuesta del servidor no fue la esperada. Revisa la consola.', 'warning');
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Error', 'No se pudo establecer conexión con el lector de correos.', 'error');
    }
}

async function registrarAbonoCombustible() {
    const { value: formValues } = await Swal.fire({
        title: 'Registrar Abono Copec',
        html: `
            <div style="text-align:left; font-size:14px;">
                <label style="font-weight:bold; color:#334155;">Monto a transferir ($):</label>
                <input id="swal-monto" type="number" class="swal2-input" placeholder="Ej: 500000" style="margin-top:5px; margin-bottom:15px; width:90%;">
                
                <label style="font-weight:bold; color:#334155;">Referencia / Nota:</label>
                <input id="swal-nota" type="text" class="swal2-input" placeholder="Ej: Transferencia Banco Estado" style="margin-top:5px; width:90%;">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonColor: '#0f4b29',
        confirmButtonText: 'Guardar Abono',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const monto = document.getElementById('swal-monto').value;
            const nota = document.getElementById('swal-nota').value;
            if (!monto || monto <= 0) {
                Swal.showValidationMessage('Debes ingresar un monto válido.');
            }
            return { monto: monto, nota: nota };
        }
    });

    if (formValues) {
        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading() });
        try {
            let emailActual = (window.APP_USER && window.APP_USER.email) ? window.APP_USER.email : 'Administrador';
            const sep = URL_API_COMBUSTIBLE.includes('?') ? '&' : '?';
            const res = await fetch(`${URL_API_COMBUSTIBLE}${sep}action=abonar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    monto: formValues.monto,
                    nota: formValues.nota,
                    usuario: emailActual
                })
            });

            const data = await res.json();
            if (data.status === 'success') {
                Swal.fire('¡Abono Registrado!', 'El saldo de la billetera se ha actualizado.', 'success');
                cargarDashboardCombustible(); // Recarga la tabla y tarjetas al instante
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo registrar el abono.', 'error');
        }
    }
}