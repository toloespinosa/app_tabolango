<?php
/* Template Name: Productos */
tabolango_requerir_rol([1, 2, 4]); // Accesible para Admin, Editor y Vendedor
get_header();
?>

<div class="tabolango-orders-container">
    <div class="admin-header-box">
        
        <button id="btn-abrir-crear" onclick="abrirModalCrear()" class="btn-crear-dinamico">
            <i class="fa-solid fa-plus"></i> CREAR PRODUCTO
        </button>

        <p class="header-subtitle">DIRECTORIO OFICIAL TABOLANGO SPA</p>
        <h1 class="header-title">Gestión de Productos</h1>
        
        <div class="search-container-wrapper">
            <div class="search-input-box">
                <i class="fa fa-search search-icon"></i>
                <input type="text" id="buscador" oninput="filtrarProductos()" placeholder="Buscar producto..." class="search-input">
                <i class="fa-solid fa-circle-xmark clear-icon" id="btn-clear-search" onclick="limpiarBuscador()"></i>
            </div>
            <div id="admin-controls" style="display:none;">
                <button id="btn-toggle-hidden" onclick="toggleOcultos()" class="btn-toggle-hidden">
                    <i class="fa-solid fa-eye-slash"></i> MOSTRAR OCULTOS
                </button>
            </div>
        </div>
    </div>

    <div id="products-grid" class="orders-grid">
        <div class="loading-catalog">
            <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Cargando catálogo...
        </div>
    </div>
</div>

<div id="modal-producto" class="modal-overlay custom-modal-producto">
    <div class="modal-content">
        
        <div class="modal-header-gradient">
            <span onclick="cerrarModal()" class="modal-close-btn">&times;</span>
            <div id="emoji-preview" class="emoji-preview-box">📦</div>
            <h2 class="modal-header-title">Nuevo Producto</h2>
            <p class="modal-header-subtitle">Completa los detalles del catálogo</p>
        </div>

        <form id="form-nuevo-producto" onsubmit="guardarNuevoProducto(event)" class="modal-form">
            <div class="form-grid">
                <div class="form-row-flex">
                    <div style="flex:1">
                        <label class="lbl-form">Icono</label>
                        <input type="text" name="icono" class="inp-form text-center txt-large" placeholder="📦" maxlength="2" oninput="document.getElementById('emoji-preview').innerText = this.value || '📦'">
                    </div>
                    <div style="flex:3">
                        <label class="lbl-form">Nombre del Producto</label>
                        <input type="text" name="producto" required placeholder="Ej: Tomate" class="inp-form">
                    </div>
                </div>

                <div class="form-row-flex">
                    <div style="flex:1">
                        <label class="lbl-form">Variedad</label>
                        <input type="text" name="variedad" placeholder="Ej: Larga Vida" class="inp-form">
                    </div>
                    <div style="flex:1">
                        <label class="lbl-form">Formato</label>
                        <input type="text" name="formato" placeholder="Ej: Malla" class="inp-form">
                    </div>
                </div>

                <div class="form-row-flex-small">
                    <div style="flex:1">
                        <label class="lbl-form">Unidad</label>
                        <input type="text" name="unidad" placeholder="Kg" required class="inp-form">
                    </div>
                    <div style="flex:1">
                        <label class="lbl-form">Kg x Und</label>
                        <input type="number" step="0.1" name="kg_por_unidad" value="1" required class="inp-form">
                    </div>
                    <div style="flex:1">
                        <label class="lbl-form">Cal