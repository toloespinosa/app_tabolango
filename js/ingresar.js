// CONFIGURACIÓN API
const URL_API_FORM = window.getApi('form.php');
const URL_API_CLI = window.getApi('detalle-cliente.php?action=list_clients');
// La API de productos suele ser api.php
const URL_API_PROD = window.getApi('api.php?action=get_products');

let listaProductosMaster = [];
let listaClientesMaster = [];
let contadorFilas = 0;
let filaActivaParaProducto = null;
let grupoSeleccionado = null;

// --- VARIABLE GLOBAL NUEVA ---
let nombreCategoriaClienteActiva = "";

// NIVELES: 0=Categoria, 1=Variedad, 2=Calibre, 3=Formato
let nivelProd = 0;
let seleccionadoCat = null;
let seleccionadoVar = null;
let seleccionadoCal = null;

// INICIALIZACIÓN PRINCIPAL
document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificamos que estamos en la página correcta (opcional pero recomendado)
    const orderForm = document.getElementById('orderForm');
    if (!orderForm) return;

    inyectarModales();

    try {
        // Usamos la función global
        const userEmail = window.obtenerEmailLimpio();

        const [resCli, resProd] = await Promise.all([
            fetch(`${URL_API_CLI}&wp_user=${encodeURIComponent(userEmail)}`).then(r => r.json()),
            fetch(URL_API_PROD).then(r => r.json()) // <-- Corregido el endpoint
        ]);

        procesarClientesAgrupados(resCli.clientes || []);
        procesarProductosAgrupados(resProd || []);

        document.getElementById('display_cliente').onclick = () => window.abrirModal('modal-clientes');
        window.agregarFilaProducto();

    } catch (e) {
        console.error("Error inicializando:", e);
    }

    // 2. CORRECCIÓN CRÍTICA: El onsubmit va adentro del DOMContentLoaded
    orderForm.onsubmit = async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnEnviar');
        const cli = document.getElementById('cliente_hidden').value;
        if (!cli || !document.getElementById('fecha_entrega').value) return alert("Faltan datos");

        let arrayProds = [], arrayCants = [];
        document.querySelectorAll('.m-fila[data-confirmada="true"]').forEach(f => {
            const idProducto = f.querySelector('.p-sel-hidden').value;
            const cantidad = f.querySelector('.p-qty').value;
            arrayProds.push(idProducto);
            arrayCants.push(cantidad);
        });

        if (!arrayProds.length) return alert("Confirme productos con ✓");

        btn.disabled = true;
        document.getElementById('btnText').innerText = "REGISTRANDO...";

        const fd = new FormData();
        fd.append('cliente', cli);
        fd.append('fecha_entrega', document.getElementById('fecha_entrega').value);
        fd.append('producto', arrayProds.join(' | '));
        fd.append('cantidad', arrayCants.join(' | '));
        fd.append('observaciones', document.getElementById('observaciones').value);
        fd.append('usuario_wp', window.obtenerEmailLimpio());

        try {
            const res = await fetch(URL_API_FORM, { method: 'POST', body: fd }).then(r => r.json());
            if (res.status === 'success') {
                alert("✅ Pedido Registrado: " + res.pedido);
                location.reload();
            }
            else { alert("Error: " + res.message); btn.disabled = false; document.getElementById('btnText').innerText = "REGISTRAR PEDIDO"; }
        } catch (err) { alert("Error de red"); btn.disabled = false; document.getElementById('btnText').innerText = "REGISTRAR PEDIDO"; }
    };
});

// --- LÓGICA DE CLIENTES ---
function procesarClientesAgrupados(clientesRaw) {
    const clientesActivos = clientesRaw.filter(c => c.activo == "1");
    const grupos = clientesActivos.reduce((acc, curr) => {
        const nombreBase = curr.cliente.split(' - ')[0].trim();
        if (!acc[nombreBase]) {
            acc[nombreBase] = { nombreRaiz: nombreBase, sucursales: [], dataGlobal: null };
        }
        acc[nombreBase].sucursales.push(curr);
        if (curr.es_global == 1 || curr.es_global == "1" || !curr.cliente.includes(' - ')) {
            acc[nombreBase].dataGlobal = curr;
        }
        return acc;
    }, {});
    listaClientesMaster = Object.values(grupos);
}


// --- LÓGICA DE CLIENTES ---
function procesarClientesAgrupados(clientesRaw) {
    const clientesActivos = clientesRaw.filter(c => c.activo == "1");

    const grupos = clientesActivos.reduce((acc, curr) => {
        const nombreBase = curr.cliente.split(' - ')[0].trim();
        if (!acc[nombreBase]) {
            acc[nombreBase] = { nombreRaiz: nombreBase, sucursales: [], dataGlobal: null, total_grupo: 0 };
        }
        acc[nombreBase].sucursales.push(curr);

        // Sumamos las compras (Viene del PHP)
        acc[nombreBase].total_grupo += parseFloat(curr.total_comprado || 0);

        if (curr.es_global == 1 || curr.es_global == "1" || !curr.cliente.includes(' - ')) {
            acc[nombreBase].dataGlobal = curr;
        }
        return acc;
    }, {});

    // Convertimos el objeto en Array
    listaClientesMaster = Object.values(grupos);

    // 🔥 Ordenamos los grupos de clientes de mayor a menor venta
    listaClientesMaster.sort((a, b) => b.total_grupo - a.total_grupo);

    // 🔥 Ordenamos las sucursales internamente de mayor a menor venta
    listaClientesMaster.forEach(grupo => {
        grupo.sucursales.sort((a, b) => parseFloat(b.total_comprado || 0) - parseFloat(a.total_comprado || 0));
    });
}

// --- LÓGICA DE PRODUCTOS ---
function procesarProductosAgrupados(productosRaw) {
    const grupos = {};
    productosRaw.forEach(curr => {
        const productoNombre = curr.producto || "Sin Nombre";

        // 🔥 CORRECCIÓN: Leemos "variedad" en minúscula (como viene de la BD)
        const valorVariedad = curr.variedad || curr.Variedad || "";
        const variedad = (valorVariedad.trim() !== "") ? valorVariedad.trim() : "";

        const calibre = curr.calibre || "S/C";
        const formato = curr.formato || "Unidad";

        if (!grupos[productoNombre]) {
            grupos[productoNombre] = {
                nombre: productoNombre,
                icono: curr.icono || '📦',
                color: curr.color_diferenciador || '#E98C00',
                variedades: {}
            };
        }
        if (!grupos[productoNombre].variedades[variedad]) {
            grupos[productoNombre].variedades[variedad] = { nombreVar: variedad, calibres: {} };
        }
        if (!grupos[productoNombre].variedades[variedad].calibres[calibre]) {
            grupos[productoNombre].variedades[variedad].calibres[calibre] = {};
        }
        grupos[productoNombre].variedades[variedad].calibres[calibre][formato] = curr;
    });
    listaProductosMaster = Object.values(grupos);
}


// --- FUNCIONES EXPUESTAS A WINDOW (Para que los onclick funcionen) ---

window.renderizarGridProductos = async function (filtro = "") {
    const grid = document.getElementById('grid-productos');
    const titulo = document.querySelector('#modal-productos h3');
    grid.innerHTML = "";
    const txt = filtro.toLowerCase();

    if (nivelProd === 0) {
        titulo.innerText = "Categorías";
        listaProductosMaster.forEach(g => {
            if (g.nombre.toLowerCase().includes(txt)) {
                grid.innerHTML += `
                    <div class="grid-item" onclick="window.seleccionarNivelCategoria('${g.nombre.replace(/'/g, "\\'")}')">
                        <div class="grid-media" style="font-size:35px;">${g.icono}</div>
                        <span>${g.nombre}</span>
                        <div class="card-color-footer" style="background:${g.color}"></div>
                    </div>`;
            }
        });
    }
    else if (nivelProd === 1) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
        titulo.innerText = "Variedad";
        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelProd=0; window.renderizarGridProductos()">⬅ VOLVER A CATEGORÍAS</div>`;

        Object.keys(grupo.variedades).forEach(vKey => {
            const labelVar = vKey === "" ? "Estándar" : vKey;
            grid.innerHTML += `
                <div class="grid-item" onclick="nivelProd=2; seleccionadoVar='${vKey}'; window.renderizarGridProductos()">
                    <div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${labelVar.substring(0, 3).toUpperCase()}</b></div>
                    <span>${labelVar}</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
        });
    }
    else if (nivelProd === 2) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
        const varObj = grupo.variedades[seleccionadoVar];

        titulo.innerText = seleccionadoVar ? `${seleccionadoCat} ${seleccionadoVar}` : `${seleccionadoCat}`;

        const tieneVariedadReal = Object.keys(grupo.variedades).length > 1 || (Object.keys(grupo.variedades)[0] !== "");
        const actionVolver = tieneVariedadReal ? "nivelProd=1" : "nivelProd=0";
        const textoVolver = tieneVariedadReal ? "VARIEDADES" : "CATEGORÍAS";

        grid.innerHTML = `<div class="btn-volver-modal" onclick="${actionVolver}; window.renderizarGridProductos()">⬅ VOLVER A ${textoVolver}</div>`;

        Object.keys(varObj.calibres).forEach(cal => {
            grid.innerHTML += `
                <div class="grid-item" onclick="nivelProd=3; seleccionadoCal='${cal}'; window.renderizarGridProductos()">
                    <div class="grid-media"><b style="font-size:20px; color:${grupo.color}">${cal}</b></div>
                    <span>CALIBRE</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
        });
    }
    else if (nivelProd === 3) {
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
        const formatos = grupo.variedades[seleccionadoVar].calibres[seleccionadoCal];
        const clienteId = document.getElementById('cliente_hidden').value;

        titulo.innerText = `${seleccionadoCal} - ${seleccionadoCat}`;

        grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelProd=2; window.renderizarGridProductos()">⬅ VOLVER A CALIBRES</div>`;
        grid.innerHTML += `<div id="alerta-precios" class="alerta-precio-cat"></div>`;
        grid.innerHTML += `<div id="loading-prices" style="grid-column:1/-1; text-align:center; padding:10px; font-size:12px; color:#666;">Actualizando precios...</div>`;

        let flagAlertaActivada = false;

        for (const ftoKey of Object.keys(formatos)) {
            const p = formatos[ftoKey];
            let precioFinal = 0;
            let esEspecial = false;

            if (clienteId) {
                try {
                    const resp = await fetch(window.getApi(`form.php?action=get_price_by_client&cliente=${clienteId}&producto=${p.id_producto}`));
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
                const alertaDiv = document.getElementById('alerta-precios');
                if (alertaDiv) {
                    alertaDiv.style.display = 'block';
                    const nombreMostrar = nombreCategoriaClienteActiva ? nombreCategoriaClienteActiva.toUpperCase() : 'ESPECIALES';
                    alertaDiv.innerHTML = `🔔 SE APLICAN PRECIOS ${nombreMostrar}`;
                    flagAlertaActivada = true;
                }
            }

            const nombreFull = seleccionadoVar ? `${p.producto} ${seleccionadoVar}` : p.producto;

            const itemHtml = `
                <div class="grid-item" onclick="window.finalizarSeleccionProducto('${p.id_producto}', '${nombreFull.replace(/'/g, "\\'")}', '${p.calibre}', '${p.formato}', ${precioFinal}, '${grupo.color}')">
                    <div class="grid-media"><b>${p.formato}</b></div>
                    <span style="font-size:15px; font-weight:900; color:#333;">
                        ${precioFinal > 0 ? window.formatearDinero(precioFinal) : 'Consultar'}
                    </span>
                    <div class="card-color-footer" style="background:${esEspecial ? '#E98C00' : '#27ae60'}"></div>
                </div>`;

            grid.insertAdjacentHTML('beforeend', itemHtml);
        }
        const loader = document.getElementById('loading-prices');
        if (loader) loader.remove();
    }
};

window.renderizarGridClientes = function (filtro = "") {
    const grid = document.getElementById('grid-clientes');
    const titulo = document.getElementById('titulo-modal-cli');
    grid.innerHTML = "";
    const txt = filtro.toLowerCase();

    if (!grupoSeleccionado) {
        titulo.innerText = "CLIENTES";
        listaClientesMaster.forEach(grupo => {
            if (grupo.nombreRaiz.toLowerCase().includes(txt)) {
                const tieneSucursales = grupo.sucursales.length > 1;
                const principal = grupo.dataGlobal || grupo.sucursales[0];
                const tieneLogo = principal.logo && principal.logo.length > 10 && principal.logo !== "null";
                const logoHtml = tieneLogo
                    ? `<img src="${principal.logo}">`
                    : `<div style="width:50px; height:50px; border-radius:50%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-weight:800; color:#E98C00; border:2px solid #E98C00;">${grupo.nombreRaiz.charAt(0)}</div>`;

                const catName = principal.nombre_categoria_cliente || '';

                const clickAction = tieneSucursales
                    ? `window.abrirSucursales('${grupo.nombreRaiz.replace(/'/g, "\\'")}')`
                    : `window.seleccionarCliente('${principal.cliente.replace(/'/g, "\\'")}', '${principal.id_interno}', '${catName.replace(/'/g, "\\'")}')`;

                grid.innerHTML += `
                    <div class="grid-item" onclick="${clickAction}" style="position:relative;">
                        ${tieneSucursales ? `<div style="position:absolute; top:8px; right:8px; background:#ff9500; color:white; font-size:10px; padding:2px 6px; border-radius:10px; font-weight:bold;">${grupo.sucursales.length - 1} SEDES</div>` : ''}
                        <div class="grid-media">${logoHtml}</div>
                        <span>${grupo.nombreRaiz}</span>
                        <div class="card-color-footer" style="background: ${tieneSucursales ? '#ff9500' : '#eee'}"></div>
                    </div>`;
            }
        });
    } else {
        const grupo = listaClientesMaster.find(g => g.nombreRaiz === grupoSeleccionado);
        titulo.innerText = grupoSeleccionado;
        grid.innerHTML = `<div onclick="grupoSeleccionado=null; window.renderizarGridClientes()" style="grid-column:1/-1; background:#fff3e0; padding:12px; text-align:center; border-radius:12px; cursor:pointer; font-weight:bold; color:#ff9500; border:1px solid #ff9500; margin-bottom:10px;">⬅ VOLVER A CLIENTES</div>`;
        const sucursalesFiltradas = grupo.sucursales.filter(s => s.es_global != 1 && s.es_global != "1" && s.cliente.includes(' - '));
        sucursalesFiltradas.forEach(s => {
            if (s.cliente.toLowerCase().includes(txt)) {
                const tieneLogo = s.logo && s.logo.length > 10 && s.logo !== "null";
                const logoHtml = tieneLogo ? `<img src="${s.logo}">` : `<div style="width:50px; height:50px; border-radius:50%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-weight:800; color:#888;">${s.cliente.charAt(0)}</div>`;
                const nombreSucursal = s.cliente.split(' - ')[1] || s.cliente;
                const catNameSuc = s.nombre_categoria_cliente || '';

                grid.innerHTML += `
                    <div class="grid-item" onclick="window.seleccionarCliente('${s.cliente.replace(/'/g, "\\'")}', '${s.id_interno}', '${catNameSuc.replace(/'/g, "\\'")}')">
                        <div class="grid-media">${logoHtml}</div>
                        <span>${nombreSucursal}</span>
                        <div class="card-color-footer" style="background:#eee"></div>
                    </div>`;
            }
        });
    }
};

window.seleccionarCliente = async function (nombre, id, categoria = "") {
    document.getElementById('display_cliente').innerHTML = `<span style="color:#333">${nombre}</span>`;
    document.getElementById('cliente_hidden').value = id;
    nombreCategoriaClienteActiva = categoria;

    document.querySelectorAll('.m-fila').forEach(f => {
        f.removeAttribute('data-price');
        f.querySelector('.p-name-display').innerText = "Toca para elegir...";
        f.querySelector('.p-sel-hidden').value = "";
    });
    window.calc();
    window.cerrarModal('modal-clientes');
};

window.seleccionarNivelCategoria = function (nombreCat) {
    seleccionadoCat = nombreCat;
    const grupo = listaProductosMaster.find(g => g.nombre === nombreCat);
    const variedadesKeys = Object.keys(grupo.variedades);
    if (variedadesKeys.length === 1 && variedadesKeys[0] === "") {
        seleccionadoVar = "";
        nivelProd = 2;
    } else {
        nivelProd = 1;
    }
    window.renderizarGridProductos();
};

window.finalizarSeleccionProducto = function (id, nombre, calibre, formato, precio, color) {
    const fila = document.getElementById(`f-${filaActivaParaProducto}`);
    fila.querySelector('.p-sel-hidden').value = id;
    fila.querySelector('.p-name-display').innerHTML = `
        <div style="color:#333; font-weight:bold;">${nombre}</div>
        <div style="font-size:11px; color:#666;">${calibre} - ${formato}</div>
    `;
    fila.setAttribute('data-price', precio);
    fila.style.borderLeft = `6px solid ${color}`;
    window.cerrarModal('modal-productos');
    window.calc();
};

window.abrirSucursales = function (nombre) {
    grupoSeleccionado = nombre;
    document.getElementById('busquedaCliente').value = "";
    window.renderizarGridClientes();
};

window.abrirModal = function (id) {
    document.getElementById(id).style.display = 'flex';
    document.body.classList.add('modal-open');
    if (id === 'modal-clientes') {
        grupoSeleccionado = null;
        window.renderizarGridClientes();
    } else {
        nivelProd = 0;
        window.renderizarGridProductos();
    }
};

window.cerrarModal = function (id) {
    document.getElementById(id).style.display = 'none';
    document.body.classList.remove('modal-open');
};

window.filtrarGrid = function (gridId, query) {
    const search = query.toLowerCase();
    const items = document.getElementById(gridId).getElementsByClassName('grid-item');
    Array.from(items).forEach(item => { item.style.display = item.innerText.toLowerCase().includes(search) ? '' : 'none'; });
};

window.agregarFilaProducto = function () {
    contadorFilas++;
    const div = document.createElement('div');
    div.className = 'm-fila';
    div.id = `f-${contadorFilas}`;
    div.innerHTML = `
        <div class="display-trigger p-name-display" onclick="window.prepararSeleccionProducto(${contadorFilas})">Toca para elegir...</div>
        <input type="hidden" class="p-sel-hidden">
        <div class="m-fila-controles">
            <input type="number" step="any" class="p-qty" placeholder="Cant." style="flex:1" inputmode="decimal">
            <button type="button" class="m-btn-action m-btn-check" onclick="window.toggleFila(${contadorFilas})">✓</button>
            <button type="button" class="m-btn-action m-btn-remove" onclick="window.removeFila(${contadorFilas})">✕</button>
        </div>`;
    document.getElementById('productos-container').appendChild(div);
    div.querySelector('.p-qty').addEventListener('input', window.calc);
};

window.prepararSeleccionProducto = function (id) {
    filaActivaParaProducto = id;
    window.abrirModal('modal-productos');
};

window.calc = function () {
    let total = 0;
    document.querySelectorAll('.m-fila').forEach(f => {
        const precio = parseFloat(f.getAttribute('data-price')) || 0;
        const inputCant = f.querySelector('.p-qty');
        const cantidad = parseFloat(inputCant.value) || 0;
        total += precio * cantidad;
    });
    document.getElementById('total_pedido_display').innerText = window.formatearDinero(total); // Usa el global
};

window.toggleFila = function (id) {
    const f = document.getElementById(`f-${id}`);
    const isConf = f.getAttribute('data-confirmada') === 'true';
    if (!isConf) {
        if (!f.querySelector('.p-sel-hidden').value || f.querySelector('.p-qty').value <= 0) return;
        f.querySelector('.p-qty').disabled = true;
        f.classList.add('m-fila-confirmada');
        f.setAttribute('data-confirmada', 'true');
        f.querySelector('.m-btn-check').innerHTML = "✎";
        f.querySelector('.m-btn-check').className = "m-btn-action m-btn-edit";
    } else {
        f.querySelector('.p-qty').disabled = false;
        f.classList.remove('m-fila-confirmada');
        f.setAttribute('data-confirmada', 'false');
        f.querySelector('.m-btn-edit').innerHTML = "✓";
        f.querySelector('.m-btn-edit').className = "m-btn-action m-btn-check";
    }
};

window.removeFila = function (id) {
    if (document.querySelectorAll('.m-fila').length > 1) {
        document.getElementById(`f-${id}`).remove();
        window.calc();
    }
};

function inyectarModales() {
    const html = `
    <div id="modal-clientes" class="modal-grid-overlay">
        <div class="modal-grid-content">
            <div class="modal-grid-header">
                <div class="header-top"><h3 id="titulo-modal-cli">Clientes</h3><button type="button" class="btn-cerrar-modal" onclick="window.cerrarModal('modal-clientes')">✕</button></div>
                <div class="header-search"><input type="text" id="busquedaCliente" placeholder="🔍 Buscar cliente..." onkeyup="window.renderizarGridClientes(this.value)"></div>
            </div>
            <div id="grid-clientes" class="grid-container"></div>
        </div>
    </div>
    <div id="modal-productos" class="modal-grid-overlay">
        <div class="modal-grid-content">
            <div class="modal-grid-header">
                <div class="header-top"><h3>Productos</h3><button type="button" class="btn-cerrar-modal" onclick="window.cerrarModal('modal-productos')">✕</button></div>
                <div class="header-search"><input type="text" placeholder="🔍 Buscar producto..." onkeyup="window.filtrarGrid('grid-productos', this.value)"></div>
            </div>
            <div id="grid-productos" class="grid-container"></div>
        </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}