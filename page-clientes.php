<?php
/* Template Name: Clientes */
get_header();
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" />
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCbgo4XsKtuPO6riZd_9eQ3ErGXaI89J2M&libraries=places"></script>

<div class="directorio-wrapper">
    <div class="header-acciones">
        <div id="container-switch-ocultos" class="ios-switch-wrapper" style="display: none;">
            <span class="ios-switch-label">Ver Ocultos</span>
            <label class="ios-switch">
                <input type="checkbox" id="check-ocultos" onchange="toggleModoOculto()">
                <span class="slider"></span>
            </label>
        </div>

        <button id="btn-crear-cliente" class="btn-crear-cliente" onclick="abrirModalCliente()" style="display: none;">
            <span class="material-symbols-outlined" style="font-size: 18px;">person_add</span>
            Nuevo
        </button>
    </div>

    <div class="search-box">
        <input type="text" id="buscador-clientes" placeholder="🔍 Buscar cliente..." onkeyup="filtrarYRenderizar()">
    </div>
    
    <div id="lista-clientes"></div>

    <div id="modal-cliente" class="modal-full-screen">
        <div class="modal-card-centered">
            <div class="modal-header-mini">
                <h3>Nuevo Cliente</h3>
                <button class="btn-close-minimal" onclick="cerrarModalCliente()">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            
            <div class="modal-body-mini">
                <form id="form-nuevo-cliente" onsubmit="guardarCliente(event)">
                    
                    <div class="form-section-title">Datos Generales</div>
                    
                    <div class="input-minimal">
                        <label>Categoría</label>
                        <select id="reg-categoria" class="input-style">
                            <option value="3">Cargando...</option>
                        </select>
                    </div>

                    <div class="grid-form-mini">
                         <div class="input-minimal">
                            <label>RUT Empresa</label>
                            <input type="text" id="reg-rut" class="input-style" placeholder="12.345.678-9" oninput="this.value = aplicarMascaraRUT(this.value)" onblur="consultarRutSimpleAPI(this.value)">
                        </div>
                        <div class="input-minimal">
                            <label>Nombre Fantasía*</label>
                            <input type="text" id="reg-cliente" class="input-style" placeholder="Ej: Rest. La Ensenada" required>
                        </div>
                    </div>

                    <div class="input-minimal">
                        <label>Razón Social</label>
                        <input type="text" id="reg-razon-social" class="input-style" placeholder="Ej: Tabolango SpA" >
                    </div>
                    
                    <div class="input-minimal">
                        <label>Giro</label>
                        <input type="text" id="reg-giro" class="input-style" placeholder="Ej: Venta de alimentos..." >
                    </div>

                    <div class="input-minimal">
                        <label>Responsable del Negocio*</label>
                        <select id="reg-responsable" required class="input-style" style="cursor: pointer;">
                            <option value="">Cargando responsables...</option>
                        </select>
                    </div>

                    <div class="input-minimal">
                        <label>Nombre Contacto (Receptor)</label>
                        <input type="text" id="reg-contacto" class="input-style" placeholder="Persona encargada">
                    </div>

                    <div class="form-section-title">Contacto General</div>
                    <div class="grid-form-mini">
                        <div class="input-minimal">
                            <label>Email General</label>
                            <input type="email" id="reg-email" class="input-style" placeholder="correo@ejemplo.cl">
                        </div>
                        <div class="input-minimal">
                            <label>Teléfono General</label>
                            <input type="tel" id="reg-telefono" class="input-style" placeholder="+569" maxlength="13">
                        </div>
                    </div>

                    <div class="form-section-title" style="color:#27ae60;">📍 Dirección de Despacho</div>
                    
                    <div class="input-minimal">
                        <label>Dirección Despacho (Buscar en Mapa)*</label>
                        <input type="text" id="reg-direccion" class="input-style" placeholder="Calle, Número, Comuna" required autocomplete="off">
                    </div>
                    
                    <input type="hidden" id="reg-ciudad" name="ciudad">
                    <input type="hidden" id="reg-comuna" name="comuna">
                    <input type="hidden" id="reg-latitud" name="lat_despacho">
                    <input type="hidden" id="reg-longitud" name="lng_despacho">

                    <div style="margin: 20px 0; display:flex; align-items:center; gap:10px; padding:10px; background:#f9f9f9; border-radius:10px;">
                        <input type="checkbox" id="check-misma-dir" onchange="toggleFacturacion()" style="transform: scale(1.3); accent-color: #111;">
                        <label for="check-misma-dir" style="font-size:12px; font-weight:700; color:#333; margin:0; cursor:pointer;">
                            Usar mismos datos para Facturación
                        </label>
                    </div>

                    <div id="wrapper-facturacion">
                        <div class="form-section-title" style="color:#2980b9;">📄 Datos Facturación</div>
                        
                        <div class="input-minimal">
                            <label>Dirección Facturación (Buscar)*</label>
                            <input type="text" id="reg-direccion-factura" class="input-style" placeholder="Buscar dirección tributaria..." autocomplete="off">
                        </div>
                        <input type="hidden" id="reg-ciudad-factura">
                        <input type="hidden" id="reg-comuna-factura">

                        <div class="grid-form-mini">
                            <div class="input-minimal">
                                <label>Nombre</label>
                                <input type="text" id="reg-nombre" class="input-style" placeholder="Ej: Juan">
                            </div>
                            <div class="input-minimal">
                                <label>Apellido</label>
                                <input type="text" id="reg-apellido" class="input-style" placeholder="Ej: Pérez">
                            </div>
                        </div>

                        <div class="grid-form-mini">
                            <div class="input-minimal">
                                <label>Email Facturación DTE</label>
                                <input type="email" id="reg-email-factura" class="input-style" placeholder="dte@empresa.cl">
                            </div>
                            <div class="input-minimal">
                                <label>Teléfono Facturación</label>
                                <input type="tel" id="reg-telefono-factura" class="input-style" placeholder="+569...">
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-mini">
                        <button type="submit" id="btn-submit-cliente" class="btn-confirmar-mini">Crear Cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
get_footer();
?>
