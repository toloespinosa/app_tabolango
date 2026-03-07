<?php
/* Template Name: Ajustes */
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<div id="session-email-bridge" style="display:none !important;">[user_email_js]</div>

<div class="ajustes-container">

    <div class="config-card">
        <div class="config-header">
            <div class="header-icon-circle"><i class="fa-solid fa-bell"></i></div>
            <h2>Preferencias</h2>
            <p>Gestiona tus notificaciones y alertas.</p>
        </div>

        <form id="prefs-form" class="config-body" onsubmit="savePreferences(event)">
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

    <div id="panel-admin-roles">
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

<?php get_footer(); ?>