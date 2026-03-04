<?php
/* Template Name: Admin Usuarios */
get_header();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administración de Usuarios</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body>

    <div style="display:none;" id="session-email-bridge">[user_email_js]</div>
    <div id="toast-confirm" class="toast-premium">¡Cambios guardados con éxito!</div>

    <div class="admin-panel">
        <div class="admin-header-box">
            <div class="header-content-wrapper">
                <div class="admin-info">
                    <p class="sub-title-italic">Directorio oficial Tabolango Spa</p>
                    <h1 class="main-title">Gestión de Usuarios</h1>
                </div>
                <div class="search-container-large">
                    <input type="text" id="userSearch" placeholder="🔍 Buscar por nombre, apellido o correo..." onkeyup="filterUsers()">
                </div>
            </div>
        </div>
        <div id="users-grid" class="users-grid"></div>
    </div>

    <div id="sidebar-overlay" class="au-overlay" onclick="closeSidebar()"></div>

    <div id="admin-sidebar" class="au-sidebar">
        <button class="au-close-sidebar-btn" onclick="closeSidebar()">✕</button>
        
        <div class="sidebar-scroll-container">
            <div class="sidebar-header-naranja">
                <div id="side-avatar-box" class="avatar-premium-sidebar"></div>
                <h3 id="side-name-display">---</h3>
                <p id="side-email-subtitle">---</p>
            </div>
            <div class="sidebar-body">
                <form id="adminEditForm">
                    <input type="hidden" id="edit_email">
                    <div class="form-content-spacing">
                        <div class="input-grid-2">
                            <div class="m-input"><label>Nombre</label><input type="text" id="edit_nombre" required></div>
                            <div class="m-input"><label>Apellido</label><input type="text" id="edit_apellido" required></div>
                        </div>
                        
                        <div class="m-input">
                            <label>🎂 Fecha de Nacimiento</label>
                            <input type="date" id="edit_fecha_nacimiento" style="width: 100%; max-width: 100%; box-sizing: border-box; display: block; -webkit-appearance: none; -moz-appearance: none; appearance: none; min-height: 48px; color: #1A1A1A;">
                        </div>

                        <div class="m-input"><label>📱 WhatsApp</label><input type="text" id="edit_telefono" oninput="this.value = this.value.replace(/[^0-9]/g, '')"></div>
                        <div id="role-selector-container"></div>
                    </div>
                    <div id="admin-actions-container" class="sidebar-footer"></div>
                </form>
            </div>
        </div>
    </div>

    <script src="app.js"></script>
</body>
</html>

<?php
get_footer();
?>