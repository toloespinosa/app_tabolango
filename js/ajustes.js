// ==========================================
// SCRIPTS UNIFICADOS DE AJUSTES (CON DETECTIVE)
// ==========================================

const API_NOTI = window.getApi('ajustes_noti.php');
const API_USUARIOS = window.getApi('usuarios.php');

document.addEventListener("DOMContentLoaded", async () => {
    // 🔥 EL DETECTIVE DEL FRONTEND 🔥
    console.log("%c========================================", "color: #e74c3c; font-size: 14px; font-weight: bold;");
    console.log("%c🕵️‍♂️ DETECTIVE DE SESIÓN ACTIVADO", "color: #3498db; font-size: 16px; font-weight: bold;");

    const bridge = document.getElementById('session-email-bridge');
    console.log("%c1. El HTML (header) dice que eres:", "color: #2ecc71; font-weight: bold;", bridge ? bridge.textContent.trim() : "NO EXISTE EL DIV");
    console.log("%c2. JavaScript (APP_USER) forzó el email a:", "color: #2ecc71; font-weight: bold;", window.APP_USER.email);
    console.log("%c3. JavaScript asume que eres Admin?:", "color: #2ecc71; font-weight: bold;", window.APP_USER.isAdmin ? "SÍ" : "NO");

    try {
        const separador = API_USUARIOS.includes('?') ? '&' : '?';
        const r = await fetch(`${API_USUARIOS}${separador}action=debug_session`);
        const text = await r.text();
        console.log("%c4. El Backend PHP y WordPress dicen:", "color: #e67e22; font-weight: bold;", text);
    } catch (e) {
        console.log("No se pudo contactar al backend para el debug.");
    }
    console.log("%c========================================", "color: #e74c3c; font-size: 14px; font-weight: bold;");

    // --- INICIO DE LÓGICA NORMAL ---
    const currentUserEmail = window.APP_USER.email;
    const currentUserIsAdmin = window.APP_USER.isAdmin;

    // 1. Iniciar siempre las notificaciones
    initPreferences(currentUserEmail, currentUserIsAdmin);

    // 2. Si es Admin, revelamos la pestaña de "Permisos" y cargamos la tabla
    if (currentUserIsAdmin) {
        const btnTabPermisos = document.getElementById('btn-tab-permisos');
        const panelAdminRoles = document.getElementById('panel-admin-roles');
        const userSelectorWrapper = document.getElementById('admin-user-selector-wrapper');

        if (btnTabPermisos) btnTabPermisos.style.display = 'flex';
        if (panelAdminRoles) panelAdminRoles.style.display = 'block';
        if (userSelectorWrapper) userSelectorWrapper.style.display = 'block';

        cargarDataRoles(currentUserEmail);
    }
});

// --- LÓGICA DE CAMBIO DE PESTAÑAS ---
window.switchTab = function (e) {
    const targetId = e.currentTarget.getAttribute('data-target');

    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
        tab.style.display = 'none';
    });

    e.currentTarget.classList.add('active');
    const activeTab = document.getElementById(targetId);
    if (activeTab) {
        activeTab.classList.add('active');
        activeTab.style.display = 'block';
    }
};

// --- GESTIÓN DE NOTIFICACIONES ---
async function initPreferences(targetEmail, isAdminFrontend) {
    const msg = document.getElementById('msg-status');
    const btn = document.getElementById('btn-submit');

    if (!targetEmail) return;

    try {
        const inputs = document.querySelectorAll('input[type="checkbox"]');
        inputs.forEach(i => i.disabled = true);
        if (btn) btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Cargando...';

        const separador = API_NOTI.includes('?') ? '&' : '?';
        const response = await fetch(`${API_NOTI}${separador}target_email=${encodeURIComponent(targetEmail)}`);
        const data = await response.json();

        if (data.error) throw new Error(data.error);

        const form = document.getElementById('prefs-form');
        if (form) {
            if (form.notify_pedido_creado) form.notify_pedido_creado.checked = data.notify_pedido_creado == 1;
            if (form.notify_cambio_estado) form.notify_cambio_estado.checked = data.notify_cambio_estado == 1;
            if (form.notify_pedido_editado) form.notify_pedido_editado.checked = data.notify_pedido_editado == 1;
            if (form.notify_pedido_entregado) form.notify_pedido_entregado.checked = data.notify_pedido_entregado == 1;
            if (form.notify_doc_por_vencer) form.notify_doc_por_vencer.checked = data.notify_doc_por_vencer == 1;
            if (form.notify_doc_vencido) form.notify_doc_vencido.checked = data.notify_doc_vencido == 1;
        }

        // Bloqueo visual de documentos para usuarios que no son Admin
        const protectedItems = document.querySelectorAll('.pref-item[data-role="admin"]');
        protectedItems.forEach(item => {
            const chk = item.querySelector('input');
            const lockBadge = item.querySelector('.lock-badge');

            if (!isAdminFrontend) {
                item.classList.add('admin-locked');
                if (chk) chk.disabled = true;
                if (!lockBadge) {
                    const badge = document.createElement('div');
                    badge.className = 'lock-badge';
                    badge.innerHTML = '<i class="fa-solid fa-lock"></i> Admin';
                    const switchEl = item.querySelector('.switch');
                    if (switchEl) item.insertBefore(badge, switchEl);
                }
            } else {
                item.classList.remove('admin-locked');
                if (chk) chk.disabled = false;
                if (lockBadge) lockBadge.remove();
            }
        });

    } catch (error) {
        if (msg) {
            msg.textContent = "Error obteniendo preferencias.";
            msg.className = 'txt-err';
        }
        console.error(error);
    } finally {
        const availableInputs = document.querySelectorAll('.pref-item:not(.admin-locked) input');
        availableInputs.forEach(i => i.disabled = false);
        if (btn) btn.innerHTML = 'Guardar Preferencias';
    }
}

// Función que se activa cuando el Admin cambia el select
window.cambiarUsuarioNoti = function () {
    const selectNoti = document.getElementById('user-noti-select');
    if (selectNoti) {
        const emailSeleccionado = selectNoti.value;
        initPreferences(emailSeleccionado, window.APP_USER.isAdmin);
    }
};

window.savePreferences = async function (e) {
    e.preventDefault();
    const btn = document.getElementById('btn-submit');
    const msg = document.getElementById('msg-status');
    const form = e.target;

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Guardando...';
    }
    if (msg) msg.className = '';

    const selectNoti = document.getElementById('user-noti-select');
    const emailObjetivo = selectNoti && selectNoti.value ? selectNoti.value : window.APP_USER.email;

    const payload = {
        target_email: emailObjetivo,
        notify_pedido_creado: form.notify_pedido_creado?.checked ? 1 : 0,
        notify_cambio_estado: form.notify_cambio_estado?.checked ? 1 : 0,
        notify_pedido_editado: form.notify_pedido_editado?.checked ? 1 : 0,
        notify_pedido_entregado: form.notify_pedido_entregado?.checked ? 1 : 0,
        notify_doc_por_vencer: form.notify_doc_por_vencer?.checked ? 1 : 0,
        notify_doc_vencido: form.notify_doc_vencido?.checked ? 1 : 0
    };

    try {
        const response = await fetch(API_NOTI, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            if (msg) {
                msg.innerHTML = '<i class="fa-solid fa-check-circle"></i> Cambios guardados para ' + emailObjetivo;
                msg.className = 'txt-ok';
            }
        } else {
            throw new Error(result.error || 'Error desconocido');
        }
    } catch (error) {
        if (msg) {
            msg.textContent = "Error al guardar. Revisa tu conexión.";
            msg.className = 'txt-err';
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Guardar Preferencias';
        }
        setTimeout(() => { if (msg && msg.classList.contains('txt-ok')) msg.className = ''; }, 3500);
    }
}

// --- LÓGICA DE ROLES DE ADMINISTRADOR ---
async function cargarDataRoles(email) {
    try {
        const separador = API_USUARIOS.includes('?') ? '&' : '?';
        const urlFetch = `${API_USUARIOS}${separador}action=get_all_users_with_roles&admin_email=${encodeURIComponent(email)}`;

        const r = await fetch(urlFetch);
        if (!r.ok) {
            const errText = await r.text();
            throw new Error(`HTTP ${r.status} (${r.statusText})`);
        }

        const text = await r.text(); // Obtenemos como texto primero por si hay un error de PHP colado

        // Si el texto es nuestro mensaje de Debug, no intentes parsearlo como JSON
        if (text.includes("PHP ve el email")) {
            throw new Error("Modo Debug Activado. Mira arriba en la consola.");
        }

        const data = JSON.parse(text);

        if (data.status === 'error') throw new Error(data.message);

        // 1. Dibujar tabla de permisos
        renderTablaRoles(data);

        // 2. Alimentar el Selector de Notificaciones
        const selectNoti = document.getElementById('user-noti-select');
        if (selectNoti) {
            selectNoti.innerHTML = `<option value="${window.APP_USER.email}">Mis Notificaciones (Yo)</option>`;
            data.usuarios.forEach(user => {
                if (user.email !== window.APP_USER.email) {
                    selectNoti.innerHTML += `<option value="${user.email}">${user.nombre} ${user.apellido} (${user.email})</option>`;
                }
            });
        }

    } catch (err) {
        console.error("Fallo detallado:", err);
        const cuerpoTabla = document.getElementById('cuerpo-tabla-usuarios');
        if (cuerpoTabla) {
            cuerpoTabla.innerHTML = `
            <tr>
                <td style="padding: 20px; text-align: center; color: #e74c3c; font-weight: bold; background: #fef2f2;">
                    🚨 Error Crítico: ${err.message}
                </td>
            </tr>`;
        }
    }
}

function renderTablaRoles(data) {
    const header = document.getElementById('fila-cabecera-roles');
    const body = document.getElementById('cuerpo-tabla-usuarios');
    if (!header || !body) return;

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
        const separador = API_USUARIOS.includes('?') ? '&' : '?';
        const resp = await fetch(`${API_USUARIOS}${separador}action=save_user_roles&admin_email=${encodeURIComponent(window.APP_USER.email)}`, {
            method: 'POST',
            body: fd
        });
        const result = await resp.json();

        if (result.status === 'success') {
            row.style.backgroundColor = '#f0fff4';
            setTimeout(() => { row.style.backgroundColor = 'transparent'; }, 600);
        } else {
            throw new Error(result.message || "Error al procesar la solicitud.");
        }
    } catch (e) {
        alert("Error al guardar cambios de permiso.");
        el.checked = !el.checked;
    } finally {
        row.style.opacity = '1';
    }
};