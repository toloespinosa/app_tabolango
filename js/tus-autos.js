// Variable Globales del Módulo
let globalUsers = [];
let currentUserEmail = '';

document.addEventListener("DOMContentLoaded", () => {
    // 1. Mover modales al body (Función para evitar z-index issues)
    const modalesIds = ['modal-vehiculo', 'modal-conductores'];
    modalesIds.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });

    // 2. Iniciar Módulo
    initApp();
});

async function initApp() {
    // Integración con el motor global de identidad
    currentUserEmail = window.obtenerEmailLimpio();

    if (currentUserEmail) {
        await cargarDatos();
    } else {
        document.getElementById('lista-autos').innerHTML = "<p style='text-align:center; color: #e74c3c; font-weight: bold;'>No se detectó sesión activa.</p>";
    }
}

// Utilidades
function maskPatente(patente) {
    let p = patente.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (p.length > 4) return p.substring(0, 4) + '-' + p.substring(4);
    return p;
}
function getDaysDiff(dateStr) {
    if (!dateStr || dateStr === '0000-00-00') return -1;
    const hoy = new Date();
    const venc = new Date(dateStr);
    return Math.ceil((venc - hoy) / (1000 * 60 * 60 * 24));
}
function formatDateUI(dateStr) {
    if (!dateStr || dateStr === '0000-00-00') return '';
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y.slice(-2)}`;
}
function getStatusClass(diff) {
    if (diff < 0) return 'color-err';
    if (diff < 30) return 'color-warn';
    return 'color-ok';
}
function getWorstStatusBg(d1, d2, d3) {
    const min = Math.min(d1, d2, d3);
    if (min < 0) return 'bg-err';
    if (min < 30) return 'bg-warn';
    return 'bg-ok';
}

async function cargarDatos() {
    try {
        // Obtenemos API de forma inteligente gracias a global.js
        const urlAutos = window.getApi('api_autos.php');
        const autosRes = await fetch(urlAutos).then(r => r.json());

        // Identidad Centralizada (Verifica permisos directamente de APP_USER)
        const isAppAdmin = window.APP_USER ? window.APP_USER.isAdmin : autosRes.is_admin;
        const isAppEditor = window.APP_USER ? window.APP_USER.isEditor : false;

        let usersData = null;
        let canEdit = isAppAdmin || isAppEditor;

        // Si tiene permiso de editar, le cargamos los controles y la lista de conductores
        if (canEdit) {
            document.getElementById('admin-controls').style.display = 'flex';
            document.getElementById('btn-add-auto').style.display = 'flex';

            // Traemos usuarios
            const urlUsers = window.getApi('usuarios.php') + '&action=get_all_users_with_roles';
            usersData = await fetch(urlUsers).then(r => r.json()).catch(() => null);

            if (usersData && usersData.usuarios) {
                // Filtrar conductores (Rol 3)
                globalUsers = usersData.usuarios.filter(u => u.roles_ids.some(r => String(r).trim() == '3'));
            }
        }

        renderAutos(autosRes.autos || [], canEdit);

    } catch (error) {
        console.error(error);
        Swal.fire({ ...window.swalConfig, title: 'Error de Conexión', text: 'No se pudo cargar la flota', icon: 'error' });
    }
}

function renderAutos(autos, canEdit) {
    const grid = document.getElementById('lista-autos');
    grid.innerHTML = "";
    if (autos.length === 0) {
        grid.innerHTML = "<p style='grid-column: 1 / -1; text-align:center;'>Sin vehículos disponibles en el sistema.</p>";
        return;
    }

    autos.forEach(auto => {
        const dPermiso = getDaysDiff(auto.venc_permiso);
        const dSoap = getDaysDiff(auto.venc_soap);
        const dRev = getDaysDiff(auto.venc_revision);

        const txtPermiso = dPermiso < 0 ? 'Vencido' : (dPermiso < 30 ? dPermiso + ' días' : 'Vigente');
        const txtSoap = dSoap < 0 ? 'Vencido' : (dSoap < 30 ? dSoap + ' días' : 'Vigente');
        const txtRev = dRev < 0 ? 'Vencido' : (dRev < 30 ? dRev + ' días' : 'Vigente');

        const bgPatente = getWorstStatusBg(dPermiso, dSoap, dRev);

        // EMPAQUETADO SEGURO: Evita que comillas o saltos de línea rompan el HTML
        const autoJsonEncoded = encodeURIComponent(JSON.stringify(auto));

        // Optimizado para Swal.fire en caso de no haber PDF
        const docHandler = (url) => url ? `window.open('${url}', '_blank')` : `Swal.fire({...window.swalConfig, title: 'No Disponible', text: 'El documento no ha sido subido.', icon: 'info'})`;
        const titleDoc = (url) => url ? 'Click para ver documento' : 'Sin documento';

        // HTML de Botones de Admin
        let adminButtons = '';
        if (canEdit) {
            adminButtons = `
            <div class="admin-actions">
                <button class="btn-mini btn-edit" onclick="openEditModal('${autoJsonEncoded}')">
                    <i class="fa-solid fa-pen"></i> Editar
                </button>
                <button class="btn-mini btn-user" onclick="openDriverModal('${auto.patente}', '${autoJsonEncoded}')">
                    <i class="fa-solid fa-user-gear"></i> Asignar
                </button>
            </div>`;
        }

        grid.innerHTML += `
        <div class="card-auto">
            <div class="card-header">
                <img src="${auto.foto || 'https://tabolango.cl/media/ilustracion_auto.png'}" alt="Auto">
                <div class="patente-badge ${bgPatente}">
                    ${maskPatente(auto.patente)}
                </div>
            </div>
            
            <div class="card-body">
                <div class="info-main">${auto.marca} ${auto.modelo}</div>
                <div class="info-sub">${auto.tipo_vehiculo || 'Vehículo'} • Clase ${auto.clase_licencia || 'B'}</div>

                <div class="status-grid">
                    <div class="status-item ${getStatusClass(dPermiso)}" onclick="${docHandler(auto.pdf_permiso)}" title="${titleDoc(auto.pdf_permiso)}">
                        <i class="fa-solid fa-file-contract"></i>
                        <small>Patente</small>
                        <span class="estado-texto">${txtPermiso}</span>
                        <span class="fecha-mini">${formatDateUI(auto.venc_permiso)}</span>
                    </div>
                    <div class="status-item ${getStatusClass(dSoap)}" onclick="${docHandler(auto.pdf_soap)}" title="${titleDoc(auto.pdf_soap)}">
                        <i class="fa-solid fa-shield-heart"></i>
                        <small>SOAP</small>
                        <span class="estado-texto">${txtSoap}</span>
                        <span class="fecha-mini">${formatDateUI(auto.venc_soap)}</span>
                    </div>
                    <div class="status-item ${getStatusClass(dRev)}" onclick="${docHandler(auto.pdf_revision)}" title="${titleDoc(auto.pdf_revision)}">
                        <i class="fa-solid fa-wrench"></i>
                        <small>R. Técnica</small>
                        <span class="estado-texto">${txtRev}</span>
                        <span class="fecha-mini">${formatDateUI(auto.venc_revision)}</span>
                    </div>
                </div>
                ${adminButtons}
            </div>
        </div>`;
    });

    if (document.getElementById('admin-mode-toggle') && document.getElementById('admin-mode-toggle').checked) {
        toggleAdminMode();
    }
}

function toggleAdminMode() {
    const on = document.getElementById('admin-mode-toggle').checked;
    document.querySelectorAll('.admin-actions').forEach(el => el.style.display = on ? 'grid' : 'none');
}

function cerrarModalAuto(id) {
    document.getElementById(id).style.display = 'none';
}

// --- MODO NUEVO ---
window.openNewModal = function () {
    document.getElementById('form-vehiculo').reset();
    document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-plus"></i> Nuevo Vehículo';

    const pInput = document.getElementById('input-patente');
    pInput.readOnly = false;
    pInput.value = '';

    document.getElementById('modal-vehiculo').style.display = 'flex';
}

// --- MODO EDITAR (Desempaqueta el JSON seguro) ---
window.openEditModal = function (jsonEncoded) {
    const a = JSON.parse(decodeURIComponent(jsonEncoded));
    document.getElementById('modal-title').innerHTML = '<i class="fa-solid fa-pen-to-square"></i> Editar Vehículo';

    const pInput = document.getElementById('input-patente');
    pInput.value = a.patente;
    pInput.readOnly = true;

    document.getElementById('input-marca').value = a.marca || '';
    document.getElementById('input-modelo').value = a.modelo || '';
    document.getElementById('input-tipo').value = a.tipo_vehiculo || '';
    document.getElementById('input-clase').value = a.clase_licencia || '';

    // Manejo de Fechas Vacías (Evita advertencias en consola)
    document.getElementById('date-permiso').value = (a.venc_permiso && a.venc_permiso !== '0000-00-00') ? a.venc_permiso : '';
    document.getElementById('date-soap').value = (a.venc_soap && a.venc_soap !== '0000-00-00') ? a.venc_soap : '';
    document.getElementById('date-revision').value = (a.venc_revision && a.venc_revision !== '0000-00-00') ? a.venc_revision : '';

    document.getElementById('modal-vehiculo').style.display = 'flex';
}

// --- MODO ASIGNAR CONDUCTOR ---
window.openDriverModal = function (patente, jsonEncoded) {
    const a = JSON.parse(decodeURIComponent(jsonEncoded));
    const assigned = a.lista_conductores || [];
    document.getElementById('link-patente').value = patente;
    const box = document.getElementById('lista-conductores-check');
    box.innerHTML = '';

    if (globalUsers.length === 0) {
        box.innerHTML = '<div style="padding:10px;text-align:center;color:#e74c3c;font-weight:bold;">No se encontraron conductores activos (Rol 3).</div>';
    } else {
        globalUsers.forEach(u => {
            const isChecked = assigned.includes(u.email) ? 'checked' : '';
            box.innerHTML += `
            <div class="checkbox-item">
                <input type="checkbox" name="conductores[]" value="${u.email}" id="user_${u.email}" ${isChecked}>
                <label for="user_${u.email}" style="cursor:pointer; width:100%">
                    <div style="font-weight:600">${u.nombre} ${u.apellido}</div>
                    <div style="font-size:0.8em; color:#94a3b8">${u.email}</div>
                </label>
            </div>`;
        });
    }
    document.getElementById('modal-conductores').style.display = 'flex';
}

async function submitCarForm(e) {
    e.preventDefault();
    const mode = document.getElementById('input-patente').readOnly ? "editar" : "crear";

    Swal.fire({
        ...window.swalConfig,
        title: `¿Confirmar acción?`,
        text: `Estás a punto de ${mode} este vehículo en el sistema.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, continuar',
        cancelButtonText: 'Cancelar'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ ...window.swalConfig, title: 'Procesando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            try {
                const urlAdmin = window.getApi('admin_auto.php');
                await fetch(urlAdmin, { method: 'POST', body: new FormData(e.target) });
                cerrarModalAuto('modal-vehiculo');
                Swal.fire({ ...window.swalConfig, title: '¡Éxito!', text: 'Vehículo guardado correctamente.', icon: 'success', timer: 1500, showConfirmButton: false });
                cargarDatos();
            } catch (err) {
                Swal.fire({ ...window.swalConfig, title: 'Error', text: 'Hubo un problema al guardar los datos.', icon: 'error' });
            }
        }
    });
}

async function submitLinkUser(e) {
    e.preventDefault();
    Swal.fire({ ...window.swalConfig, title: 'Asignando...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    try {
        const urlAdmin = window.getApi('admin_auto.php');
        await fetch(urlAdmin, { method: 'POST', body: new FormData(e.target) });
        Swal.fire({ ...window.swalConfig, title: '¡Actualizado!', text: 'Los conductores fueron asignados al vehículo.', icon: 'success', timer: 1500, showConfirmButton: false });
        cerrarModalAuto('modal-conductores');
        cargarDatos();
    } catch (err) {
        Swal.fire({ ...window.swalConfig, title: 'Error', text: 'Hubo un problema al asignar los conductores.', icon: 'error' });
    }
}