/**
 * 🚀 SCRIPT DETALLE CLIENTE (OPTIMIZADO PARA LOCAL Y PRODUCCIÓN)
 */

let dataCliente = null;
let editando = false;
let isAdmin = false;
let sucursalesCliente = [];
let listaUsuarios = [];
let listaCategorias = [];

// 1. 🔥 CORRECCIÓN: INICIALIZAMOS CON EL MES Y AÑO ACTUALES (EJ: MARZO 2026)
const mesActual = String(new Date().getMonth() + 1).padStart(2, '0');
const anoActual = String(new Date().getFullYear());
let filtroActivo = { mes: mesActual, año: anoActual };

const nombresMeses = { "01": "Enero", "02": "Febrero", "03": "Marzo", "04": "Abril", "05": "Mayo", "06": "Junio", "07": "Julio", "08": "Agosto", "09": "Septiembre", "10": "Octubre", "11": "Noviembre", "12": "Diciembre" };

// Configuración de Toasts Elegantes con SweetAlert2
const Toast = Swal.mixin({
    toast: true,
    position: 'bottom',
    showConfirmButton: false,
    timer: 2500,
    timerProgressBar: true,
    background: '#0F4B29',
    color: '#fff'
});

async function initApp() {
    relocalizarModales();
    await cargarRecursos();
    const id = new URLSearchParams(window.location.search).get('id');

    if (!id) {
        document.getElementById('loading').style.display = 'none';
        return;
    }

    try {
        const url = window.getApi(`data_cliente_detalle.php?id=${id}`);
        const response = await fetch(url);
        dataCliente = await response.json();

        if (dataCliente && !dataCliente.error) {
            if (dataCliente.sucursales) {
                sucursalesCliente = Array.isArray(dataCliente.sucursales) ? dataCliente.sucursales : [dataCliente.sucursales];
                renderSelectorSucursales();
            }
            isAdmin = (dataCliente.is_admin_user === true || dataCliente.is_admin_user == 1);
            if (isAdmin) {
                document.getElementById('admin-header').style.display = 'flex';
                renderBotonEliminar();
            }

            // Renderizamos los datos iniciales
            renderData();

            // 2. 🔥 CORRECCIÓN: PINTAMOS LOS BOTONES CON EL MES ACTUAL (MARZO)
            const btnMes = document.getElementById('btn-filtro-mes');
            const btnAno = document.getElementById('btn-filtro-año');
            const btnTotal = document.getElementById('btn-filtro-total');

            if (btnMes) { btnMes.innerText = nombresMeses[mesActual]; btnMes.classList.add('active'); }
            if (btnAno) { btnAno.innerText = anoActual; btnAno.classList.add('active'); }
            if (btnTotal) btnTotal.classList.remove('active');

            // 🔥 INYECTA ESTO AQUÍ: Generar el menú desplegable la primera vez
            renderizarDropdownMeses();

            // 3. 🔥 CORRECCIÓN: EJECUTAMOS EL FILTRO INMEDIATAMENTE AL CARGAR
            ejecutarFiltroFinal(false);

            document.getElementById('loading').style.display = 'none';
            document.getElementById('main-content').style.display = 'block';
        } else {
            throw new Error(dataCliente.error || "Datos no encontrados");
        }
    } catch (e) {
        console.error(e);
        document.getElementById('loading').style.display = 'none';
        Swal.fire('Error', 'No se pudo cargar la información del cliente.', 'error');
    }
}
async function cargarRecursos() {
    try {
        const [u, c] = await Promise.all([
            fetch(window.getApi('obtener_usuarios.php')).then(r => r.ok ? r.json() : []),
            fetch(window.getApi('data_cliente_detalle.php?action=get_categories')).then(r => r.ok ? r.json() : [])
        ]);
        listaUsuarios = u; listaCategorias = c;
    } catch (e) {
        console.warn("Recursos secundarios no cargados:", e);
    }
}

function renderData() {
    if (!dataCliente || !dataCliente.perfil) return;
    const d = dataCliente.perfil;
    const s = dataCliente.stats;

    const uiTipo = document.getElementById('ui-tipo');
    if (uiTipo) {
        const valOrig = d.tipo_cliente || 'CLIENTE';
        let nombreMostrar = valOrig;
        if (listaCategorias.length > 0) {
            const match = listaCategorias.find(c => String(c.id) === String(valOrig));
            if (match) nombreMostrar = match.nombre;
        }
        uiTipo.innerText = nombreMostrar.toUpperCase();
        const catLower = nombreMostrar.toLowerCase();
        if (catLower.includes('premium')) { uiTipo.style.background = "#FFD700"; uiTipo.style.color = "#000"; }
        else if (catLower.includes('mayorista')) { uiTipo.style.background = "#E98C00"; uiTipo.style.color = "#fff"; }
        else { uiTipo.style.background = "#3498db"; uiTipo.style.color = "#fff"; }
    }

    document.getElementById('ui-nombre').innerText = d.cliente;
    document.getElementById('ui-id').innerText = `ID: ${d.id_interno} | ${d.rut_cliente}`;
    document.getElementById('ui-avatar').style.backgroundImage = d.logo ? `url(${d.logo})` : 'none';
    document.getElementById('ui-avatar').innerText = d.logo ? '' : d.cliente.charAt(0).toUpperCase();

    const elResp = document.getElementById('ui-responsable');
    if (d.responsable_nombre) elResp.innerText = d.responsable_nombre.toLowerCase().split(' ').map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(' ');
    else elResp.innerText = "No asignado";

    document.getElementById('ui-rut').innerText = d.rut_cliente || '---';
    document.getElementById('ui-direccion').innerText = d.direccion;
    document.getElementById('ui-email').innerText = d.email;
    document.getElementById('ui-telefono').innerText = formatearTelefonoChileno(d.telefono);
    document.getElementById('ui-contacto-local').innerText = d.contacto || '---';

    const wrapper = document.getElementById('ui-contacto-wrapper');
    const nombreCompleto = `${d.nombre || ''} ${d.apellido || ''}`.trim();
    wrapper.innerHTML = `<span style="font-weight:600;">${nombreCompleto || 'No especificado'}</span>`;

    if (document.getElementById('orig-nombre')) document.getElementById('orig-nombre').value = d.nombre || '';
    if (document.getElementById('orig-apellido')) document.getElementById('orig-apellido').value = d.apellido || '';

    document.getElementById('ui-pedidos').innerText = s.total.pedidos || 0;
    document.getElementById('ui-monto').innerText = window.formatCLP(s.total.inversion || 0);
    document.getElementById('ui-ultimo-pedido').innerText = s.fecha_ultimo || '-';
    document.getElementById('ui-prediccion').innerText = s.prediccion || '-';

    const boxRecurrente = document.getElementById('ui-recurring-container');
    if (dataCliente.analisis_recurrente) {
        const ar = dataCliente.analisis_recurrente;
        boxRecurrente.style.display = 'flex';
        boxRecurrente.innerHTML = `
                <div class="recurring-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="recurring-text">
                    <h4>${ar.titulo}</h4>
                    <p>${ar.mensaje}</p>
                </div>`;
    } else { boxRecurrente.style.display = 'none'; }

    renderFacturasList(dataCliente.facturas || []);
    renderProductos(dataCliente.productos || []);
    renderFacturaBody();
}

function renderProductos(lista) {
    const container = document.getElementById('ui-productos');
    if (!lista || lista.length === 0) {
        container.innerHTML = '<div style="padding:15px; text-align:center; opacity:0.5;">No hay historial</div>';
        return;
    }
    container.innerHTML = lista.map(p => {
        const variedad = p.variedad ? ` ${p.variedad}` : '';
        const formato = p.formato ? ` | ${p.formato}` : '';
        const calibre = p.calibre ? ` | Cal: ${p.calibre}` : '';
        let unidad = p.unidad || 'u';
        const cant = Math.round(p.cant);
        if (cant > 1 && unidad.toLowerCase() !== 'kg' && !unidad.toLowerCase().endsWith('s')) unidad += 's';

        return `
                <div class="prod-row">
                    <div style="display:flex; flex-direction:column;">
                        <span style="font-weight:700; font-size:14px;">📦 ${p.producto}${variedad}</span>
                        <span style="font-size:12px; color:#888; margin-top:3px;">${formato}${calibre}</span>
                    </div>
                    <div style="text-align:right;">
                        <b style="font-size:15px; color:#0F4B29;">${cant}</b>
                        <span style="font-size:10px; color:#999; text-transform:uppercase;">${unidad}</span>
                    </div>
                </div>`;
    }).join('');
}

function renderFacturasList(lista) {
    const tbody = document.getElementById('ui-facturas-body');
    if (!lista || lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#ccc; padding:15px;">Sin facturas</td></tr>';
        return;
    }
    tbody.innerHTML = lista.slice(0, 3).map(f => `
            <tr>
                <td>#${f.folio}</td>
                <td>${f.fecha || '-'}</td>
                <td>${window.formatCLP(f.monto_neto)}</td>
                <td style="text-align:right;"><button class="btn-view-inv" onclick="verFactura('${f.url_pdf || '#'}')"><i class="fas fa-file-pdf"></i></button></td>
            </tr>`).join('');
}

function renderFacturaBody() {
    const container = document.getElementById('factura-body');
    if (!dataCliente || !dataCliente.perfil) return;
    const d = dataCliente.perfil;
    const fields = [
        { l: 'RUT Factura', v: d.rut_cliente, k: 'rut_cliente' },
        { l: 'Razón Social', v: d.razon_social, k: 'razon_social' },
        { l: 'Giro', v: d.giro, k: 'giro' },
        { l: 'Dirección Factura', v: d.direccion_factura, k: 'direccion_factura' },
        { l: 'Comuna Factura', v: d.comuna_factura, k: 'comuna_factura' },
        { l: 'Ciudad Factura', v: d.ciudad_factura, k: 'ciudad_factura' },
        { l: 'Email DTE', v: d.email_factura, k: 'email_factura' },
        { l: 'Teléfono DTE', v: d.telefono_factura, k: 'telefono_factura' }
    ];
    container.innerHTML = fields.map(f => `
            <div class="copy-box">
                <div style="flex:1">
                    <small style="display:block; font-size:10px; color:#999; text-transform:uppercase; font-weight:bold; margin-bottom:4px;">${f.l}</small>
                    ${editando ? `<input type="text" class="input-edit-ui" data-key="${f.k}" value="${f.v || ''}" oninput="sincronizarCampo(this)">` : `<span style="font-size:14px; font-weight:600;">${f.v || '---'}</span>`}
                </div>
                ${!editando && f.v ? `<button class="btn-copy" onclick="copiarTexto('${f.v}')"><i class="fas fa-copy"></i></button>` : ''}
            </div>`).join('');
}

function toggleEdicion() {
    editando = !editando;
    const btn = document.getElementById('btn-editar-master');
    const uiTipo = document.getElementById('ui-tipo');
    const elResp = document.getElementById('ui-responsable');

    if (editando) {
        btn.style.background = "#2ecc71";
        btn.innerHTML = `<i class="fas fa-save"></i> <span id="txt-edit">GUARDAR CAMBIOS</span>`;

        document.querySelectorAll('.editable-field').forEach(span => {
            const key = span.getAttribute('data-key');
            let val = (span.innerText === 'No registrado' || span.innerText === '---') ? '' : span.innerText;
            if (key === 'telefono') val = val.replace(/\D/g, "").slice(-8);
            const inputId = (key === 'direccion') ? 'edit-direccion-autocomplete' : '';
            span.innerHTML = `<input type="text" id="${inputId}" class="input-edit-ui" data-key="${key}" value="${val}" style="width:100%" oninput="sincronizarCampo(this)">`;
        });

        const wrapper = document.getElementById('ui-contacto-wrapper');
        const nom = document.getElementById('orig-nombre').value;
        const ape = document.getElementById('orig-apellido').value;
        wrapper.innerHTML = `<div style="display:flex; gap:8px;"><input type="text" class="input-edit-ui" data-key="nombre" value="${nom}" placeholder="Nombre" style="width:50%"><input type="text" class="input-edit-ui" data-key="apellido" value="${ape}" placeholder="Apellido" style="width:50%"></div>`;

        if (elResp && listaUsuarios.length > 0) {
            const emailActual = (dataCliente.perfil.responsable_email || "").toLowerCase().trim();
            let opts = listaUsuarios.map(u => `<option value="${u.email}" ${u.email.toLowerCase() === emailActual ? 'selected' : ''}>${u.nombre}</option>`).join('');
            elResp.innerHTML = `<select id="edit-responsable-email" class="input-edit-ui" style="width:100%">${opts}</select>`;
        }

        if (uiTipo && listaCategorias.length > 0) {
            const idCat = String(dataCliente.perfil.id_categoria || dataCliente.perfil.tipo_cliente || "");
            let optsCat = listaCategorias.map(c => `<option value="${c.id}" ${String(c.id) === idCat ? 'selected' : ''}>${c.nombre.toUpperCase()}</option>`).join('');
            uiTipo.style.background = "white"; uiTipo.style.border = "1px solid #E98C00"; uiTipo.style.padding = "2px";
            uiTipo.innerHTML = `<select id="select-categoria-directo" class="input-edit-ui" data-key="id_categoria" style="border:none;">${optsCat}</select>`;
        }
        if (typeof initEditAutocomplete === "function") initEditAutocomplete();
    } else {
        guardarEnBD();
    }
    renderFacturaBody();
}

async function guardarEnBD() {
    const formData = new FormData();
    formData.append('action', 'update_client_profile');
    formData.append('id_interno', dataCliente.perfil.id_interno);

    document.querySelectorAll('.input-edit-ui').forEach(inp => {
        if (inp.getAttribute('data-key')) formData.append(inp.getAttribute('data-key'), inp.value);
    });

    formData.append('latitud', document.getElementById('edit-latitud')?.value || dataCliente.perfil.lat_despacho);
    formData.append('longitud', document.getElementById('edit-longitud')?.value || dataCliente.perfil.lng_despacho);

    if (document.getElementById('select-categoria-directo')) formData.append('id_categoria', document.getElementById('select-categoria-directo').value);
    if (document.getElementById('edit-responsable-email')) formData.append('responsable_email', document.getElementById('edit-responsable-email').value);

    const logoInput = document.getElementById('input-logo');
    if (logoInput && logoInput.files[0]) formData.append('logo_cliente', logoInput.files[0]);

    try {
        const res = await fetch(window.getApi('data_cliente_detalle.php'), { method: 'POST', body: formData });
        const json = await res.json();
        if (json.success) {
            mostrarToast("Datos actualizados");
            setTimeout(() => location.reload(), 1000);
        } else {
            Swal.fire('Error', json.error || 'Ocurrió un error al guardar', 'error');
        }
    } catch (e) {
        Swal.fire('Fallo de Red', 'No se pudo conectar con el servidor', 'error');
    }
}

function renderSelectorSucursales() {
    const menu = document.getElementById('menu-sucursales');
    const wrapper = document.getElementById('wrapper-sucursales');
    if (!menu || !wrapper || sucursalesCliente.length === 0) return;
    wrapper.style.display = 'block';
    menu.innerHTML = `<div class="sucursal-item" onclick="cambiarSucursal('casa_matriz')"><div class="sucursal-icon"><i class="fas fa-globe"></i></div>Global</div>` +
        sucursalesCliente.map(s => `<div class="sucursal-item" onclick="cambiarSucursal('${s.id_interno}')"><div class="sucursal-icon"><i class="fas fa-map-marker-alt"></i></div>${s.cliente.includes("-") ? s.cliente.split("-")[1].trim() : s.cliente}</div>`).join('');
}

async function cambiarSucursal(idS) {
    document.getElementById('loading').style.display = 'block';
    const idOrig = new URLSearchParams(window.location.search).get('id');
    const target = idS === 'casa_matriz' ? idOrig : idS;
    try {
        const res = await fetch(window.getApi(`data_cliente_detalle.php?id=${target}`));
        const nd = await res.json();
        if (nd && !nd.error) {
            dataCliente = nd;
            renderData();
            if (isAdmin) renderBotonEliminar();
            document.getElementById('menu-sucursales').style.display = 'none';
        }
    } catch (e) {
        Swal.fire('Error', 'No se pudo cargar la sucursal', 'error');
    } finally {
        document.getElementById('loading').style.display = 'none';
    }
}

// --- SISTEMA DE FILTROS ---

function ejecutarFiltroFinal(esTotal = false) {
    if (!dataCliente) return;

    // Blindaje: Si el HTML envía un evento 'click' accidentalmente, redirigimos a reset
    if (typeof esTotal === 'object' || esTotal === 'total') {
        resetFiltroTotal();
        return;
    }

    let tipo = "total", valor = "";

    if (!esTotal) {
        if (filtroActivo.mes && filtroActivo.año) {
            tipo = "mes_año";
            valor = `${filtroActivo.año}-${filtroActivo.mes}`;
        } else if (filtroActivo.mes) {
            tipo = "solo_mes";
            valor = filtroActivo.mes;
        } else if (filtroActivo.año) {
            tipo = "solo_año";
            valor = filtroActivo.año;
        }
    }

    fetch(window.getApi(`data_cliente_detalle.php?id=${dataCliente.perfil.id_interno}&filtro_tipo=${tipo}&filtro_valor=${valor}`))
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('ui-pedidos').innerText = data.stats_personalizada.pedidos;
                document.getElementById('ui-monto').innerText = window.formatCLP(data.stats_personalizada.monto);
                renderProductos(data.productos_filtrados || []);
            }
        })
        .catch(err => console.error("Error al filtrar:", err));
}

// 1. Arreglo ordenado para construir los meses dinámicamente
const arrayMeses = [
    { id: "01", nombre: "Enero" }, { id: "02", nombre: "Febrero" }, { id: "03", nombre: "Marzo" },
    { id: "04", nombre: "Abril" }, { id: "05", nombre: "Mayo" }, { id: "06", nombre: "Junio" },
    { id: "07", nombre: "Julio" }, { id: "08", nombre: "Agosto" }, { id: "09", nombre: "Septiembre" },
    { id: "10", nombre: "Octubre" }, { id: "11", nombre: "Noviembre" }, { id: "12", nombre: "Diciembre" }
];

// 2. FUNCIÓN INTELIGENTE: Dibuja los meses permitidos según el año
function renderizarDropdownMeses() {
    const dropMeses = document.getElementById('drop-meses'); // Asegúrate que el ID coincida con tu HTML
    if (!dropMeses) return;

    const fechaActual = new Date();
    const anoActualNum = fechaActual.getFullYear();
    const mesActualNum = fechaActual.getMonth() + 1;

    // Vemos qué año está seleccionado en el filtro (Si no hay, usamos el actual)
    const anoSeleccionado = parseInt(filtroActivo.año) || anoActualNum;

    let limite = 12; // Por defecto mostramos 12 meses (para años anteriores)

    // Si el año filtrado es el año actual, cortamos la lista hasta el mes actual
    if (anoSeleccionado === anoActualNum) {
        limite = mesActualNum;
    }

    let html = '';
    for (let i = 0; i < limite; i++) {
        const m = arrayMeses[i];
        // 🔥 CORRECCIÓN: Cambiamos <a> por <div> para igualar el CSS de los años
        html += `<div onclick="seleccionarOpcion('mes', '${m.id}', '${m.nombre}')">${m.nombre}</div>`;
    }

    // Inyectamos al HTML
    dropMeses.innerHTML = html;
}


// 3. ACTUALIZAMOS la función seleccionarOpcion
function seleccionarOpcion(t, v, l) {
    filtroActivo[t] = v;
    document.getElementById('btn-filtro-' + t).innerText = l;
    document.querySelectorAll('.mini-drop').forEach(m => m.style.display = 'none');

    const btnTotal = document.getElementById('btn-filtro-total');
    const btnMes = document.getElementById('btn-filtro-mes');
    const btnAno = document.getElementById('btn-filtro-año');

    if (btnTotal) btnTotal.classList.remove('active');
    if (btnMes) btnMes.classList.add('active');
    if (btnAno) btnAno.classList.add('active');

    // 🔥 MAGIA: Si el usuario cambia el AÑO, redibujamos el límite de meses automáticamente
    if (t === 'año') {
        renderizarDropdownMeses();

        // Blindaje extra: Si teníamos seleccionado "Diciembre" de 2025 y pasamos a 2026, 
        // Diciembre ya no es válido. Lo forzamos a caer en el mes actual (Marzo).
        const fechaActual = new Date();
        if (parseInt(v) === fechaActual.getFullYear() && parseInt(filtroActivo.mes) > fechaActual.getMonth() + 1) {
            const mesActualLimitado = String(fechaActual.getMonth() + 1).padStart(2, '0');
            filtroActivo.mes = mesActualLimitado;
            if (btnMes) btnMes.innerText = arrayMeses[fechaActual.getMonth()].nombre;
        }
    }

    ejecutarFiltroFinal(false);
}

// 🔥 CORRECCIÓN: Ahora sí obliga al servidor a recalcular el total
function resetFiltroTotal() {
    filtroActivo = { mes: "", año: "" };
    const btnMes = document.getElementById('btn-filtro-mes');
    const btnAno = document.getElementById('btn-filtro-año');
    const btnTotal = document.getElementById('btn-filtro-total');

    if (btnMes) btnMes.innerText = "Mes";
    if (btnAno) btnAno.innerText = "Año";
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    if (btnTotal) btnTotal.classList.add('active');

    // Disparamos la búsqueda de la data histórica
    ejecutarFiltroFinal(true);
}

function manejarClickSimple() {
    seleccionarOpcion('mes', String(new Date().getMonth() + 1).padStart(2, '0'), nombresMeses[String(new Date().getMonth() + 1).padStart(2, '0')]);
    seleccionarOpcion('año', String(new Date().getFullYear()), new Date().getFullYear());
}

function toggleDrop(id, e) {
    e.stopPropagation();
    const el = document.getElementById(id);
    const s = getComputedStyle(el).display;
    document.querySelectorAll('.mini-drop,#menu-sucursales').forEach(x => x.style.display = 'none');
    el.style.display = s === 'none' ? 'block' : 'none';
}

function renderBotonEliminar() {
    document.getElementById('admin-actions-status').innerHTML = dataCliente.perfil.activo == 1
        ? `<button onclick="cambiarEstado('delete_client')" style="background:#e74c3c; color:white; border:none; padding:10px 15px; border-radius:10px; font-size:12px; font-weight:bold; cursor:pointer;" title="Desactivar Cliente"><i class="fas fa-trash"></i></button>`
        : `<button onclick="cambiarEstado('reactivate_client')" style="background:#2ecc71; color:white; border:none; padding:10px 15px; border-radius:10px; font-size:12px; font-weight:bold; cursor:pointer;" title="Reactivar Cliente"><i class="fas fa-check"></i></button>`;
}

async function cambiarEstado(a) {
    const isDesactivar = a === 'delete_client';
    const confirmacion = await Swal.fire({
        title: '¿Confirmar acción?',
        text: isDesactivar ? "El cliente pasará a inactivo." : "El cliente será reactivado.",
        icon: 'warning',
        showCancelButton: true,
        ...window.swalConfig
    });

    if (confirmacion.isConfirmed) {
        try {
            await fetch(window.getApi('data_cliente_detalle.php'), {
                method: 'POST',
                body: new URLSearchParams({ action: a, id_interno: dataCliente.perfil.id_interno })
            });
            location.reload();
        } catch (e) {
            Swal.fire('Error', 'No se pudo aplicar el cambio', 'error');
        }
    }
}

// --- HELPERS BÁSICOS ---
function formatearTelefonoChileno(v) { return v ? "+56 9 " + v.replace(/\D/g, "").slice(-8) : ''; }

function copiarTelefonoFormateado() {
    if (!editando) {
        const t = document.getElementById('ui-telefono').innerText;
        navigator.clipboard.writeText("+569" + t.replace(/\D/g, "").slice(-8));
        mostrarToast("Teléfono copiado");
    }
}

function mostrarToast(m) {
    Toast.fire({ icon: 'success', title: m });
}

function copiarTexto(t) { navigator.clipboard.writeText(t).then(() => mostrarToast("Texto copiado al portapapeles")); }
function sincronizarCampo(i) { const k = i.getAttribute('data-key'); document.querySelectorAll(`.input-edit-ui[data-key="${k}"]`).forEach(e => { if (e !== i) e.value = i.value; }); }
function relocalizarModales() { ['modal-factura', 'modal-mapa'].forEach(id => { const el = document.getElementById(id); if (el) document.body.appendChild(el); }); }
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }
function abrirModal(id) { document.getElementById(id).style.display = 'flex'; if (id === 'modal-factura') renderFacturaBody(); }

function verFactura(u) {
    if (u && u != '#') window.open(u, '_blank');
    else Swal.fire('Aviso', 'El archivo PDF no está disponible', 'info');
}

function abrirMapa() {
    const lat = dataCliente.perfil.lat_despacho;
    const lng = dataCliente.perfil.lng_despacho;
    if (!lat || !lng) {
        Swal.fire('Atención', 'Este cliente no tiene coordenadas de despacho registradas.', 'warning');
        return;
    }
    abrirModal('modal-mapa');
    document.getElementById('map-canvas').innerHTML = `<iframe width="100%" height="100%" frameborder="0" src="https://maps.google.com/maps?q=$${lat},${lng}&hl=es&z=18&output=embed"></iframe>`;
    document.getElementById('btn-gmaps-externo').href = `https://www.google.com/maps/search/?api=1&query=$${lat},${lng}`;
}

document.addEventListener('DOMContentLoaded', initApp);
document.addEventListener('click', e => {
    if (!e.target.closest('.filter-btn')) document.querySelectorAll('.mini-drop').forEach(x => x.style.display = 'none');
    if (!e.target.closest('#wrapper-sucursales')) {
        const menuSucursales = document.getElementById('menu-sucursales');
        if (menuSucursales) menuSucursales.style.display = 'none';
    }
});