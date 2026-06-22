// ============================================================
// PROVEEDORES / COTIZACIONES DE COMPRAS — hub
// ============================================================
(function () {
    if (!document.getElementById('contenedor-proveedores')) return;

    const URL_API = window.getApi('api_proveedores.php');
    let tabActiva        = 'proveedores';
    let proveedoresData  = [];
    let productosRaw     = []; // todas las filas crudas del servidor
    let productosAgrupados = []; // grupos por (producto + calibre) con stats
    let filtroDisp       = 'todos'; // todos | disponible | futuro | terminado

    const fmt = window.formatCLP || (v => '$' + v);

    // ── Chips de filtro de disponibilidad ────────────────────
    document.querySelectorAll('.prov-chip').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.prov-chip').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filtroDisp = this.dataset.filtro;
            renderProductos();
        });
    });

    // ── Tabs ─────────────────────────────────────────────────
    document.querySelectorAll('.prov-tab').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.prov-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            tabActiva = this.dataset.tab;
            document.getElementById('vista-proveedores').style.display =
                tabActiva === 'proveedores' ? 'block' : 'none';
            document.getElementById('vista-productos').style.display =
                tabActiva === 'productos' ? 'block' : 'none';
            document.getElementById('prov-buscar').placeholder =
                tabActiva === 'proveedores' ? 'Buscar proveedor...' : 'Buscar producto...';
            document.getElementById('prov-buscar').value = '';
            // Al pasar a "Por producto" carga todos automáticamente
            if (tabActiva === 'productos' && productosRaw.length === 0) {
                cargarProductos();
            } else if (tabActiva === 'productos') {
                renderProductos();
            }
        });
    });

    // ── Cargar proveedores ───────────────────────────────────
    async function cargarProveedores() {
        try {
            const res = await fetch(URL_API + '&action=list_proveedores');
            proveedoresData = await res.json();
            renderProveedores();
        } catch (e) {
            console.error('Error cargando proveedores:', e);
            document.getElementById('grid-proveedores').innerHTML =
                '<div class="prov-loading">Error al cargar proveedores</div>';
        }
    }

    function renderProveedores() {
        const grid = document.getElementById('grid-proveedores');
        const q    = (document.getElementById('prov-buscar').value || '').toLowerCase();
        const list = proveedoresData.filter(p =>
            (p.nombre + ' ' + (p.contacto || '') + ' ' + (p.ciudad || '')).toLowerCase().includes(q)
        );

        if (!list.length) {
            grid.innerHTML = '<div class="prov-loading">' +
                (proveedoresData.length === 0
                    ? 'Aún no hay proveedores. Crea el primero con el botón "Nuevo proveedor"'
                    : 'Sin resultados') +
                '</div>';
            return;
        }

        grid.innerHTML = list.map(p => {
            const fechaUlt = p.ultima_cotizacion
                ? new Date(p.ultima_cotizacion).toLocaleDateString('es-CL')
                : '—';
            const url = '/proveedor-detalle/?id=' + p.id_proveedor;
            return `
                <a href="${url}" class="prov-card" style="text-decoration:none; color:inherit;">
                    <h3 class="prov-card-titulo">${escapeHtml(p.nombre)}</h3>
                    <div class="prov-card-meta">
                        ${p.contacto ? `<span><i class="fa-solid fa-user"></i>${escapeHtml(p.contacto)}</span>` : ''}
                        ${p.ciudad   ? `<span><i class="fa-solid fa-location-dot"></i>${escapeHtml(p.ciudad)}</span>` : ''}
                        ${p.telefono ? `<span><i class="fa-solid fa-phone"></i>${escapeHtml(p.telefono)}</span>` : ''}
                    </div>
                    <div class="prov-card-stats">
                        <div>
                            <div class="prov-card-stat-num">${p.n_productos || 0}</div>
                            <div class="prov-card-stat-lbl">Productos</div>
                        </div>
                        <div style="text-align:right;">
                            <div class="prov-card-stat-num" style="font-size:12px;">${fechaUlt}</div>
                            <div class="prov-card-stat-lbl">Última cotización</div>
                        </div>
                    </div>
                </a>`;
        }).join('');
    }

    // ── Carga + agrupación de productos ──────────────────────
    async function cargarProductos() {
        const grid = document.getElementById('grid-productos');
        grid.innerHTML = '<div class="prov-loading">Cargando productos...</div>';
        try {
            const res = await fetch(URL_API + '&action=search_by_product&q=');
            productosRaw = await res.json();
            agruparProductos();
            renderProductos();
        } catch (e) {
            grid.innerHTML = '<div class="prov-loading">Error al cargar productos</div>';
        }
    }

    /**
     * Agrupa las filas por (nombre + calibre) y calcula stats:
     * precio mínimo vigente, proveedor con ese precio, cantidad de proveedores.
     */
    function agruparProductos() {
        const grupos = new Map();
        productosRaw.forEach(r => {
            const key = (r.producto_nombre || '').toLowerCase().trim()
                      + '|' + (r.calibre || '').toLowerCase().trim();
            if (!grupos.has(key)) {
                grupos.set(key, {
                    producto_nombre: r.producto_nombre,
                    variedad:        r.variedad || '',
                    calibre:         r.calibre  || '',
                    unidad:          r.unidad   || '',
                    formato:         r.formato  || '',
                    filas: []
                });
            }
            grupos.get(key).filas.push(r);
        });

        // Calcular stats por grupo. "Mejor precio" = el más bajo entre filas
        // con cotización vigente o vence_hoy. Si no hay vigentes, usa el más
        // bajo entre vencidos (mostrando que está vencido).
        productosAgrupados = [...grupos.values()].map(g => {
            const n_total = g.filas.length;
            const conPrecio = g.filas.filter(f => f.precio && Number(f.precio) > 0);
            const vigentes  = conPrecio.filter(f =>
                f.estado_vigencia === 'vigente' || f.estado_vigencia === 'vence_hoy');
            const fuente = vigentes.length ? vigentes : conPrecio;

            let mejor = null;
            fuente.forEach(f => {
                const p = parseFloat(f.precio);
                if (!mejor || p < mejor.precio) {
                    mejor = {
                        precio: p,
                        proveedor_id:     f.id_proveedor,
                        proveedor_nombre: f.proveedor_nombre,
                        estado:           f.estado_vigencia,
                        validez:          f.validez
                    };
                }
            });

            // Cuántos proveedores tienen stock disponible HOY (sin_info = se asume disponible)
            const disponiblesAhora = g.filas.filter(f =>
                f.estado_disponibilidad === 'disponible' || f.estado_disponibilidad === 'sin_info'
            ).length;

            // Set de estados presentes en este producto (cualquier proveedor).
            // Lo usa el filtro para mostrar un mismo producto en varios chips a la vez.
            const estadosPresentes = new Set(
                g.filas.map(f => f.estado_disponibilidad || 'sin_info')
            );

            return {
                ...g,
                n_proveedores:     n_total,
                n_disponibles:     disponiblesAhora,
                tiene_vigentes:    vigentes.length > 0,
                estados_presentes: estadosPresentes,
                mejor:             mejor
            };
        });

        // Orden: alfabético por nombre (estable)
        productosAgrupados.sort((a, b) =>
            a.producto_nombre.localeCompare(b.producto_nombre, 'es', { sensitivity: 'base' }));
    }

    function renderProductos() {
        const grid = document.getElementById('grid-productos');
        const q    = (document.getElementById('prov-buscar').value || '').toLowerCase().trim();
        const list = productosAgrupados.filter(g => {
            // Filtro por texto
            const matchTxt = (g.producto_nombre + ' ' + g.variedad + ' ' + g.calibre)
                                .toLowerCase().includes(q);
            if (!matchTxt) return false;
            // Filtro por disponibilidad — pasa si CUALQUIER proveedor del producto
            // está en ese estado. Así un producto con un proveedor disponible y
            // otro próximo aparece en ambos filtros.
            if (filtroDisp === 'todos') return true;
            if (filtroDisp === 'disponible') {
                return g.estados_presentes.has('disponible') || g.estados_presentes.has('sin_info');
            }
            return g.estados_presentes.has(filtroDisp);
        });

        if (!list.length) {
            grid.innerHTML = '<div class="prov-loading">' +
                (productosAgrupados.length === 0
                    ? 'Aún no hay productos cotizados. Agrega cotizaciones desde la página de un proveedor.'
                    : 'Sin resultados con este filtro') +
                '</div>';
            return;
        }

        grid.innerHTML = list.map((g, idx) => {
            const tieneMejor = !!g.mejor;
            const klassEstado = g.mejor ? g.mejor.estado : 'sin_precio';
            const precioStr = g.mejor ? fmt(g.mejor.precio) : '<span style="color:#aaa;">—</span>';
            return `
                <div class="prov-card" onclick="prov_verDetalle(${idx})" style="cursor:pointer;">
                    <h3 class="prov-card-titulo">
                        ${escapeHtml(g.producto_nombre)}
                        ${g.variedad ? `<span class="prov-prod-variedad">${escapeHtml(g.variedad)}</span>` : ''}
                    </h3>
                    <div class="prov-card-meta">
                        ${g.calibre ? `<span><i class="fa-solid fa-ruler-horizontal"></i>${escapeHtml(g.calibre)}</span>` : ''}
                        ${g.formato ? `<span><i class="fa-solid fa-box"></i>${escapeHtml(g.formato)}</span>` : ''}
                    </div>
                    <div class="prov-mejor-precio">
                        <div class="prov-mejor-precio-lbl">
                            ${tieneMejor && g.tiene_vigentes ? '🏷️ Mejor precio' : (tieneMejor ? '⚠️ Precio vencido' : 'Sin precios')}
                        </div>
                        <div class="prov-mejor-precio-val ${klassEstado}">${precioStr}</div>
                        ${g.mejor ? `<div class="prov-mejor-precio-prov">${escapeHtml(g.mejor.proveedor_nombre)}</div>` : ''}
                    </div>
                    <div class="prov-card-stats">
                        <div>
                            <div class="prov-card-stat-num">${g.n_proveedores}</div>
                            <div class="prov-card-stat-lbl">${g.n_proveedores === 1 ? 'Proveedor' : 'Proveedores'}</div>
                        </div>
                        <div style="text-align:right;">
                            <div class="prov-card-stat-num" style="color:${g.n_disponibles > 0 ? '#1b5e20' : '#c62828'};">
                                ${g.n_disponibles}
                            </div>
                            <div class="prov-card-stat-lbl">Con stock</div>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    // ── Modal de detalle: todos los proveedores para un producto ──
    window.prov_verDetalle = function (idx) {
        const g = productosAgrupados[idx];
        if (!g) return;

        document.getElementById('prod-det-titulo').textContent =
            g.producto_nombre + (g.calibre ? ' · ' + g.calibre : '');

        document.getElementById('prod-det-resumen').innerHTML = `
            <div style="display:flex; gap:14px; flex-wrap:wrap; padding:12px 14px; background:#fbf6ee; border:1px solid #efe5d3; border-radius:10px;">
                ${g.variedad ? `<div><strong style="font-size:11px; color:#888;">VARIEDAD</strong><br>${escapeHtml(g.variedad)}</div>` : ''}
                <div><strong style="font-size:11px; color:#888;">UNIDAD</strong><br>${escapeHtml(g.unidad || '-')}</div>
                <div><strong style="font-size:11px; color:#888;">FORMATO</strong><br>${escapeHtml(g.formato || '-')}</div>
                <div><strong style="font-size:11px; color:#888;">PROVEEDORES</strong><br>${g.n_proveedores}</div>
                ${g.mejor && g.tiene_vigentes ? `
                    <div style="margin-left:auto;">
                        <strong style="font-size:11px; color:#888;">MEJOR PRECIO</strong><br>
                        <span class="prov-precio" style="font-size:16px;">${fmt(g.mejor.precio)}</span>
                        <span style="font-size:11px; color:#666;"> · ${escapeHtml(g.mejor.proveedor_nombre)}</span>
                    </div>` : ''}
            </div>
        `;

        // Filas ordenadas: primero los que tienen stock + precio vigente, luego por precio ascendente.
        const prioDisp = { disponible: 0, sin_info: 1, futuro: 2, terminado: 3 };
        const prioVig  = { vigente: 0, vence_hoy: 1, vencido: 2, sin_precio: 3 };
        const ordenadas = [...g.filas].sort((a, b) => {
            const pda = prioDisp[a.estado_disponibilidad] ?? 4;
            const pdb = prioDisp[b.estado_disponibilidad] ?? 4;
            if (pda !== pdb) return pda - pdb;
            const pva = prioVig[a.estado_vigencia] ?? 4;
            const pvb = prioVig[b.estado_vigencia] ?? 4;
            if (pva !== pvb) return pva - pvb;
            return (parseFloat(a.precio) || Infinity) - (parseFloat(b.precio) || Infinity);
        });

        const fmtDMY = d => d ? new Date(d + 'T00:00:00').toLocaleDateString('es-CL', {day:'2-digit', month:'2-digit'}) : '';

        document.getElementById('prod-det-tbody').innerHTML = ordenadas.map(f => {
            const precio = f.precio ? fmt(f.precio) : '<span style="color:#aaa;">—</span>';
            // Disponibilidad
            const dispLabel = ({
                disponible: 'Disponible',
                futuro:     'Llega ' + fmtDMY(f.disponible_desde),
                terminado:  'Fuera de temp.',
                sin_info:   '—'
            })[f.estado_disponibilidad] || '—';
            const dispFechas = (() => {
                if (f.estado_disponibilidad === 'disponible' && f.disponible_hasta) return 'hasta ' + fmtDMY(f.disponible_hasta);
                if (f.estado_disponibilidad === 'futuro'     && f.disponible_hasta) return 'hasta ' + fmtDMY(f.disponible_hasta);
                if (f.estado_disponibilidad === 'terminado'  && f.disponible_hasta) return 'terminó ' + fmtDMY(f.disponible_hasta);
                return '';
            })();
            return `
                <tr>
                    <td>
                        <strong>${escapeHtml(f.proveedor_nombre)}</strong>
                    </td>
                    <td style="text-align:center; font-size:12px; color:#666;">
                        ${f.proveedor_contacto ? escapeHtml(f.proveedor_contacto) : '—'}
                        ${f.proveedor_telefono ? `<br><span style="font-size:10px; color:#999;">${escapeHtml(f.proveedor_telefono)}</span>` : ''}
                    </td>
                    <td class="prov-precio" style="text-align:right;">${precio}</td>
                    <td style="text-align:center;">
                        ${f.validez ? `<span class="badge-validez">${escapeHtml(f.validez)}</span>` : '<span style="color:#aaa;">—</span>'}
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-vigencia ${f.estado_vigencia}">${textoEstado(f.estado_vigencia)}</span>
                    </td>
                    <td style="text-align:center;">
                        <span class="badge-disp ${f.estado_disponibilidad || 'sin_info'}">${dispLabel}</span>
                        ${dispFechas ? `<div style="font-size:10px; color:#666; margin-top:3px;">${dispFechas}</div>` : ''}
                    </td>
                    <td style="text-align:center;">
                        <a href="/proveedor-detalle/?id=${f.id_proveedor}"
                           style="color:#6f4a1f; text-decoration:none; font-weight:700;"
                           title="Ir al proveedor">
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </td>
                </tr>`;
        }).join('');

        document.getElementById('modal-prod-detalle').style.display = 'flex';
    };

    window.prov_cerrarDetalle = function () {
        document.getElementById('modal-prod-detalle').style.display = 'none';
    };

    function textoEstado(s) {
        return ({
            vigente:    'Vigente',
            vence_hoy:  'Vence hoy',
            vencido:    'Vencido',
            sin_precio: 'Sin precio'
        })[s] || s;
    }

    // ── Buscador global (siempre client-side) ────────────────
    window.prov_filtrar = function () {
        if (tabActiva === 'proveedores') {
            renderProveedores();
        } else {
            renderProductos();
        }
    };

    // ── Modal nuevo proveedor ────────────────────────────────
    document.getElementById('btnAbrirNuevoProveedor').addEventListener('click', () => {
        document.getElementById('modal-nuevo-proveedor').style.display = 'flex';
        document.getElementById('np-nombre').focus();
    });

    window.prov_cerrarModal = function () {
        document.getElementById('modal-nuevo-proveedor').style.display = 'none';
        ['np-nombre','np-rut','np-contacto','np-telefono','np-email','np-ciudad','np-direccion','np-notas']
            .forEach(id => {
                const el = document.getElementById(id);
                el.value = '';
                el.classList.remove('nc-error');
            });
    };

    document.getElementById('btnGuardarProveedor').addEventListener('click', async function () {
        const nombre = document.getElementById('np-nombre').value.trim();
        if (!nombre) {
            document.getElementById('np-nombre').classList.add('nc-error');
            document.getElementById('np-nombre').focus();
            return;
        }
        const btn = this;
        btn.disabled  = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';
        try {
            const res = await fetch(URL_API + '&action=add_proveedor', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nombre:    nombre,
                    rut:       document.getElementById('np-rut').value.trim(),
                    contacto:  document.getElementById('np-contacto').value.trim(),
                    telefono:  document.getElementById('np-telefono').value.trim(),
                    email:     document.getElementById('np-email').value.trim(),
                    ciudad:    document.getElementById('np-ciudad').value.trim(),
                    direccion: document.getElementById('np-direccion').value.trim(),
                    notas:     document.getElementById('np-notas').value.trim()
                })
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Error');

            // Ir directo a la página de detalle del proveedor recién creado
            window.location.href = '/proveedor-detalle/?id=' + data.id_proveedor;
        } catch (e) {
            btn.disabled  = false;
            btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Error';
            btn.style.background = '#e74c3c';
            setTimeout(() => {
                btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar proveedor';
                btn.style.background = '';
            }, 2000);
        }
    });

    // ── Util ─────────────────────────────────────────────────
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, ch => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        })[ch]);
    }

    // ── Init ─────────────────────────────────────────────────
    cargarProveedores();
})();
