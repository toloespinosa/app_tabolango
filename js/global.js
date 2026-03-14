console.log("🚀 SISTEMA TABOLANGO: Cargando Globales...");

// ==========================================================================
// 1. UTILIDADES BÁSICAS (OPTIMIZADO PARA ERP)
// ==========================================================================
const isLocal = window.location.hostname.includes('.local') || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

// Si es local, usa tu ruta de desarrollo. 
// Si es producción, ahora captura dinámicamente el dominio actual (erp.tabolango.cl)
const BASE_URL = isLocal
    ? `${window.location.protocol}//${window.location.hostname}/wp-content/themes/Tabolango/inc`
    : `https://${window.location.hostname}/wp-content/themes/app_tabolango/inc`; // Ajusta "/inc" según dónde guardes los PHP en tu repo de GitHub

console.log(`📍 Entorno: ${isLocal ? 'LOCAL 💻' : 'PRODUCCIÓN ☁️'} | API: ${BASE_URL}`);

window.formatCLP = (v) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', minimumFractionDigits: 0 }).format(v);
window.formatearDinero = window.formatCLP;

// ==========================================================================
// 2. MOTOR CENTRAL DE IDENTIFICACIÓN Y SIMULADOR DE ROLES (MODO DIOS)
// ==========================================================================

// 🔥 WIDGET VISUAL: Selector flotante de roles (Solo se dibuja en Local)
if (isLocal) {
    document.addEventListener('DOMContentLoaded', () => {
        const panel = document.createElement('div');
        panel.style.cssText = "position:fixed; bottom:15px; right:15px; z-index:9999999; background:rgba(0,0,0,0.85); padding:8px 12px; border-radius:8px; color:white; font-size:11px; font-family:sans-serif; display:flex; align-items:center; gap:10px; backdrop-filter:blur(5px); border:1px solid #E98C00; box-shadow: 0 4px 10px rgba(0,0,0,0.5);";
        panel.innerHTML = `
            <span style="color:#E98C00; font-weight:900; letter-spacing:0.5px;">⚙️ SIMULAR ROL:</span>
            <select id="sim-rol-select" style="background:#222; color:white; border:1px solid #555; border-radius:4px; padding:4px 8px; font-size:11px; cursor:pointer; outline:none;">
                <option value="1">1. Administrador</option>
                <option value="2">2. Editor</option>
                <option value="3">3. Conductor</option>
                <option value="4">4. Vendedor</option>
                <option value="0">0. Cliente / Sin Permisos</option>
            </select>
        `;
        document.body.appendChild(panel);

        // Lee el rol guardado, si no hay, por defecto es Admin (1)
        const selector = document.getElementById('sim-rol-select');
        selector.value = localStorage.getItem('simular_rol_tabolango') || "1";

        // Cuando cambias el rol, lo guarda y recarga la página
        selector.addEventListener('change', (e) => {
            localStorage.setItem('simular_rol_tabolango', e.target.value);
            // 🔥 EL PUENTE: Le enviamos el rol al PHP mediante una Cookie
            document.cookie = "simular_rol_tabolango=" + e.target.value + "; path=/";
            location.reload();
        });
    });
}

// 🧠 CEREBRO DE IDENTIDAD: Interceptamos la variable global de forma inteligente
Object.defineProperty(window, 'APP_USER', {
    get: function () {
        // 1. Intentamos obtener el email real del bridge de WordPress (identidad real)
        const bridge = document.getElementById('session-email-bridge');
        const realEmail = bridge ? bridge.textContent.trim() : null;

        // Incluimos faltaTelefono en la estructura base
        let baseUser = window.APP_USER_DATA || { email: realEmail || "", rol_id: 0, isAdmin: false, faltaTelefono: false };

        if (isLocal) {
            const rolSimulado = parseInt(localStorage.getItem('simular_rol_tabolango') || "1");
            return {
                email: realEmail || baseUser.email || 'jandres@tabolango.cl',
                rol_id: rolSimulado,
                isAdmin: rolSimulado === 1,
                isEditor: rolSimulado === 2,
                isConductor: rolSimulado === 3,
                isVendedor: rolSimulado === 4,
                faltaTelefono: baseUser.faltaTelefono // Respetamos el estado real del teléfono
            };
        }

        return baseUser;
    }
});

window.obtenerEmailLimpio = function () {
    // 1. Revisa la variable de sesión principal de tu App
    if (typeof window.APP_USER !== 'undefined' && window.APP_USER && window.APP_USER.email) {
        return window.APP_USER.email;
    }

    // 2. Revisa la variable secundaria (si la usas en otra parte de tu código)
    if (typeof window.APP_USER_DATA !== 'undefined' && window.APP_USER_DATA && window.APP_USER_DATA.email) {
        return window.APP_USER_DATA.email;
    }

    // 3. (Opcional) Si tu login guarda el correo en el navegador, lo saca de ahí
    const localEmail = localStorage.getItem('app_user_email');
    if (localEmail) {
        return localEmail;
    }

    // Si tu App no tiene sesión iniciada, devuelve null. 
    // SE ACABÓ EL DEPENDER DEL HTML DE WORDPRESS.
    return null;
};

// ==========================================================================
// 3. GENERADOR DINÁMICO DE PETICIONES (API)
// ==========================================================================
window.getApi = function (archivo) {
    const archivoLimpio = archivo.startsWith('/') ? archivo.substring(1) : archivo;
    let urlFinal = `${BASE_URL}/${archivoLimpio}`;

    // Pegamos el correo REAL del usuario conectado en cada petición
    const userEmail = window.obtenerEmailLimpio();
    if (userEmail) {
        const separador = urlFinal.includes('?') ? '&' : '?';
        urlFinal += `${separador}wp_user=${encodeURIComponent(userEmail)}`;
    }

    // Blindaje Total comprobando el protocolo directamente
    if (window.location.protocol === 'https:') {
        urlFinal = urlFinal.replace('http://', 'https://');
    }

    return urlFinal;
};

// ==========================================================================
// 4. ESTADO GLOBAL (Variables compartidas)
// ==========================================================================
window.listaProductosMaster = [];
window.listaClientesMaster = [];
window.listaProductosPlana = [];
window.currentOrders = {};

// Variables UI / Editor
window.contadorFilas = 0;
window.filaActivaParaProducto = null;
window.grupoSeleccionado = null;
window.nombreCategoriaClienteActiva = "";
window.nivelProd = 0;
window.seleccionadoCat = null;
window.seleccionadoVar = null;
window.seleccionadoCal = null;
window.idPedidoEnEdicion = null;
window.clienteIdEditor = null;
window.filaActivaEditor = null;
window.nivelVisual = 0;
window.nombreCategoriaClienteEditor = "";
window.seleccionCatVisual = null;
window.seleccionVarVisual = null;
window.seleccionCalVisual = null;

// ==========================================================================
// 5. FUNCIONES GLOBALES (Modales, Filtros, etc)
// ==========================================================================
window.moverModalesAlBody = function () {
    const ids = ['contenedor-modales-tabolango', 'modal-comanda', 'modal-factura', 'modal-detalle-pedido', 'modal-editar-pedido', 'modal-selector-visual', 'modal-vista-previa', 'dynamic-qr-modal'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.parentNode !== document.body) document.body.appendChild(el);
    });
};

window.abrirModal = function (idOrUrl) {
    if (idOrUrl.includes('/') || idOrUrl.includes('.')) {
        const modal = document.getElementById('modal-factura');
        if (modal) {
            const frame = document.getElementById('frame-modal');
            const img = document.getElementById('img-modal');
            if (frame && img) {
                if (idOrUrl.toLowerCase().endsWith('.pdf') || idOrUrl.toLowerCase().endsWith('.xml')) {
                    frame.src = idOrUrl; frame.style.display = 'block'; img.style.display = 'none';
                } else {
                    img.src = idOrUrl; img.style.display = 'block'; frame.style.display = 'none';
                }
            }
            modal.style.display = 'flex';
        }
    } else {
        const el = document.getElementById(idOrUrl);
        if (el) {
            el.style.display = 'flex'; document.body.classList.add('modal-open');
            if (idOrUrl === 'modal-clientes') { window.grupoSeleccionado = null; if (window.renderizarGridClientes) window.renderizarGridClientes(); }
            if (idOrUrl === 'modal-productos') { window.nivelProd = 0; if (window.renderizarGridProductos) window.renderizarGridProductos(); }
        }
    }
};

window.cerrarModal = function (id) {
    const target = id || 'modal-factura';
    const el = document.getElementById(target);
    if (el) { el.style.display = 'none'; document.body.classList.remove('modal-open'); }
};

window.swalConfig = {
    customClass: { popup: 'm-swal-popup', title: 'm-swal-title', confirmButton: 'm-swal-confirm', cancelButton: 'm-swal-cancel' },
    buttonsStyling: true, confirmButtonColor: '#0F4B29', cancelButtonColor: '#e74c3c'
};

// ==========================================================================
// 6. FUNCIONES DE VALIDACIÓN DE PERFIL Y ARRANQUE (PREMIUM V2)
// ==========================================================================
window.verificarTelefonoFaltante = function () {
    const user = window.APP_USER;

    // Si falta el teléfono, y NO estamos en la página de login
    if (user && user.email && user.faltaTelefono && !window.location.pathname.includes('login')) {

        // 1. Inyectamos CSS Premium dinámicamente con medidas exactas
        if (!document.getElementById('premium-modal-styles')) {
            const style = document.createElement('style');
            style.id = 'premium-modal-styles';
            style.innerHTML = `
                .premium-swal-popup {
                    border-radius: 24px !important;
                    padding: 0 !important;
                    overflow: hidden;
                    box-shadow: 0 25px 60px rgba(0,0,0,0.2) !important;
                    border: none !important;
                    width: 420px !important; /* Ancho fijo para que no se apriete */
                }
                .premium-swal-popup .swal2-html-container {
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .premium-swal-popup .swal2-actions {
                    margin: 0 0 35px 0 !important; /* Espacio para el botón abajo */
                }
                .premium-modal-header {
                    background: linear-gradient(135deg, #0F4B29 0%, #165c38 100%);
                    padding: 45px 20px 30px 20px;
                    color: white;
                    text-align: center;
                }
                .ppm-icon {
                    width: 75px;
                    height: 75px;
                    background: white;
                    color: #25D366; /* Verde oficial WhatsApp */
                    font-size: 40px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 50%;
                    margin: 0 auto 20px auto;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                }
                .ppm-title {
                    color: white;
                    font-weight: 900;
                    font-size: 24px;
                    margin: 0;
                    letter-spacing: -0.5px;
                }
                .premium-modal-body {
                    padding: 35px 30px 15px 30px;
                    text-align: center;
                    background: #ffffff;
                }
                .ppm-text {
                    color: #555;
                    font-size: 15px;
                    line-height: 1.5;
                    margin: 0 0 30px 0;
                    font-weight: 500;
                }
                .premium-swal-input {
                    width: 100% !important;
                    max-width: 300px !important;
                    margin: 0 auto !important;
                    text-align: center;
                    font-size: 24px !important;
                    font-weight: 900 !important;
                    letter-spacing: 2px;
                    color: #1A1A1A !important;
                    background: #F9FAFB !important;
                    border: 2px solid #E5E7EB !important;
                    border-radius: 16px !important;
                    padding: 18px !important;
                    transition: all 0.3s ease !important;
                    box-sizing: border-box !important;
                    display: block;
                }
                .premium-swal-input:focus {
                    border-color: #E98C00 !important;
                    background: #FFF !important;
                    box-shadow: 0 8px 20px rgba(233, 140, 0, 0.15) !important;
                    outline: none !important;
                }
                .premium-swal-btn {
                    background: #0F4B29 !important;
                    color: white !important;
                    border: none !important;
                    padding: 16px 35px !important;
                    border-radius: 16px !important;
                    font-size: 15px !important;
                    font-weight: 800 !important;
                    text-transform: uppercase !important;
                    letter-spacing: 1px !important;
                    cursor: pointer !important;
                    transition: all 0.3s ease !important;
                    box-shadow: 0 10px 20px rgba(15, 75, 41, 0.2) !important;
                    width: 85% !important;
                    max-width: 300px !important;
                }
                .premium-swal-btn:hover {
                    transform: translateY(-3px) !important;
                    box-shadow: 0 15px 30px rgba(15, 75, 41, 0.3) !important;
                    background: #165c38 !important;
                }
                .swal2-validation-message {
                    margin-top: 15px !important;
                    border-radius: 8px !important;
                }
            `;
            document.head.appendChild(style);
        }

        // 2. Disparamos SweetAlert2 usando nuestro propio input en el HTML
        Swal.fire({
            html: `
                <div class="premium-modal-header">
                    <div class="ppm-icon">
                        <i class="fa-brands fa-whatsapp"></i>
                    </div>
                    <h2 class="ppm-title">Completa tu Perfil</h2>
                </div>
                <div class="premium-modal-body">
                    <p class="ppm-text">Para brindarte una experiencia fluida y enviarte notificaciones, necesitamos tu número de WhatsApp.</p>
                    <input type="text" id="custom-phone-input" class="premium-swal-input" value="+569" maxlength="12" placeholder="+569XXXXXXXX">
                </div>
            `,
            customClass: {
                popup: 'premium-swal-popup',
                confirmButton: 'premium-swal-btn'
            },
            buttonsStyling: false,
            showCancelButton: false,
            confirmButtonText: 'GUARDAR NÚMERO <i class="fa-solid fa-check" style="margin-left:8px;"></i>',
            allowOutsideClick: false,
            allowEscapeKey: false,

            // Lógica de máscara aplicada a nuestro input personalizado
            didOpen: () => {
                const input = document.getElementById('custom-phone-input');
                // Pone el cursor al final automáticamente
                input.focus();
                const valLength = input.value.length;
                input.setSelectionRange(valLength, valLength);

                input.addEventListener('input', (e) => {
                    let val = e.target.value;
                    val = val.replace(/[^\d+]/g, ''); // Solo deja números y +

                    if (!val.startsWith('+569')) {
                        val = '+569' + val.replace(/^\+?5?6?9?/, '');
                    }
                    if (val.length > 12) {
                        val = val.substring(0, 12);
                    }
                    e.target.value = val;
                });
            },
            // Valida el campo antes de dejar que el usuario haga clic en Guardar
            preConfirm: () => {
                const inputVal = document.getElementById('custom-phone-input').value;
                const regex = /^\+569\d{8}$/;
                if (!regex.test(inputVal)) {
                    Swal.showValidationMessage('Faltan dígitos. El formato debe ser +569XXXXXXXX');
                    return false; // Detiene el cierre
                }
                return inputVal; // Lo envía al result.value
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Guardando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                const fd = new FormData();
                fd.append('action', 'guardar_mi_telefono');
                fd.append('telefono', result.value); // Aquí viene el número validado

                fetch(wpData.siteUrl + '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: fd
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                title: '¡Excelente!',
                                text: 'Tu perfil está completo.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                window.APP_USER_DATA.faltaTelefono = false;
                            });
                        } else {
                            Swal.fire('Error', data.message, 'error').then(() => window.verificarTelefonoFaltante());
                        }
                    })
                    .catch(() => {
                        Swal.fire('Error', 'Fallo de conexión.', 'error').then(() => window.verificarTelefonoFaltante());
                    });
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.moverModalesAlBody();
    window.verificarTelefonoFaltante(); // Arranca la verificación global
});

