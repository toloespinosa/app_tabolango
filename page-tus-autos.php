<?php
/* Template Name: Tus Autos */
tabolango_requerir_rol([1, 2, 3, 4]);
get_header();
?>

    <h2 style="margin-top: 10px; margin-bottom: 40px; color: #ffffff !important; text-align: center; font-weight: 800;">
        Gestión de Flota
    </h2>

    <!-- Botón SAG: visible para todos los roles (los conductores lo necesitan en terreno) -->
    <div id="sag-controls">
        <button id="btn-sag" class="btn-sag" onclick="openSagModal()">
            <i class="fa-solid fa-file-shield"></i> Autorización SAG
        </button>
    </div>

    <div id="admin-controls">
        <div class="admin-badge">
            <i class="fa-solid fa-user-shield"></i>
            <label class="switch">
                <input type="checkbox" id="admin-mode-toggle" onchange="toggleAdminMode()">
                <span class="slider"></span>
            </label>
        </div>
        <button id="btn-add-auto" class="btn-add" style="display:none;" onclick="openNewModal()">
            <i class="fa-solid fa-plus"></i> Agregar Vehículo
        </button>
    </div>

    <div id="lista-autos" class="autos-grid">
        <div style="text-align:center; padding:40px; color:#ffffff;">
            <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br><br>Cargando flota...
        </div>
    </div>

<div id="modal-vehiculo" class="modal-overlay custom-modal-autos">
    <div class="modal-flota-box">
        <span class="close-modal" onclick="cerrarModalAuto('modal-vehiculo')">&times;</span>
        <h3 style="margin-top:0" id="modal-title"><i class="fa-solid fa-car"></i> Vehículo</h3>
        <form id="form-vehiculo" onsubmit="submitCarForm(event)">
            <input type="hidden" name="action" value="guardar_vehiculo_full">

            <div class="form-row">
                <label>Foto del Vehículo</label>
                <input type="file" name="foto_auto" accept="image/*">
             </div>

            <div class="form-row">
                <label>Patente</label>
                <input type="text" name="patente" id="input-patente" placeholder="ABCD-12" required>
            </div>
            
            <div class="form-row grid-2">
                <div>
                    <label>Marca</label>
                    <input type="text" name="marca" id="input-marca" placeholder="Toyota" required>
                </div>
                <div>
                    <label>Modelo</label>
                    <input type="text" name="modelo" id="input-modelo" placeholder="Hilux" required>
                </div>
            </div>

            <div class="form-row grid-2">
                <div>
                    <label>Tipo Vehículo</label>
                    <input type="text" name="tipo_vehiculo" id="input-tipo" placeholder="Camioneta">
                </div>
                <div>
                    <label>Clase Licencia</label>
                    <input type="text" name="clase_licencia" id="input-clase" placeholder="B">
                </div>
            </div>

            <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">

            <div class="form-row">
                <label>Permiso Circulación</label>
                <input type="file" name="pdf_permiso">
                <input type="date" name="fecha_permiso" id="date-permiso" style="margin-top:5px;">
            </div>
            <div class="form-row">
                <label>SOAP</label>
                <input type="file" name="pdf_soap">
                <input type="date" name="fecha_soap" id="date-soap" style="margin-top:5px;">
            </div>
            <div class="form-row">
                <label>Revisión Técnica</label>
                <input type="file" name="pdf_revision">
                <input type="date" name="fecha_revision" id="date-revision" style="margin-top:5px;">
            </div>
            <button class="btn-full">GUARDAR DATOS</button>
        </form>
    </div>
</div>

<div id="modal-conductores" class="modal-overlay custom-modal-autos">
    <div class="modal-flota-box">
        <span class="close-modal" onclick="cerrarModalAuto('modal-conductores')">&times;</span>
        <h3 style="margin-top:0"><i class="fa-solid fa-users"></i> Asignar Conductores</h3>
        <form id="form-vincular" onsubmit="submitLinkUser(event)">
            <input type="hidden" name="action" value="vincular_conductores_masivo">
            <input type="hidden" name="patente_vincular" id="link-patente">
            <div class="checkbox-list" id="lista-conductores-check"></div>
            <button class="btn-full">ACTUALIZAR ASIGNACIONES</button>
        </form>
    </div>
</div>

<!-- ────────── Modal Autorización SAG ────────── -->
<div id="modal-sag" class="modal-overlay custom-modal-autos">
    <div class="modal-flota-box">
        <span class="close-modal" onclick="cerrarModalAuto('modal-sag')">&times;</span>
        <h3 style="margin-top:0"><i class="fa-solid fa-file-shield"></i> Autorización SAG</h3>

        <div id="sag-meta" style="font-size:12px; color:#888; margin-bottom:12px;"></div>

        <!-- Documento 1 -->
        <div class="sag-slot">
            <div class="sag-slot-header">
                <strong>Inscripción de comercializadora</strong>
                <a id="sag-link-1" href="#" target="_blank" class="sag-link" style="display:none;">
                    <i class="fa-solid fa-down-to-line"></i> Ver / Descargar
                </a>
            </div>
            <div class="sag-upload-row sag-admin-only" style="display:none;">
                <input type="file" id="sag-file-1" accept=".pdf,image/*">
                <button class="sag-btn-subir" onclick="subirSagDoc(1)">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Subir / Reemplazar
                </button>
            </div>
        </div>

        <!-- Documento 2 -->
        <div class="sag-slot" style="margin-top:14px;">
            <div class="sag-slot-header">
                <strong>Plan operacional de trabajo</strong>
                <a id="sag-link-2" href="#" target="_blank" class="sag-link" style="display:none;">
                    <i class="fa-solid fa-down-to-line"></i> Ver / Descargar
                </a>
            </div>
            <div class="sag-upload-row sag-admin-only" style="display:none;">
                <input type="file" id="sag-file-2" accept=".pdf,image/*">
                <button class="sag-btn-subir" onclick="subirSagDoc(2)">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Subir / Reemplazar
                </button>
            </div>
        </div>

        <div id="sag-msg" style="margin-top:14px; font-size:12px;"></div>
    </div>
</div>

<?php get_footer(); ?>