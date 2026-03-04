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
// 2. MOTOR CENTRAL DE IDENTIFICACIÓN (CONECTADO 100% A PHP)
// ==========================================================================
// Ya no adivinamos correos ni usamos fallbacks locales. Leemos la cédula inyectada desde PHP.
window.APP_USER = window.APP_USER_DATA || {
    email: "",
    rol_id: 0,
    isAdmin: false,
    isEditor: false,
    isConductor: false,
    isVendedor: false
};

window.obtenerEmailLimpio = function () {
    return window.APP_USER.email;
};

// ==========================================================================
// 3. GENERADOR DINÁMICO DE PETICIONES (API)
// ==========================================================================
window.getApi = function (archivo) {
    const archivoLimpio = archivo.startsWith('/') ? archivo.substring(1) : archivo;
    let urlFinal = `${BASE_URL}/${archivoLimpio}`;

    // Pegamos el correo REAL del usuario conectado en cada petición
    if (window.APP_USER.email) {
        const separador = urlFinal.includes('?') ? '&' : '?';
        urlFinal += `${separador}wp_user=${encodeURIComponent(window.APP_USER.email)}`;
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