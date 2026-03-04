// --- RUTAS DINÁMICAS (Respeta Local y Producción automáticamente) ---
const URL_LISTAR = window.getApi('api.php');
const URL_SUBIR = window.getApi('upload.php');
const URL_DETALLE_CLIENTE = window.getApi('detalle-cliente.php');
const URL_EDITAR_API = window.getApi('api-edit.php');
const URL_PHP_COMANDA = window.getApi('generar_pdf_comanda.php');
const URL_FACTURACION = window.getApi('procesar_facturacion.php');
const URL_PRECIOS = window.getApi('form.php');

window.currentOrders = {};
let listaProductosMaster = [];
let listaProductosPlana = [];
let filaActivaEditor = null;
let nivelVisual = 0;
let seleccionCat = null;
let seleccionVar = null;
let seleccionCal = null;
let idPedidoEnEdicion = null;
let clienteIdEditor = null;
let nombreCategoriaClienteEditor = "";

const formatCLP = (v) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(v);

function obtenerEmailLimpio() {
    const bridge = document.getElementById('session-email-bridge');
    if (!bridge) return "";
    let emailMatch = (bridge.textContent || bridge.innerText).match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
    return emailMatch ? emailMatch[0].toLowerCase().trim() : "";
}

function moverModalesAlBody() {
    const contenedorModales = document.getElementById('contenedor-modales-tabolango');
    const modalComanda = document.getElementById('modal-comanda');
    if (contenedorModales && contenedorModales.parentNode !== document.body) document.body.appendChild(contenedorModales);
    if (modalComanda && modalComanda.parentNode !== document.body) document.body.appendChild(modalComanda);
}

async function cargarDatosGlobales() {
    try {
        const sep = URL_LISTAR.includes('?') ? '&' : '?';
        const res = await fetch(`${URL_LISTAR}${sep}action=get_products`);
        const rawData = await res.json();
        listaProductosPlana = rawData;
        procesarProductosVisuales(rawData);
    } catch (e) { console.error("Error cargando productos", e); }
}

function procesarProductosVisuales(productosRaw) {
    const grupos = {};
    productosRaw.forEach(curr => {
        const prodName = curr.producto || "Otros";
        const valVariedad = curr.variedad || curr.Variedad || "";
        const variedad = valVariedad.trim();
        const calibre = curr.calibre || "S/C";
        const formato = curr.formato || "Unidad";

        if (!grupos[prodName]) grupos[prodName] = { nombre: prodName, icono: curr.icono || '📦', color: curr.color_diferenciador || '#E98C00', variedades: {} };
        if (!grupos[prodName].variedades[variedad]) grupos[prodName].variedades[variedad] = { nombreVar: variedad, calibres: {} };
        if (!grupos[prodName].variedades[variedad].calibres[calibre]) grupos[grupos[prodName].nombre].variedades[variedad].calibres[calibre] = {};

        grupos[prodName].variedades[variedad].calibres[calibre][formato] = curr;
    });
    listaProductosMaster = Object.values(grupos);
}

async function loadOrders() {
    try {
        const userEmail = obtenerEmailLimpio();
        const sep = URL_LISTAR.includes('?') ? '&' : '?';
        const response = await fetch(`${URL_LISTAR}${sep}action=get_active_orders&wp_user=${encodeURIComponent(userEmail)}`);
        const data = await response.json();
        const grid = document.getElementById('orders-grid');

        if (!Array.isArray(data) || data.length === 0) {
            grid.classList.add('is-empty');
            grid.innerHTML = `<div class="empty-state-container"><span>📦</span><p>No tienes pedidos activos en este momento.</p></div>`;
            return;
        } else { grid.classList.remove('is-empty'); }

        const isAdmin = window.APP_USER.isAdmin === true;
        const isEditor = window.APP_USER.isEditor === true;
        const canEdit = (isAdmin || isEditor);

        const ordersGrouped = data.reduce((acc, current) => {
            const id = current.id_pedido;
            if (!acc[id]) acc[id] = { ...current, products: [], total_acumulado: 0 };

            const pUnitario = parseFloat(current.precio_unitario || 0);
            const cant = parseFloat(current.cantidad || 0);
            const pTotal = parseFloat(current.total_venta || (pUnitario * cant) || 0);

            acc[id].products.push({
                nombre: current.producto, variedad: current.variedad || current.Variedad || '',
                calibre: current.calibre || '-', formato: current.formato || '-', cantidad: cant,
                unidad: current.unidad_real || current.unidad || 'Kg', color: current.color_diferenciador || '#0F4B29',
                precio_u: pUnitario, precio_t: pTotal
            });
            acc[id].total_acumulado += pTotal;
            return acc;
        }, {});

        window.currentOrders = ordersGrouped;
        grid.innerHTML = '';

        Object.values(ordersGrouped).forEach(order => {
            const statusClass = (order.estado || 'Confirmado').replace(/\s+/g, '-');
            const f = (order.fecha_despacho || '').split('-');
            const fechaFormateada = f.length === 3 ? `${f[2]}/${f[1]}/${f[0]}` : order.fecha_despacho;

            // --- LÓGICA VISUAL BLINDADA (DETECTA SIMULACIONES POR URL) ---
            let bloqueGuia = '';
            let escudoGuia = '';

            let isGuiaSimulada = (order.url_guia && order.url_guia.includes('_SIM')) || String(order.numero_guia).includes('S') || order.numero_guia >= 900000;
            let isFacturaSimulada = (order.url_factura && order.url_factura.includes('_SIM')) || String(order.numero_factura).includes('S');

            if (order.url_guia) {
                let txtBoton = isGuiaSimulada ? "🚚 VER GUÍA (BORRADOR)" : `🚚 VER GUÍA N° ${order.numero_guia}`;
                bloqueGuia = `<div class="upload-zone-small" style="border-style: solid; border-color: #3498db; color:#3498db; background:#f0f8ff;" onclick="abrirModal('${order.url_guia}')">${txtBoton}</div>${isAdmin ? `<button class="btn-x-delete" onclick="eliminarDoc('${order.id_pedido}', 'guia')">×</button>` : ''}`;
                escudoGuia = `<span class="factura-badge" style="background:#ebf5fb; color:#2980b9; border-color:#2980b9;">G: ${isGuiaSimulada ? 'SIM' : order.numero_guia}</span>`;
            } else if (canEdit) {
                bloqueGuia = `<button onclick="event.stopPropagation(); abrirVistaPrevia('guia', '${order.id_pedido}')" style="width:100%; padding:10px; background:#E98C00; color:white; border:none; border-radius:8px; font-weight:800; font-size:11px; cursor:pointer; margin-bottom:5px; box-shadow:0 3px 0 #d35400;">🚚 GENERAR GUÍA DE DESPACHO</button>${isAdmin ? `<div style="text-align:center; font-size:9px; color:#999; margin-bottom:5px; cursor:pointer; text-decoration:underline;" onclick="document.getElementById('file-guia-${order.id_pedido}').click()">o subir manual</div>` : ''}<input type="file" id="file-guia-${order.id_pedido}" accept="image/*,application/pdf" style="display:none" onchange="subirGuia(this, '${order.id_pedido}')">`;
            }

            let escudoFactura = '';
            let bloqueFacturaAdmin = '';

            if (order.url_factura) {
                let txtBotonFact = isFacturaSimulada ? "📄 VER FACTURA (BORRADOR)" : `📄 VER FACTURA N° ${order.numero_factura}`;
                bloqueFacturaAdmin = `<div class="doc-container"><div class="upload-zone-small" style="border-style: solid; border-color:#E98C00; color:#E98C00;" onclick="abrirModal('${order.url_factura}')">${txtBotonFact}</div>${isAdmin ? `<button class="btn-x-delete" onclick="eliminarDoc('${order.id_pedido}', 'factura')">×</button>` : ''}</div>`;
                escudoFactura = `<span class="factura-badge" style="background:#e8f6f3; color:#0F4B29; border-color:#0F4B29;">F: ${isFacturaSimulada ? 'SIM' : order.numero_factura}</span>`;
            } else if (isAdmin) {
                bloqueFacturaAdmin = `<div class="admin-fields"><div class="admin-input-row"><input type="number" id="num-fact-${order.id_pedido}" placeholder="N° Factura" value=""><button class="btn-pdf-upload" id="btn-pdf-label-${order.id_pedido}" onclick="document.getElementById('file-pdf-${order.id_pedido}').click()">📎 PDF</button><input type="file" id="file-pdf-${order.id_pedido}" accept="application/pdf" style="display:none" onchange="handlePdfSelect(this, '${order.id_pedido}')"></div><button class="btn-save-admin" id="btn-save-${order.id_pedido}" onclick="event.stopPropagation(); guardarAdmin('${order.id_pedido}')">GUARDAR</button></div>`;
            }

            grid.innerHTML += `
                <div class="order-card" id="card-${order.id_pedido}">
                    <div class="card-inner">
                        <div class="card-front">
                            <div class="status-bar status-${statusClass}"></div>
                            <div class="card-main-content" onclick="openQR('${order.id_pedido}', '${order.qr_token}')">
                                <div class="order-header">
                                    <div>
                                        <span class="creado-por-label">${order.creado_por || 'Sistema'}</span>
                                        <span class="order-id">ID: ${order.id_pedido}</span>
                                        <div class="fecha-despacho-label">📅 ${fechaFormateada}</div>
                                    </div>
                                    <div class="status-group">
                                        <span class="order-status-label status-${statusClass}">${order.estado}</span>
                                        ${escudoFactura}
                                        ${escudoGuia}
                                    </div>
                                </div>
                                <div class="order-body">
                                    <h3>${order.cliente}</h3>
                                    <div class="products-list" onclick="event.stopPropagation(); verDetalleLista('${order.id_pedido}')" style="cursor:zoom-in;" onwheel="event.stopPropagation()" ontouchstart="event.stopPropagation()">
                                        ${order.products.map(p => `
                                            <div class="product-item" style="border-left: 6px solid ${p.color};">
                                                <div style="display:flex; align-items: center; flex:1; gap: 6px;">
                                                    <div style="display:flex; flex-direction:column; flex:1; justify-content:center;">
                                                        <span class="product-name" style="font-weight:700; line-height:1.1;">${p.nombre}</span>
                                                        ${p.variedad ? `<span style="font-size:10px; color:#666; font-style:italic;">${p.variedad}</span>` : ''}
                                                    </div>
                                                    <div style="display:flex; flex-direction:column; align-items:flex-end; margin-right:5px;">
                                                        <span style="font-size:10px; color:#666; white-space: nowrap;">${p.calibre} <span style="color:#E98C00; font-weight:bold;">|</span> ${p.formato}</span>
                                                    </div>
                                                </div>
                                                <span class="product-qty" style="margin-left: 10px;">${Math.round(p.cantidad)} ${p.unidad}</span>
                                            </div>`).join('')}
                                    </div>
                                </div>
                                <div class="order-footer">
                                    <span class="order-total">${formatCLP(order.total_acumulado)} <small style="font-size:11px; opacity:0.7;">+ IVA</small></span>
                                </div>
                            </div>
                            <div class="trigger-flip-bar" onclick="flipCard(event, '${order.id_pedido}')"><span>Documentos / Gestión</span></div>
                        </div>
                        <div class="card-back">
                            <div style="flex:1; overflow-y:auto;">
                                <h4 style="margin:0 0 12px 0; font-size:14px; color:#333; text-transform:uppercase; font-weight:800; border-bottom: 1px solid #ddd; padding-bottom:5px;">Gestión de Pedido</h4>
                                <div class="doc-container">${bloqueGuia}</div>
                                ${bloqueFacturaAdmin}
                                ${canEdit ? `<button class="btn-edit-order" onclick="event.stopPropagation(); abrirEditor('${order.id_pedido}')">✏️ EDITAR PEDIDO</button>` : ''}
                            </div>
                            <div class="trigger-flip-bar back-btn" onclick="flipCard(event, '${order.id_pedido}')"><span>Volver al Pedido</span></div>
                        </div>
                    </div>
                </div>`;
        });
    } catch (err) { console.error(err); }
}

// --- FUNCIONES EDITOR DE PEDIDOS ---
async function abrirEditor(idPedido) {
    const pedido = window.currentOrders[idPedido];
    if (!pedido) return;

    idPedidoEnEdicion = idPedido;
    clienteIdEditor = pedido.id_interno_cliente;
    nombreCategoriaClienteEditor = "";

    try {
        const sep = URL_DETALLE_CLIENTE.includes('?') ? '&' : '?';
        const res = await fetch(`${URL_DETALLE_CLIENTE}${sep}id=${encodeURIComponent(clienteIdEditor)}`);
        const data = await res.json();
        if (data && data.perfil && data.perfil.nombre_categoria_cliente) nombreCategoriaClienteEditor = data.perfil.nombre_categoria_cliente;
    } catch (e) { }

    document.getElementById('edit-subtitle').innerText = `${idPedido} • ${pedido.cliente}`;
    const inputFecha = document.getElementById('editor-fecha-despacho');
    if (inputFecha) inputFecha.value = pedido.fecha_despacho;

    const contenedor = document.getElementById('editor-productos-container');
    contenedor.innerHTML = '';

    pedido.products.forEach(prod => {
        let idReal = ''; let color = '#ccc';
        const match = listaProductosPlana.find(p => p.producto === prod.nombre && p.formato === prod.formato && (p.calibre || '-') === prod.calibre && (p.variedad || p.Variedad || '') === prod.variedad);
        if (match) { idReal = match.id_producto; color = match.color_diferenciador; }

        let nombreDisplay = prod.nombre + (prod.variedad ? ` (${prod.variedad})` : "");
        agregarFilaEditor(idReal, prod.cantidad, nombreDisplay, `${prod.calibre} - ${prod.formato}`, color, prod.precio_u);
    });

    document.getElementById('modal-editar-pedido').style.display = 'flex';
}

function cerrarEditor() { document.getElementById('modal-editar-pedido').style.display = 'none'; }

function agregarFilaEditor(id = '', qty = 1, nombre = 'Toca para elegir...', detalle = '', color = '#eee', precio = 0) {
    const contenedor = document.getElementById('editor-productos-container');
    const rowId = Date.now() + Math.random().toString(36).substr(2, 9);
    const div = document.createElement('div');
    div.className = 'm-fila'; div.id = `row-${rowId}`; div.style.borderLeft = `5px solid ${color}`; div.setAttribute('data-price', precio);
    div.innerHTML = `<div class="display-trigger" onclick="abrirSelectorEnEditor('${rowId}')"><div style="flex:1"><div style="font-weight:800; color:#333; line-height:1.2" class="p-name">${nombre}</div><div style="font-size:11px; color:#888; margin-top:2px;" class="p-detail">${detalle}</div></div></div><input type="hidden" class="p-id-hidden" value="${id}"><div class="m-fila-controles"><input type="number" class="p-qty" value="${qty}" min="0.1" step="any" inputmode="decimal"><button type="button" class="m-btn-remove" onclick="this.closest('.m-fila').remove()">✕</button></div>`;
    contenedor.appendChild(div);
}

function abrirSelectorEnEditor(rowId) { filaActivaEditor = rowId; nivelVisual = 0; renderizarGridVisual(); document.getElementById('modal-selector-visual').style.display = 'flex'; }

async function renderizarGridVisual(filtro = "") {
    const grid = document.getElementById('grid-visual-productos');
    const titulo = document.getElementById('titulo-selector-visual');
    grid.innerHTML = ""; const txt = filtro.toLowerCase();

    if (nivelVisual === 0) {
        titulo.innerText = "PRODUCTOS";
        listaProductosMaster.forEach(g => {
            if (g.nombre.toLowerCase().includes(txt)) {
                grid.innerHTML += `<div class="grid-item" onclick="seleccionarNivelCategoriaVisual('${g.nombre.replace(/'/g, "\\'")}')"><div class="grid-media" style="font-size:35px;">${g.icono}</div><span style="font-weight:700; font-size:13px; color:#333 !important;">${g.nombre}</span><div class="card-color-footer" style="background:${g.color}"></div></div>`;
            }
        });
    } else if (nivelVisual === 1) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        titulo.innerText = "Variedad";
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=0;renderizarGridVisual()">⬅ VOLVER A PRODUCTOS</div>`;
        Object.keys(grupo.variedades).forEach(vKey => {
            const labelVar = vKey === "" ? "Estándar" : vKey;
            grid.innerHTML += `<div class="grid-item" onclick="nivelVisual=2;seleccionVar='${vKey}';renderizarGridVisual()"><div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${labelVar.substring(0, 3).toUpperCase()}</b></div><span style="font-size:13px; color:#333 !important;">${labelVar}</span><div class="card-color-footer" style="background:${grupo.color}"></div></div>`;
        });
    } else if (nivelVisual === 2) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        titulo.innerText = seleccionVar ? `${seleccionCat} (${seleccionVar})` : seleccionCat;
        const tieneVariedad = Object.keys(grupo.variedades).length > 1 || (Object.keys(grupo.variedades)[0] !== "");
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=${tieneVariedad ? 1 : 0};renderizarGridVisual()">⬅ VOLVER A ${tieneVariedad ? "VARIEDADES" : "PRODUCTOS"}</div>`;
        Object.keys(grupo.variedades[seleccionVar].calibres).forEach(cal => {
            grid.innerHTML += `<div class="grid-item" onclick="nivelVisual=3;seleccionCal='${cal}';renderizarGridVisual()"><div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${cal}</b></div><span style="font-size:11px; color:#666 !important;">CALIBRE</span><div class="card-color-footer" style="background:${grupo.color}"></div></div>`;
        });
    } else if (nivelVisual === 3) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        const formatos = grupo.variedades[seleccionVar].calibres[seleccionCal];
        titulo.innerText = `${seleccionCal} - ${seleccionCat}`;
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=2;renderizarGridVisual()">⬅ VOLVER A CALIBRES</div><div id="alerta-precios-editor" class="alerta-precio-cat"></div><div id="load-msg" style="grid-column:1/-1; text-align:center; padding:10px; font-size:11px;">Cargando precios...</div>`;
        let flagAlertaActivada = false;

        for (const ftoKey of Object.keys(formatos)) {
            const p = formatos[ftoKey];
            let precioFinal = parseFloat(p.precio_actual || p.precio_por_kilo || 0); let esEspecial = false;
            if (clienteIdEditor) {
                try {
                    const sep = URL_PRECIOS.includes('?') ? '&' : '?';
                    const resp = await fetch(`${URL_PRECIOS}${sep}action=get_price_by_client&cliente=${clienteIdEditor}&producto=${p.id_producto}`);
                    const data = await resp.json();
                    precioFinal = parseFloat(data.precio); esEspecial = (data.origen === "categoria");
                } catch (e) { }
            }
            if (esEspecial && !flagAlertaActivada) {
                const alertaDiv = document.getElementById('alerta-precios-editor');
                if (alertaDiv) { alertaDiv.style.display = 'block'; alertaDiv.innerHTML = `🔔 SE APLICAN PRECIOS ${nombreCategoriaClienteEditor ? nombreCategoriaClienteEditor.toUpperCase() : 'ESPECIALES'}`; flagAlertaActivada = true; }
            }
            const nombreSafe = (p.producto + (seleccionVar ? ` (${seleccionVar})` : '')).replace(/'/g, "\\'");
            grid.innerHTML += `<div class="grid-item" onclick="finalizarSeleccionEditor('${p.id_producto}', '${nombreSafe}', '${p.calibre}', '${p.formato}', '${grupo.color}', ${precioFinal})"><div class="grid-media"><b style="font-size:16px; color:#333 !important;">${p.formato}</b></div><span style="font-size:14px; font-weight:900; color:#333 !important;">${precioFinal > 0 ? formatCLP(precioFinal) : 'Consultar'}</span><div class="card-color-footer" style="background:${esEspecial ? '#E98C00' : grupo.color}"></div></div>`;
        }
        document.getElementById('load-msg')?.remove();
    }
}

function seleccionarNivelCategoriaVisual(nombreCat) {
    seleccionCat = nombreCat;
    const grupo = listaProductosMaster.find(g => g.nombre === nombreCat);
    const varKeys = Object.keys(grupo.variedades);
    if (varKeys.length === 1 && varKeys[0] === "") { seleccionVar = ""; nivelVisual = 2; } else { nivelVisual = 1; }
    renderizarGridVisual();
}

function filtrarGridVisual(val) {
    Array.from(document.getElementById('grid-visual-productos').getElementsByClassName('grid-item')).forEach(item => {
        if (!item.classList.contains('btn-volver-modal')) item.style.display = item.innerText.toLowerCase().includes(val.toLowerCase()) ? '' : 'none';
    });
}

function finalizarSeleccionEditor(id, nombre, calibre, formato, color, precio) {
    const fila = document.getElementById(`row-${filaActivaEditor}`);
    if (fila) {
        fila.querySelector('.p-id-hidden').value = id; fila.querySelector('.p-name').innerText = nombre; fila.querySelector('.p-detail').innerText = `${calibre} - ${formato}`;
        fila.style.borderLeft = `5px solid ${color}`; fila.setAttribute('data-price', precio);
    }
    document.getElementById('modal-selector-visual').style.display = 'none';
}

async function guardarEdicionAPI() {
    if (!idPedidoEnEdicion) return;
    const filas = document.querySelectorAll('#editor-productos-container .m-fila');
    let ids = [], cants = [], precios = [];

    filas.forEach(fila => {
        const id = fila.querySelector('.p-id-hidden').value; const cant = fila.querySelector('.p-qty').value; const precio = fila.getAttribute('data-price') || 0;
        if (id && cant > 0) { ids.push(id); cants.push(cant); precios.push(precio); }
    });

    if (ids.length === 0) { Swal.fire('Error', 'El pedido no puede quedar vacío.', 'error'); return; }

    const btn = document.getElementById('btn-guardar-edicion');
    const txtOriginal = btn.innerText; btn.innerText = "GUARDANDO..."; btn.disabled = true;

    const formData = new FormData();
    formData.append('wp_user', obtenerEmailLimpio()); formData.append('action', 'update_order_items'); formData.append('id_pedido', idPedidoEnEdicion);
    formData.append('producto', ids.join(' | ')); formData.append('cantidad', cants.join(' | ')); formData.append('precios_venta', precios.join(' | '));
    const inputFecha = document.getElementById('editor-fecha-despacho');
    if (inputFecha && inputFecha.value) formData.append('fecha_despacho', inputFecha.value);

    try {
        const res = await fetch(URL_EDITAR_API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') { cerrarEditor(); loadOrders(); Swal.fire('Éxito', 'Pedido actualizado correctamente', 'success'); }
        else { Swal.fire('Error', data.message, 'error'); }
    } catch (e) { Swal.fire('Error', 'Problema de conexión', 'error'); }
    finally { btn.innerText = txtOriginal; btn.disabled = false; }
}

async function eliminarPedidoAPI() {
    if (!idPedidoEnEdicion) return;

    const result = await Swal.fire({
        title: '¿Eliminar pedido?', text: `Se eliminará permanentemente el pedido ${idPedidoEnEdicion}`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6', confirmButtonText: 'Sí, eliminar'
    });

    if (!result.isConfirmed) return;

    const formData = new FormData();
    formData.append('action', 'delete_order'); formData.append('id_pedido', idPedidoEnEdicion); formData.append('wp_user', obtenerEmailLimpio());

    try {
        const res = await fetch(URL_EDITAR_API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') { cerrarEditor(); loadOrders(); Swal.fire('Eliminado', 'El pedido ha sido borrado', 'success'); }
        else { Swal.fire('Error', data.message, 'error'); }
    } catch (e) { Swal.fire('Error', 'Problema de conexión', 'error'); }
}

function abrirModal(url) {
    const modal = document.getElementById('modal-factura');
    const container = document.getElementById('modal-body-content');
    container.innerHTML = url.toLowerCase().endsWith('.pdf') ? `<iframe src="${url}" style="width:100%; height:70vh; border:none;"></iframe>` : `<img src="${url}" style="max-width:100%;">`;
    modal.style.display = 'flex';
}
function cerrarModal() { document.getElementById('modal-factura').style.display = 'none'; }
function flipCard(event, id) { if (event) event.stopPropagation(); document.getElementById(`card-${id}`).classList.toggle('is-flipped'); }

function handlePdfSelect(input, id) {
    const btn = document.getElementById(`btn-pdf-label-${id}`);
    if (input.files.length > 0) { btn.innerText = "✅ PDF LISTO"; btn.classList.add('pdf-ready'); }
    else { btn.innerText = "📎 PDF"; btn.classList.remove('pdf-ready'); }
}

async function verDetalleLista(idPedido) {
    const pedido = window.currentOrders[idPedido];
    if (!pedido) return;
    const modal = document.getElementById('modal-detalle-pedido');
    const body = document.getElementById('detalle-pedido-body');
    const fmt = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 });

    body.innerHTML = '<div style="text-align:center; padding:50px; color:#0F4B29; font-weight:bold;">Cargando información...</div>';
    modal.style.display = 'flex';

    let rutFinal = "No disponible";
    try {
        const sep = URL_DETALLE_CLIENTE.includes('?') ? '&' : '?';
        const response = await fetch(`${URL_DETALLE_CLIENTE}${sep}id=${pedido.id_interno_cliente}`);
        const data = await response.json();
        if (data && data.perfil) rutFinal = data.perfil.rut_cliente || data.perfil.id_cliente || "Pendiente";
    } catch (e) { }

    let totalNeto = 0;
    const productosHTML = pedido.products.map(p => {
        const sub = p.precio_t || (p.precio_u * p.cantidad); totalNeto += sub;
        return `<div class="item-row"><div class="item-info"><span class="item-title">${p.nombre} ${p.variedad ? `<span style="font-weight:400; color:#666; font-size:12px; margin-left:5px;">(${p.variedad})</span>` : ''}</span><div class="item-meta"><span class="meta-tag">Cant:</span> <span class="meta-val">${Math.round(p.cantidad)} ${p.unidad}</span><span style="color:#ddd">|</span><span class="unit-price-tag">Unit: ${fmt.format(p.precio_u)}</span></div><div style="font-size:11px; color:#999; margin-top:4px;">Calibre: ${p.calibre} • Formato: ${p.formato}</div></div><div class="item-price-total">${fmt.format(sub)}</div></div>`;
    }).join('');

    const obsTexto = pedido.observacion || pedido.observaciones || '';
    const observacionBlock = obsTexto ? `<div style="padding: 15px 25px; background: #fffbf0; border-bottom: 1px solid #f0f0f0; border-top: 1px solid #f0f0f0;"><div style="font-size: 11px; font-weight: 800; color: #E98C00; text-transform: uppercase; margin-bottom: 5px;">📝 Observaciones del Pedido</div><div style="font-size: 13px; color: #555; line-style: italic;">"${obsTexto}"</div></div>` : '';
    const iva = Math.round(totalNeto * 0.19); const total = totalNeto + iva;

    body.innerHTML = `<div class="detalle-header"><h3>Detalle del Pedido</h3><div style="color:rgba(255,255,255,0.8); font-size:14px; font-weight:600; margin-top:4px;">${pedido.cliente}</div><div class="rut-container" onclick="copiarRutLimpio('${rutFinal}')"><span class="rut-label">RUT:</span><span class="rut-value">${rutFinal}</span></div></div><div class="detalle-body" style="max-height: 40vh;">${productosHTML}</div>${observacionBlock}<div class="detalle-footer"><div class="resumen-row"><span>Subtotal Neto</span><span style="font-weight:700; color:#1a1a1a;">${fmt.format(totalNeto)}</span></div><div class="resumen-row"><span>IVA (19%)</span><span style="font-weight:700; color:#1a1a1a;">${fmt.format(iva)}</span></div><div class="resumen-total"><span class="label">Total Final</span><span class="value">${fmt.format(total)}</span></div><button onclick="cerrarDetalle()" class="btn-cerrar-block">Cerrar Detalle</button></div>`;
}
function cerrarDetalle() { document.getElementById('modal-detalle-pedido').style.display = 'none'; }
function copiarRutLimpio(rutOriginal) {
    let rutLimpio = rutOriginal.split('-')[0].replace(/\./g, '').trim();
    navigator.clipboard.writeText(rutLimpio).then(() => {
        Swal.fire({ toast: true, position: 'bottom', icon: 'success', title: 'RUT copiado: ' + rutLimpio, showConfirmButton: false, timer: 2000 });
    });
}
async function eliminarDoc(id, tipo) {
    const result = await Swal.fire({ title: '¿Borrar documento?', text: `Se eliminará la ${tipo} de este pedido.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, borrar' });
    if (!result.isConfirmed) return;
    const formData = new FormData(); formData.append('action', 'delete_document'); formData.append('id_pedido', id); formData.append('tipo', tipo);
    try { const res = await fetch(URL_SUBIR, { method: 'POST', body: formData }); const data = await res.json(); if (data.status === 'success') loadOrders(); } catch (e) { }
}
async function subirGuia(input, id) {
    if (!input.files[0]) return;
    const formData = new FormData(); formData.append('foto_guia', input.files[0]); formData.append('id_pedido', id); formData.append('action', 'upload_guia_despacho');
    try { const res = await fetch(URL_SUBIR, { method: 'POST', body: formData }); const data = await res.json(); if (data.status === 'success') { Swal.fire('Éxito', 'Documento subido', 'success'); loadOrders(); } } catch (e) { }
}

function openQR(idPedido, token) {
    const existing = document.getElementById('dynamic-qr-modal');
    if (existing) existing.remove();

    // 🔥 CORECCIÓN AQUÍ: Detecta la URL base dinámicamente (Local o Producción)
    const baseUrl = window.location.origin;
    const urlValidacion = `${baseUrl}/validar-entrega?token=${token}`;

    const overlay = document.createElement('div');
    overlay.id = 'dynamic-qr-modal';
    overlay.className = 'qr-modal-overlay';
    overlay.onclick = closeQR;

    overlay.innerHTML = `
        <div class="qr-modal-content" onclick="event.stopPropagation()">
            <span class="qr-close-btn" onclick="closeQR()">✕</span>
            <div style="margin-bottom: 20px;">
                <h3 style="color:#333; margin: 0; font-size:18px; font-weight: 800;">VALIDACIÓN DE ENTREGA</h3>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #888; font-weight: bold;">PEDIDO ID: ${idPedido}</p>
            </div>
            <div id="qrcode-target" style="background: white; padding: 10px; border-radius: 15px; display: inline-block; border: 1px solid #eee;"></div>
            <div style="background: #fdf2e2; padding: 12px; border-radius: 12px; border: 1px solid #f9e1bc; margin-top: 20px;">
                <p style="margin: 0; font-size: 11px; color: #E98C00; font-weight: bold; line-height: 1.4;">Escanee el código o use el botón inferior para validar la recepción.</p>
            </div>
            <button class="btn-qr-cerrar" onclick="window.location.href='${urlValidacion}'">IR A VALIDAR</button>
            <p style="font-size:9px; color:#ccc; margin-top:15px; letter-spacing: 1px;">TOKEN: ${token}</p>
        </div>`;

    document.body.appendChild(overlay);

    setTimeout(() => {
        new QRCode(document.getElementById('qrcode-target'), {
            text: urlValidacion,
            width: 200,
            height: 200,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    }, 50);
}
function closeQR() { const modal = document.getElementById('dynamic-qr-modal'); if (modal) modal.remove(); }

// --- MODAL COMANDA LOGICA VISTA ---
function abrirModalComanda() {
    const modal = document.getElementById('modal-comanda');
    if (modal.parentNode !== document.body) document.body.appendChild(modal);
    const hoy = new Date().toISOString().split('T')[0];
    if (!document.getElementById('fecha-comanda').value) document.getElementById('fecha-comanda').value = hoy;
    modal.style.display = 'flex';
}
function cerrarModalComanda() { document.getElementById('modal-comanda').style.display = 'none'; }
function generarListaComandaVisual() {
    const fechaInput = document.getElementById('fecha-comanda').value;
    if (!fechaInput) return Swal.fire('Error', "Seleccione una fecha", 'warning');
    const contenedor = document.getElementById('lista-comanda-body');
    contenedor.innerHTML = '<div style="text-align:center; padding:20px;">Calculando insumos...</div>';
    const resumen = {}; let totalPedidos = 0;

    Object.values(window.currentOrders).forEach(pedido => {
        if (pedido.fecha_despacho === fechaInput && pedido.estado !== 'Entregado') {
            totalPedidos++;
            pedido.products.forEach(p => {
                const llave = `${p.nombre}|${p.variedad || ''}|${p.calibre}|${p.formato}`;
                if (!resumen[llave]) resumen[llave] = { nombre: p.nombre, variedad: p.variedad || '', calibre: p.calibre, formato: p.formato, unidad: p.unidad, cantidad: 0, id_unico: llave.replace(/[^a-zA-Z0-9]/g, '') };
                resumen[llave].cantidad += parseFloat(p.cantidad);
            });
        }
    });

    contenedor.innerHTML = '';
    if (totalPedidos === 0) { contenedor.innerHTML = `<div style="text-align: center; padding: 40px; color: #666;">No hay pedidos activos para el <b>${fechaInput}</b></div>`; return; }

    const lista = Object.values(resumen).sort((a, b) => a.nombre.localeCompare(b.nombre));
    const headerHTML = `<div style="padding: 0 10px 10px; font-size: 12px; color: #0F4B29; font-weight: bold; border-bottom: 2px solid #0F4B29; margin-bottom: 10px; display:flex; justify-content:space-between;"><span>RESUMEN: ${totalPedidos} PEDIDOS</span><span>${fechaInput}</span></div>`;
    let htmlItems = '';

    lista.forEach(item => {
        const storageKey = `cmd_${fechaInput}_${item.id_unico}`;
        const isChecked = localStorage.getItem(storageKey) === 'true';
        htmlItems += `<div class="comanda-item ${isChecked ? 'checked' : ''}" onclick="toggleComandaCheck(this, '${storageKey}')"><div class="chk-circle"></div><div class="comanda-info"><span class="comanda-name">${item.nombre} ${item.variedad ? `<span style="font-weight:400; color:#E98C00;">(${item.variedad})</span>` : ''}</span><div class="comanda-meta">${item.calibre} • ${item.formato}</div></div><div class="comanda-qty">${parseFloat(item.cantidad.toFixed(2))} ${item.unidad}</div></div>`;
    });
    contenedor.innerHTML = headerHTML + htmlItems;
}
function toggleComandaCheck(el, key) {
    el.classList.toggle('checked');
    if (el.classList.contains('checked')) localStorage.setItem(key, 'true'); else localStorage.removeItem(key);
}
async function limpiarTicks() {
    const result = await Swal.fire({ title: '¿Limpiar Ticks?', text: "Se desmarcarán todos los insumos.", icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, limpiar' });
    if (!result.isConfirmed) return;
    const fechaInput = document.getElementById('fecha-comanda').value;
    Object.keys(localStorage).forEach(k => { if (k.startsWith(`cmd_${fechaInput}`)) localStorage.removeItem(k); });
    generarListaComandaVisual();
}
function llamarPHPComanda() {
    const fecha = document.getElementById('fecha-comanda').value;
    if (!fecha) return Swal.fire('Cuidado', "Seleccione una fecha primero", 'warning');
    const sep = URL_PHP_COMANDA.includes('?') ? '&' : '?';
    window.open(`${URL_PHP_COMANDA}${sep}fecha=${fecha}`, '_blank');
}

// --- GENERAR DOCUMENTOS ---
async function generarDocumento(tipo, idPedido) {
    const nombreDoc = tipo === 'guia' ? 'Guía de Despacho' : 'Factura Electrónica';
    const btn = document.getElementById(`btn-confirmar-emision`);
    const txtOrig = btn ? btn.innerText : '';
    if (btn) { btn.disabled = true; btn.innerText = "GENERANDO..."; }
    try {
        const res = await fetch(URL_FACTURACION, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, tipo_doc: tipo })
        });
        const data = await res.json();
        if (data.status === 'success') {
            Swal.fire('¡Documento Listo!', data.message, 'success');
            loadOrders();
        } else { Swal.fire('Error', data.message, 'error'); }
    } catch (e) { Swal.fire('Error', "Error de conexión con el servidor.", 'error'); }
    finally { if (btn) { btn.disabled = false; btn.innerText = txtOrig; } }
}

async function abrirVistaPrevia(tipo, idPedido) {
    const pedido = window.currentOrders[idPedido];
    if (!pedido) return Swal.fire('Error', "No se encontraron los datos del pedido", 'error');

    document.getElementById('vp-tipo-doc').innerText = (tipo === 'guia') ? 'GUÍA DE DESPACHO (52)' : 'FACTURA ELECTRÓNICA (33)';
    const container = document.getElementById('vp-items-container');
    container.innerHTML = '';

    let rs = pedido.cliente; let rut = "Cargando...";
    try {
        const sep = URL_DETALLE_CLIENTE.includes('?') ? '&' : '?';
        const resp = await fetch(`${URL_DETALLE_CLIENTE}${sep}id=${pedido.id_interno_cliente}`);
        const d = await resp.json();
        if (d && d.perfil) {
            if (d.perfil.razon_social) rs = d.perfil.razon_social;
            rut = d.perfil.rut_cliente || "Sin RUT";
        }
    } catch (e) { }

    document.getElementById('vp-cliente').innerHTML = `<div style="font-size: 16px; font-weight: 900; color: #2c3e50;">${pedido.cliente}</div><div style="font-size: 11px; color: #666; border-top: 1px solid #eee; padding-top: 2px;">R.S: ${rs} | RUT: ${rut}</div>`;
    document.getElementById('vp-rut').style.display = 'none';

    let suma = 0; const fmt = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 });
    pedido.products.forEach(p => {
        const tot = Math.round(p.precio_u * p.cantidad); suma += tot;
        let det = `<strong>${p.nombre}</strong>`;
        if (p.variedad) det += ` <span style="color:#666; font-size:11px;">(${p.variedad})</span>`;
        if (p.calibre) det += ` <span style="color:#666; font-size:11px;">${p.calibre}</span>`;

        container.innerHTML += `<tr style="border-bottom: 1px solid #f0f0f0;"><td style="padding: 8px 0;"><div style="font-size: 12px; color: #333;">${det}</div><div style="font-size: 10px; color: #999;">Unit: ${fmt.format(p.precio_u)}</div></td><td style="text-align: right; font-weight: 700; font-size: 12px; color: #555;">${p.cantidad}</td><td style="text-align: right; font-weight: 700; font-size: 12px; color: #333;">${fmt.format(tot)}</td></tr>`;
    });

    const iva = Math.round(suma * 0.19);
    document.getElementById('vp-neto').innerText = fmt.format(suma);
    document.getElementById('vp-iva').innerText = fmt.format(iva);
    document.getElementById('vp-total').innerText = fmt.format(suma + iva);

    document.getElementById('btn-confirmar-emision').onclick = function () { window.cerrarVistaPrevia(); generarDocumento(tipo, idPedido); };
    document.getElementById('modal-vista-previa').style.display = 'flex';
}

window.cerrarVistaPrevia = function () {
    const m = document.getElementById('modal-vista-previa');
    if (m) m.style.display = 'none';
};

document.addEventListener('DOMContentLoaded', () => {
    moverModalesAlBody();
    cargarDatosGlobales();
    loadOrders();
});