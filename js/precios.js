// ============================================================================
// LÓGICA DE LA PÁGINA: MATRIZ DE PRECIOS (BLINDADA)
// ============================================================================
(function () {
    // 1. Si no estamos en la página de matriz, abortar
    if (!document.getElementById('contenedor-matriz')) return;

    // 🔥 2. BLINDAJE DE FRONT-END (Zero Trust) 🔥
    // Verificamos si el usuario es Admin(1) o Editor(2) según la Cédula Global
    const puedeVerYEditar = window.APP_USER.isAdmin === true || window.APP_USER.isEditor === true;

    if (!puedeVerYEditar) {
        // Si no tiene permisos, destruimos la tabla y mostramos el error.
        document.getElementById('contenedor-matriz').innerHTML = `
            <div style="text-align:center; padding:100px 20px; color:white; animation: fadeIn 0.5s;">
                <i class="fa-solid fa-shield-halved" style="font-size: 60px; margin-bottom: 20px; color: #e74c3c;"></i>
                <h2 style="font-size: 28px; font-weight: 900; margin-bottom: 10px;">Acceso Restringido</h2>
                <p style="font-size: 16px; color: #ddd;">No tienes los privilegios necesarios para ver o modificar la matriz de precios.</p>
                <button onclick="window.location.href='${window.wpData?.siteUrl || '/'}'" style="margin-top: 30px; background: #E98C00; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; cursor: pointer;">VOLVER AL INICIO</button>
            </div>`;
        return; // Abortamos la ejecución del resto del script
    }

    // --- 3. BOTONES DE DESCARGA PDF ---
    const btnLista = document.getElementById('btnDescargarLista');
    const btnNorte = document.getElementById('btnDescargarNorte');

    if (btnLista) {
        btnLista.addEventListener('click', function () {
            // Ruta exacta hacia la carpeta inc de tu tema en WordPress
            window.open('/wp-content/themes/app_tabolango/inc/generar_pdf_precios.php', '_blank');
        });
    }

    if (btnNorte) {
        btnNorte.addEventListener('click', function () {
            // Ruta exacta hacia la carpeta inc de tu tema en WordPress
            window.open('/wp-content/themes/app_tabolango/inc/generar_pdf_precio_vnorte.php', '_blank');
        });
    }

    const URL_API_PRECIOS = window.getApi('precios.php');

    // --- 4. FUNCIONES DE UI EXPUESTAS AL HTML ---
    window.precios_filtrar = function () {
        const q = document.getElementById('busc-prod').value.toLowerCase();
        document.querySelectorAll('.fila-p').forEach(f => {
            f.style.display = f.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
    };

    window.precios_inFocus = function (el) {
        el.value = el.dataset.realValue || "0";
        el.type = "number";
    };

    window.precios_outFocus = function (el) {
        el.dataset.realValue = el.value;
        el.type = "text";
        el.value = window.formatearDinero(el.dataset.realValue);
    };

    window.precios_handleInput = function (el) {
        el.dataset.realValue = el.value;
        window.precios_calc(el);
        el.classList.add('modified');
        el.closest('tr').querySelector('.btn-save-row').classList.add('has-changes');
    };

    window.precios_calc = function (el) {
        const precio = parseFloat(el.dataset.realValue) || 0;
        const costo = parseFloat(el.dataset.costo) || 0;
        const kg = parseFloat(el.dataset.kgFactor) || 1;
        const cell = el.closest('td');
        const m = cell.querySelector('.m-gan'), g = cell.querySelector('.p-marg'), k = cell.querySelector('.val-kilo');

        if (precio > 0) {
            const gan = precio - costo;
            m.innerText = window.formatearDinero(gan);
            g.innerText = Math.round((gan / precio) * 100) + '%';
            k.innerText = window.formatearDinero(precio / kg);
            m.classList.toggle('negativo', gan < 0);
        }
    };

    // --- 5. GUARDADO DE DATOS ---
    window.precios_guardarFila = async function (btn) {
        if (!btn.classList.contains('has-changes')) return;

        const fila = btn.closest('tr');
        const idProd = fila.dataset.id;
        const inputs = fila.querySelectorAll('.in-matriz.modified');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

        try {
            for (let input of inputs) {
                const fd = new FormData();
                fd.append('id_producto', idProd);
                fd.append('id_categoria', input.dataset.cat);
                fd.append('precio', input.dataset.realValue);

                const res = await fetch(URL_API_PRECIOS, { method: 'POST', body: fd });
                const json = await res.json();

                if (json.status !== 'success') throw new Error(json.message);

                input.classList.remove('modified');
            }

            btn.classList.remove('has-changes');
            btn.innerHTML = '<i class="fa-solid fa-check"></i>';
            btn.style.background = "#27ae60";
            setTimeout(() => { btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i>'; btn.style.background = ""; }, 2000);

        } catch (e) {
            console.error("Error guardando:", e);
            btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
            if (typeof Swal !== 'undefined') {
                Swal.fire('Error', 'No se pudo guardar. Revisa la conexión.', 'error');
            }
        }
    };

    // --- 6. RENDERIZADO DE LA TABLA ---
    function generarCelda(idCat, valor, costo, kgFactor, estilo) {
        const val = valor || 0;
        let cssClass = '';
        if (estilo === 'lista') cssClass = 'celda-lista';
        else if (estilo === true) cssClass = 'col-highlight';

        return `
        <td class="${cssClass}">
            <input type="text" class="in-matriz" value="${val}" 
                   data-cat="${idCat}" data-costo="${costo}" data-kg-factor="${kgFactor}" data-real-value="${val}"
                   onfocus="window.precios_inFocus(this)" onblur="window.precios_outFocus(this)" oninput="window.precios_handleInput(this)">
            <div class="stats-row"><span class="m-gan">$0</span><span class="p-marg">0%</span></div>
            <div class="p-kilo-badge">Venta Kg: <span class="val-kilo">$0</span></div>
        </td>`;
    }

    async function cargarMatriz() {
        try {
            const res = await fetch(URL_API_PRECIOS);

            if (res.status === 403 || res.status === 401) {
                // Si el servidor (PHP) detecta que no eres Admin, devuelve error HTTP. Lo capturamos.
                document.getElementById('contenedor-matriz').innerHTML = `<div style="text-align:center; padding:80px; color:white;"><h2><i class="fa-solid fa-lock"></i> Acceso Denegado por el Servidor</h2><p>Intento de acceso bloqueado.</p></div>`;
                return;
            }

            const data = await res.json();
            const productos = data.productos || [];

            productos.sort((a, b) => {
                const estadoA = parseInt(a.activo) || 0;
                const estadoB = parseInt(b.activo) || 0;
                if (estadoA !== estadoB) return estadoB - estadoA;
                return a.producto.localeCompare(b.producto);
            });

            const tbody = document.getElementById('body-matriz');
            tbody.innerHTML = '';

            productos.forEach(p => {
                const tr = document.createElement('tr');
                const esInactivo = (p.activo == 0);

                tr.className = esInactivo ? 'fila-p producto-inactivo' : 'fila-p';
                tr.dataset.id = p.id_producto;
                const kg = parseFloat(p.kg_por_unidad) || 1;

                const variedadHtml = p.variedad ? `<span class="badge-var">${p.variedad}</span>` : '';
                const formatoTxt = p.formato || '-';
                const calibreTxt = p.calibre || '-';

                tr.innerHTML = `
            <td class="sticky-col">
                <div class="prod-wrapper">
                    <div class="prod-icon-large">${p.icono || '📦'}</div>
                    <div class="prod-data-col">
                        <div class="prod-top-row">
                            <span class="prod-name-main">${p.producto}</span>
                            ${variedadHtml}
                            ${esInactivo ? '<span style="font-size:9px; color:red; margin-left:5px; border:1px solid red; padding:0 3px; border-radius:3px;">OFF</span>' : ''}
                        </div>
                        <div class="prod-meta">
                            <span title="Calibre"><i class="fa-solid fa-ruler-horizontal"></i> ${calibreTxt}</span>
                            <span class="sep-dot">|</span>
                            <span title="Formato"><i class="fa-solid fa-box-open"></i> ${formatoTxt}</span>
                        </div>
                    </div>
                </div>
            </td>
            <td style="font-weight:700; color:#888;">${window.formatearDinero(p.costo_actual)}</td>
            ${generarCelda('lista', p.precio_actual, p.costo_actual, kg, 'lista')}        
            ${generarCelda(1, p.p1, p.costo_actual, kg, true)}
            ${generarCelda(2, p.p2, p.costo_actual, kg, false)}
            ${generarCelda(4, p.p4, p.costo_actual, kg, true)} 
            <td style="text-align:center;">
                <button class="btn-save-row" onclick="window.precios_guardarFila(this)"><i class="fa-solid fa-floppy-disk"></i></button>
            </td>`;
                tbody.appendChild(tr);
            });

            document.querySelectorAll('.in-matriz').forEach(el => {
                el.dataset.realValue = el.value;
                el.value = window.formatearDinero(el.dataset.realValue);
                window.precios_calc(el);
            });

        } catch (e) {
            console.error("Excepción cargando matriz:", e);
        }
    }

    // --- 7. INICIALIZADOR ---
    cargarMatriz();

})();