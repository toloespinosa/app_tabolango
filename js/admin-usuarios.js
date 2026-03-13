let users = [];
let rolesExistentes = [];
let isAppAdmin = false;

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mover los modales/sidebar al body para evitar conflictos z-index
    const overlay = document.getElementById('sidebar-overlay');
    const sidebar = document.getElementById('admin-sidebar');
    const toast = document.getElementById('toast-confirm');

    if (overlay && overlay.parentNode !== document.body) document.body.appendChild(overlay);
    if (sidebar && sidebar.parentNode !== document.body) document.body.appendChild(sidebar);
    if (toast && toast.parentNode !== document.body) document.body.appendChild(toast);

    // 2. Usar identidad inteligente de global.js
    const currentUser = window.APP_USER;
    if (currentUser && currentUser.isAdmin) {
        isAppAdmin = true;
    }

    loadData();
});

async function loadData() {
    try {
        const urlAPI = window.getApi('admin_data.php?action=get_users');
        const res = await fetch(urlAPI);
        const json = await res.json();

        if (json.status === 'success') {
            users = json.data;
            rolesExistentes = json.available_roles || [];
            render(users);
        } else {
            throw new Error(json.message || "No se pudo cargar la data.");
        }
    } catch (e) {
        console.error("Error cargando usuarios:", e);
        document.getElementById('users-grid').innerHTML = `<p style="grid-column: 1/-1; text-align:center; color:#ff6b6b; font-weight:bold;">Error al cargar el directorio: ${e.message}</p>`;
    }
}

function render(data) {
    const grid = document.getElementById('users-grid');
    if (!grid) return;

    if (data.length === 0) {
        grid.innerHTML = '<p style="grid-column: 1/-1; text-align:center; color:#ffffff;">No se encontraron usuarios.</p>';
        return;
    }

    grid.innerHTML = data.map(u => {
        const hasPhoto = u.foto_url && u.foto_url.length > 10;
        const cargoText = u.cargo ? u.cargo : 'Usuario';

        return `
        <div class="user-card" onclick="openSidebar('${u.email}')">
            ${hasPhoto ? `<img src="${u.foto_url}" class="card-photo" alt="Perfil">` : `<div class="card-initials">${u.nombre[0]}</div>`}
            <div><span class="role-capsule role-usuario">${cargoText}</span></div>
            <h4>${u.nombre}</h4><span class="lastname">${u.apellido}</span>
            <div class="info-line">🎂 ${u.fecha_nacimiento && u.fecha_nacimiento !== '0000-00-00' ? window.formatDateUI(u.fecha_nacimiento) : 'No registrada'}</div>
            <div class="info-line">📧 ${u.email}</div>
            <div class="card-actions">
                <a href="https://wa.me/${u.telefono}" class="action-btn btn-wa" onclick="event.stopPropagation()" target="_blank">WhatsApp</a>
                <a href="mailto:${u.email}" class="action-btn btn-mail" onclick="event.stopPropagation()">Email</a>
            </div>
        </div>`;
    }).join('');
}

// Función auxiliar para formatear fecha
window.formatDateUI = function (dateStr) {
    if (!dateStr || dateStr === '0000-00-00') return '';
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
};

function openSidebar(emailTarget) {
    const u = users.find(x => x.email === emailTarget);
    if (!u) return;

    // Llenar campos básicos
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_nombre').value = u.nombre || '';
    document.getElementById('edit_apellido').value = u.apellido || '';
    document.getElementById('edit_telefono').value = u.telefono || '';
    document.getElementById('edit_fecha_nacimiento').value = (u.fecha_nacimiento && u.fecha_nacimiento !== '0000-00-00') ? u.fecha_nacimiento : '';

    const roleContainer = document.getElementById('role-selector-container');
    const actionContainer = document.getElementById('admin-actions-container');

    if (isAppAdmin) {
        // CAMBIO APLICADO: Ahora es un input type text libre
        roleContainer.innerHTML = `
            <div class="m-input">
                <label>📸 URL de la Foto de Perfil</label>
                <input type="text" id="edit_foto_url" value="${u.foto_url || ''}" placeholder="https://ejemplo.com/foto.jpg">
            </div>
            <div class="m-input">
                <label>👔 Cargo (Etiqueta Visible)</label>
                <input type="text" id="edit_cargo" value="${u.cargo || ''}" placeholder="Ej: Vendedor Zona Norte">
            </div>`;

        actionContainer.innerHTML = `<button type="submit" class="btn-save-premium">GUARDAR CAMBIOS</button>`;
    } else {
        // Vista de solo lectura
        roleContainer.innerHTML = `
            <div class="m-input">
                <label>👔 Cargo</label>
                <div class="role-display-text">${(u.cargo || 'SIN CARGO').toUpperCase()}</div>
            </div>`;
        actionContainer.innerHTML = `<button type="button" class="btn-save-premium" style="background:#555" onclick="closeSidebar()">CERRAR</button>`;
    }

    // Desbloquear campos si es admin
    const fields = ['edit_nombre', 'edit_apellido', 'edit_telefono', 'edit_fecha_nacimiento'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.disabled = !isAppAdmin;
    });

    // Actualizar Banner
    document.getElementById('side-name-display').innerText = `${u.nombre || ''} ${u.apellido || ''}`;
    document.getElementById('side-email-subtitle').innerText = u.email;

    const box = document.getElementById('side-avatar-box');
    const sidePhoto = (u.foto_url && u.foto_url.length > 10);
    box.innerHTML = sidePhoto ? `<img src="${u.foto_url}" alt="Avatar">` : (u.nombre ? u.nombre[0] : '?');

    // Abrir UI
    document.getElementById('admin-sidebar').classList.add('open');
    document.getElementById('sidebar-overlay').classList.add('show');
    document.body.classList.add('modal-abierto');
}

function closeSidebar() {
    document.getElementById('admin-sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('show');
    document.body.classList.remove('modal-abierto');
}

async function guardarUsuario(e) {
    e.preventDefault();

    const fd = new FormData();
    fd.append('action', 'update_user_admin');
    fd.append('email', document.getElementById('edit_email').value);
    fd.append('nombre', document.getElementById('edit_nombre').value);
    fd.append('apellido', document.getElementById('edit_apellido').value);
    fd.append('telefono', document.getElementById('edit_telefono').value);
    fd.append('fecha_nacimiento', document.getElementById('edit_fecha_nacimiento').value);

    // Recoger nuevos valores
    const cargoInput = document.getElementById('edit_cargo');
    const fotoInput = document.getElementById('edit_foto_url');
    if (cargoInput) fd.append('cargo', cargoInput.value);
    if (fotoInput) fd.append('foto_url', fotoInput.value);

    Swal.fire({ ...window.swalConfig, title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const urlAPI = window.getApi('admin_data.php');
        const res = await fetch(urlAPI, { method: 'POST', body: fd });
        const json = await res.json();

        if (json.status === 'success') {
            Swal.close();
            closeSidebar();

            const toast = document.getElementById('toast-confirm');
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 3000);

            await loadData();
        } else {
            throw new Error(json.message);
        }
    } catch (err) {
        console.error("Error al guardar:", err);
        Swal.fire({ ...window.swalConfig, title: 'Error', text: err.message || 'No se pudieron guardar los cambios', icon: 'error' });
    }
}

function filterUsers() {
    const s = document.getElementById('userSearch').value.toLowerCase().trim();
    if (!s) {
        render(users);
        return;
    }

    const filtered = users.filter(u => {
        // Agregamos ${u.cargo || ''} al string combinado para que el buscador lo lea
        const fullString = `${u.nombre || ''} ${u.apellido || ''} ${u.email || ''} ${u.cargo || ''}`.toLowerCase();
        return fullString.includes(s);
    });

    render(filtered);
}