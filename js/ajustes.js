// ==========================================
// SCRIPTS UNIFICADOS DE AJUSTES
// ==========================================

// Utilidades Globales para obtener usuario
function obtenerEmailSesion() {
    if (typeof window.APP_USER !== 'undefined' && window.APP_USER.email) {
        return window.APP_USER.email;
    }
    const b = document.getElementById('session-email-bridge');
    if (b) {
        const m = b.textContent.match(/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/);
        if (m) return m[0].toLowerCase();
    }
    return '';
}

function esAdminSesion() {
    if (typeof window.APP_USER !== 'undefined') return window.APP_USER.isAdmin;
    return false;
}

let currentUserEmail = '';
let currentUserIsAdmin = false;

// 🔥 FORZAMOS RUTAS A PRODUCCIÓN (Evita el "Error de conexión BD" en local)
const API_NOTI = 'https://tabolango.cl/ajustes_noti.php';
const API_USUARIOS = 'https://tabolango.cl/usuarios.php';

document.addEventListener("DOMContentLoaded", () => {
    currentUserEmail = obtenerEmailSesion();
    currentUserIsAdmin = esAdminSesion();

    // 1. Iniciar siempre las notificaciones
    initPreferences();

    // 2. Solo si es Admin, inyectamos y cargamos el panel de Roles
    if (currentUserIsAdmin) {
        document.getElementById('panel-admin-roles').style.display = 'block';
        cargarDataRoles();
    }
});

// --- LÓGICA DE NOTIFICACIONES ---
async function initPreferences() {
    const msg = document.getElementById('msg-status');
    const btn = document.getElementById('btn-submit');

    if (!currentUserEmail) {
        msg.textContent = "No se detectó sesión activa.";
        msg.className = 'txt-err';
        btn.disabled = true;
        return;
    }

    try {
        const inputs = document.querySelectorAll('input[type="checkbox"]');
        inputs.forEach(i => i.disabled = true);
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cargando...';

        const response = await fetch(`${API_NOTI}?email=${encodeURIComponent(currentUserEmail)}`);
        const data = await response.json();

        if (data.error) throw new Error(data.error);

        const form = document.getElementById('prefs-form');
        if (form.notify_pedido_creado) form.notify_pedido_creado.checked = data.notify_pedido_creado == 1;
        if (form.notify_cambio_estado) form.notify_cambio_estado.checked = data.notify_cambio_estado == 1;
        if (form.notify_pedido_editado) form.notify_pedido_editado.checked = data.notify_pedido_editado == 1;
        if (form.notify_pedido_entregado) form.notify_pedido_entregado.checked = data.notify_pedido_entregado == 1;
        if (form.notify_doc_por_vencer) form.notify_doc_por_vencer.checked = data.notify_doc_por_vencer == 1;
        if (form.notify_doc_vencido) form.notify_doc_vencido.checked = data.notify_doc_vencido == 1;

        // Bloquear campos si no es admin
        if (!data.is_admin && !currentUserIsAdmin) {
            const protectedItems = document.querySelectorAll('.pref-item[data-role="admin"]');
            protectedItems.forEach(item => {
                item.classList.add('admin-locked');
                const chk = item.querySelector('input');
                if (chk) chk.disabled = true;

                const switchLabel = item.querySelector('.switch');
                if (switchLabel) {
                    const badge = document.createElement('div');
                    badge.className = 'lock-badge';
                    badge.innerHTML = '<i class="fa-solid fa-lock"></i> Admin';
                    item.insertBefore(badge, switchLabel);
                }
            });
        }

    } catch (error) {
        msg.textContent = "Error de conexión BD.";
        msg.className = 'txt-err';
        console.error(error);
    } finally {
        const availableInputs = document.querySelectorAll('.pref-item:not(.admin-locked) input');
        availableInputs.forEach(i => i.disabled = false);
        btn.innerHTML = 'Guardar Preferencias';
    }
}

async function savePreferences(e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit');
    const msg = document.getElementById('msg-status');
    const form = e.target;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Guardando...';
    msg.className = '';

    const payload = {
        email: currentUserEmail,
        notify_pedido_creado: form.notify_pedido_creado?.checked || false,
        notify_cambio_estado: form.notify_cambio_estado?.checked || false,
        notify_pedido_editado: form.notify_pedido_editado?.checked || false,
        notify_pedido_entregado: form.notify_pedido_entregado?.checked || false,
        notify_doc_por_vencer: form.notify_doc_por_vencer?.checked || false,
        notify_doc_vencido: form.notify_doc_vencido?.checked || false
    };

    try {
        const response = await fetch(API_NOTI, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            msg.innerHTML = '<i class="fa-solid fa-check-circle"></i> Cambios guardados';
            msg.className = 'txt-ok';
        } else {
            throw new Error(result.error || 'Error desconocido');
        }
    } catch (error) {
        msg.textContent = "Error al guardar. Revisa tu conexión.";
        msg.className = 'txt-err';
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Guardar Preferencias';
        setTimeout(() => { if (msg.classList.contains('txt-ok')) msg.className = ''; }, 3000);
    }
}


// --- LÓGICA DE ROLES DE ADMINISTRADOR ---
async function cargarDataRoles() {
    try {
        const r = await fetch(`${API_USUARIOS}?action=get_all_users_with_roles&admin_email=${encodeURIComponent(currentUserEmail)}`);
        const data = await r.json();

        if (data.status === 'error') throw new Error(data.message);
        renderTablaRoles(data);
    } catch (err) {
        console.error(err);
        document.getElementById('cuerpo-tabla-usuarios').innerHTML = `<tr><td style="padding: 20px; text-align: center; color: #e74c3c;">Error cargando permisos (Revisa la conexión).</td></tr>`;
    }
}

function renderTablaRoles(data) {
    const header = document.getElementById('fila-cabecera-roles');
    const body = document.getElementById('cuerpo-tabla-usuarios');
    body.innerHTML = '';

    if (header.children.length === 1) {
        data.maestro_roles.forEach(rol => {
            const th = document.createElement('th');
            th.style.cssText = 'padding: 15px; text-align: center; background: #f8f9fa; border-bottom: 2px solid #dee2e6; font-size: 13px; text-transform: uppercase; color: #555;';
            th.textContent = rol.nombre_rol;
            header.appendChild(th);
        });
    }

    data.usuarios.forEach(user => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #eee';

        let html = `
            <td style="padding: 15px;">
                <div style="font-weight: bold; color: #333;">${user.nombre} ${user.apellido}</div>
                <div style="font-size: 12px; color: #888;">${user.email}</div>
            </td>`;

        data.maestro_roles.forEach(rol => {
            const isChecked = user.roles_ids.includes(rol.id.toString()) ? 'checked' : '';
            html += `
                <td style="text-align: center; padding: 15px;">
                    <input type="checkbox" 
                           class="check-permiso" 
                           data-email="${user.email}" 
                           data-rolid="${rol.id}" 
                           ${isChecked} 
                           style="width: 20px; height: 20px; cursor: pointer; accent-color: #0F4B29;"
                           onchange="procesarCambioRol(this)">
                </td>`;
        });

        tr.innerHTML = html;
        body.appendChild(tr);
    });
}

window.procesarCambioRol = async function (el) {
    const emailTarget = el.dataset.email;
    const row = el.closest('tr');
    const allChecked = row.querySelectorAll('.check-permiso:checked');
    const rolesIds = Array.from(allChecked).map(cb => cb.dataset.rolid);

    const fd = new FormData();
    fd.append('email_target', emailTarget);
    rolesIds.forEach(id => fd.append('roles_ids[]', id));

    row.style.opacity = '0.5';

    try {
        const resp = await fetch(`${API_USUARIOS}?action=save_user_roles&admin_email=${encodeURIComponent(currentUserEmail)}`, {
            method: 'POST',
            body: fd
        });
        const result = await resp.json();

        if (result.status === 'success') {
            row.style.backgroundColor = '#f0fff4';
            setTimeout(() => { row.style.backgroundColor = 'transparent'; }, 600);
        } else {
            throw new Error(result.message || "Error");
        }
    } catch (e) {
        alert("Error al guardar cambios de permiso.");
        el.checked = !el.checked;
    } finally {
        row.style.opacity = '1';
    }
};