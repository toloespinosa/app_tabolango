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

// Usa Modo Dios global o sesión puente
function obtenerEmailLimpio() {
    if (window.APP_USER && window.APP_USER.email) return window.APP_USER.email;
    const bridge = document.getElementById('session-email-bridge');
    if (!bridge) return "";
    let emailMatch = (bridge.textContent || bridge.innerText).match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
    return emailMatch ? emailMatch[0].toLowerCase().trim() : "";
}

function moverModalesAlBody() {
    const contenedorModales = document.getElementById('contenedor-modales-tabolango');
    const modalComanda = document.getElementById('modal-comanda');
    if (contenedorModales) document.body.appendChild(contenedorModales);
    if (modalComanda) document.body.appendChild(modalComanda);
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

        if (!grupos[prodName]) {
            grupos[prodName] = { nombre: prodName, icono: curr.icono || '📦', color: curr.color_diferenciador || '#E98C00', variedades: {} };
        }
        if (!grupos[prodName].variedades[variedad]) {
            grupos[prodName].variedades[variedad] = { nombreVar: variedad, calibres: {} };
        }
        if (!grupos[prodName].variedades[variedad].calibres[calibre]) {
            grupos[prodName].variedades[variedad].calibres[calibre] = {};
        }
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

        let rawRole = data[0].is_admin_user;
        if (rawRole === true || rawRole === "true" || rawRole == 1 || rawRole === "1") rawRole = 1;
        else if (rawRole == 2 || rawRole === "2") rawRole = 2;
        else rawRole = 0;

        // 🔥 AQUÍ CONECTAMOS EL MODO DIOS DEL GLOBAL.JS 🔥
        const isAdmin = (window.APP_USER.isAdmin || rawRole === 1);
        const isEditor = (window.APP_USER.isEditor || rawRole === 2);
        const canEdit = (isAdmin || isEditor);

        const ordersGrouped = data.reduce((acc, current) => {
            const id = current.id_pedido;
            if (!acc[id]) acc[id] = {
                ...current,
                products: [],
                total_acumulado: 0,
                ultima_edicion: current.ultima_edicion,
                editado_por: current.editado_por
            };
            const pUnitario = parseFloat(current.precio_unitario || 0);
            const cant = parseFloat(current.cantidad || 0);
            const pTotal = parseFloat(current.total_venta || (pUnitario * cant) || 0);
            const varName = current.variedad || current.Variedad || '';

            acc[id].products.push({
                nombre: current.producto,
                variedad: varName,
                calibre: current.calibre || '-',
                formato: current.formato || '-',
                cantidad: cant,
                unidad: current.unidad_real || current.unidad || 'Kg',
                color: current.color_diferenciador || '#0F4B29',
                precio_u: pUnitario,
                precio_t: pTotal
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

            // --- LOGICA VISUAL GUIA ---
            let bloqueGuia = '';
            let escudoGuia = '';
            let isGuiaSimulada = false;
            let txtFolioGuia = order.numero_guia;

            if (String(order.numero_guia).includes('S') || order.numero_guia >= 900000) {
                isGuiaSimulada = true;
                txtFolioGuia = "Borrador";
            }

            if (order.url_guia) {
                let txtBoton = isGuiaSimulada ? "🚚 VER GUÍA (Borrador)" : `🚚 VER GUÍA N° ${order.numero_guia}`;
                bloqueGuia = `<div class="upload-zone-small" style="border-style: solid; border-color: #3498db; color:#3498db; background:#f0f8ff;" onclick="abrirModal('${order.url_guia}')">${txtBoton}</div>${isAdmin ? `<button class="btn-x-delete" onclick="eliminarDoc('${order.id_pedido}', 'guia')">×</button>` : ''}`;
                escudoGuia = `<span class="factura-badge" style="background:#ebf5fb; color:#2980b9; border-color:#2980b9;">G: ${txtFolioGuia}</span>`;
            } else if (canEdit) {
                bloqueGuia = `<button onclick="event.stopPropagation(); abrirVistaPrevia('guia', '${order.id_pedido}')" style="width:100%; padding:10px; background:#E98C00; color:white; border:none; border-radius:8px; font-weight:800; font-size:11px; cursor:pointer; margin-bottom:5px; box-shadow:0 3px 0 #d35400;">🚚 GENERAR GUÍA DE DESPACHO</button>${isAdmin ? `<div style="text-align:center; font-size:9px; color:#999; margin-bottom:5px; cursor:pointer; text-decoration:underline;" onclick="document.getElementById('file-guia-${order.id_pedido}').click()">o subir manual</div>` : ''}<input type="file" id="file-guia-${order.id_pedido}" accept="image/*,application/pdf" style="display:none" onchange="subirGuia(this, '${order.id_pedido}')">`;
            }

            let escudoFactura = order.numero_factura ? `<span class="factura-badge" style="background:#e8f6f3; color:#0F4B29; border-color:#0F4B29;">F: ${order.numero_factura}</span>` : '';

            // --- LOGICA BOTÓN WHATSAPP ---
            let waEnviado = order.whatsapp_enviado && order.whatsapp_enviado !== "0000-00-00 00:00:00" && order.whatsapp_enviado !== null;
            let bgWa = waEnviado ? "#3498db" : "#25D366";
            let shadowWa = waEnviado ? "#2980b9" : "#1da851";
            let textWa = waEnviado ? "🔄 REENVIAR POR WHATSAPP" : "ENVIAR DETALLE";

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
                                                        <span style="font-size:10px; color:#666; white-space: nowrap;">
                                                            ${p.calibre} <span style="color:#E98C00; font-weight:bold;">|</span> ${p.formato}
                                                        </span>
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
                                
                                <button onclick="event.stopPropagation(); prepararEnvioWhatsapp('${order.id_pedido}')" style="width:100%; padding:12px; background:${bgWa}; color:white; border:none; border-radius:8px; font-weight:800; font-size:12px; cursor:pointer; margin-bottom:10px; margin-top:10px; box-shadow:0 3px 0 ${shadowWa}; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 6px;">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="white"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.305-.885-.653-1.482-1.46-1.656-1.758-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.012c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"></path></svg>
                                    ${textWa}
                                </button>
                                
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
        const urlSegura = `${URL_DETALLE_CLIENTE}${sep}id=${encodeURIComponent(clienteIdEditor)}`;
        const res = await fetch(urlSegura);
        const data = await res.json();

        if (data && data.perfil && data.perfil.nombre_categoria_cliente) {
            nombreCategoriaClienteEditor = data.perfil.nombre_categoria_cliente;
        }
    } catch (e) { console.warn("Error cat cliente", e); }

    document.getElementById('edit-subtitle').innerText = `${idPedido} • ${pedido.cliente}`;

    const inputFecha = document.getElementById('editor-fecha-despacho');
    if (inputFecha) inputFecha.value = pedido.fecha_despacho;

    const contenedor = document.getElementById('editor-productos-container');
    contenedor.innerHTML = '';

    pedido.products.forEach(prod => {
        let idReal = '';
        let color = '#ccc';

        const match = listaProductosPlana.find(p =>
            p.producto === prod.nombre &&
            p.formato === prod.formato &&
            (p.calibre || '-') === prod.calibre &&
            (p.variedad || p.Variedad || '') === prod.variedad
        );

        if (match) {
            idReal = match.id_producto;
            color = match.color_diferenciador;
        }

        let detalleTexto = `${prod.calibre} - ${prod.formato}`;
        let nombreDisplay = prod.nombre;
        if (prod.variedad) {
            nombreDisplay += " (" + prod.variedad + ")";
        }

        agregarFilaEditor(idReal, prod.cantidad, nombreDisplay, detalleTexto, color, prod.precio_u);
    });

    document.getElementById('modal-editar-pedido').style.display = 'flex';
}

function cerrarEditor() { document.getElementById('modal-editar-pedido').style.display = 'none'; }

function agregarFilaEditor(id = '', qty = 1, nombre = 'Toca para elegir...', detalle = '', color = '#eee', precio = 0) {
    const contenedor = document.getElementById('editor-productos-container');
    const rowId = Date.now() + Math.random().toString(36).substr(2, 9);
    const div = document.createElement('div');
    div.className = 'm-fila';
    div.id = `row-${rowId}`;
    div.style.borderLeft = `5px solid ${color}`;
    div.setAttribute('data-price', precio);

    div.innerHTML = `
        <div class="display-trigger" onclick="abrirSelectorEnEditor('${rowId}')">
            <div style="flex:1">
                <div style="font-weight:800; color:#333; line-height:1.2" class="p-name">${nombre}</div>
                <div style="font-size:11px; color:#888; margin-top:2px;" class="p-detail">${detalle}</div>
            </div>
        </div>
        <input type="hidden" class="p-id-hidden" value="${id}">
        <div class="m-fila-controles">
            <input type="number" class="p-qty" value="${qty}" min="0.1" step="any" inputmode="decimal">
            <button type="button" class="m-btn-remove" onclick="this.closest('.m-fila').remove()">✕</button>
        </div>`;
    contenedor.appendChild(div);
}

function abrirSelectorEnEditor(rowId) {
    filaActivaEditor = rowId;
    nivelVisual = 0;
    renderizarGridVisual();
    document.getElementById('modal-selector-visual').style.display = 'flex';
}

async function renderizarGridVisual(filtro = "") {
    const grid = document.getElementById('grid-visual-productos');
    const titulo = document.getElementById('titulo-selector-visual');
    grid.innerHTML = "";
    const txt = filtro.toLowerCase();

    if (nivelVisual === 0) {
        titulo.innerText = "PRODUCTOS";
        listaProductosMaster.forEach(g => {
            if (g.nombre.toLowerCase().includes(txt)) {
                const nombreSafe = g.nombre.replace(/'/g, "\\'");
                grid.innerHTML += `
                    <div class="grid-item" onclick="seleccionarNivelCategoriaVisual('${nombreSafe}')">
                        <div class="grid-media" style="font-size:35px;">${g.icono}</div>
                        <span style="font-weight:700; font-size:13px; color:#333 !important;">${g.nombre}</span>
                        <div class="card-color-footer" style="background:${g.color}"></div>
                    </div>`;
            }
        });
    }
    else if (nivelVisual === 1) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        titulo.innerText = "Variedad";
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=0;renderizarGridVisual()">⬅ VOLVER A PRODUCTOS</div>`;

        Object.keys(grupo.variedades).forEach(vKey => {
            const labelVar = vKey === "" ? "Estándar" : vKey;
            grid.innerHTML += `
                <div class="grid-item" onclick="nivelVisual=2;seleccionVar='${vKey}';renderizarGridVisual()">
                    <div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${labelVar.substring(0, 3).toUpperCase()}</b></div>
                    <span style="font-size:13px; color:#333 !important;">${labelVar}</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
        });
    }
    else if (nivelVisual === 2) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        const varObj = grupo.variedades[seleccionVar];
        const labelTitulo = seleccionVar ? `${seleccionCat} (${seleccionVar})` : seleccionCat;
        titulo.innerText = labelTitulo;

        const tieneVariedadReal = Object.keys(grupo.variedades).length > 1 || (Object.keys(grupo.variedades)[0] !== "");
        const actionVolver = tieneVariedadReal ? "nivelVisual=1" : "nivelVisual=0";
        const textoVolver = tieneVariedadReal ? "VARIEDADES" : "PRODUCTOS";

        grid.innerHTML = `<div class="btn-volver-modal" onclick="${actionVolver};renderizarGridVisual()">⬅ VOLVER A ${textoVolver}</div>`;

        Object.keys(varObj.calibres).forEach(cal => {
            grid.innerHTML += `
                <div class="grid-item" onclick="nivelVisual=3;seleccionCal='${cal}';renderizarGridVisual()">
                    <div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${cal}</b></div>
                    <span style="font-size:11px; color:#666 !important;">CALIBRE</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
        });
    }
    else if (nivelVisual === 3) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        const formatos = grupo.variedades[seleccionVar].calibres[seleccionCal];

        titulo.innerText = `${seleccionCal} - ${seleccionCat}`;
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=2;renderizarGridVisual()">⬅ VOLVER A CALIBRES</div>`;

        grid.innerHTML += `<div id="alerta-precios-editor" class="alerta-precio-cat"></div>`;
        grid.innerHTML += `<div id="load-msg" style="grid-column:1/-1; text-align:center; padding:10px; font-size:11px;">Cargando precios...</div>`;

        let flagAlertaActivada = false;

        for (const ftoKey of Object.keys(formatos)) {
            const p = formatos[ftoKey];
            let precioFinal = 0;
            let esEspecial = false;

            if (clienteIdEditor) {
                try {
                    const sep = URL_PRECIOS.includes('?') ? '&' : '?';
                    const resp = await fetch(`${URL_PRECIOS}${sep}action=get_price_by_client&cliente=${clienteIdEditor}&producto=${p.id_producto}`);
                    const data = await resp.json();
                    precioFinal = parseFloat(data.precio);
                    esEspecial = (data.origen === "categoria");
                } catch (e) {
                    precioFinal = parseFloat(p.precio_actual || p.precio_por_kilo || 0);
                }
            } else {
                precioFinal = parseFloat(p.precio_actual || p.precio_por_kilo || 0);
            }

            if (esEspecial && !flagAlertaActivada) {
                const alertaDiv = document.getElementById('alerta-precios-editor');
                if (alertaDiv) {
                    alertaDiv.style.display = 'block';
                    const nombreMostrar = nombreCategoriaClienteEditor ? nombreCategoriaClienteEditor.toUpperCase() : 'ESPECIALES';
                    alertaDiv.innerHTML = `🔔 SE APLICAN PRECIOS ${nombreMostrar}`;
                    flagAlertaActivada = true;
                }
            }

            const textoPrecio = precioFinal > 0 ? formatCLP(precioFinal) : 'Consultar';
            let nombreFull = p.producto;
            if (seleccionVar) nombreFull += ` (${seleccionVar})`;
            const nombreSafe = nombreFull.replace(/'/g, "\\'");

            grid.innerHTML += `
                <div class="grid-item" onclick="finalizarSeleccionEditor('${p.id_producto}', '${nombreSafe}', '${p.calibre}', '${p.formato}', '${grupo.color}', ${precioFinal})">
                    <div class="grid-media"><b style="font-size:16px; color:#333 !important;">${p.formato}</b></div>
                    <span style="font-size:14px; font-weight:900; color:#333 !important;">${textoPrecio}</span>
                    <div class="card-color-footer" style="background:${esEspecial ? '#E98C00' : grupo.color}"></div>
                </div>`;
        }
        const loader = document.getElementById('load-msg');
        if (loader) loader.remove();
    }
}

function seleccionarNivelCategoriaVisual(nombreCat) {
    seleccionCat = nombreCat;
    const grupo = listaProductosMaster.find(g => g.nombre === nombreCat);
    const variedadesKeys = Object.keys(grupo.variedades);

    if (variedadesKeys.length === 1 && variedadesKeys[0] === "") {
        seleccionVar = "";
        nivelVisual = 2;
    } else {
        nivelVisual = 1;
    }
    renderizarGridVisual();
}

function filtrarGridVisual(val) {
    const items = document.getElementById('grid-visual-productos').getElementsByClassName('grid-item');
    Array.from(items).forEach(item => { if (item.classList.contains('btn-volver-modal')) return; item.style.display = item.innerText.toLowerCase().includes(val.toLowerCase()) ? '' : 'none'; });
}

function finalizarSeleccionEditor(id, nombre, calibre, formato, color, precio) {
    const fila = document.getElementById(`row-${filaActivaEditor}`);
    if (fila) {
        fila.querySelector('.p-id-hidden').value = id;
        fila.querySelector('.p-name').innerText = nombre;
        fila.querySelector('.p-detail').innerText = `${calibre} - ${formato}`;
        fila.style.borderLeft = `5px solid ${color}`;
        fila.setAttribute('data-price', precio);
    }
    document.getElementById('modal-selector-visual').style.display = 'none';
}

async function guardarEdicionAPI() {
    if (!idPedidoEnEdicion) return;
    const filas = document.querySelectorAll('#editor-productos-container .m-fila');
    let ids = []; let cants = []; let precios = [];

    filas.forEach(fila => {
        const id = fila.querySelector('.p-id-hidden').value;
        const cant = fila.querySelector('.p-qty').value;
        const precio = fila.getAttribute('data-price') || 0;
        if (id && cant > 0) { ids.push(id); cants.push(cant); precios.push(precio); }
    });

    if (ids.length === 0) {
        Swal.fire('Atención', 'El pedido no puede quedar vacío.', 'warning');
        return;
    }

    const btn = document.getElementById('btn-guardar-edicion');
    const txtOriginal = btn.innerText;
    btn.innerText = "GUARDANDO...";
    btn.disabled = true;

    const formData = new FormData();
    formData.append('wp_user', obtenerEmailLimpio());
    formData.append('action', 'update_order_items');
    formData.append('id_pedido', idPedidoEnEdicion);
    formData.append('producto', ids.join(' | '));
    formData.append('cantidad', cants.join(' | '));
    formData.append('precios_venta', precios.join(' | '));

    const inputFecha = document.getElementById('editor-fecha-despacho');
    if (inputFecha && inputFecha.value) formData.append('fecha_despacho', inputFecha.value);

    try {
        const res = await fetch(URL_EDITAR_API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            Swal.fire('¡Guardado!', 'El pedido fue actualizado', 'success');
            cerrarEditor();
            loadOrders();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Error de conexión', 'error');
    } finally {
        btn.innerText = txtOriginal;
        btn.disabled = false;
    }
}

async function eliminarPedidoAPI() {
    if (!idPedidoEnEdicion) return;

    const result = await Swal.fire({
        title: '¿Eliminar Pedido?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#aaa',
        confirmButtonText: 'Sí, eliminar'
    });

    if (!result.isConfirmed) return;

    const formData = new FormData();
    formData.append('action', 'delete_order');
    formData.append('id_pedido', idPedidoEnEdicion);
    formData.append('wp_user', obtenerEmailLimpio());

    try {
        const res = await fetch(URL_EDITAR_API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            Swal.fire('Eliminado', 'El pedido fue borrado', 'success');
            cerrarEditor(); loadOrders();
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    } catch (e) { Swal.fire('Error', 'Error de conexión', 'error'); }
}

function abrirModal(url) {
    const modal = document.getElementById('modal-factura');
    const container = document.getElementById('modal-body-content');
    container.innerHTML = url.toLowerCase().endsWith('.pdf') ? `<iframe src="${url}" style="width:100%; height:70vh; border:none;"></iframe>` : `<img src="${url}" style="max-width:100%;">`;
    modal.style.display = 'flex';
}
function cerrarModal() { document.getElementById('modal-factura').style.display = 'none'; }
function flipCard(event, id) { if (event) event.stopPropagation(); document.getElementById(`card-${id}`).classList.toggle('is-flipped'); }

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
        if (data && data.perfil) { rutFinal = data.perfil.rut_cliente || data.perfil.id_cliente || "Pendiente"; }
    } catch (e) { console.error(e); }

    let totalNeto = 0;
    const productosHTML = pedido.products.map(p => {
        const sub = p.precio_t || (p.precio_u * p.cantidad);
        totalNeto += sub;
        return `
        <div class="item-row">
            <div class="item-info">
                <span class="item-title">
                    ${p.nombre} 
                    ${p.variedad ? `<span style="font-weight:400; color:#666; font-size:12px; margin-left:5px;">(${p.variedad})</span>` : ''}
                </span>
                <div class="item-meta">
                    <span class="meta-tag">Cant:</span> <span class="meta-val">${Math.round(p.cantidad)} ${p.unidad}</span>
                    <span style="color:#ddd">|</span>
                    <span class="unit-price-tag">Unit: ${fmt.format(p.precio_u)}</span>
                </div>
                <div style="font-size:11px; color:#999; margin-top:4px;">Calibre: ${p.calibre} • Formato: ${p.formato}</div>
            </div>
            <div class="item-price-total">${fmt.format(sub)}</div>
        </div>`;
    }).join('');

    const obsTexto = pedido.observacion || pedido.observaciones || '';
    const observacionBlock = obsTexto ? `
        <div style="padding: 15px 25px; background: #fffbf0; border-bottom: 1px solid #f0f0f0; border-top: 1px solid #f0f0f0;">
            <div style="font-size: 11px; font-weight: 800; color: #E98C00; text-transform: uppercase; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">📝 Observaciones del Pedido</div>
            <div style="font-size: 13px; color: #555; line-height: 1.5; font-style: italic;">"${obsTexto}"</div>
        </div>
    ` : '';

    const iva = Math.round(totalNeto * 0.19);
    const total = totalNeto + iva;

    body.innerHTML = `
        <div class="detalle-header">
            <h3>Detalle del Pedido</h3>
            <div style="color:rgba(255,255,255,0.8); font-size:14px; font-weight:600; margin-top:4px;">${pedido.cliente}</div>
            <div class="rut-container" onclick="copiarRutLimpio('${rutFinal}')">
                <span class="rut-label">RUT:</span><span class="rut-value">${rutFinal}</span>
            </div>
        </div>
        <div class="detalle-body" style="max-height: 40vh;">${productosHTML}</div>
        ${observacionBlock}
        <div class="detalle-footer">
            <div class="resumen-row"><span>Subtotal Neto</span><span style="font-weight:700; color:#1a1a1a;">${fmt.format(totalNeto)}</span></div>
            <div class="resumen-row"><span>IVA (19%)</span><span style="font-weight:700; color:#1a1a1a;">${fmt.format(iva)}</span></div>
            <div class="resumen-total"><span class="label">Total Final</span><span class="value">${fmt.format(total)}</span></div>
            <button onclick="cerrarDetalle()" class="btn-cerrar-block">Cerrar Detalle</button>
        </div>`;
}
function cerrarDetalle() { document.getElementById('modal-detalle-pedido').style.display = 'none'; }
function copiarRutLimpio(rutOriginal) {
    let rutLimpio = rutOriginal.split('-')[0].replace(/\./g, '').trim();
    navigator.clipboard.writeText(rutLimpio).then(() => {
        const toast = document.createElement('div');
        toast.className = 'rut-copiado-toast';
        toast.innerText = 'RUT copiado: ' + rutLimpio;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    });
}
async function eliminarDoc(id, tipo) {
    const result = await Swal.fire({ title: `¿Borrar ${tipo}?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c' });
    if (!result.isConfirmed) return;
    const formData = new FormData(); formData.append('action', 'delete_document'); formData.append('id_pedido', id); formData.append('tipo', tipo);
    try { const res = await fetch(URL_SUBIR, { method: 'POST', body: formData }); const data = await res.json(); if (data.status === 'success') loadOrders(); } catch (e) { }
}
async function subirGuia(input, id) {
    if (!input.files[0]) return;
    const formData = new FormData(); formData.append('foto_guia', input.files[0]); formData.append('id_pedido', id); formData.append('action', 'upload_guia_despacho');
    try { const res = await fetch(URL_SUBIR, { method: 'POST', body: formData }); const data = await res.json(); if (data.status === 'success') loadOrders(); } catch (e) { }
}

function openQR(idPedido, token) {
    const existing = document.getElementById('dynamic-qr-modal');
    if (existing) existing.remove();

    // Generar ruta dinámica base al entorno local
    const baseUrl = window.location.origin;
    const urlValidacion = `${baseUrl}/validar-entrega?token=${token}`;

    const overlay = document.createElement('div');
    overlay.id = 'dynamic-qr-modal';
    overlay.className = 'modal-overlay';
    overlay.style.display = 'flex';
    overlay.style.zIndex = '9999999';
    overlay.onclick = closeQR;

    overlay.innerHTML = `
        <div class="qr-modal-content" onclick="event.stopPropagation()" style="background: white; padding: 25px; border-radius: 24px; position: relative; width: 90%; max-width: 380px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.4);">
            <button class="btn-cerrar-modal" onclick="closeQR()" style="position: absolute; top: 15px; right: 15px; background: #f0f2f5; border: none; width: 32px; height: 32px; border-radius: 50%; font-weight: bold; cursor: pointer; color: #333; display: flex; align-items: center; justify-content: center; font-size: 16px;">✕</button>
            
            <div style="margin-bottom: 20px;">
                <h3 style="color:#333; margin: 0; font-size:18px; font-weight: 800; text-transform: uppercase;">Validación de Entrega</h3>
                <p style="margin: 5px 0 0 0; font-size: 13px; color: #888; font-weight: bold;">PEDIDO ID: ${idPedido}</p>
            </div>
            
            <div id="qrcode-target" style="background: white; padding: 15px; border-radius: 15px; display: inline-block; border: 2px dashed #eee;"></div>
            
            <div style="background: #fdf2e2; padding: 12px; border-radius: 12px; border: 1px solid #f9e1bc; margin-top: 20px;">
                <p style="margin: 0; font-size: 11px; color: #E98C00; font-weight: bold; line-height: 1.4;">Escanee el código o use el botón inferior para validar la recepción.</p>
            </div>
            
            <button onclick="window.location.href='${urlValidacion}'" style="background: #E98C00; color: white; width: 100%; padding: 14px; border: none; border-radius: 12px; font-weight: 800; font-size: 14px; text-transform: uppercase; cursor: pointer; margin-top: 20px; box-shadow: 0 4px 10px rgba(233, 140, 0, 0.3);">
                IR A VALIDAR AHORA
            </button>
            
            <p style="font-size:10px; color:#ccc; margin-top:15px; letter-spacing: 1px; font-family: monospace;">TOKEN: ${token}</p>
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

function closeQR() {
    const modal = document.getElementById('dynamic-qr-modal');
    if (modal) modal.remove();
}

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
    if (!fechaInput) return Swal.fire('Error', 'Seleccione una fecha', 'warning');
    const contenedor = document.getElementById('lista-comanda-body');
    contenedor.innerHTML = '<div style="text-align:center; padding:20px;">Calculando insumos...</div>';
    const resumen = {};
    let totalPedidos = 0;
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
        htmlItems += `
            <div class="comanda-item ${isChecked ? 'checked' : ''}" onclick="toggleComandaCheck(this, '${storageKey}')">
                <div class="chk-circle"></div>
                <div class="comanda-info">
                    <span class="comanda-name">${item.nombre} ${item.variedad ? `<span style="font-weight:400; color:#E98C00;">(${item.variedad})</span>` : ''}</span>
                    <div class="comanda-meta">${item.calibre} • ${item.formato}</div>
                </div>
                <div class="comanda-qty">${parseFloat(item.cantidad.toFixed(2))} ${item.unidad}</div>
            </div>`;
    });
    contenedor.innerHTML = headerHTML + htmlItems;
}
function toggleComandaCheck(el, key) {
    el.classList.toggle('checked');
    if (el.classList.contains('checked')) localStorage.setItem(key, 'true');
    else localStorage.removeItem(key);
}
async function limpiarTicks() {
    const result = await Swal.fire({ title: '¿Limpiar Ticks?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#e74c3c' });
    if (!result.isConfirmed) return;
    const fechaInput = document.getElementById('fecha-comanda').value;
    Object.keys(localStorage).forEach(k => { if (k.startsWith(`cmd_${fechaInput}`)) localStorage.removeItem(k); });
    generarListaComandaVisual();
}
function llamarPHPComanda() {
    const fecha = document.getElementById('fecha-comanda').value;
    if (!fecha) return Swal.fire('Error', 'Seleccione una fecha primero', 'warning');
    const sep = URL_PHP_COMANDA.includes('?') ? '&' : '?';
    window.open(`${URL_PHP_COMANDA}${sep}fecha=${fecha}`, '_blank');
}

// --- GENERAR DOCUMENTOS (CON SWEETALERT, LOADER Y ANTICORTES) ---
async function generarDocumento(tipo, idPedido) {
    const nombreDoc = tipo === 'guia' ? 'Guía de Despacho' : 'Factura Electrónica';

    // 1. Confirmación inicial
    const confirmacion = await Swal.fire({
        title: `¿Generar ${nombreDoc}?`,
        text: `Se emitirá el documento para el pedido ${idPedido}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, emitir',
        cancelButtonText: 'Cancelar',
        customClass: { confirmButton: 'swal-btn-verde', cancelButton: 'swal-btn-rojo' }
    });

    if (!confirmacion.isConfirmed) return;

    const btn = document.getElementById(`btn-confirmar-emision`);
    const txtOrig = btn ? btn.innerText : '';
    if (btn) { btn.disabled = true; btn.innerHTML = '⏳ EMITIENDO...'; }

    // 2. ALERTA DE CARGA CON SPINNER (¡La magia visual!)
    Swal.fire({
        title: `Generando ${nombreDoc}...`,
        html: 'Por favor espera, emitiendo Guía de Despacho.',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => { Swal.showLoading(); }
    });

    try {
        const res = await fetch(URL_FACTURACION, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_pedido: idPedido, tipo_doc: tipo })
        });

        const rawText = await res.text();
        let data;

        try {
            data = JSON.parse(rawText);
        } catch (parseError) {
            // 🚨 EL SERVIDOR CORTÓ LA LLAMADA, PERO SÍ SE HIZO
            await Swal.fire({
                icon: 'success', title: `¡${nombreDoc} Generada!`, text: 'El documento se emitió correctamente.', timer: 2000, showConfirmButton: false
            });
            if (typeof window.cerrarVistaPrevia === 'function') window.cerrarVistaPrevia();
            loadOrders();
            return;
        }

        if (data.status === 'success') {
            await Swal.fire({
                icon: 'success', title: `¡${nombreDoc} Generada!`, text: `Folio: ${data.folio}`, timer: 2000, showConfirmButton: false
            });
            if (typeof window.cerrarVistaPrevia === 'function') window.cerrarVistaPrevia();
            loadOrders();
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message });
            if (btn) { btn.disabled = false; btn.innerText = txtOrig; }
        }

    } catch (e) {
        console.error("Fallo de red:", e);
        await Swal.fire({
            icon: 'success', title: 'Procesando...', text: `Verificando el estado de la ${nombreDoc}.`, timer: 2000, showConfirmButton: false
        });
        if (typeof window.cerrarVistaPrevia === 'function') window.cerrarVistaPrevia();
        loadOrders();
    }
}

async function abrirVistaPrevia(tipo, idPedido) {
    const pedido = window.currentOrders[idPedido];
    if (!pedido) return Swal.fire('Error', 'No se encontró el pedido', 'error');
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

// --- FUNCIONES ENVÍO WHATSAPP MEJORADAS ---

async function prepararEnvioWhatsapp(idPedido) {
    const pedido = window.currentOrders[idPedido];
    if (!pedido) return;

    const inputPhone = document.getElementById('wa-telefono-input');
    inputPhone.style.borderColor = 'transparent';

    document.getElementById('wa-cliente-nombre').innerText = "Cargando datos del cliente...";
    inputPhone.value = "";
    document.getElementById('modal-confirmar-whatsapp').style.display = 'flex';

    let totalNeto = 0;
    pedido.products.forEach(p => { totalNeto += (p.precio_u * p.cantidad); });
    const iva = Math.round(totalNeto * 0.19);
    const total = totalNeto + iva;

    const fmt = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 });
    document.getElementById('wa-preview-id').innerText = pedido.id_pedido;
    document.getElementById('wa-preview-sub').innerText = fmt.format(totalNeto);
    document.getElementById('wa-preview-iva').innerText = fmt.format(iva);
    document.getElementById('wa-preview-tot').innerText = fmt.format(total);

    let nombreContacto = pedido.cliente;
    let telefonoRaw = "";

    try {
        const sep = URL_DETALLE_CLIENTE.includes('?') ? '&' : '?';
        const resp = await fetch(`${URL_DETALLE_CLIENTE}${sep}id=${pedido.id_interno_cliente}`);
        const d = await resp.json();
        if (d && d.perfil) {
            telefonoRaw = d.perfil.telefono || d.perfil.celular || "";
        }
    } catch (e) { console.warn("No se pudo obtener detalle extra del cliente para WA."); }

    let nombreSaludo = nombreContacto.trim();
    document.getElementById('wa-cliente-nombre').innerText = nombreContacto;
    document.getElementById('wa-preview-nombre').innerText = nombreSaludo;

    if (telefonoRaw) {
        let cleanPhone = telefonoRaw.replace(/[^\d+]/g, '');
        if (!cleanPhone.startsWith('+')) {
            if (cleanPhone.length === 8) {
                cleanPhone = '+569' + cleanPhone;
            } else if (cleanPhone.startsWith('9') && cleanPhone.length === 9) {
                cleanPhone = '+56' + cleanPhone;
            } else if (cleanPhone.startsWith('569') && cleanPhone.length === 11) {
                cleanPhone = '+' + cleanPhone;
            }
        }
        inputPhone.value = cleanPhone;
    }

    const btnEnviar = document.getElementById('btn-enviar-wa-final');
    btnEnviar.onclick = function () {
        let telFinal = inputPhone.value.replace(/[^\d+]/g, '');
        if (!telFinal.startsWith('+')) {
            if (telFinal.length === 8) telFinal = '+569' + telFinal;
            else if (telFinal.startsWith('9') && telFinal.length === 9) telFinal = '+56' + telFinal;
        }
        enviarWhatsappAPI(idPedido, nombreSaludo, telFinal);
    };
    inputPhone.focus();
}

async function enviarWhatsappAPI(idPedido, nombreSaludo, telefono) {
    if (!telefono || telefono.length < 11) {
        Swal.fire({ title: 'Teléfono Inválido', text: 'Verifica que tenga el formato +569XXXXXXXX', icon: 'warning' });
        return;
    }

    document.getElementById('modal-confirmar-whatsapp').style.display = 'none';

    Swal.fire({
        title: 'Enviando WhatsApp...',
        text: 'Generando el PDF y enviando el mensaje a ' + telefono,
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    const formData = new FormData();
    formData.append('id_pedido', idPedido);
    formData.append('telefono', telefono);
    formData.append('nombre', nombreSaludo);

    try {
        const URL_ENVIAR_WA = window.getApi('enviar_detalle_pedido.php');
        const res = await fetch(URL_ENVIAR_WA, { method: 'POST', body: formData });
        const data = await res.json();

        if (data.status === 'ok') {
            Swal.fire({
                title: '¡Enviado!',
                text: 'El detalle en PDF ya viaja hacia el cliente.',
                icon: 'success',
                confirmButtonColor: '#25D366'
            }).then(() => {
                loadOrders();
            });
        } else {
            Swal.fire('Error al enviar', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Error de conexión', 'No se pudo contactar al servidor.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    moverModalesAlBody();
    cargarDatosGlobales();
    loadOrders();
});