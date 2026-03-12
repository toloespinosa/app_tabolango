console.log("🚀 SISTEMA TABOLANGO: Cargando Globales...");

// ==========================================================================
// 1. UTILIDADES BÁSICAS (OPTIMIZADO PARA ERP)
// ==========================================================================
const isLocal = window.location.hostname.includes('.local') || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

// Si es local, usa tu ruta de desarrollo. 
// Si es producción, ahora captura dinámicamente el dominio actual (erp.tabolango.cl)
const BASE_URL = isLocal
    ? `${window.location.protocol}//${window.location.hostname}/wp-content/themes/Tabolango/inc`
    : `https://${window.location.hostname}/inc`; // Ajusta "/inc" según dónde guardes los PHP en tu repo de GitHub

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

        let baseUser = window.APP_USER_DATA || { email: realEmail || "", rol_id: 0, isAdmin: false };

        if (isLocal) {
            const rolSimulado = parseInt(localStorage.getItem('simular_rol_tabolango') || "1");
            return {
                // PRIORIDAD: 1. Email real de WP | 2. Email de baseUser | 3. Fallback (TU correo real de admin)
                email: realEmail || baseUser.email || 'jandres@tabolango.cl',
                rol_id: rolSimulado,
                isAdmin: rolSimulado === 1,
                isEditor: rolSimulado === 2,
                isConductor: rolSimulado === 3,
                isVendedor: rolSimulado === 4
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

document.addEventListener('DOMContentLoaded', () => {
    window.moverModalesAlBody();
});