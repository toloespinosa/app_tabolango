<?php
/* Template Name: Mi Perfil */
get_header();
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>

<div class="tabolango-profile-wrapper">
    <div id="m-overlay" class="m-overlay">
        <div class="m-modal-content">
            <div id="m-modal-icon">✅</div>
            <p id="m-modal-text">Datos guardados</p>
        </div>
    </div>

    <div class="m-card">
        <div class="m-header">
            <div class="profile-photo-trigger" onclick="document.getElementById('p_foto_file').click();" title="Haz clic para cambiar tu foto">
                <div class="profile-avatar">
                    <img id="user-photo" src="" style="width:100%; height:100%; border-radius:50%; object-fit: cover; display:none;">
                    <div id="avatar-placeholder">?</div>
                    <div class="profile-photo-overlay">
                        <span style="font-size: 24px;">📷</span>
                    </div>
                </div>
            </div>
            
            <h2 id="display-name">Cargando...</h2>
            <h3 id="display-surname">...</h3>
            <div class="m-line"></div>
        </div>

        <div id="view-mode">
            <div class="info-grid">
                <div class="info-item"><label>Nombre Completo</label><span id="view-full">--</span></div>
                <div class="info-item"><label>Teléfono</label><span id="view-phone">--</span></div>
                <div class="info-item"><label>Fecha de Nacimiento</label><span id="view-birth">--</span></div>
                <div class="info-item"><label>Correo</label><span id="view-email">--</span></div>
            </div>
            <div class="profile-actions">
                <button class="m-btn-edit-mode" onclick="enableEditMode()">✎ EDITAR DATOS</button>
            </div>
        </div>

        <form id="profileForm" style="display: none;">
            <input type="file" id="p_foto_file" name="foto_file" accept="image/jpeg, image/png, image/webp" style="display:none;">
            
            <div class="m-group"><label>Nombre</label><input type="text" id="p_nombre" required></div>
            <div class="m-group"><label>Apellido</label><input type="text" id="p_apellido" required></div>
            
            <div class="m-group">
                <label>Teléfono (9 números)</label>
                <div class="phone-input-container">
                    <input type="tel" id="p_telefono">
                </div>
                <small id="phone-error" style="color: #cc0000; display: none; font-size: 11px; margin-top: 5px; font-weight: bold;">Debes ingresar exactamente 9 números.</small>
            </div>

            <div class="m-group"><label>Fecha Nacimiento</label><input type="date" id="p_fecha_nac"></div>
            <div class="m-group"><label>Email (No editable)</label><input type="text" id="p_email_display" readonly style="background:#f0f0f0; color:#888;"></div>
            
            <div class="edit-buttons">
                <button type="submit" class="m-btn-save">GUARDAR</button>
                <button type="button" class="m-btn-cancel" onclick="disableEditMode()">VOLVER</button>
            </div>
        </form>
    </div>
</div>

<?php get_footer(); ?>