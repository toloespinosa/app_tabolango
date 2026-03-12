// 1. VARIABLES GLOBALES DE ESTADO
let productosCache = [];
let mostrarInactivos = false;
let esAdminGlobal = false;
let colorEnfasis = '#0F4B29';

// 2. INICIALIZACIÓN
document.addEventListener('DOMContentLoaded', inicializarPagina);

async function inicializarPagina() {
    window.moverModalesAlBody();

    const btnCrear = document.getElementById('btn-abrir-crear');
    if (btnCrear) btnCrear.style.display = 'none';

    // 🌟 Uso de la Identidad Inteligente de global.js
    const user = window.APP_USER;

    // Si tiene privilegios de edición en productos (Admin = 1 o Editor = 2)
    if (user && (user.isAdmin || user.isEditor)) {
        esAdminGlobal = true;
        colorEnfasis = user.isEditor ? '#F57C00' : '#0F4B29';

        document.getElementById('admin-controls').style.display = 'block';
        if (btnCrear) btnCrear.style.display = 'block';
    }

    await fetchProductos();
}

// 3. OBTENCIÓN Y RENDERIZADO (BLINDADO)
async function fetchProductos() {
    try {
        const urlAPI = window.getApi('obtener_productos.php');
        console.log("📍 Consultando Catálogo en:", urlAPI); // <-- CHIVATO 1: Verifica la ruta

        const res = await fetch(urlAPI);

        // CHIVATO 2: Si el archivo no existe (404) o hay error de servidor (500)
        if (!res.ok) {
            throw new Error(`HTTP Error ${res.status}: Revisa que la ruta del tema en global.js sea correcta.`);
        }

        const data = await res.json();

        // CHIVATO 3: Si la API devuelve un error SQL o de Auth, no es un Array.
        if (!Array.isArray(data)) {
            console.error("Respuesta inesperada de la API:", data);
            throw new Error(data.message || "La base de datos no devolvió la lista de productos.");
        }

        productosCache = data;
        filtrarProductos();

    } catch (e) {
        console.error("❌ Error Crítico cargando productos:", e);

        // Mostrar el error exacto en la interfaz para depurar rápido
        document.getElementById('products-grid').innerHTML = `
            <div style="grid-column: 1/-1; text-align:center; padding: 40px; background: rgba(255,0,0,0.1); border-radius: 15px; border: 1px solid rgba(255,0,0,0.2);">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 40px; color: #ff6b6b; margin-bottom: 15px;"></i>
                <h3 style="color: white; margin: 0;">Fallo de Conexión</h3>
                <p style="color: #ffbaba; font-size: 14px; margin-top: 5px; font-weight: 600;">${e.message}</p>
                <p style="color: #ccc; font-size: 12px; margin-top: 15px;">Abre la consola (F12) para ver más detalles técnicos.</p>
            </div>`;
    }
}

function filtrarProductos() {
    const grid = document.getElementById('products-grid');
    const busqueda = document.getElementById('buscador').value.toLowerCase().trim();
    document.getElementById('btn-clear-search').style.display = busqueda.length > 0 ? 'block' : 'none';

    grid.innerHTML = '';
    const filtrados = productosCache.filter(p => {
        const activo = parseInt(p.activo) === 1;
        const nombre = (p.producto || "").toLowerCase();
        const cumpleEstado = activo || (esAdminGlobal && mostrarInactivos);
        const cumpleBusqueda = !busqueda || nombre.includes(busqueda);
        return cumpleEstado && cumpleBusqueda;
    });

    if (filtrados.length === 0) {
        grid.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: #AAA;"><i class="fa-solid fa-magnifying-glass" style="font-size: 40px; opacity: 0.3;"></i><h3 style="margin:10px 0 0; color:#FFF;">Sin resultados</h3></div>`;
        return;
    }

    filtrados.forEach(p => {
        const kg = parseFloat(p.kg_por_unidad) || 1;
        const precioKg = Math.round(p.precio_actual / kg);
        const colorBanda = p.color_diferenciador || '#0F4B29';
        const icono = (p.icono && p.icono.trim() !== "") ? p.icono : "📦";
        const activo = parseInt(p.activo) === 1;
        const textoCalibre = (p.calibre && p.calibre.trim() !== "") ? p.calibre : "---";

        const valVariedad = p.variedad || "";
        const valFormato = p.formato || "";

        grid.innerHTML += `
            <div class="order-card ${!activo ? 'card-inactivo' : ''}" id="card-${p.id_producto}">
                <div class="card-inner">
                    <div class="card-front">
                        <div class="product-color-band" style="background:${colorBanda}"></div>
                        <div class="card-main-content">
                            <div class="product-header">
                                <div class="badge-calibre" style="background:${colorBanda};">${textoCalibre}</div>
                                <div class="product-icon-circle">${icono}</div>
                                
                                <h4 class="product-name">${p.producto}</h4>
                                ${valVariedad ? `<span class="product-variety-label">${valVariedad}</span>` : ''}
                                
                                <p style="font-size:13px; color:#888; font-weight:600; margin-top:5px;">
                                    ${valFormato ? valFormato + ' · ' : ''}${p.unidad} · ${kg} Kg
                                </p>
                            </div>
                            <div class="price-separator"></div>
                            <div class="price-display">
                                <span class="price-value" style="color:${colorEnfasis}">$${window.formatCLP(p.precio_actual).replace('$', '')}</span>
                                <div style="font-size:11px; color:#999; font-weight:600;">$${window.formatCLP(precioKg).replace('$', '')} / Kg</div>
                            </div>
                        </div>
                        ${esAdminGlobal ? `<div class="trigger-flip-bar" onclick="flip('${p.id_producto}')" style="background:${colorEnfasis}">✎ CONFIGURAR</div>` : ''}
                    </div>

                    <div class="card-back">
                        <div class="scrollable-form admin-fields">
                            <form onsubmit="guardarCambios(event, '${p.id_producto}')">
                                <div class="admin-box-section">
                                    <label>Identidad y Calibre</label>
                                    <div style="display:flex; gap:8px; flex-wrap: wrap;">
                                        <input type="text" id="edit-ico-${p.id_producto}" value="${icono}" class="input-emoji" style="flex:1; min-width:50px; text-align:center;" maxlength="2">
                                        <input type="text" id="edit-nom-${p.id_producto}" value="${p.producto}" style="flex:2; min-width:120px;">
                                        <input type="text" id="edit-cal-${p.id_producto}" value="${p.calibre || ''}" placeholder="Cal" style="flex:1; min-width:60px; text-align:center; font-weight:bold; background:#F9F9F9;">
                                    </div>
                                </div>
                                
                                <div class="admin-box-section">
                                    <label>Variedad y Formato</label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="edit-var-${p.id_producto}" value="${valVariedad}" placeholder="Variedad" style="flex:1;">
                                        <input type="text" id="edit-fmt-${p.id_producto}" value="${valFormato}" placeholder="Formato" style="flex:1;">
                                    </div>
                                </div>

                                <div class="admin-box-section">
                                    <label>Logística</label>
                                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                                        <div style="flex:1"><label>Unidad</label><input type="text" id="edit-uni-${p.id_producto}" value="${p.unidad}"></div>
                                        <div style="flex:1"><label>Kg/Und</label><input type="number" step="0.1" id="edit-kg-${p.id_producto}" value="${kg}"></div>
                                    </div>
                                    <div style="display:flex; gap:8px;">
                                        <div style="flex:1"><label>Precio Venta</label><input type="number" id="edit-pre-${p.id_producto}" value="${p.precio_actual}"></div>
                                        <div style="flex:1"><label>Costo</label><input type="number" id="edit-cos-${p.id_producto}" value="${p.costo_actual}"></div>
                                    </div>
                                </div>
                                <div class="admin-box-section">
                                    <label>Estado y Color</label>
                                    <div style="display:flex; gap:8px;">
                                        <select id="edit-act-${p.id_producto}" class="admin-select" style="flex:2">
                                            <option value="1" ${activo ? 'selected' : ''}>ACTIVO</option>
                                            <option value="0" ${!activo ? 'selected' : ''}>INACTIVO</option>
                                        </select>
                                        <input type="color" id="edit-col-${p.id_producto}" value="${colorBanda}" style="flex:1; height:40px; padding:2px; cursor:pointer; border:none; background:transparent;">
                                    </div>
                                </div>
                                <button type="submit" style="background:#0F4B29; color:white; border:none; padding:15px; border-radius:12px; font-weight:800; width:100%; cursor:pointer; transition:0.2s;">GUARDAR CAMBIOS</button>
                            </form>
                        </div>
                        <div class="trigger-flip-bar" onclick="flip('${p.id_producto}')" style="background:#333; color:white !important;">VOLVER</div>
                    </div>
                </div>
            </div>`;
    });
}

// 4. FUNCIONES INTERACTIVAS Y MODALES
function limpiarBuscador() {
    const input = document.getElementById('buscador');
    input.value = '';
    filtrarProductos();
    input.focus();
}

function toggleOcultos() {
    mostrarInactivos = !mostrarInactivos;
    const btn = document.getElementById('btn-toggle-hidden');
    btn.innerHTML = mostrarInactivos ? '<i class="fa-solid fa-eye"></i> OCULTAR INACTIVOS' : '<i class="fa-solid fa-eye-slash"></i> MOSTRAR OCULTOS';
    btn.style.background = mostrarInactivos ? '#E98C00' : '#f0f0f0';
    btn.style.color = mostrarInactivos ? 'white' : '#666';
    filtrarProductos();
}

function flip(id) { document.getElementById('card-' + id).classList.toggle('is-flipped'); }

function abrirModalCrear() {
    const modal = document.getElementById('modal-producto');
    if (modal) {
        document.body.classList.add('modal-abierto');
        modal.style.display = 'flex';
    }
}

function cerrarModal() {
    const modal = document.getElementById('modal-producto');
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-abierto');
    }
}

// 5. OPERACIONES DE GUARDADO (AJAX + SweetAlert2)
async function guardarCambios(event, id) {
    event.preventDefault();

    const fd = new FormData();
    fd.append('id_producto', id);
    fd.append('producto', document.getElementById(`edit-nom-${id}`).value);
    fd.append('icono', document.getElementById(`edit-ico-${id}`).value);
    fd.append('unidad', document.getElementById(`edit-uni-${id}`).value);
    fd.append('kg_por_unidad', document.getElementById(`edit-kg-${id}`).value);
    fd.append('precio_actual', document.getElementById(`edit-pre-${id}`).value);
    fd.append('costo_actual', document.getElementById(`edit-cos-${id}`).value);
    fd.append('color_diferenciador', document.getElementById(`edit-col-${id}`).value);
    fd.append('activo', document.getElementById(`edit-act-${id}`).value);
    fd.append('calibre', document.getElementById(`edit-cal-${id}`).value);
    fd.append('variedad', document.getElementById(`edit-var-${id}`).value);
    fd.append('formato', document.getElementById(`edit-fmt-${id}`).value);

    Swal.fire({ ...window.swalConfig, title: 'Guardando cambios...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const urlSave = window.getApi('guardar_producto.php');
        const req = await fetch(urlSave, { method: 'POST', body: fd });

        if (!req.ok) throw new Error("Error en el servidor al guardar");

        Swal.fire({ ...window.swalConfig, title: 'Producto Actualizado', icon: 'success', timer: 1500, showConfirmButton: false });
        flip(id);
        await fetchProductos();
    } catch (e) {
        Swal.fire({ ...window.swalConfig, title: 'Error', text: 'No se pudo guardar el producto. ' + e.message, icon: 'error' });
    }
}

async function guardarNuevoProducto(event) {
    event.preventDefault();

    const fd = new FormData(event.target);
    fd.append('activo', '1');

    Swal.fire({ ...window.swalConfig, title: 'Creando producto...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const urlSave = window.getApi('guardar_producto.php');
        const req = await fetch(urlSave, { method: 'POST', body: fd });

        if (!req.ok) throw new Error("Error en el servidor al crear");

        cerrarModal();
        event.target.reset();
        document.getElementById('emoji-preview').innerText = '📦';

        Swal.fire({ ...window.swalConfig, title: '¡Producto Creado!', icon: 'success', timer: 1500, showConfirmButton: false });
        await fetchProductos();
    } catch (e) {
        Swal.fire({ ...window.swalConfig, title: 'Error', text: 'No se pudo crear el producto. ' + e.message, icon: 'error' });
    }
}