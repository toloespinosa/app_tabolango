<?php
/* Template Name: Admin Usuarios */
tabolango_requerir_rol([1,2,3,4]); 
get_header();
?>

<div id="toast-confirm" class="toast-premium">¡Cambios guardados con éxito!</div>

<div class="admin-panel">
    <div class="admin-header-box">
        <div class="header-content-wrapper">
            <div class="admin-info">
                <p class="sub-title-italic">Directorio oficial Tabolango Spa</p>
                <h1 class="main-title">Gestión de Usuarios</h1>
            </div>
            <div class="search-container-large">
                <input type="text" id="userSearch" placeholder="🔍 Buscar por nombre, apellido, correo o cargo..." onkeyup="filterUsers()">
            </div>
        </div>
    </div>
    
    <div id="users-grid" class="users-grid">
        <div style="grid-column: 1/-1; text-align:center; padding:40px; color:#ffffff;">
            <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Cargando usuarios...
        </div>
    </div>
</div>

<div id="sidebar-overlay" class="overlay" onclick="closeSidebar()"></div>

<div id="admin-sidebar" class="sidebar">
    <button class="close-sidebar-btn" onclick="closeSidebar()">✕</button>
    <div class="sidebar-scroll-container">
        <div class="sidebar-header-naranja">
            <div id="side-avatar-box" class="avatar-premium-sidebar"></div>
            <h3 id="side-name-display">---</h3>
            <p id="side-email-subtitle">---</p>
        </div>
        <div class="sidebar-body">
            <form id="adminEditForm" onsubmit="guardarUsuario(event)">
                <input type="hidden" id="edit_email">
                <div class="form-content-spacing">
                    <div class="input-grid-2">
                        <div class="m-input">
                            <label>Nombre</label>
                            <input type="text" id="edit_nombre" required>
                        </div>
                        <div class="m-input">
                            <label>Apellido</label>
                            <input type="text" id="edit_apellido" required>
                        </div>
                    </div>
                    
                    <div class="m-input">
                        <label>🎂 Fecha de Nacimiento</label>
                        <input type="date" id="edit_fecha_nacimiento" class="custom-date-input">
                    </div>

                    <div class="m-input">
                        <label>📱 WhatsApp</label>
                        <input type="text" id="edit_telefono" oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                    </div>
                    
                    <div id="role-selector-container"></div>
                </div>
                <div id="admin-actions-container" class="sidebar-footer"></div>
            </form>
        </div>
    </div>
</div>

<?php get_footer(); ?>