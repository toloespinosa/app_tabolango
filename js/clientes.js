let todosLosClientes = [];
let modoOculto = false;
let isAdmin = false;

// --- 1. LÓGICA DE UI FACTURACIÓN ---
function toggleFacturacion() {
    const isChecked = document.getElementById('check-misma-dir').checked;
    const wrapper = document.getElementById('wrapper-facturacion');

    if (isChecked) {
        wrapper.style.display = 'none';
    } else {
        wrapper.style.display = 'block';
    }
}

// --- 2. CARGAR DIRECTORIO (SIN CAMBIOS) ---
async function cargarDirectorio() {
    const userEmail = obtenerEmailLimpio();
    try {
        const response = await fetch(`https://tabolango.cl/detalle-cliente.php?action=list_clients&wp_user=${encodeURIComponent(userEmail)}`);
        const data = await response.json();
        todosLosClientes = data.clientes || [];
        isAdmin = data.is_admin || false;
        if (isAdmin) document.getElementById('container-switch-ocultos').style.display = 'flex';
        filtrarYRenderizar();
    } catch (e) { console.error("Error al cargar"); }
}

function filtrarYRenderizar() {
    const txt = document.getElementById('buscador-clientes').value.toLowerCase();

    const baseFiltrada = todosLosClientes.filter(c => {
        const valActivo = String(c.activo);

        let coincideEstado = false;
        if (modoOculto) {
            if (valActivo === "0") coincideEstado = true;
            if (valActivo === "false") coincideEstado = true;
        } else {
            if (valActivo === "1") coincideEstado = true;
            if (valActivo === "true") coincideEstado = true;
        }

        if (coincideEstado) {
            return c.cliente.toLowerCase().includes(txt);
        }
        return false;
    });

    const clientesUnificados = Object.values(baseFiltrada.reduce((acc, curr) => {
        const nombreBase = curr.cliente.split(' - ')[0].trim();
        const key = nombreBase;

        if (!acc[key]) {
            acc[key] = {
                ...curr,
                cliente: key,
                monto_mes_actual: 0,
                promedio_mensual: 0,
                sucursales_count: -2,
                id_global: curr.id_interno
            };
        }

        if (curr.es_global == 1) {
            acc[key].id_global = curr.id_interno;
        } else if (curr.es_global == "1") {
            acc[key].id_global = curr.id_interno;
        }

        acc[key].monto_mes_actual += parseFloat(curr.monto_mes_actual || 0);
        acc[key].promedio_mensual += parseFloat(curr.promedio_mensual || 0);
        acc[key].sucursales_count += 1;

        return acc;
    }, {}));

    renderizarLista(clientesUnificados);
}

function renderizarLista(clientes) {
    const contenedor = document.getElementById('lista-clientes');
    const hoy = new Date();
    const dia = hoy.getDate();
    let porcMeta = dia > 21 ? 1.0 : (dia > 14 ? 0.75 : (dia > 7 ? 0.50 : 0.25));

    contenedor.innerHTML = clientes.map(c => {
        const compra = parseFloat(c.monto_mes_actual || 0);
        const prom = parseFloat(c.promedio_mensual || 0);
        const metaSem = prom * porcMeta;
        const pct = prom > 0 ? Math.min(Math.round((compra / prom) * 100), 100) : 0;

        let status = "success";
        let msg = "✅ Vamos Bien! 🎉";
        if (!modoOculto) {
            if (prom > 0) {
                if (compra === 0) { status = "danger"; msg = `Falta compra<br><b>${formatearDinero(metaSem)}</b>`; }
                else if (compra < metaSem) { status = "warning"; msg = `Faltan<br><b>${formatearDinero(metaSem - compra)}</b>`; }
            }
        }

        let emoji = "";
        if (c.ranking == 1) emoji = "🏆";
        else if (c.ranking == 2) emoji = "🥈";
        else if (c.ranking == 3) emoji = "🥉";

        return `
            <div class="cliente-mini-card ${modoOculto ? '' : 'card-' + status}" 
                 onclick="handleCardClick(event, '${c.id_global}')" 
                 style="${modoOculto ? 'border-left-color:#999' : ''}">
                <div class="mini-left">
                    <div class="mini-logo" style="${c.logo ? `background-image: url('${c.logo}')` : ''}">
                        ${c.logo ? '' : c.cliente.charAt(0)}
                    </div>
                    <div class="mini-body">
                        <h4 class="mini-nombre">
                            ${c.cliente} 
                            ${c.sucursales_count > 0 ? `<span style="font-size:10px; color:#ff9500; margin-left:5px;">(${c.sucursales_count + 1} sucursales)</span>` : ''}
                        </h4>
                        ${modoOculto ?
                `<button onclick="activarCliente(event, '${c.id_interno}')" class="btn-reactivar">RE-ACTIVAR</button>` :
                `<div>
                                <span class="label-meta">Compra Actual</span>
                                <span class="monto-badge">${formatearDinero(compra)}</span>
                                <span class="meta-destacada"> / ${formatearDinero(metaSem)}</span>
                                <div class="mensual-info">META MENSUAL: ${formatearDinero(prom)} (${pct}%)</div>
                            </div>`
            }
                    </div>
                </div>
                <div class="flip-zone" onclick="event.stopPropagation(); this.classList.toggle('is-flipped')">
                    <div class="flip-inner">
                        <div class="flip-front">
                            <div class="alerta-circulo"></div>
                            <div class="rank-pill"><span class="rank-number">${emoji} #${c.ranking}</span></div>
                        </div>
                        <div class="flip-back"><div class="msg-texto">${modoOculto ? 'Oculto' : msg}</div></div>
                    </div>
                </div>
                ${!modoOculto ? `<div class="progress-container"><div class="progress-bar" style="width:${pct}%"></div></div>` : ''}
            </div>`;
    }).join('');
}

// --- 3. FUNCIONES DE CARGA DE DATOS ---
async function cargarUsuariosResponsables() {
    const select = document.getElementById('reg-responsable');
    try {
        const response = await fetch('https://tabolango.cl/obtener_usuarios.php');
        const usuarios = await response.json();

        select.innerHTML = '<option value="">Seleccione responsable...</option>';
        if (usuarios.error || !usuarios.length) {
            select.innerHTML = '<option value="">No hay responsables disponibles</option>';
            return;
        }

        usuarios.forEach(u => {
            if (u.nombre && u.email) {
                const option = document.createElement('option');
                option.value = u.email;
                const nombreFormateado = u.nombre.toLowerCase().split(' ').map(p => p.charAt(0).toUpperCase() + p.slice(1)).join(' ');
                option.textContent = nombreFormateado;
                select.appendChild(option);
            }
        });
    } catch (e) {
        select.innerHTML = '<option value="">Error al cargar usuarios</option>';
    }
}

async function cargarCategorias() {
    const select = document.getElementById('reg-categoria');
    select.innerHTML = '<option value="3">Cargando...</option>';

    try {
        const response = await fetch('https://tabolango.cl/crear-cliente.php?action=get_categories');
        if (!response.ok) throw new Error("HTTP error " + response.status);

        const categorias = await response.json();
        select.innerHTML = '';

        if (!categorias || categorias.length === 0) {
            select.innerHTML = '<option value="3">Minorista (Default)</option>';
            return;
        }

        let foundDefault = false;
        categorias.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.nombre;
            select.appendChild(option);
            if (cat.id == 3) foundDefault = true;
        });

        if (foundDefault) select.value = "3";

    } catch (e) {
        select.innerHTML = '<option value="3" selected>Minorista (Error Carga)</option>';
    }
}

// --- 4. MODAL Y GUARDADO ---

function abrirModalCliente() {
    const modal = document.getElementById('modal-cliente');
    document.getElementById('form-nuevo-cliente').reset();

    // Resetear campos ocultos
    document.getElementById('reg-ciudad').value = "";
    document.getElementById('reg-comuna').value = "";
    document.getElementById('reg-ciudad-factura').value = "";
    document.getElementById('reg-comuna-factura').value = "";
    document.getElementById('wrapper-facturacion').style.display = 'block'; // Reset display

    cargarUsuariosResponsables();
    cargarCategorias();

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function cerrarModalCliente() {
    document.getElementById('modal-cliente').style.display = 'none';
    document.body.style.overflow = 'auto';
}

async function guardarCliente(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit-cliente');
    const originalText = btn.innerText;

    btn.innerText = "Guardando...";
    btn.disabled = true;

    // --- LÓGICA DE UNIFICACIÓN ---
    const usarMisma = document.getElementById('check-misma-dir').checked;

    // Variables de Facturación
    let dirFactura = document.getElementById('reg-direccion-factura').value;
    let comFactura = document.getElementById('reg-comuna-factura').value;
    let ciuFactura = document.getElementById('reg-ciudad-factura').value;
    let emailFactura = document.getElementById('reg-email-factura').value;
    let telFactura = document.getElementById('reg-telefono-factura').value;

    // Variables para columnas 'nombre' y 'apellido'
    let valNombre = document.getElementById('reg-nombre').value;
    let valApellido = document.getElementById('reg-apellido').value;

    if (usarMisma) {
        dirFactura = document.getElementById('reg-direccion').value;
        comFactura = document.getElementById('reg-comuna').value;
        ciuFactura = document.getElementById('reg-ciudad').value;
        emailFactura = document.getElementById('reg-email').value;
        telFactura = document.getElementById('reg-telefono').value;

        // Lógica: Tomar el "Nombre Contacto" general y partirlo en Nombre y Apellido
        const contactoFull = document.getElementById('reg-contacto').value.trim();
        if (contactoFull) {
            const partes = contactoFull.split(' ');
            valNombre = partes[0];
            valApellido = partes.slice(1).join(' ');
        }
    }

    const datos = {
        action: 'add_client',
        cliente: document.getElementById('reg-cliente').value,
        razon_social: document.getElementById('reg-razon-social').value,
        giro: document.getElementById('reg-giro').value,
        tipo_cliente: document.getElementById('reg-categoria').value,
        rut: document.getElementById('reg-rut').value,

        // Datos Despacho / General
        telefono: document.getElementById('reg-telefono').value,
        email: document.getElementById('reg-email').value,
        contacto: document.getElementById('reg-contacto').value,
        responsable: document.getElementById('reg-responsable').value,
        vendedor: obtenerEmailLimpio(),
        direccion: document.getElementById('reg-direccion').value,
        ciudad: document.getElementById('reg-ciudad').value,
        comuna: document.getElementById('reg-comuna').value,
        lat_despacho: document.getElementById('reg-latitud').value,
        lng_despacho: document.getElementById('reg-longitud').value,

        // Datos Factura
        direccion_factura: dirFactura,
        ciudad_factura: ciuFactura,
        comuna_factura: comFactura,
        email_factura: emailFactura,
        telefono_factura: telFactura,

        // AQUÍ VAN LAS COLUMNAS EXISTENTES
        nombre: valNombre,
        apellido: valApellido
    };

    try {
        const resp = await fetch('https://tabolango.cl/crear-cliente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });
        const res = await resp.json();
        if (res.success) {
            alert("✅ Cliente creado correctamente");
            cerrarModalCliente();
            cargarDirectorio();
        } else { alert("❌ Error: " + res.error); }
    } catch (err) { alert("❌ Error de conexión"); }
    finally {
        btn.innerText = originalText;
        btn.disabled = false;
    }
}

// --- UTILIDADES ---

function handleCardClick(e, id) {
    if (!e.target.closest('.flip-zone') && !e.target.closest('button')) {
        location.href = `/id/?id=${id}`;
    }
}

function formatearDinero(n) { return new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 }).format(n); }

function obtenerEmailLimpio() {
    const b = document.getElementById('session-email-bridge');
    try {
        if (b) {
            const match = b.innerText.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
            if (match) return match[0].toLowerCase();
        }
    } catch (e) { }
    return "";
}

function toggleModoOculto() {
    modoOculto = document.getElementById('check-ocultos').checked;
    filtrarYRenderizar();
}

function aplicarMascaraRUT(rut) {
    let valor = rut.replace(/[^\dkK]/g, "");
    if (valor.length <= 1) return valor;
    let cuerpo = valor.slice(0, -1).slice(0, 8);
    let dv = valor.slice(-1).toUpperCase();
    let cuerpoFormateado = "";
    while (cuerpo.length > 3) {
        cuerpoFormateado = "." + cuerpo.slice(-3) + cuerpoFormateado;
        cuerpo = cuerpo.slice(0, -3);
    }
    return cuerpo + cuerpoFormateado + "-" + dv;
}

// --- PROXY RUT ---
async function consultarRutSimpleAPI(rutCompleto) {
    if (!rutCompleto) return;
    const checkLen = rutCompleto.replace(/[^0-9kK]/g, '');
    if (checkLen.length < 5) return;

    try {
        const response = await fetch('https://tabolango.cl/crear-cliente.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'consultar_rut', rut: rutCompleto })
        });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        if (data && !data.error) {
            if (data.razonSocial) document.getElementById('reg-razon-social').value = data.razonSocial;
            if (data.actividadesEconomicas && data.actividadesEconomicas.length > 0) {
                if (data.actividadesEconomicas[0].descripcion) {
                    document.getElementById('reg-giro').value = data.actividadesEconomicas[0].descripcion;
                }
            }
        }
    } catch (e) { console.warn("Error autocompletado:", e); }
}

document.getElementById('reg-telefono').addEventListener('input', function (e) {
    let v = e.target.value;
    if (!v.startsWith('+569')) e.target.value = '+569' + v.replace(/\D/g, "").slice(-8);
    if (v.length > 12) e.target.value = v.slice(0, 12);
});

// --- GOOGLE MAPS AUTOCOMPLETE (DOBLE) ---
let autocompleteDespacho;
let autocompleteFactura;

function initAutocomplete() {
    // 1. Autocomplete para DESPACHO
    const inputDespacho = document.getElementById('reg-direccion');
    autocompleteDespacho = new google.maps.places.Autocomplete(inputDespacho, {
        componentRestrictions: { country: "cl" },
        fields: ["address_components", "geometry"]
    });
    autocompleteDespacho.addListener("place_changed", () => fillAddress(autocompleteDespacho, 'reg-latitud', 'reg-longitud', 'reg-comuna', 'reg-ciudad'));

    // 2. Autocomplete para FACTURACIÓN
    const inputFactura = document.getElementById('reg-direccion-factura');
    autocompleteFactura = new google.maps.places.Autocomplete(inputFactura, {
        componentRestrictions: { country: "cl" },
        fields: ["address_components", "geometry"] // No necesitamos lat/lng para factura, pero geometry viene por defecto
    });
    autocompleteFactura.addListener("place_changed", () => fillAddress(autocompleteFactura, null, null, 'reg-comuna-factura', 'reg-ciudad-factura'));
}

function fillAddress(autocompleteObj, idLat, idLng, idComuna, idCiudad) {
    const place = autocompleteObj.getPlace();
    if (!place.geometry) return;

    // Solo llenamos Lat/Lng si se proveen los IDs (para Despacho)
    if (idLat && idLng) {
        document.getElementById(idLat).value = place.geometry.location.lat();
        document.getElementById(idLng).value = place.geometry.location.lng();
    }

    let comuna = "";
    let ciudad = "";

    for (const component of place.address_components) {
        const types = component.types;
        if (types.includes("administrative_area_level_3")) {
            comuna = component.long_name;
        }
        if (types.includes("locality")) {
            ciudad = component.long_name;
        } else if (types.includes("administrative_area_level_2")) {
            if (ciudad === "") ciudad = component.long_name;
        }
    }

    if (idComuna) document.getElementById(idComuna).value = comuna;
    if (idCiudad) document.getElementById(idCiudad).value = ciudad;
}

document.addEventListener('DOMContentLoaded', () => {
    cargarDirectorio();
    initAutocomplete();
});