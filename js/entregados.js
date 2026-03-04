// CONFIGURACIÓN API
const URL_API = window.getApi('api.php');
const URL_EDITAR_API = window.getApi('api-edit.php');
const URL_PRECIOS = window.getApi('form.php');

let offset = 0;
const LIMIT_INICIAL = 9;
const LIMIT_SCROLL = 6;
let cargando = false;
let hayMasDatos = true;
let busquedaActual = '';
let observer;
let timeoutBusqueda;
let pedidosGlobales = {}; // Almacena info completa del pedido

// Variables Editor
let listaProductosMaster = [];
let filaActivaEditor = null;
let nivelVisual = 0;
let seleccionCat = null;
let seleccionVar = null;
let seleccionCal = null;
let idPedidoEnEdicion = null;
let clienteIdEditor = null;

// --- UTILIDADES ---
const formatCLP = (v) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(v);
function obtenerEmailLimpio() {
    const bridge = document.getElementById('session-email-bridge');
    if (!bridge) return "";
    let emailMatch = (bridge.textContent || bridge.innerText).match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
    return emailMatch ? emailMatch[0].toLowerCase().trim() : "";
}

document.addEventListener('DOMContentLoaded', () => {
    // Mover modales al body para evitar conflictos z-index
    const modalFactura = document.getElementById('modal-factura');
    const modalEditor = document.getElementById('modal-editar-pedido');
    const modalSelector = document.getElementById('modal-selector-visual');

    if (modalFactura) document.body.appendChild(modalFactura);
    if (modalEditor) document.body.appendChild(modalEditor);
    if (modalSelector) document.body.appendChild(modalSelector);

    cargarDatosGlobales(); // Cargar productos para el editor
    cargarPedidos(true);
    iniciarObserver();
});

// --- CARGA DE PRODUCTOS (Para el Editor) ---
async function cargarDatosGlobales() {
    try {
        // SOLUCIÓN: Separador dinámico
        const sep = URL_API.includes('?') ? '&' : '?';
        const res = await fetch(`${URL_API}${sep}action=get_products`);
        const rawData = await res.json();
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

// --- LÓGICA DE LISTADO DE ENTREGADOS ---
function filtrarEntregas() {
    const input = document.getElementById('buscador-entregados');
    clearTimeout(timeoutBusqueda);
    timeoutBusqueda = setTimeout(() => { novaBusqueda(input.value); }, 500);
}

function novaBusqueda(texto) { busquedaActual = texto.trim(); cargarPedidos(true); }

async function cargarPedidos(reset = false) {
    if (cargando || (!hayMasDatos && !reset)) return;
    cargando = true;
    mostrarLoading(true);

    if (reset) {
        offset = 0;
        hayMasDatos = true;
        document.getElementById('lista-entregados').innerHTML = "";
        pedidosGlobales = {};
    }

    const limit = reset ? LIMIT_INICIAL : LIMIT_SCROLL;

    try {
        // SOLUCIÓN: Usar getApi para adaptarse al entorno
        const URL_ENTREGADOS = window.getApi('api_entregados.php');
        const sep = URL_ENTREGADOS.includes('?') ? '&' : '?';
        let url = `${URL_ENTREGADOS}${sep}limit=${limit}&offset=${offset}`;
        if (busquedaActual) url += `&search=${encodeURIComponent(busquedaActual)}`;

        const response = await fetch(url);
        const rawData = await response.json();

        if (!rawData || rawData.length === 0) hayMasDatos = false;

        // PROCESAR DATOS
        const pedidosMap = rawData.reduce((acc, item) => {
            if (!acc[item.id_pedido]) {
                acc[item.id_pedido] = {
                    ...item,
                    filasProductos: [],
                    rawProducts: [] // Array para el editor
                };
            }

            const sep = '<span style="color: #E98C00; font-weight: bold; margin: 0 4px;">|</span>';
            acc[item.id_pedido].filasProductos.push({
                html: `${item.producto} ${sep} <small>${item.calibre || 'S/C'}</small> ${sep} <small>${item.formato || 'S/F'}</small>`,
                cantidad: Math.round(item.cantidad),
                unidad: item.unidad || 'Kg'
            });

            // DATA CRUDA PARA EL EDITOR (IMPORTANTE)
            acc[item.id_pedido].rawProducts.push({
                id_producto: item.id_producto,
                nombre: item.producto,
                variedad: item.variedad,
                calibre: item.calibre,
                formato: item.formato,
                cantidad: item.cantidad,
                unidad: item.unidad,
                color: item.color_diferenciador || '#ccc',
                precio_u: item.precio_unitario || 0
            });

            return acc;
        }, {});

        const nuevosPedidos = Object.values(pedidosMap);

        if (nuevosPedidos.length === 0 && reset) {
            document.getElementById('lista-entregados').innerHTML = '<p style="color:white; text-align:center; grid-column: 1/-1;">No se encontraron resultados.</p>';
        } else {
            renderizarCards(nuevosPedidos);
            setTimeout(() => {
                nuevosPedidos.forEach((p) => {
                    if (p.lat_entrega && p.lng_entrega) obtenerComuna(p.lat_entrega, p.lng_entrega, p.id_pedido);
                });
            }, 300);
        }

        if (nuevosPedidos.length < limit) hayMasDatos = false;
        offset += limit;

    } catch (e) {
        console.error("Error cargando historial:", e);
    } finally {
        cargando = false;
        mostrarLoading(false);
        const initLoader = document.getElementById('loading-entregados');
        if (initLoader) initLoader.style.display = 'none';
    }
}

function renderizarCards(lista) {
    const contenedor = document.getElementById('lista-entregados');

    lista.forEach(p => {
        if (document.getElementById(`card-${p.id_pedido}`)) return;

        let rawTel = p.telefono || p.telefono_real || '';
        rawTel = rawTel.toString().replace(/[^0-9]/g, '');
        let telFinal = '';
        if (rawTel.length >= 8) {
            telFinal = rawTel.startsWith('56') ? '+' + rawTel : '+56' + rawTel;
        }

        // -------------------------------------------------------------
        // LOGICA NUEVA: PREPARAR TELEFONO DE FACTURACIÓN
        // -------------------------------------------------------------
        let rawTelFac = p.telefono_factura || '';
        rawTelFac = rawTelFac.toString().replace(/[^0-9]/g, '');
        let telFacFinal = '';
        if (rawTelFac.length >= 8) {
            telFacFinal = rawTelFac.startsWith('56') ? '+' + rawTelFac : '+56' + rawTelFac;
        }

        // --- GUARDA DATOS EN MEMORIA (CLAVE PARA EL EDITOR Y ENVÍO) ---
        pedidosGlobales[p.id_pedido] = {
            id: p.id_pedido,
            cliente: p.cliente,
            id_interno_cliente: p.id_interno_cliente,
            nombre_ws: p.saludo_whatsapp,
            telefono: telFinal,

            // DATOS ESTRICTOS DE FACTURACIÓN
            telefono_factura: telFacFinal, // Usamos el procesado
            email_factura: p.email_factura,
            // ------------------------------

            url_factura: p.url_factura,
            folio: p.numero_factura,
            enviado: p.whatsapp_enviado ? true : false,
            email: p.email_cliente,
            observacion_entrega: p.observacion_entrega,
            products: p.rawProducts
        };

        const tieneObservacion = p.observacion_entrega && p.observacion_entrega.trim() !== "";

        const htmlWarning = tieneObservacion ? `
            <i class="fa-solid fa-triangle-exclamation warning-icon-corner"
               onclick="verObservacion(event, '${p.id_pedido}')">
            </i>
        ` : '';

        const fecha = p.fecha_despacho ? p.fecha_despacho.split(' ')[0].split('-').reverse().join('/') : 'S/F';
        const listaHtml = p.filasProductos.map(prod => `<div class="detail-row"><span>${prod.html}</span><strong>${prod.cantidad} ${prod.unidad}</strong></div>`).join('');

        let btnFactura;
        let colorPestana;

        if (p.url_factura && p.url_factura !== 'null') {
            btnFactura = `<button onclick="abrirModal('${p.url_factura}')" class="btn-main" style="background:#2980b9; margin-top:0; padding:10px; font-size:11px;">📄 VER FACTURA (XML/PDF)</button>`;
            colorPestana = '#27ae60';
        } else {
            btnFactura = `<button onclick="generarFactura('${p.id_pedido}', this)" class="btn-main" style="background:#e74c3c; margin-top:0; padding:10px; font-size:11px;">⚡ GENERAR FACTURA SII</button>`;
            colorPestana = '#95a5a6';
        }

        let btnGuia = p.url_factura_firmada ?
            `<button onclick="abrirModal('${p.url_factura_firmada}')" class="btn-main" style="background:#27ae60; margin-top:0; padding:10px; font-size:11px;">📝 VER GUÍA FIRMADA</button>` :
            `<label class="btn-upload-manual" style="color:#3498db; border-color:#3498db">📤 SUBIR GUÍA<input type="file" accept="image/*,.pdf" style="display:none" onchange="subirDoc(this, '${p.id_pedido}', 'guia')"></label>`;

        let btnEvidencia = p.evidencia_entrega ? `<button onclick="abrirModal('${p.evidencia_entrega}')" class="btn-main" style="background:#e67e22; margin-top:0; padding:10px; font-size:11px;">📸 VER EVIDENCIA</button>` : "";

        // SOLUCIÓN: Corrección link de Google Maps
        const mapUrl = `https://maps.google.com/maps?q=${p.lat_entrega},${p.lng_entrega}&z=15&output=embed`;

        let btnWsClass = p.whatsapp_enviado ? 'btn-whatsapp-sent' : 'btn-whatsapp-card';
        let btnWsText = p.whatsapp_enviado ? 'REENVIAR' : 'ENVIAR';
        let btnWsIcon = p.whatsapp_enviado ? 'fa-solid fa-check-double' : 'fa-brands fa-whatsapp';

        let nombreSafe = (p.saludo_whatsapp || "").replace(/'/g, "\\'").replace(/"/g, '"');

        // --- CAMBIO CLAVE: Acción del botón WhatsApp usa el teléfono de facturación ---
        let onClickAction = telFacFinal.length >= 11
            ? `enviarPruebaWhatsApp('${nombreSafe}', '${p.url_factura}', '${p.id_pedido}', '${telFacFinal}', '${p.numero_factura || ""}')`
            : `Swal.fire({ icon: 'warning', title: 'Sin Teléfono Factura', text: 'No tiene teléfono de facturación configurado.' })`;

        const htmlCard = `
            <div class="card-entregado" id="card-${p.id_pedido}">
                <div class="card-inner">
                    <div class="card-front">
                        ${htmlWarning} <div class="badge-factura" style="background:${colorPestana}">
                            ${p.numero_factura ? 'FACTURA: ' + p.numero_factura : 'PENDIENTE'}
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px; align-items:center; margin-top:10px;">
                            <span style="font-size:10px; color:#aaa; font-weight:800;">ID: ${p.id_pedido}</span>
                            <span class="fecha-entrega-verde">📅 ${fecha}</span>
                        </div>
                        <h3 style="margin-bottom:2px;">${p.cliente}</h3>
                        <div class="fila-dividida">
                            <div class="mitad-texto" title="${p.razon_social}">
                                🏢 ${p.razon_social || 'S/R'}
                            </div>
                            <span class="separador-central">|</span>
                            <div class="mitad-texto derecha" title="${p.contacto_completo}">
                                👤 <i>${p.contacto_completo}</i>
                            </div>
                        </div>
                        <div class="productos-wrapper">${listaHtml}</div>
                        
                        <button class="btn-main" onclick="toggleFlip('${p.id_pedido}', ${p.lat_entrega}, ${p.lng_entrega})">
                            📄 DOCUMENTOS / MAPA
                        </button>
                    </div>
                    <div class="card-back">
                        <div class="card-back-header">
                            <div style="flex-grow: 1;">
                                <div style="font-size:15px; font-weight:800; color:#0F4B29; line-height: 1.2;">${p.cliente}</div>
                                <div id="comuna-${p.id_pedido}" style="font-size:10px; color:#27ae60; font-weight:600;">📍 ESPERANDO...</div>
                            </div>
                            <button id="btn-ws-${p.id_pedido}" class="${btnWsClass}" onclick="${onClickAction}">
                                <i class="${btnWsIcon}" style="font-size: 14px;"></i> <span>${btnWsText}</span>
                            </button>
                        </div>
                        <div class="map-container"><iframe src="${mapUrl}" loading="lazy"></iframe></div>
                        <div style="display:flex; flex-direction:column; gap:6px; flex-grow:1; overflow-y:auto;">
                            ${btnFactura} ${btnGuia} ${btnEvidencia} 
                        </div>
                        <button class="btn-main" style="background:#27ae60; margin-top:5px;" onclick="toggleFlip('${p.id_pedido}')">VOLVER</button>
                    </div>
                </div>
            </div>`;
        contenedor.insertAdjacentHTML('beforeend', htmlCard);
    });
}

function verObservacion(evt, idPedido) {
    if (evt) evt.stopPropagation();
    const pedido = pedidosGlobales[idPedido];
    const textoObservacion = pedido ? (pedido.observacion_entrega || "Sin detalles") : "Sin detalles";

    Swal.fire({
        title: 'Observación de Entrega',
        text: textoObservacion,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#E98C00',
        cancelButtonColor: '#aaa',
        confirmButtonText: '✏️ EDITAR PEDIDO',
        cancelButtonText: 'Cerrar'
    }).then((result) => {
        if (result.isConfirmed) {
            abrirEditor(idPedido);
        }
    });
}

// ==========================================
// 🛠 LÓGICA DEL EDITOR DE PEDIDOS 
// ==========================================

async function abrirEditor(idPedido) {
    const pedido = pedidosGlobales[idPedido];
    if (!pedido) {
        Swal.fire('Error', 'No se encontraron datos del pedido para editar.', 'error');
        return;
    }

    idPedidoEnEdicion = idPedido;
    clienteIdEditor = pedido.id_interno_cliente;

    const contenedor = document.getElementById('editor-productos-container');
    contenedor.innerHTML = '';

    if (pedido.products && pedido.products.length > 0) {
        pedido.products.forEach(prod => {
            let idReal = prod.id_producto;
            let color = prod.color || '#ccc';
            let nombreDisplay = prod.nombre;
            if (prod.variedad) nombreDisplay += ` (${prod.variedad})`;
            let detalleTexto = `${prod.calibre || '-'} - ${prod.formato || '-'}`;
            let precio = prod.precio_u || 0;

            agregarFilaEditor(idReal, prod.cantidad, nombreDisplay, detalleTexto, color, precio);
        });
    }

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
    } else if (nivelVisual === 1) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        titulo.innerText = "Variedad";
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=0;renderizarGridVisual()">⬅ VOLVER A PRODUCTOS</div>`;

        Object.keys(grupo.variedades).forEach(vKey => {
            const labelVar = vKey === "" ? "Estándar" : vKey;
            grid.innerHTML += `
                <div class="grid-item" onclick="nivelVisual=2;seleccionVar='${vKey}';renderizarGridVisual()">
                    <div class="grid-media"><b style="font-size:18px; color:#333 !important;">${labelVar.substring(0, 3).toUpperCase()}</b></div>
                    <span style="font-size:13px; color:#333 !important;">${labelVar}</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
        });
    } else if (nivelVisual === 2) {
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
                    <div class="grid-media"><b style="font-size:18px; color:#333 !important;">${cal}</b></div>
                    <span style="font-size:11px; color:#666 !important;">CALIBRE</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
        });
    } else if (nivelVisual === 3) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionCat);
        const formatos = grupo.variedades[seleccionVar].calibres[seleccionCal];

        titulo.innerText = `${seleccionCal} - ${seleccionCat}`;
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=2;renderizarGridVisual()">⬅ VOLVER A CALIBRES</div>`;

        grid.innerHTML += `<div id="load-msg" style="grid-column:1/-1; text-align:center; padding:10px; font-size:11px;">Cargando precios...</div>`;

        let flagAlertaActivada = false;
        const alertaDiv = document.createElement('div');
        alertaDiv.className = 'alerta-precio-cat';
        grid.prepend(alertaDiv);

        for (const ftoKey of Object.keys(formatos)) {
            const p = formatos[ftoKey];
            let precioFinal = 0;
            let esEspecial = false;

            // Consultar precio cliente
            if (clienteIdEditor) {
                try {
                    // SOLUCIÓN: Separador dinámico
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
                alertaDiv.style.display = 'block';
                alertaDiv.innerHTML = `🔔 PRECIOS ESPECIALES APLICADOS`;
                flagAlertaActivada = true;
            }

            const textoPrecio = precioFinal > 0 ? formatCLP(precioFinal) : 'Consultar';
            let nombreFull = p.producto;
            if (seleccionVar) nombreFull += ` (${seleccionVar})`;
            const nombreSafe = nombreFull.replace(/'/g, "\\'");

            grid.innerHTML += `
                <div class="grid-item" onclick="finalizarSeleccionEditor('${p.id_producto}', '${nombreSafe}', '${p.calibre}', '${p.formato}', '${grupo.color}', ${precioFinal})">
                    <div class="grid-media"><b style="font-size:16px; color:#333 !important;">${p.formato}</b></div>
                    <span style="font-size:14px; font-weight:900; color:#333 !important;">
                        ${textoPrecio}
                    </span>
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
        nivelVisual = 2; // Saltar
    } else {
        nivelVisual = 1; // Ir a Variedades
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

        if (id && cant > 0) {
            ids.push(id);
            cants.push(cant);
            precios.push(precio);
        }
    });

    if (ids.length === 0) { Swal.fire("Alerta", "El pedido no puede quedar vacío.", "warning"); return; }

    const btn = document.getElementById('btn-guardar-edicion');
    const txtOriginal = btn.innerText;
    btn.innerText = "GUARDANDO..."; btn.disabled = true;

    const formData = new FormData();
    formData.append('wp_user', obtenerEmailLimpio());
    formData.append('action', 'update_order_items');
    formData.append('id_pedido', idPedidoEnEdicion);
    formData.append('producto', ids.join(' | '));
    formData.append('cantidad', cants.join(' | '));
    formData.append('precios_venta', precios.join(' | '));

    try {
        const res = await fetch(URL_EDITAR_API, { method: 'POST', body: formData });
        const data = await res.json();
        if (data.status === 'success') {
            cerrarEditor();
            Swal.fire('Éxito', 'Pedido editado correctamente', 'success');
            cargarPedidos(true); // Recargar la lista
        } else {
            Swal.fire("Error", data.message, "error");
        }
    } catch (e) { console.error(e); Swal.fire("Error", "Fallo de conexión", "error"); }
    finally { btn.innerText = txtOriginal; btn.disabled = false; }
}

// --- FUNCIÓN GENERAR FACTURA ---
async function generarFactura(idPedido, btn) {
    const confirm = await Swal.fire({
        title: '¿Generar Factura SII?',
        text: "Se emitirá el documento electrónico oficial.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, emitir',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> EMITIENDO...';
    btn.disabled = true;
    btn.style.opacity = "0.7";

    try {
        const formData = new FormData();
        formData.append('id_pedido', idPedido);
        formData.append('tipo_doc', 'factura');

        // SOLUCIÓN: Usar getApi()
        const URL_PROCESAR = window.getApi('procesar_facturacion.php');
        const response = await fetch(URL_PROCESAR, { method: 'POST', body: formData });
        const text = await response.text();
        let data;
        try { data = JSON.parse(text); } catch (err) { throw new Error("Respuesta inválida del servidor"); }

        if (data.status === 'success') {
            await Swal.fire({ icon: 'success', title: '¡Factura Generada!', text: `Folio: ${data.folio}`, timer: 2000, showConfirmButton: false });
            cargarPedidos(true);
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Error desconocido' });
            btn.innerHTML = textoOriginal;
            btn.disabled = false;
            btn.style.opacity = "1";
        }
    } catch (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Fallo de conexión' });
        btn.innerHTML = textoOriginal;
        btn.disabled = false;
        btn.style.opacity = "1";
    }
}

// ----------------------------------------------------
// MASIVO & WHATSAPP
// ----------------------------------------------------
// ----------------------------------------------------
// MASIVO & WHATSAPP
// ----------------------------------------------------
async function abrirMenuMasivo() {
    const hoy = new Date().toISOString().split('T')[0];

    const { value: fechaSeleccionada } = await Swal.fire({
        title: 'Envío Masivo',
        html: 'Selecciona la fecha de despacho.',
        input: 'date',
        inputValue: hoy,
        confirmButtonText: '🔍 Cargar Lista',
        confirmButtonColor: '#0F4B29',
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        scrollbarPadding: false,
        heightAuto: false
    });

    if (!fechaSeleccionada) return;

    Swal.fire({
        title: 'Analizando...',
        didOpen: () => Swal.showLoading()
    });

    try {
        const URL_ENTREGADOS = window.getApi('api_entregados.php');
        const sep = URL_ENTREGADOS.includes('?') ? '&' : '?';
        const response = await fetch(`${URL_ENTREGADOS}${sep}limit=500&fecha=${fechaSeleccionada}`);

        const data = await response.json();
        const unicosMap = {};
        data.forEach(p => { if (!unicosMap[p.id_pedido]) unicosMap[p.id_pedido] = p; });
        const unicosArray = Object.values(unicosMap);
        const pendientes = unicosArray.filter(p => p.url_factura && p.url_factura !== 'null');

        if (pendientes.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin facturas', text: 'No hay facturas cargadas para esa fecha.' });
            return;
        }

        mostrarListaConfirmacion(pendientes, fechaSeleccionada);
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión.' });
    }
}

async function mostrarListaConfirmacion(lista, fecha) {
    let htmlLista = `<div class="lista-masiva-container">`;
    lista.forEach(p => {
        let checked = !p.whatsapp_enviado ? 'checked' : '';
        htmlLista += `
            <div class="item-masivo">
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="checkbox" class="chk-masivo" value="${p.id_pedido}" ${checked} onchange="actualizarContador()">
                    <div style="text-align:left;">
                        <strong style="font-size:13px; color:#333;">
                            ${p.cliente} 
                            <button type="button" class="btn-ver-factura-masivo" onclick="abrirModal('${p.url_factura}')" title="Ver Factura" style="background:none; border:none; color:#2980b9; cursor:pointer;">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </strong><br>
                        <small style="color:#777;">N° ${p.numero_factura || 'S/N'}</small>
                    </div>
                </div>
                <span style="color:${p.whatsapp_enviado ? '#27ae60' : '#e67e22'}; font-weight:bold; font-size:10px;">
                    ${p.whatsapp_enviado ? 'ENVIADO' : 'PENDIENTE'}
                </span>
            </div>`;
    });
    htmlLista += `</div>`;

    window.tempListaMasiva = lista;
    const result = await Swal.fire({
        title: 'Seleccionar Envíos', width: '550px', icon: 'warning',
        html: `<div class="masivo-summary-box"><strong id="live-count">Se enviarán ${lista.length} Facturas</strong><small>Fecha: ${fecha.split('-').reverse().join('/')}</small></div><div class="masivo-channels"><input type="checkbox" id="ws-switch" class="channel-chk" checked><label for="ws-switch" class="channel-btn"><i class="fa-brands fa-whatsapp"></i> WhatsApp</label><input type="checkbox" id="mail-switch" class="channel-chk"><label for="mail-switch" class="channel-btn"><i class="fa-solid fa-envelope"></i> Email</label></div><label style="display:block; text-align:left; margin-bottom:5px; font-size:13px; font-weight:bold; color:#333;"><input type="checkbox" checked onclick="toggleMaster(this)" style="transform:scale(1.1); margin-right:5px; accent-color:#0F4B29;"> Seleccionar Todos</label>${htmlLista}<button type="button" class="link-solo-marcar" onclick="ejecutarSoloMarcar()">Solo marcar como enviado</button>`,
        showCancelButton: true, confirmButtonColor: '#25D366', confirmButtonText: '🚀 ENVIAR AHORA', cancelButtonText: 'Cancelar',
        scrollbarPadding: false,
        heightAuto: false,
        didOpen: () => { actualizarContador(); },
        preConfirm: () => {
            const ids = Array.from(document.querySelectorAll('.chk-masivo:checked')).map(el => el.value);
            const ws = document.getElementById('ws-switch').checked;
            const mail = document.getElementById('mail-switch').checked;
            if (ids.length === 0) { Swal.showValidationMessage('Selecciona al menos un cliente'); return false; }
            if (!ws && !mail) { Swal.showValidationMessage('Elige WhatsApp o Email'); return false; }
            return { ids, ws, mail, soloMarcar: false };
        }
    });
    if (result.isConfirmed) ejecutarEnvioCola(lista, result.value);
}

function actualizarContador() { const seleccionados = document.querySelectorAll('.chk-masivo:checked').length; const label = document.getElementById('live-count'); if (label) label.innerText = `Se enviarán ${seleccionados} Facturas`; }
function toggleMaster(master) { document.querySelectorAll('.chk-masivo').forEach(c => c.checked = master.checked); actualizarContador(); }
function ejecutarSoloMarcar() {
    const ids = Array.from(document.querySelectorAll('.chk-masivo:checked')).map(el => el.value);
    if (ids.length === 0) { Swal.showValidationMessage('Selecciona al menos uno'); return; }
    const listaActual = window.tempListaMasiva; Swal.close(); ejecutarEnvioCola(listaActual, { ids, ws: false, mail: false, soloMarcar: true });
}

// --- FUNCIÓN DE ENVÍO MASIVO CORREGIDA (SOLO FACTURACIÓN) ---
async function ejecutarEnvioCola(dataOriginal, config) {
    const { ids, ws, mail, soloMarcar } = config;
    let ok = 0;
    Swal.fire({ title: soloMarcar ? 'Actualizando...' : 'Enviando...', html: `Iniciando...`, allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    // SOLUCIÓN: Instanciar APIs aquí
    const URL_ENVIAR = window.getApi('enviar_factura.php');
    const URL_ENVIAR_MAIL = window.getApi('enviar_factura_email.php');

    for (let i = 0; i < ids.length; i++) {
        const id = ids[i];
        const p = dataOriginal.find(x => x.id_pedido == id);
        if (!p) continue;

        Swal.update({ html: `Procesando <b>${i + 1} / ${ids.length}</b><br>${p.cliente}` });

        try {
            if (soloMarcar) {
                const fd = new FormData();
                fd.append('id_pedido', p.id_pedido);
                fd.append('solo_marcar', '1');
                await fetch(URL_ENVIAR, { method: 'POST', body: fd });
                ok++;
            }
            else {
                // --- WHATSAPP (A TELEFONO_FACTURA) ---
                if (ws) {
                    let rawTel = p.telefono_factura || '';
                    rawTel = rawTel.replace(/[^0-9]/g, '');

                    if (rawTel.length >= 8) {
                        let telFinal = rawTel.startsWith('56') ? '+' + rawTel : '+56' + rawTel;

                        const fd = new FormData();
                        fd.append('telefono_factura', telFinal); // CLAVE: telefono_factura
                        fd.append('nombre', p.saludo_whatsapp || "");
                        fd.append('cliente', p.cliente);
                        fd.append('folio', p.numero_factura);
                        fd.append('url_pdf', p.url_factura);
                        fd.append('id_pedido', p.id_pedido);

                        await fetch(URL_ENVIAR, { method: 'POST', body: fd });
                    }
                }

                // --- EMAIL (A EMAIL_FACTURA) ---
                if (mail && p.email_factura) {
                    const fdMail = new FormData();
                    fdMail.append('id_pedido', p.id_pedido);
                    fdMail.append('email_factura', p.email_factura); // CLAVE: email_factura
                    fdMail.append('url_pdf', p.url_factura);
                    fdMail.append('cliente', p.cliente);
                    fdMail.append('folio', p.numero_factura);

                    await fetch(URL_ENVIAR_MAIL, { method: 'POST', body: fdMail });
                }
                ok++;
            }
        } catch (e) { console.error(e); }

        if (!soloMarcar && i < ids.length - 1) await new Promise(r => setTimeout(r, 200));
    }
    Swal.fire({ icon: 'success', title: 'Terminado', text: `${ok} registros procesados.` });
    cargarPedidos(true);
}

// --- FUNCIÓN DE WHATSAPP INDIVIDUAL (MODIFICADA) ---
async function enviarPruebaWhatsApp(saludo, urlPdf, idPedido, telefonoFacturaReal, folio) {
    if (!urlPdf || urlPdf === 'null') { Swal.fire({ icon: 'warning', title: 'Falta Documento', text: 'No hay factura cargada.' }); return; }

    let nombreEmpresa = "Cliente";
    if (pedidosGlobales[idPedido]) {
        nombreEmpresa = pedidosGlobales[idPedido].cliente;
        if (!folio) folio = pedidosGlobales[idPedido].folio;
    }

    const btn = document.getElementById(`btn-ws-${idPedido}`);
    const originalHTML = btn.innerHTML;
    const originalClass = btn.className;

    // --- AQUÍ ESTÁ EL CAMBIO ---
    // Ahora muestra "Se enviará al número de: [NOMBRE]"
    const result = await Swal.fire({
        title: '¿Enviar por WhatsApp?',
        html: `Se enviará al número de: <b>${saludo}</b><br><b>${telefonoFacturaReal}</b><br><small>Factura N°: ${folio}</small>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#25D366',
        confirmButtonText: 'Sí, enviar'
    });

    if (!result.isConfirmed) return;

    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    btn.disabled = true;

    const datos = new FormData();
    datos.append('telefono_factura', telefonoFacturaReal);
    datos.append('nombre', saludo);
    datos.append('cliente', nombreEmpresa);
    datos.append('folio', folio);
    datos.append('url_pdf', urlPdf);
    datos.append('id_pedido', idPedido);

    try {
        // SOLUCIÓN: Usar getApi()
        const URL_ENVIAR = window.getApi('enviar_factura.php');
        const response = await fetch(URL_ENVIAR, { method: 'POST', body: datos });
        const res = await response.json();

        if (res.status === 'ok') {
            const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true });
            Toast.fire({ icon: 'success', title: 'Enviado correctamente' });
            btn.className = 'btn-whatsapp-sent';
            btn.innerHTML = '<i class="fa-solid fa-check-double"></i> <span>REENVIAR</span>';
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: res.message });
            btn.innerHTML = originalHTML;
            btn.className = originalClass;
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Fallo de conexión' });
        btn.innerHTML = originalHTML;
        btn.className = originalClass;
    } finally {
        btn.disabled = false;
    }
}
// =============================================================================
// 4. FUNCIONES AUXILIARES
// =============================================================================

function mostrarLoading(show) {
    const el = document.getElementById('loading-txt');
    if (el) el.style.display = show ? 'block' : 'none';
}

function iniciarObserver() {
    const sentinel = document.getElementById('sentinel');
    const options = { root: null, rootMargin: '100px', threshold: 0.1 };
    observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && hayMasDatos && !cargando) cargarPedidos(false);
    }, options);
    if (sentinel) observer.observe(sentinel);
}

async function obtenerComuna(lat, lng, idPedido) {
    const contenedor = document.getElementById(`comuna-${idPedido}`);
    if (!contenedor || !contenedor.innerText.includes('ESPERANDO')) return;
    try {
        const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`;
        const res = await fetch(url, { headers: { 'Accept-Language': 'es' } });
        const data = await res.json();
        const comuna = data.display_name ? data.display_name.split(',')[0].trim().toUpperCase() : 'VER MAPA';
        contenedor.innerHTML = `📍 ${comuna}`;
    } catch (e) {
        contenedor.innerHTML = `<span onclick="obtenerComuna(${lat}, ${lng}, '${idPedido}')" style="cursor:pointer; color:#F57C00;">📍 REINTENTAR</span>`;
    }
}

function toggleFlip(id, lat, lng) {
    const card = document.getElementById(`card-${id}`);
    if (!card) return;
    card.classList.toggle('is-flipped');
    if (card.classList.contains('is-flipped') && lat && lng) {
        const cComuna = document.getElementById(`comuna-${id}`);
        if (cComuna && cComuna.innerText.includes('ESPERANDO')) obtenerComuna(lat, lng, id);
    }
}

async function subirDoc(input, id, tipo) {
    if (!input.files[0]) return;
    const formData = new FormData();
    formData.append('id_pedido', id);
    let action = tipo === 'factura' ? 'update_admin_order' : 'upload_guia_despacho';
    let fileField = tipo === 'factura' ? 'pdf_factura' : 'foto_guia';
    formData.append('action', action);
    formData.append(fileField, input.files[0]);
    input.parentElement.innerText = "⏳...";
    try {
        // SOLUCIÓN: Usar getApi()
        const URL_UPLOAD = window.getApi('upload.php');
        await fetch(URL_UPLOAD, { method: 'POST', body: formData });
        cargarPedidos(true);
    } catch (e) { alert("Error al subir"); }
}

function abrirModal(url) {
    const modal = document.getElementById('modal-factura');
    const ventana = document.getElementById('ventana-dinamica');
    const frame = document.getElementById('frame-modal');
    const img = document.getElementById('img-modal');
    document.body.classList.add('modal-abierto');
    frame.style.display = "none"; img.style.display = "none";
    const extension = url.toLowerCase().split('.').pop();

    // SOLUCIÓN: Usar getApi() + separador dinámico para el XML
    const URL_VER_FACTURA = window.getApi('ver-factura.php');
    const sep = URL_VER_FACTURA.includes('?') ? '&' : '?';

    if (['jpg', 'jpeg', 'png', 'webp'].includes(extension)) {
        ventana.className = "modal-ventana modo-guia";
        img.src = url; img.style.display = "block";
    } else {
        ventana.className = "modal-ventana modo-factura";
        frame.src = extension === 'xml' ? `${URL_VER_FACTURA}${sep}xml=${encodeURIComponent(url)}` : url;
        frame.style.display = "block";
    }
    modal.style.display = "flex";
}

function cerrarModal() {
    document.getElementById('modal-factura').style.display = "none";
    document.body.classList.remove('modal-abierto');
    document.getElementById('frame-modal').src = "";
}