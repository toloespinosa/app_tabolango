document.addEventListener('DOMContentLoaded', async () => {

    console.log("🚀 SISTEMA TABOLANGO: Iniciando App...");

    // ==========================================================================
    // #region 1. CONFIGURACIÓN Y UTILIDADES (CORE)
    // ==========================================================================

    const isLocal = window.location.hostname.includes('.local') || window.location.hostname === 'localhost';
    const BASE_URL = isLocal ? 'http://tabolango-app.local/wp-content/themes/Tabolango/inc' : 'https://tabolango.cl';

    console.log(`📍 Entorno: ${isLocal ? 'LOCAL 💻' : 'PRODUCCIÓN ☁️'}`);

    window.getApi = function (archivo) {
        const archivoLimpio = archivo.startsWith('/') ? archivo.substring(1) : archivo;
        return `${BASE_URL}/${archivoLimpio}`;
    };

    const formatCLP = (v) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(v);

    window.obtenerEmailLimpio = function () {
        const bridge = document.getElementById('session-email-bridge');
        let email = "";
        if (bridge) {
            let emailMatch = (bridge.textContent || bridge.innerText).match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
            if (emailMatch) email = emailMatch[0].toLowerCase().trim();
        }
        if (isLocal && !email) return "jandres@tabolango.cl";
        return email || "Sistema";
    };

    window.moverModalesAlBody = function () {
        const ids = ['contenedor-modales-tabolango', 'modal-comanda', 'modal-factura', 'modal-detalle-pedido', 'modal-editar-pedido', 'modal-selector-visual', 'modal-vista-previa', 'dynamic-qr-modal'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (el && el.parentNode !== document.body) document.body.appendChild(el);
        });
    };

    // Configuración SweetAlert
    const swalConfig = {
        customClass: { popup: 'm-swal-popup', title: 'm-swal-title', confirmButton: 'm-swal-confirm', cancelButton: 'm-swal-cancel' },
        buttonsStyling: true,
        confirmButtonColor: '#0F4B29',
        cancelButtonColor: '#e74c3c'
    };

    // #endregion

    // ==========================================================================
    // #region 2. ESTADO GLOBAL
    // ==========================================================================

    let listaProductosMaster = [];
    let listaClientesMaster = [];
    let listaProductosPlana = [];

    // Variables Ingreso
    let contadorFilas = 0;
    let filaActivaParaProducto = null;
    let grupoSeleccionado = null;
    let nombreCategoriaClienteActiva = "";
    let nivelProd = 0;
    let seleccionadoCat = null;
    let seleccionadoVar = null;
    let seleccionadoCal = null;

    // Variables Dashboard
    window.currentOrders = {};
    let idPedidoEnEdicion = null;
    let clienteIdEditor = null;
    let filaActivaEditor = null;
    let nivelVisual = 0;
    let nombreCategoriaClienteEditor = "";
    let seleccionCatVisual = null;
    let seleccionVarVisual = null;
    let seleccionCalVisual = null;

    const orderForm = document.getElementById('orderForm');
    const ordersGrid = document.getElementById('orders-grid');

    // #endregion

    // ==========================================================================
    // #region 3. PROCESADORES DE DATOS
    // ==========================================================================

    window.cargarDatosGlobales = async function () {
        try {
            const res = await fetch(window.getApi('api.php?action=get_products'));
            const rawData = await res.json();
            listaProductosPlana = rawData;
            procesarProductosAgrupados(rawData);
        } catch (e) { console.error("Error cargando productos", e); }
    };

    window.procesarClientesAgrupados = function (clientesRaw) {
        if (!Array.isArray(clientesRaw)) return;
        const clientesActivos = clientesRaw.filter(c => c.activo == "1");
        const grupos = clientesActivos.reduce((acc, curr) => {
            const nombreBase = curr.cliente.split(' - ')[0].trim();
            if (!acc[nombreBase]) acc[nombreBase] = { nombreRaiz: nombreBase, sucursales: [], dataGlobal: null };
            acc[nombreBase].sucursales.push(curr);
            if (curr.es_global == 1 || curr.es_global == "1" || !curr.cliente.includes(' - ')) acc[nombreBase].dataGlobal = curr;
            return acc;
        }, {});
        listaClientesMaster = Object.values(grupos);
    };

    window.procesarProductosAgrupados = function (productosRaw) {
        if (!Array.isArray(productosRaw)) return;
        const grupos = {};
        productosRaw.forEach(curr => {
            const prodName = curr.producto || "Sin Nombre";
            const variedad = (curr.Variedad || curr.variedad || "").trim();
            const calibre = curr.calibre || "S/C";
            const formato = curr.formato || "Unidad";

            if (!grupos[prodName]) grupos[prodName] = { nombre: prodName, icono: curr.icono || '📦', color: curr.color_diferenciador || '#E98C00', variedades: {} };
            if (!grupos[prodName].variedades[variedad]) grupos[prodName].variedades[variedad] = { nombreVar: variedad, calibres: {} };
            if (!grupos[prodName].variedades[variedad].calibres[calibre]) grupos[prodName].variedades[variedad].calibres[calibre] = {};

            grupos[prodName].variedades[variedad].calibres[calibre][formato] = curr;
        });
        listaProductosMaster = Object.values(grupos);
    };

    // Control Genérico de Modales
    window.abrirModal = function (idOrUrl) {
        if (idOrUrl.includes('/') || idOrUrl.includes('.')) {
            const modal = document.getElementById('modal-factura');
            const container = document.getElementById('modal-body-content');
            if (modal && container) {
                container.innerHTML = idOrUrl.toLowerCase().endsWith('.pdf') ? `<iframe src="${idOrUrl}" style="width:100%; height:70vh; border:none;"></iframe>` : `<img src="${idOrUrl}" style="max-width:100%;">`;
                modal.style.display = 'flex';
            }
        } else {
            const el = document.getElementById(idOrUrl);
            if (el) {
                el.style.display = 'flex';
                document.body.classList.add('modal-open');
                if (idOrUrl === 'modal-clientes') { grupoSeleccionado = null; renderizarGridClientes(); }
                if (idOrUrl === 'modal-productos') { nivelProd = 0; renderizarGridProductos(); }
            }
        }
    };

    window.cerrarModal = function (id) {
        const target = id || 'modal-factura';
        const el = document.getElementById(target);
        if (el) { el.style.display = 'none'; document.body.classList.remove('modal-open'); }
    };

    window.filtrarGrid = function (gridId, query) {
        const search = query.toLowerCase();
        const grid = document.getElementById(gridId);
        if (grid) {
            const items = grid.getElementsByClassName('grid-item');
            Array.from(items).forEach(item => { item.style.display = item.innerText.toLowerCase().includes(search) ? '' : 'none'; });
        }
    };

    // #endregion
    // ==========================================================================


    // #region 4. PÁGINA: INGRESAR PEDIDO
    // ==========================================================================

    // --- Funciones UI del Formulario ---
    window.agregarFilaProducto = function () {
        contadorFilas++;
        const div = document.createElement('div');
        div.className = 'm-fila';
        div.id = `f-${contadorFilas}`;
        div.innerHTML = `
            <div class="display-trigger p-name-display" onclick="prepararSeleccionProducto(${contadorFilas})">Toca para elegir...</div>
            <input type="hidden" class="p-sel-hidden">
            <div class="m-fila-controles">
                <input type="number" step="any" class="p-qty" placeholder="Cant." style="flex:1" inputmode="decimal">
                <button type="button" class="m-btn-action m-btn-check" onclick="toggleFila(${contadorFilas})">✓</button>
                <button type="button" class="m-btn-action m-btn-remove" onclick="removeFila(${contadorFilas})">✕</button>
            </div>`;
        const container = document.getElementById('productos-container');
        if (container) {
            container.appendChild(div);
            div.querySelector('.p-qty').addEventListener('input', window.calc);
        }
    };

    window.removeFila = function (id) {
        if (document.querySelectorAll('.m-fila').length > 1) {
            document.getElementById(`f-${id}`).remove();
            window.calc();
        }
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

    window.calc = function () {
        let total = 0;
        document.querySelectorAll('.m-fila').forEach(f => {
            const precio = parseFloat(f.getAttribute('data-price')) || 0;
            const inputCant = f.querySelector('.p-qty');
            const cantidad = parseFloat(inputCant.value) || 0;
            total += precio * cantidad;
        });
        const display = document.getElementById('total_pedido_display');
        if (display) display.innerText = formatCLP(total);
    };

    window.prepararSeleccionProducto = function (id) {
        filaActivaParaProducto = id;
        window.abrirModal('modal-productos');
    };

    // --- Renderizador de Clientes ---
    window.renderizarGridClientes = function (filtro = "") {
        const grid = document.getElementById('grid-clientes');
        if (!grid) return;
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

                    const catName = (principal.nombre_categoria_cliente || '').replace(/'/g, "\\'");

                    const clickAction = tieneSucursales
                        ? `abrirSucursales('${grupo.nombreRaiz.replace(/'/g, "\\'")}')`
                        : `seleccionarCliente('${principal.cliente.replace(/'/g, "\\'")}', '${principal.id_interno}', '${catName}')`;

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
            grid.innerHTML = `<div onclick="grupoSeleccionado=null;renderizarGridClientes()" style="grid-column:1/-1; background:#fff3e0; padding:12px; text-align:center; border-radius:12px; cursor:pointer; font-weight:bold; color:#ff9500; border:1px solid #ff9500; margin-bottom:10px;">⬅ VOLVER A CLIENTES</div>`;
            const sucursalesFiltradas = grupo.sucursales.filter(s => s.es_global != 1 && s.es_global != "1" && s.cliente.includes(' - '));
            sucursalesFiltradas.forEach(s => {
                if (s.cliente.toLowerCase().includes(txt)) {
                    const nombreSucursal = s.cliente.split(' - ')[1] || s.cliente;
                    const catNameSuc = (s.nombre_categoria_cliente || '').replace(/'/g, "\\'");

                    grid.innerHTML += `
                        <div class="grid-item" onclick="seleccionarCliente('${s.cliente.replace(/'/g, "\\'")}', '${s.id_interno}', '${catNameSuc}')">
                            <div class="grid-media"><div style="width:50px; height:50px; border-radius:50%; background:#f0f0f0; display:flex; align-items:center; justify-content:center; font-weight:800; color:#888;">${s.cliente.charAt(0)}</div></div>
                            <span>${nombreSucursal}</span>
                            <div class="card-color-footer" style="background:#eee"></div>
                        </div>`;
                }
            });
        }
    };

    window.abrirSucursales = function (nombre) {
        grupoSeleccionado = nombre;
        const searchInput = document.getElementById('busquedaCliente');
        if (searchInput) searchInput.value = "";
        renderizarGridClientes();
    };

    window.seleccionarCliente = function (nombre, id, categoria = "") {
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

    // --- LÓGICA DE SELECCIÓN DE PRODUCTOS ---

    window.volverAlNivel = function (nivel) {
        nivelProd = nivel;
        renderizarGridProductos();
    };

    window.seleccionarCalibre = function (cal) {
        seleccionadoCal = cal;
        nivelProd = 3;
        renderizarGridProductos();
    };

    window.seleccionarVariedad = function (nombreVar) {
        seleccionadoVar = nombreVar;
        const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
        if (!grupo || !grupo.variedades[nombreVar]) return;

        const varObj = grupo.variedades[nombreVar];
        const calibresKeys = Object.keys(varObj.calibres);

        if (calibresKeys.length === 1) {
            seleccionadoCal = calibresKeys[0];
            nivelProd = 3;
        } else {
            nivelProd = 2;
        }
        renderizarGridProductos();
    };

    window.seleccionarNivelCategoria = function (nombreCat) {
        seleccionadoCat = nombreCat;
        const grupo = listaProductosMaster.find(g => g.nombre === nombreCat);
        if (!grupo) return;

        const variedadesKeys = Object.keys(grupo.variedades);

        if (variedadesKeys.length === 1) {
            window.seleccionarVariedad(variedadesKeys[0]);
        } else {
            nivelProd = 1;
            renderizarGridProductos();
        }
    };

    window.renderizarGridProductos = async function (filtro = "") {
        const grid = document.getElementById('grid-productos');
        if (!grid) return;
        const titulo = document.querySelector('#modal-productos h3');
        grid.innerHTML = "";
        const txt = filtro.toLowerCase();

        // Nivel 0: Categorías
        if (nivelProd === 0) {
            titulo.innerText = "Categorías";
            listaProductosMaster.forEach(g => {
                if (g.nombre.toLowerCase().includes(txt)) {
                    grid.innerHTML += `<div class="grid-item" onclick="seleccionarNivelCategoria('${g.nombre.replace(/'/g, "\\'")}')"><div class="grid-media" style="font-size:35px;">${g.icono}</div><span>${g.nombre}</span><div class="card-color-footer" style="background:${g.color}"></div></div>`;
                }
            });
        }
        // Nivel 1: Variedades
        else if (nivelProd === 1) {
            const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
            titulo.innerText = "Variedad";
            grid.innerHTML = `<div class="btn-volver-modal" onclick="volverAlNivel(0)">⬅ VOLVER A CATEGORÍAS</div>`;
            Object.keys(grupo.variedades).forEach(vKey => {
                const labelVar = vKey === "" ? "Estándar" : vKey;
                const safeKey = vKey.replace(/'/g, "\\'");
                grid.innerHTML += `<div class="grid-item" onclick="seleccionarVariedad('${safeKey}')"><div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${labelVar.substring(0, 3).toUpperCase()}</b></div><span>${labelVar}</span><div class="card-color-footer" style="background:${grupo.color}"></div></div>`;
            });
        }
        // Nivel 2: Calibres
        else if (nivelProd === 2) {
            const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
            const varObj = grupo.variedades[seleccionadoVar];
            titulo.innerText = `${seleccionadoCat} ${seleccionadoVar ? '(' + seleccionadoVar + ')' : ''}`;

            const variedadesKeys = Object.keys(grupo.variedades);
            const backLevel = (variedadesKeys.length === 1) ? 0 : 1;
            const backText = (variedadesKeys.length === 1) ? "CATEGORÍAS" : "VARIEDADES";

            grid.innerHTML = `<div class="btn-volver-modal" onclick="volverAlNivel(${backLevel})">⬅ VOLVER A ${backText}</div>`;
            Object.keys(varObj.calibres).forEach(cal => {
                grid.innerHTML += `<div class="grid-item" onclick="seleccionarCalibre('${cal}')"><div class="grid-media"><b style="font-size:20px; color:${grupo.color}">${cal}</b></div><span>CALIBRE</span><div class="card-color-footer" style="background:${grupo.color}"></div></div>`;
            });
        }
        // Nivel 3: Formatos y Precios
        else if (nivelProd === 3) {
            const grupo = listaProductosMaster.find(g => g.nombre === seleccionadoCat);
            const formatos = grupo.variedades[seleccionadoVar].calibres[seleccionadoCal];
            const clienteId = document.getElementById('cliente_hidden').value;
            titulo.innerText = `${seleccionadoCal} - ${seleccionadoCat}`;

            const varObj = grupo.variedades[seleccionadoVar];
            const calibresKeys = Object.keys(varObj.calibres);
            const variedadesKeys = Object.keys(grupo.variedades);
            let backLevel = 2;
            let backText = "CALIBRES";

            if (calibresKeys.length === 1) {
                if (variedadesKeys.length === 1) {
                    backLevel = 0;
                    backText = "CATEGORÍAS";
                } else {
                    backLevel = 1;
                    backText = "VARIEDADES";
                }
            }

            grid.innerHTML = `<div class="btn-volver-modal" onclick="volverAlNivel(${backLevel})">⬅ VOLVER A ${backText}</div>`;
            grid.innerHTML += `<div id="alerta-precios" class="alerta-precio-cat"></div>`;
            grid.innerHTML += `<div id="loading-prices" style="grid-column:1/-1;text-align:center;padding:10px;">Cargando precios...</div>`;

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
                    } catch (e) { precioFinal = parseFloat(p.precio_actual || p.precio_por_kilo || 0); }
                } else {
                    precioFinal = parseFloat(p.precio_actual || p.precio_por_kilo || 0);
                }

                if (esEspecial && !flagAlertaActivada) {
                    const alertaDiv = document.getElementById('alerta-precios');
                    if (alertaDiv) {
                        alertaDiv.style.display = 'block';
                        const nom = (nombreCategoriaClienteActiva && nombreCategoriaClienteActiva.trim() !== "")
                            ? nombreCategoriaClienteActiva.toUpperCase()
                            : 'ESPECIALES';
                        alertaDiv.innerHTML = `🔔 SE APLICAN PRECIOS ${nom}`;
                        flagAlertaActivada = true;
                    }
                }

                let nombreFull = p.producto;
                if (seleccionadoVar) nombreFull += ` (${seleccionadoVar})`;

                const itemHtml = `
                    <div class="grid-item" onclick="finalizarSeleccionProducto('${p.id_producto}', '${nombreFull.replace(/'/g, "\\'")}', '${p.calibre}', '${p.formato}', ${precioFinal}, '${grupo.color}')">
                        <div class="grid-media"><b>${p.formato}</b></div>
                        <span style="font-size:15px; font-weight:900; color:#333;">${precioFinal > 0 ? formatCLP(precioFinal) : 'Consultar'}</span>
                        <div class="card-color-footer" style="background:${esEspecial ? '#E98C00' : '#27ae60'}"></div>
                    </div>`;
                grid.insertAdjacentHTML('beforeend', itemHtml);
            }
            const loader = document.getElementById('loading-prices');
            if (loader) loader.remove();
        }
    };

    window.finalizarSeleccionProducto = function (id, nombre, calibre, formato, precio, color) {
        const fila = document.getElementById(`f-${filaActivaParaProducto}`);
        if (fila) {
            fila.querySelector('.p-sel-hidden').value = id;
            fila.querySelector('.p-name-display').innerHTML = `<div style="color:#333; font-weight:bold;">${nombre}</div><div style="font-size:11px; color:#666;">${calibre} - ${formato}</div>`;
            fila.setAttribute('data-price', precio);
            fila.style.borderLeft = `6px solid ${color}`;
            window.cerrarModal('modal-productos');
            window.calc();
        }
    };

    function inyectarModales() {
        if (document.getElementById('modal-clientes')) return;
        const html = `
        <div id="modal-clientes" class="modal-grid-overlay"><div class="modal-grid-content"><div class="modal-grid-header"><div class="header-top"><h3 id="titulo-modal-cli">Clientes</h3><button type="button" class="btn-cerrar-modal" onclick="cerrarModal('modal-clientes')">✕</button></div><div class="header-search"><input type="text" id="busquedaCliente" placeholder="🔍 Buscar cliente..." onkeyup="renderizarGridClientes(this.value)"></div></div><div id="grid-clientes" class="grid-container"></div></div></div>
        <div id="modal-productos" class="modal-grid-overlay"><div class="modal-grid-content"><div class="modal-grid-header"><div class="header-top"><h3>Productos</h3><button type="button" class="btn-cerrar-modal" onclick="cerrarModal('modal-productos')">✕</button></div><div class="header-search"><input type="text" placeholder="🔍 Buscar producto..." onkeyup="filtrarGrid('grid-productos', this.value)"></div></div><div id="grid-productos" class="grid-container"></div></div></div>`;
        document.body.insertAdjacentHTML('beforeend', html);
    }

    // --- INIT PÁGINA INGRESAR ---
    if (orderForm) {
        console.log("📝 Página: Ingresar Pedido");
        moverModalesAlBody();
        inyectarModales();

        try {
            const userEmail = window.obtenerEmailLimpio();
            Promise.all([
                fetch(window.getApi(`detalle-cliente.php?action=list_clients&wp_user=${encodeURIComponent(userEmail)}`)).then(r => r.json()),
                fetch(window.getApi(`api.php?action=get_products`)).then(r => r.json())
            ]).then(([resCli, resProd]) => {
                procesarClientesAgrupados(resCli.clientes || []);
                listaProductosPlana = resProd || [];
                procesarProductosAgrupados(resProd || []);

                const displayCli = document.getElementById('display_cliente');
                if (displayCli) displayCli.onclick = () => window.abrirModal('modal-clientes');
                window.agregarFilaProducto();
            });

        } catch (e) { console.error("Error init ingreso:", e); }

        orderForm.onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnEnviar');
            const cli = document.getElementById('cliente_hidden').value;

            if (!cli || !document.getElementById('fecha_entrega').value) {
                return Swal.fire({
                    icon: 'warning',
                    title: 'DATOS INCOMPLETOS',
                    text: 'Selecciona un cliente y la fecha de despacho.',
                    confirmButtonColor: '#E98C00',
                    customClass: swalConfig.customClass
                });
            }

            let arrayProds = [], arrayCants = [];
            document.querySelectorAll('.m-fila[data-confirmada="true"]').forEach(f => {
                arrayProds.push(f.querySelector('.p-sel-hidden').value);
                arrayCants.push(f.querySelector('.p-qty').value);
            });

            if (!arrayProds.length) {
                return Swal.fire({
                    icon: 'info',
                    title: 'FALTA CONFIRMACIÓN',
                    text: 'Marca con ✓ los productos antes de registrar.',
                    confirmButtonColor: '#27ae60',
                    customClass: swalConfig.customClass
                });
            }

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
                const res = await fetch(window.getApi('form.php'), { method: 'POST', body: fd }).then(r => r.json());

                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'PEDIDO REGISTRADO',
                        text: `ID: ${res.pedido}`,
                        showConfirmButton: false,
                        timer: 1800,
                        customClass: swalConfig.customClass,
                        willClose: () => { location.reload(); }
                    });
                } else {
                    throw new Error(res.message);
                }
            } catch (err) {
                Swal.fire({
                    icon: 'error',
                    title: 'ERROR',
                    text: err.message || 'Error de conexión',
                    confirmButtonColor: '#e74c3c',
                    customClass: swalConfig.customClass
                });
                btn.disabled = false;
                document.getElementById('btnText').innerText = "REGISTRAR PEDIDO";
            }
        };
    }
    // #endregion

    // ==========================================================================
    // #region 5. PÁGINA: PEDIDOS ACTIVOS
    // ==========================================================================

    window.cargarDatosVistaPedidos = async function () {
        try {
            const rawData = await fetch(window.getApi('api.php?action=get_products')).then(r => r.json());
            listaProductosPlana = rawData;
            window.procesarProductosAgrupados(rawData);
        } catch (e) { console.error("Error cargando productos", e); }
    };

    window.loadOrders = async function () {
        try {
            const userEmail = window.obtenerEmailLimpio();
            const response = await fetch(window.getApi(`api.php?action=get_active_orders&wp_user=${encodeURIComponent(userEmail)}`));
            const data = await response.json();
            const grid = document.getElementById('orders-grid');

            if (!grid) return;

            if (!Array.isArray(data) || data.length === 0) {
                grid.classList.add('is-empty');
                grid.innerHTML = `<div class="empty-state-container"><span>📦</span><p>No tienes pedidos activos en este momento.</p></div>`;
                return;
            } else { grid.classList.remove('is-empty'); }

            let rawRole = data[0].is_admin_user;
            const isAdmin = (rawRole === true || rawRole === "true" || rawRole == 1 || rawRole === "1");
            const isEditor = (rawRole == 2 || rawRole === "2");
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
                    precio_t: pTotal,
                    id_producto: current.id_producto
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

                let bloqueGuia = '';
                if (order.url_guia) {
                    bloqueGuia = `<div class="upload-zone-small" style="border-style: solid; border-color: #3498db; color:#3498db; background:#f0f8ff;" onclick="abrirModal('${order.url_guia}')">🚚 VER GUÍA N° ${order.numero_guia || ''}</div>${isAdmin ? `<button class="btn-x-delete" onclick="eliminarDoc('${order.id_pedido}', 'guia')">×</button>` : ''}`;
                } else if (canEdit) {
                    bloqueGuia = `<button onclick="event.stopPropagation(); abrirVistaPrevia('guia', '${order.id_pedido}')" style="width:100%; padding:10px; background:#E98C00; color:white; border:none; border-radius:8px; font-weight:800; font-size:11px; cursor:pointer; margin-bottom:5px; box-shadow:0 3px 0 #d35400;">🚚 GENERAR GUÍA DE DESPACHO</button>${isAdmin ? `<div style="text-align:center; font-size:9px; color:#999; margin-bottom:5px; cursor:pointer; text-decoration:underline;" onclick="document.getElementById('file-guia-${order.id_pedido}').click()">o subir manual</div>` : ''}<input type="file" id="file-guia-${order.id_pedido}" accept="image/*,application/pdf" style="display:none" onchange="subirGuia(this, '${order.id_pedido}')">`;
                }

                let bloqueAdminFactura = '';
                if (isAdmin) {
                    bloqueAdminFactura = `
                <div class="admin-fields">
                    <div class="admin-input-row">
                        <input type="number" id="num-fact-${order.id_pedido}" placeholder="N° Factura" value="${order.numero_factura || ''}">
                        ${!order.url_factura ? `<button class="btn-pdf-upload" id="btn-pdf-label-${order.id_pedido}" onclick="document.getElementById('file-pdf-${order.id_pedido}').click()">📎 PDF</button><input type="file" id="file-pdf-${order.id_pedido}" accept="application/pdf" style="display:none" onchange="handlePdfSelect(this, '${order.id_pedido}')">` : ''}
                    </div>
                    <button class="btn-save-admin" id="btn-save-${order.id_pedido}" onclick="event.stopPropagation(); guardarAdmin('${order.id_pedido}')">GUARDAR</button>
                </div>`;
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
                                        ${order.numero_factura ? `<span class="factura-badge" style="background:#e8f6f3; color:#0F4B29; border-color:#0F4B29;">F: ${order.numero_factura}</span>` : ''}
                                        ${order.numero_guia ? `<span class="factura-badge" style="background:#ebf5fb; color:#2980b9; border-color:#2980b9;">Guia: ${order.numero_guia}</span>` : ''}
                                    </div>
                                </div>
                                <div class="order-body">
                                    <h3>${order.cliente}</h3>
                                    <div class="products-list" onclick="event.stopPropagation(); verDetalleLista('${order.id_pedido}')" style="cursor:zoom-in;">
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
                                ${order.url_factura ? `<div class="doc-container"><div class="upload-zone-small" style="border-style: solid; border-color:#E98C00; color:#E98C00;" onclick="abrirModal('${order.url_factura}')">📄 VER FACTURA N° ${order.numero_factura || ''}</div>${isAdmin ? `<button class="btn-x-delete" onclick="eliminarDoc('${order.id_pedido}', 'factura')">×</button>` : ''}</div>` : ''}
                                ${bloqueAdminFactura}
                                ${canEdit ? `<button class="btn-edit-order" onclick="event.stopPropagation(); abrirEditor('${order.id_pedido}')">✏️ EDITAR PEDIDO</button>` : ''}
                            </div>
                            <div class="trigger-flip-bar back-btn" onclick="flipCard(event, '${order.id_pedido}')"><span>Volver al Pedido</span></div>
                        </div>
                    </div>
                </div>`;
            });
        } catch (err) { console.error(err); }
    };

    window.abrirEditor = async function (idPedido) {
        const pedido = window.currentOrders[idPedido];
        if (!pedido) return;

        idPedidoEnEdicion = idPedido;
        clienteIdEditor = pedido.id_interno_cliente;

        nombreCategoriaClienteEditor = "";
        try {
            const res = await fetch(window.getApi(`detalle-cliente.php?id=${clienteIdEditor}`));
            const data = await res.json();
            if (data && data.perfil && data.perfil.nombre_categoria_cliente) {
                nombreCategoriaClienteEditor = data.perfil.nombre_categoria_cliente;
            }
        } catch (e) { console.warn("Error cat cliente", e); }

        document.getElementById('edit-subtitle').innerText = `${idPedido} • ${pedido.cliente}`;
        const contenedor = document.getElementById('editor-productos-container');
        contenedor.innerHTML = '';

        pedido.products.forEach(prod => {
            let idReal = prod.id_producto;
            let color = prod.color || '#ccc';
            let detalleTexto = `${prod.calibre} - ${prod.formato}`;
            let nombreDisplay = prod.nombre;
            if (prod.variedad) nombreDisplay += ` (${prod.variedad})`;

            window.agregarFilaEditor(idReal, prod.cantidad, nombreDisplay, detalleTexto, color, prod.precio_u);
        });
        document.getElementById('modal-editar-pedido').style.display = 'flex';
    };

    window.cerrarEditor = function () { document.getElementById('modal-editar-pedido').style.display = 'none'; };

    window.agregarFilaEditor = function (id = '', qty = 1, nombre = 'Toca para elegir...', detalle = '', color = '#eee', precio = 0) {
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
    };

    window.abrirSelectorEnEditor = function (rowId) {
        filaActivaEditor = rowId;
        nivelVisual = 0;
        window.renderizarGridVisual();
        document.getElementById('modal-selector-visual').style.display = 'flex';
    };

    window.renderizarGridVisual = async function (filtro = "") {
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
            const grupo = listaProductosMaster.find(g => g.nombre === seleccionCatVisual);
            titulo.innerText = "Variedad";
            grid.innerHTML = `<div class="btn-volver-modal" onclick="nivelVisual=0;renderizarGridVisual()">⬅ VOLVER A PRODUCTOS</div>`;
            Object.keys(grupo.variedades).forEach(vKey => {
                const labelVar = vKey === "" ? "Estándar" : vKey;
                grid.innerHTML += `
                <div class="grid-item" onclick="nivelVisual=2;seleccionVarVisual='${vKey}';renderizarGridVisual()">
                    <div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${labelVar.substring(0, 3).toUpperCase()}</b></div>
                    <span style="font-size:13px; color:#333 !important;">${labelVar}</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
            });
        }
        else if (nivelVisual === 2) {
            const grupo = listaProductosMaster.find(g => g.nombre === seleccionCatVisual);
            const varObj = grupo.variedades[seleccionVarVisual];
            const labelTitulo = seleccionVarVisual ? `${seleccionCatVisual} (${seleccionVarVisual})` : seleccionCatVisual;
            titulo.innerText = labelTitulo;

            const tieneVariedadReal = Object.keys(grupo.variedades).length > 1 || (Object.keys(grupo.variedades)[0] !== "");
            const actionVolver = tieneVariedadReal ? "nivelVisual=1" : "nivelVisual=0";
            const textoVolver = tieneVariedadReal ? "VARIEDADES" : "PRODUCTOS";

            grid.innerHTML = `<div class="btn-volver-modal" onclick="${actionVolver};renderizarGridVisual()">⬅ VOLVER A ${textoVolver}</div>`;
            Object.keys(varObj.calibres).forEach(cal => {
                grid.innerHTML += `
                <div class="grid-item" onclick="nivelVisual=3;seleccionCalVisual='${cal}';renderizarGridVisual()">
                    <div class="grid-media"><b style="font-size:18px; color:${grupo.color}">${cal}</b></div>
                    <span style="font-size:11px; color:#666 !important;">CALIBRE</span>
                    <div class="card-color-footer" style="background:${grupo.color}"></div>
                </div>`;
            });
        }
        else if (nivelVisual === 3) {
            const grupo = listaProductosMaster.find(g => g.nombre === seleccionCatVisual);
            const formatos = grupo.variedades[seleccionVarVisual].calibres[seleccionCalVisual];

            titulo.innerText = `${seleccionCalVisual} - ${seleccionCatVisual}`;
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
                        const resp = await fetch(window.getApi(`form.php?action=get_price_by_client&cliente=${clienteIdEditor}&producto=${p.id_producto}`));
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
                if (seleccionVarVisual) nombreFull += ` (${seleccionVarVisual})`;
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
    };

    window.seleccionarNivelCategoriaVisual = function (nombreCat) {
        seleccionCatVisual = nombreCat;
        const grupo = listaProductosMaster.find(g => g.nombre === nombreCat);
        const variedadesKeys = Object.keys(grupo.variedades);
        if (variedadesKeys.length === 1 && variedadesKeys[0] === "") {
            seleccionVarVisual = "";
            nivelVisual = 2; // Saltar
        } else {
            nivelVisual = 1; // Ir a Variedades
        }
        window.renderizarGridVisual();
    };

    window.filtrarGridVisual = function (val) {
        const items = document.getElementById('grid-visual-productos').getElementsByClassName('grid-item');
        Array.from(items).forEach(item => { if (item.classList.contains('btn-volver-modal')) return; item.style.display = item.innerText.toLowerCase().includes(val.toLowerCase()) ? '' : 'none'; });
    };

    window.finalizarSeleccionEditor = function (id, nombre, calibre, formato, color, precio) {
        const fila = document.getElementById(`row-${filaActivaEditor}`);
        if (fila) {
            fila.querySelector('.p-id-hidden').value = id;
            fila.querySelector('.p-name').innerText = nombre;
            fila.querySelector('.p-detail').innerText = `${calibre} - ${formato}`;
            fila.style.borderLeft = `5px solid ${color}`;
            fila.setAttribute('data-price', precio);
        }
        document.getElementById('modal-selector-visual').style.display = 'none';
    };

    window.guardarEdicionAPI = async function () {
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
        if (ids.length === 0) { alert("⚠️ El pedido no puede quedar vacío."); return; }

        const btn = document.getElementById('btn-guardar-edicion');
        const txtOriginal = btn.innerText;
        btn.innerText = "GUARDANDO..."; btn.disabled = true;

        const formData = new FormData();
        formData.append('wp_user', window.obtenerEmailLimpio());
        formData.append('action', 'update_order_items');
        formData.append('id_pedido', idPedidoEnEdicion);
        formData.append('producto', ids.join(' | '));
        formData.append('cantidad', cants.join(' | '));
        formData.append('precios_venta', precios.join(' | '));

        try {
            const res = await fetch(window.getApi('api-edit.php'), { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { window.cerrarEditor(); window.loadOrders(); }
            else { alert("Error: " + data.message); }
        } catch (e) { console.error(e); alert("Error de conexión"); }
        finally { btn.innerText = txtOriginal; btn.disabled = false; }
    };

    window.flipCard = function (event, id) { if (event) event.stopPropagation(); document.getElementById(`card-${id}`).classList.toggle('is-flipped'); };

    window.handlePdfSelect = function (input, id) {
        const btn = document.getElementById(`btn-pdf-label-${id}`);
        if (input.files.length > 0) { btn.innerText = "✅ PDF LISTO"; btn.classList.add('pdf-ready'); }
        else { btn.innerText = "📎 PDF"; btn.classList.remove('pdf-ready'); }
    };

    window.verDetalleLista = async function (idPedido) {
        const pedido = window.currentOrders[idPedido];
        if (!pedido) return;

        const modal = document.getElementById('modal-detalle-pedido');
        const body = document.getElementById('detalle-pedido-body');

        body.innerHTML = '<div style="text-align:center; padding:50px; color:#0F4B29; font-weight:bold;">Cargando información...</div>';
        modal.style.display = 'flex';

        let rutFinal = "No disponible";
        try {
            const response = await fetch(window.getApi(`detalle-cliente.php?id=${pedido.id_interno_cliente}`));
            const data = await response.json();
            if (data && data.perfil) { rutFinal = data.perfil.rut_cliente || data.perfil.id_cliente || "Pendiente"; }
        } catch (e) { }

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
                    <span class="unit-price-tag">Unit: ${formatCLP(p.precio_u)}</span>
                </div>
                <div style="font-size:11px; color:#999; margin-top:4px;">Calibre: ${p.calibre} • Formato: ${p.formato}</div>
            </div>
            <div class="item-price-total">${formatCLP(sub)}</div>
        </div>`;
        }).join('');

        const obsTexto = pedido.observacion || pedido.observaciones || '';
        const observacionBlock = obsTexto ? `
        <div style="padding: 15px 25px; background: #fffbf0; border-bottom: 1px solid #f0f0f0; border-top: 1px solid #f0f0f0;">
            <div style="font-size: 11px; font-weight: 800; color: #E98C00; text-transform: uppercase; margin-bottom: 5px; display: flex; align-items: center; gap: 5px;">
                📝 Observaciones del Pedido
            </div>
            <div style="font-size: 13px; color: #555; line-height: 1.5; font-style: italic;">
                "${obsTexto}"
            </div>
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
            <div class="resumen-row"><span>Subtotal Neto</span><span style="font-weight:700; color:#1a1a1a;">${formatCLP(totalNeto)}</span></div>
            <div class="resumen-row"><span>IVA (19%)</span><span style="font-weight:700; color:#1a1a1a;">${formatCLP(iva)}</span></div>
            <div class="resumen-total"><span class="label">Total Final</span><span class="value">${formatCLP(total)}</span></div>
            <button onclick="cerrarDetalle()" class="btn-cerrar-block">Cerrar Detalle</button>
        </div>`;
    };

    window.cerrarDetalle = function () { document.getElementById('modal-detalle-pedido').style.display = 'none'; };

    window.copiarRutLimpio = function (rutOriginal) {
        let rutLimpio = rutOriginal.split('-')[0].replace(/\./g, '').trim();
        navigator.clipboard.writeText(rutLimpio).then(() => {
            const toast = document.createElement('div');
            toast.className = 'rut-copiado-toast';
            toast.innerText = 'RUT copiado: ' + rutLimpio;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        });
    };

    window.eliminarDoc = async function (id, tipo) {
        if (!confirm(`¿Borrar definitivamente la ${tipo}?`)) return;
        const formData = new FormData(); formData.append('action', 'delete_document'); formData.append('id_pedido', id); formData.append('tipo', tipo);
        try { const res = await fetch(window.getApi('upload.php'), { method: 'POST', body: formData }); const data = await res.json(); if (data.status === 'success') window.loadOrders(); } catch (e) { }
    };

    window.subirGuia = async function (input, id) {
        if (!input.files[0]) return;
        const formData = new FormData(); formData.append('foto_guia', input.files[0]); formData.append('id_pedido', id); formData.append('action', 'upload_guia_despacho');
        try { const res = await fetch(window.getApi('upload.php'), { method: 'POST', body: formData }); const data = await res.json(); if (data.status === 'success') window.loadOrders(); } catch (e) { }
    };

    window.guardarAdmin = async function (id) {
        const numFactura = document.getElementById(`num-fact-${id}`).value;
        const filePdf = document.getElementById(`file-pdf-${id}`)?.files[0];
        const btn = document.getElementById(`btn-save-${id}`);
        btn.innerText = "GUARDANDO..."; btn.disabled = true;
        const formData = new FormData();
        formData.append('action', 'update_admin_order'); formData.append('id_pedido', id); formData.append('numero_factura', numFactura);
        if (filePdf) formData.append('pdf_factura', filePdf);
        try {
            const res = await fetch(window.getApi('upload.php'), { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { window.loadOrders(); setTimeout(() => { window.flipCard(null, id); }, 500); }
            else { btn.innerText = "ERROR"; btn.disabled = false; }
        } catch (e) { btn.innerText = "ERROR"; btn.disabled = false; }
    };

    window.openQR = function (idPedido, token) {
        const existing = document.getElementById('dynamic-qr-modal');
        if (existing) existing.remove();
        const urlValidacion = "https://app.tabolango.cl/validar-entrega?token=" + token;
        const overlay = document.createElement('div');
        overlay.id = 'dynamic-qr-modal'; overlay.className = 'qr-modal-overlay'; overlay.onclick = window.closeQR;
        overlay.innerHTML = `
        <div class="qr-modal-content" onclick="event.stopPropagation()">
            <span class="qr-close-btn" onclick="closeQR()">✕</span>
            <div style="margin-bottom: 20px;"><h3 style="color:#333; margin: 0; font-size:18px; font-weight: 800;">VALIDACIÓN DE ENTREGA</h3><p style="margin: 5px 0 0 0; font-size: 13px; color: #888; font-weight: bold;">PEDIDO ID: ${idPedido}</p></div>
            <div id="qrcode-target" style="background: white; padding: 10px; border-radius: 15px; display: inline-block; border: 1px solid #eee;"></div>
            <div style="background: #fdf2e2; padding: 12px; border-radius: 12px; border: 1px solid #f9e1bc; margin-top: 20px;"><p style="margin: 0; font-size: 11px; color: #E98C00; font-weight: bold; line-height: 1.4;">Escanee el código o use el botón inferior para validar la recepción.</p></div>
            <button class="btn-qr-cerrar" onclick="window.location.href='${urlValidacion}'">IR A VALIDAR</button>
            <p style="font-size:9px; color:#ccc; margin-top:15px; letter-spacing: 1px;">TOKEN: ${token}</p>
        </div>`;
        document.body.appendChild(overlay);
        setTimeout(() => { new QRCode(document.getElementById('qrcode-target'), { text: urlValidacion, width: 200, height: 200, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.H }); }, 50);
    };

    window.closeQR = function () { const modal = document.getElementById('dynamic-qr-modal'); if (modal) modal.remove(); };

    // --- MODAL COMANDA LOGICA VISTA ---
    window.abrirModalComanda = function () {
        const modal = document.getElementById('modal-comanda');
        if (modal.parentNode !== document.body) document.body.appendChild(modal);
        const hoy = new Date().toISOString().split('T')[0];
        if (!document.getElementById('fecha-comanda').value) document.getElementById('fecha-comanda').value = hoy;
        modal.style.display = 'flex';
    };

    window.cerrarModalComanda = function () { document.getElementById('modal-comanda').style.display = 'none'; };

    window.generarListaComandaVisual = function () {
        const fechaInput = document.getElementById('fecha-comanda').value;
        if (!fechaInput) return alert("Seleccione una fecha");
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
    };

    window.toggleComandaCheck = function (el, key) {
        el.classList.toggle('checked');
        if (el.classList.contains('checked')) localStorage.setItem(key, 'true');
        else localStorage.removeItem(key);
    };

    window.limpiarTicks = function () {
        if (!confirm("¿Desea destickear todos los productos?")) return;
        const fechaInput = document.getElementById('fecha-comanda').value;
        Object.keys(localStorage).forEach(k => { if (k.startsWith(`cmd_${fechaInput}`)) localStorage.removeItem(k); });
        window.generarListaComandaVisual();
    };

    window.llamarPHPComanda = function () {
        const fecha = document.getElementById('fecha-comanda').value;
        if (!fecha) return alert("Seleccione una fecha primero");
        window.open(window.getApi(`generar_pdf_comanda.php?fecha=${fecha}`), '_blank');
    };

    window.generarDocumento = async function (tipo, idPedido) {
        const nombreDoc = tipo === 'guia' ? 'Guía de Despacho' : 'Factura Electrónica';
        if (!confirm(`¿Generar ${nombreDoc} para pedido ${idPedido}?`)) return;
        const btn = document.getElementById(`btn-gen-${tipo}-${idPedido}`);
        const txtOrig = btn ? btn.innerText : '';
        if (btn) { btn.disabled = true; btn.innerText = "GENERANDO..."; }
        try {
            const res = await fetch(window.getApi('procesar_facturacion.php'), {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_pedido: idPedido, tipo_doc: tipo })
            });
            const data = await res.json();
            if (data.status === 'success') { alert(`✅ ${nombreDoc} generada! Folio: ${data.folio}`); window.loadOrders(); }
            else { alert(`❌ Error: ${data.message}`); }
        } catch (e) { alert("Error conexión"); }
        finally { if (btn) { btn.disabled = false; btn.innerText = txtOrig; } }
    };

    // INIT
    if (document.getElementById('orders-grid')) {
        console.log("📝 Página: Ver Pedidos");
        window.moverModalesAlBody();
        window.cargarDatosVistaPedidos();
        window.loadOrders();
    }
    // #endregion
    // ============================================================================
}); // <-- CIERRE DEL DOMContentLoaded GLOBAL DEL APP.JS