<?php
/* Template Name: Mi Perfil */
get_header();
$current_user = wp_get_current_user();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css">
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
</head>
<body>
    <div style="display:none;" id="session-email-bridge">[user_email_js]</div>

    <div class="tabolango-profile-wrapper">
        <div id="m-overlay" class="m-overlay">
            <div class="perf-modal-content">
                <div id="m-modal-icon">✅</div>
                <p id="m-modal-text">Datos guardados</p>
            </div>
        </div>

        <div class="perf-card">
            <div class="perf-header">
                <div class="profile-avatar">
                    <img id="user-photo" src="" style="width:100%; height:100%; border-radius:50%; object-fit: cover; display:none;">
                    <div id="avatar-placeholder">?</div>
                </div>
                <h2 id="display-name">Cargando...</h2>
                <h3 id="display-surname">...</h3>
                <div class="perf-line"></div>
            </div>

            <div id="view-mode">
                <div class="info-grid">
                    <div class="info-item"><label>Nombre Completo</label><span id="view-full">--</span></div>
                    <div class="info-item"><label>Teléfono</label><span id="view-phone">--</span></div>
                    <div class="info-item"><label>Fecha de Nacimiento</label><span id="view-birth">--</span></div>
                    <div class="info-item"><label>Correo</label><span id="view-email">--</span></div>
                </div>
                <div class="profile-actions">
                    <button class="perf-btn-edit-mode" onclick="enableEditMode()">✎ EDITAR DATOS</button>
                </div>
            </div>

            <form id="profileForm" style="display: none;">
                <input type="hidden" id="p_foto_url">
                <div class="perf-group"><label>Nombre</label><input type="text" id="p_nombre" required></div>
                <div class="perf-group"><label>Apellido</label><input type="text" id="p_apellido" required></div>
                
                <div class="perf-group">
                    <label>Teléfono (9 números)</label>
                    <div class="phone-input-container">
                        <input type="tel" id="p_telefono">
                    </div>
                    <small id="phone-error" style="color: #cc0000; display: none; font-size: 11px; margin-top: 5px; font-weight: bold;">Debes ingresar exactamente 9 números.</small>
                </div>

                <div class="perf-group"><label>Fecha Nacimiento</label><input type="date" id="p_fecha_nac"></div>
                <div class="perf-group"><label>Email (No editable)</label><input type="text" id="p_email_display" readonly style="background:#f0f0f0; color:#888;"></div>
                
                <div class="edit-buttons">
                    <button type="submit" class="m-btn-save">GUARDAR</button>
                    <button type="button" class="m-btn-cancel" onclick="disableEditMode()">VOLVER</button>
                </div>
            </form>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>
<?php get_footer(); ?>