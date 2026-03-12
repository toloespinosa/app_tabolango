<?php
/* Template Name: Ajustes */
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<div class="ajustes-container">

    <div class="ajustes-tabs-nav">
        <button class="tab-btn active" data-target="tab-notificaciones" onclick="switchTab(event)">
            <i class="fa-solid fa-bell"></i> Notificaciones
        </button>
        <button class="tab-btn" id="btn-tab-permisos" data-target="tab-permisos" onclick="switchTab(event)" style="display: none;">
            <i class="fa-solid fa-shield-halved"></i> Permisos
        </button>
    </div>

    <div id="tab-notificaciones" class="tab-content active" style="display: block;">
        <div class="config-card" style="margin: 0 auto;">
            <div class="config-header">
                <div class="header-icon-circle"><i class="fa-solid fa-bell"></i></div>
                <h2>Preferencias</h2>
                <p>Gestiona las alertas del sistema.</p>
            </div>

            <form id="prefs-form" class="config-body" onsubmit="savePreferences(event)">
                
                <div id="admin-user-selector-wrapper" style="display: none; margin-bottom: 25px; background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0;">
                    <label style="font-size: 0.8rem; font-weight: 800; color: #0F4B29; text-transform: uppercase; margin-bottom: 8px; display: block;">
                        <i class="fa-solid fa-user-gear"></i> Gestionar usuario:
                    </label>
                    <select id="user-noti-select" onchange="cambiarUsuarioNoti()" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: 600; color: #334155; background: white; cursor: pointer; outline: none;">
                        <option value="">Cargando usuarios...</option>
                    </select>
                </div>

                <span class="section-title">Operaciones</span>

                <div class="pref-item">
                    <div class="pref-info">
                        <div class="pref-icon icon-blue"><i class="fa-solid fa-plus"></i></div>
                        <div class="pref-text">
                            <h4>Nuevo Pedido</h4>
                            <p>Notificar al crear solicitud.</p>
                        </div>
                    </div>
                    <label class="switch"><input type="checkbox" name="notify_pedido_creado"><span class="slider"></span></label>
                </div>

                <div class="pref-item">
                    <div class="pref-info">
                        <div class="pref-icon icon-purple"><i class="fa-solid fa-rotate"></i></div>
                        <div class="pref-text">
                            <h4>Cambio de Estado</h4>
                            <p>Avances en el proceso.</p>
                        </div>
                    </div>
                    <label class="switch"><input type="checkbox" name="notify_cambio_estado"><span class="slider"></span></label>
                </div>

                <div class="pref-item">
                    <div class="pref-info">
                        <div class="pref-icon icon-cyan"><i class="fa-solid fa-pen-to-square"></i></div>
                        <div class="pref-text">
                            <h4>Pedido Editado</h4>
                            <p>Modificaciones de detalles.</p>
                        </div>
                    </div>
                    <label class="switch"><input type="checkbox" name="notify_pedido_editado"><span class="slider"></span></label>
                </div>

                <div class="pref-item">
                    <div class="pref-info">
                        <div class="pref-icon icon-green"><i class="fa-solid fa-check"></i></div>
                        <div class="pref-text">
                            <h4>Pedido Entregado</h4>
                            <p>Confirmación de entrega.</p>
                        </div>
                    </div>
                    <label class="switch"><input type="checkbox" name="notify_pedido_entregado"><span class="slider"></span></label>
                </div>

                <span class="section-title">Documentación</span>

                <div class="pref-item" data-role="admin">
                    <div class="pref-info">
                        <div class="pref-icon icon-orange"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="pref-text">
                            <h4>Por Vencer</h4>
                            <p>Avisos preventivos.</p>
                        </div>
                    </div>
                    <label class="switch"><input type="checkbox" name="notify_doc_por_vencer"><span class="slider"></span></label>
                </div>

                <div class="pref-item" data-role="admin">
                    <div class="pref-info">
                        <div class="pref-icon icon-red"><i class="fa-solid fa-file-circle-xmark"></i></div>
                        <div class="pref-text">
                            <h4>Documento Vencido</h4>
                            <p>Alertas críticas.</p>
                        </div>
                    </div>
                    <label class="switch"><input type="checkbox" name="notify_doc_vencido"><span class="slider"></span></label>
                </div>

                <button type="submit" id="btn-submit" class="btn-save">Guardar Preferencias</button>
                <div id="msg-status"></div>
            </form>
        </div>
    </div>

    <div id="tab-permisos" class="tab-content" style="display: none;">
        <div id="panel-admin-roles" style="margin: 0 auto; display: none;">
            <div style="background: #0F4B29; color: white; padding: 25px; border-radius: 12px 12px 0 0; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h2 style="margin:0; color: white; font-family: sans-serif;">🛡️ Control de Permisos</h2>
                <p style="margin:5px 0 0 0; opacity: 0.9; font-size: 14px;">Gestiona los accesos dinámicos de la plataforma.</p>
            </div>

            <div class="admin-roles-card">
                <table id="tabla-usuarios-roles" style="width: 100%; border-collapse: collapse; font-family: sans-serif; min-width: 700px;">
                    <thead>
                        <tr id="fila-cabecera-roles">
                            <th style="padding: 15px; text-align: left; background: #f8f9fa; border-bottom: 2px solid #dee2e6; color: #333;">Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpo-tabla-usuarios">
                        <tr><td style="padding: 20px; text-align: center; color: #888;">Cargando roles...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php get_footer(); ?>