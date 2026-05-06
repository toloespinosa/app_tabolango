console.log("🚀 Iniciando productos.js con Tramos Profesionales...");

// 1. VARIABLES GLOBALES DE ESTADO
let productosCache = [];
let mostrarInactivos = false;
let esAdminGlobal = false;
let colorEnfasis = '#0F4B29';

// 2. INICIALIZACIÓN BLINDADA (Se ejecuta sí o sí)
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inicializarPagina);
} else {
    inicializarPagina(); // Si el DOM ya cargó, lo fuerza a iniciar
}

async function inicializarPagina() {
    try {
        console.log("✅ Ejecutando inicializarPagina()...");

        if (typeof window.moverModalesAlBody === 'function') {
            window.moverModalesAlBody();
        }

        const btnCrear = document.getElementById('btn-abrir-crear');
        if (btnCrear) btnCrear.style.display = 'none';

        // Identidad Inteligente
        const user = window.APP_USER;
        console.log("👤 Usuario detectado:", user);

        if (user && (user.isAdmin || user.isEditor)) {
            esAdminGlobal = true;
            colorEnfasis = '#0F4B29';

            const adminCtrls = document.getElementById('admin-controls');
            if (adminCtrls) adminCtrls.style.display = 'block';
            if (btnCrear) btnCrear.style.display = 'block';
        }

        await fetchProductos();

    } catch (err) {
        console.error("❌ Error en inicialización:", err);
        document.getElementById('products-grid').innerHTML = `<p style="color:#ff6b6b; text-align:center; font-weight:bold;">Error de JS Local: ${err.message}</p>`;
    }
}

// 3. OBTENCIÓN Y RENDERIZADO
async function fetchProductos() {
    const grid = document.getElementById('products-grid');
    console.log("📡 Ejecutando fetchProductos...");

    try {
        if (typeof window.getApi !== 'function') {
            throw new Error("El archivo global.js no está cargado correctamente. Falta la función getApi().");
        }

        const urlAPI = window.getApi('obtener_productos.php');
        const res = await fetch(urlAPI);

        if (!res.ok) throw new Error(`Error del Servidor HTTP ${res.status}.`);

        const textRaw = await res.text();

        try {
            const data = JSON.parse(textRaw);
            if (data.status === 'error') throw new Error(data.message);

            productosCache = Array.isArray(data) ? data : [];

            if (productosCache.length === 0) {
                grid.innerHTML = '<p style="text-align:center; color:#AAA;">El catálogo está vacío o no hay conexión con la base de datos.</p>';
            } else {
                filtrarProductos();
            }
        } catch (jsonErr) {
            throw new Error("El servidor no devolvió un JSON válido. Revisa el archivo PHP.");
        }

    } catch (e) {
        console.error("❌ Error Crítico API:", e);
        grid.innerHTML = `<div style="grid-column: 1/-1; text-align:center; padding: 40px;"><p style="color: #ffbaba;">${e.message}</p></div>`;
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

        // ESTADO DE DESCUENTOS Y TRAMOS
        const aplicaDescuentos = parseInt(p.aplica_descuentos) === 1;
        const t = p.tramos || []; // Extraemos los tramos

        // VALORES POR DEFECTO PARA TRAMOS (Si no existen, aplica 3-5%, 5-10%, 10-15%)
        const t1_min = t[0]?.min !== undefined ? t[0].min : 3;
        const t1_pct = t[0]?.pct !== undefined ? t[0].pct : 5;

        const t2_min = t[1]?.min !== undefined ? t[1].min : 5;
        const t2_pct = t[1]?.pct !== undefined ? t[1].pct : 10;

        const t3_min = t[2]?.min !== undefined ? t[2].min : 10;
        const t3_pct = t[2]?.pct !== undefined ? t[2].pct : 15;

        // TEXTOS VISUALES
        const textoCalibre = (p.calibre && p.calibre.trim() !== "") ? p.calibre : "---";
        const inputCalibre = (textoCalibre === "---") ? "" : textoCalibre;
        const valVariedad = p.variedad || "";
        const valFormato = p.formato || "";

        grid.innerHTML += `
            <div class="order-card ${!activo ? 'card-inactivo' : ''}" id="card-${p.id_producto}" style="position:relative;">
                <div class="card-inner">
                    
                    <div class="card-front">
                        ${aplicaDescuentos ? `<div style="position:absolute; top:8px; right:8px; background:#E98C00; color:white; font-size:10px; font-weight:800; padding:4px 8px; border-radius:8px; z-index:10; box-shadow:0 2px 5px rgba(0,0,0,0.2);">🔥 TRAMOS</div>` : ''}
                        
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
                                        <input type="text" id="edit-cal-${p.id_producto}" value="${inputCalibre}" placeholder="Cal" style="flex:1; min-width:60px; text-align:center; font-weight:bold; background:#F9F9F9;">
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
                                        <div style="flex:1"><label>Kg/Und</label><input type="number" step="any" id="edit-kg-${p.id_producto}" value="${kg}"></div>
                                    </div>
                                    <div style="display:flex; gap:8px;">
                                        <div style="flex:1"><label>Precio Venta</label><input type="number" step="any" id="edit-pre-${p.id_producto}" value="${p.precio_actual}"></div>
                                        <div style="flex:1"><label>Costo</label><input type="number" step="any" id="edit-cos-${p.id_producto}" value="${p.costo_actual}"></div>
                                    </div>
                                </div>
                                
                                <div class="admin-box-section">
                                    <label>Configuración Adicional</label>
                                    <div style="display:flex; gap:8px; margin-bottom:8px;">
                                        <select id="edit-act-${p.id_producto}" class="admin-select" style="flex:1">
                                            <option value="1" ${activo ? 'selected' : ''}>ESTADO: ACTIVO</option>
                                            <option value="0" ${!activo ? 'selected' : ''}>ESTADO: INACTIVO</option>
                                        </select>
                                        
                                        <select id="edit-desc-${p.id_producto}" onchange="toggleTramosUI('${p.id_producto}')" class="admin-select" style="flex:1; background:${aplicaDescuentos ? '#fff3e0' : '#f9f9f9'}; color:${aplicaDescuentos ? '#E98C00' : '#555'}; font-weight:bold;">
                                            <option value="0" ${!aplicaDescuentos ? 'selected' : ''}>SIN TRAMOS</option>
                                            <option value="1" ${aplicaDescuentos ? 'selected' : ''}>CON TRAMOS</option>
                                        </select>
                                    </div>

                                    <div style="display:flex; gap:8px;">
                                        <label style="flex:2; align-self:center; margin:0;">Color Diferenciador:</label>
                                        <input type="color" id="edit-col-${p.id_producto}" value="${colorBanda}" style="flex:1; height:35px; padding:0; cursor:pointer; border:1px solid #ccc; border-radius:6px; background:transparent;">
                                    </div>
                                </div>

                                <div class="admin-box-section tramos-container" id="tramos-container-${p.id_producto}" style="display:${aplicaDescuentos ? 'block' : 'none'}; background:#fff3e0; border:1px solid #ffcc80; padding:12px; border-radius:8px; margin-bottom:15px;">
                                    <label style="color:#e65100; font-weight:900; margin-bottom:10px; display:block;">Configuración de Tramos (Volumen)</label>
                                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:8px;">
                                        <span style="font-size:11px; font-weight:bold; color:#666;">Cantidad Mín.</span>
                                        <span style="font-size:11px; font-weight:bold; color:#666;">% Descuento</span>
                                        
                                        <input type="number" step="any" id="t1-q-${p.id_producto}" value="${t1_min}" placeholder="Ej: 3" style="padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                                        <input type="number" step="any" id="t1-p-${p.id_producto}" value="${t1_pct}" placeholder="Ej: 5" style="padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                                        
                                        <input type="number" step="any" id="t2-q-${p.id_producto}" value="${t2_min}" placeholder="Ej: 5" style="padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                                        <input type="number" step="any" id="t2-p-${p.id_producto}" value="${t2_pct}" placeholder="Ej: 10" style="padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                                        
                                        <input type="number" step="any" id="t3-q-${p.id_producto}" value="${t3_min}" placeholder="Ej: 10" style="padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
                                        <input type="number" step="any" id="t3-p-${p.id_producto}" value="${t3_pct}" placeholder="Ej: 15" style="padding:8px; border:1px solid #ccc; border-radius:4px; text-align:center;">
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

function toggleTramosUI(id) {
    const val = document.getElementById(`edit-desc-${id}`).value;
    const container = document.getElementById(`tramos-container-${id}`);
    const select = document.getElementById(`edit-desc-${id}`);

    if (val == "1") {
        container.style.display = 'block';
        select.style.background = '#fff3e0';
        select.style.color = '#E98C00';
    } else {
        container.style.display = 'none';
        select.style.background = '#f9f9f9';
        select.style.color = '#555';
    }
}

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

// 5. OPERACIONES DE GUARDADO
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
    fd.append('aplica_descuentos', document.getElementById(`edit-desc-${id}`).value);

    // Recolectar tramos para enviar al PHP
    const tramos = [];
    for (let i = 1; i <= 3; i++) {
        const q = document.getElementById(`t${i}-q-${id}`).value;
        const p = document.getElementById(`t${i}-p-${id}`).value;
        if (q && p) {
            tramos.push({ min: parseFloat(q), pct: parseFloat(p) });
        }
    }
    fd.append('tramos_json', JSON.stringify(tramos));

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

    if (!fd.has('aplica_descuentos')) {
        fd.append('aplica_descuentos', '0');
    }

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