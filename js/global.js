console.log("🚀 SISTEMA TABOLANGO: Cargando Globales...");

// ==========================================================================
// 1. UTILIDADES BÁSICAS
// ==========================================================================
const isLocal = window.location.hostname.includes('.local') || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

// Ajuste dinámico de la base URL
const BASE_URL = isLocal ? `${window.location.protocol}//${window.location.hostname}/wp-content/themes/Tabolango/inc` : 'https://tabolango.cl';

console.log(`📍 Entorno: ${isLocal ? 'LOCAL 💻' : 'PRODUCCIÓN ☁️'} (${window.location.protocol})`);

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
            location.reload();
        });
    });
}

// 🧠 CEREBRO DE IDENTIDAD: Interceptamos la variable global
Object.defineProperty(window, 'APP_USER', {
    get: function () {
        let baseUser = window.APP_USER_DATA || { email: "", rol_id: 0, isAdmin: false, isEditor: false, isConductor: false, isVendedor: false };

        // Si estamos en local, sobrescribimos los permisos con lo que diga el selector
        if (isLocal) {
            const rolSimulado = parseInt(localStorage.getItem('simular_rol_tabolango') || "1");
            return {
                email: baseUser.email || 'jaespinosaa@gmail.com', // Mantiene tu correo para que la API funcione
                rol_id: rolSimulado,
                isAdmin: rolSimulado === 1,
                isEditor: rolSimulado === 2,
                isConductor: rolSimulado === 3,
                isVendedor: rolSimulado === 4
            };
        }

        // En Producción, entrega los datos reales intactos
        return baseUser;
    }
});

window.obtenerEmailLimpio = function () {
    if (typeof window.APP_USER_DATA !== 'undefined' && window.APP_USER_DATA.email) {
        return window.APP_USER_DATA.email;
    }
    const b = document.getElementById('session-email-bridge');
    return b ? b.innerText.trim() : '';
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