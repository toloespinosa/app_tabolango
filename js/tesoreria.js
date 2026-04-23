/* ==========================================================================
   Motor Frontend - Tesorería IA V6 (Resúmenes y Alertas Parciales)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {

    const cleanRut = (rut) => rut ? rut.toString().replace(/[^0-9kK]/gi, '').toUpperCase() : '';

    // 🔥 1. COMPACTADOR DE FACTURAS 🔥
    window.facturasAgrupadas = [];
    if (window.facturasPendientes) {
        const mapaAgrupado = {};
        window.facturasPendientes.forEach(f => {
            const clave = f.numero_factura ? `FAC_${f.numero_factura}` : `PED_${f.id_pedido}`;
            if (!mapaAgrupado[clave]) {
                mapaAgrupado[clave] = {
                    ...f, clave_agrupacion: clave, deuda_actual: parseFloat(f.deuda_actual) || 0, productos_internos: [f]
                };
            } else {
                mapaAgrupado[clave].deuda_actual += parseFloat(f.deuda_actual) || 0;
                mapaAgrupado[clave].productos_internos.push(f);
            }
        });
        window.facturasAgrupadas = Object.values(mapaAgrupado);
    }

    // 🔥 2. ALGORITMO IA UNIVERSAL 🔥
    function encontrarCombinacionExacta(items, montoObjetivo, propValor) {
        let mejorResultado = null;
        let menorDiferencia = 101;
        const itemsFiltrados = items.slice(0, 15);

        function buscar(index, sumaActual, combinacionActual) {
            if (sumaActual > 0) {
                const diff = Math.abs(Math.round(sumaActual) - Math.round(montoObjetivo));
                if (diff < menorDiferencia) {
                    menorDiferencia = diff;
                    mejorResultado = [...combinacionActual];
                }
                if (diff === 0) return true;
            }
            if (index >= itemsFiltrados.length || sumaActual > montoObjetivo + 100) return false;

            combinacionActual.push(itemsFiltrados[index]);
            let valorActual = Math.round(parseFloat(itemsFiltrados[index][propValor]));
            let encontradoExacto = buscar(index + 1, sumaActual + valorActual, combinacionActual);
            combinacionActual.pop();

            if (encontradoExacto) return true;
            return buscar(index + 1, sumaActual, combinacionActual);
        }

        buscar(0, 0, []);
        return mejorResultado;
    }

    // 🔥 3. ESCÁNER PROACTIVO 🔥
    function escanearMatchesProactivos() {
        if (!window.facturasAgrupadas || !window.abonosPendientes) return;

        document.querySelectorAll('.fila-banco').forEach(fila => {
            const bancoId = fila.getAttribute('data-id');
            const bancoRut = cleanRut(fila.getAttribute('data-rut'));
            const bancoMonto = Math.round(parseFloat(fila.getAttribute('data-monto')));
            const btnMatch = fila.querySelector('.btn-buscar-match');

            if (!bancoRut || bancoMonto <= 0) return;

            const facturasDelCliente = window.facturasAgrupadas.filter(p => cleanRut(p.rut_cliente) === bancoRut);
            if (facturasDelCliente.length === 0) return;

            // REGLA 1: Match Perfecto
            const matchesPerfectos = facturasDelCliente.filter(p => Math.abs(Math.round(parseFloat(p.deuda_actual)) - bancoMonto) <= 100);
            if (matchesPerfectos.length > 0) {
                matchesPerfectos.sort((a, b) => new Date(a.fecha_creacion) - new Date(b.fecha_creacion));
                btnMatch.innerHTML = '<i class="fa-solid fa-star"></i> MATCH PERFECTO';
                btnMatch.style.background = 'rgba(111, 66, 193, 0.15)';
                btnMatch.style.color = '#4a2377';
                btnMatch.style.border = '1px solid #4a2377';
                btnMatch.setAttribute('data-ia-tipo', 'perfecto');
                btnMatch.setAttribute('data-ia-factura', matchesPerfectos[0].clave_agrupacion);
                return;
            }

            // REGLA 2: Match Múltiple
            const combMultiple = encontrarCombinacionExacta(facturasDelCliente, bancoMonto, 'deuda_actual');
            if (combMultiple && combMultiple.length > 1) {
                btnMatch.innerHTML = '<i class="fa-solid fa-layer-group"></i> MATCH MÚLTIPLE';
                btnMatch.style.background = 'rgba(23, 162, 184, 0.15)';
                btnMatch.style.color = '#0c5460';
                btnMatch.style.border = '1px solid #0c5460';
                btnMatch.setAttribute('data-ia-tipo', 'multiple');
                btnMatch.setAttribute('data-ia-facturas', JSON.stringify(combMultiple.map(f => f.clave_agrupacion)));
                return;
            }

            // REGLA 3: Match Combinado Inverso
            const otrosAbonosCliente = window.abonosPendientes.filter(a => cleanRut(a.rut_remitente) === bancoRut && a.id != bancoId);
            if (otrosAbonosCliente.length > 0) {
                for (let fac of facturasDelCliente) {
                    let deuda = Math.round(parseFloat(fac.deuda_actual));
                    let faltante = deuda - bancoMonto;
                    if (faltante > 0) {
                        let combBancos = encontrarCombinacionExacta(otrosAbonosCliente, faltante, 'saldo_disponible');
                        if (combBancos) {
                            btnMatch.innerHTML = '<i class="fa-solid fa-link"></i> MATCH COMBINADO';
                            btnMatch.style.background = 'rgba(13, 110, 253, 0.15)';
                            btnMatch.style.color = '#0d6efd';
                            btnMatch.style.border = '1px solid #0d6efd';
                            btnMatch.setAttribute('data-ia-tipo', 'combinado');
                            btnMatch.setAttribute('data-ia-factura', fac.clave_agrupacion);
                            btnMatch.setAttribute('data-ia-bancos', JSON.stringify([bancoId, ...combBancos.map(b => b.id)]));
                            return;
                        }
                    }
                }
            }

            // REGLA 4: Match Parcial 50%
            const matchesMitad = facturasDelCliente.filter(p => Math.abs(Math.round(parseFloat(p.deuda_actual) / 2) - bancoMonto) <= 100);
            if (matchesMitad.length > 0) {
                matchesMitad.sort((a, b) => new Date(a.fecha_creacion) - new Date(b.fecha_creacion));
                btnMatch.innerHTML = '<i class="fa-solid fa-star-half-stroke"></i> MATCH 50%';
                btnMatch.style.background = 'rgba(253, 126, 20, 0.15)';
                btnMatch.style.color = '#d35400';
                btnMatch.style.border = '1px solid #d35400';
                btnMatch.setAttribute('data-ia-tipo', 'mitad');
                btnMatch.setAttribute('data-ia-factura', matchesMitad[0].clave_agrupacion);
                return;
            }
        });
    }

    escanearMatchesProactivos();

    // 🔥 4. EL GESTOR DE CLICS 🔥
    window.gestionarMatch = function (idMovimiento, rutBanco, montoBanco) {
        const btn = event.currentTarget;
        const iaTipo = btn.getAttribute('data-ia-tipo');

        if (iaTipo === 'perfecto' || iaTipo === 'mitad') {
            const factura = window.facturasAgrupadas.find(f => f.clave_agrupacion === btn.getAttribute('data-ia-factura'));
            const numDoc = factura.numero_factura ? `N° ${factura.numero_factura}` : `Pedido ${factura.id_pedido}`;
            const deuda = Math.round(parseFloat(factura.deuda_actual));

            let titulo = iaTipo === 'perfecto' ? '¡Match Perfecto!' : '¡Abono Parcial (50%)!';
            let extraHtml = '';

            if (iaTipo === 'perfecto') {
                const dif = deuda - montoBanco;
                if (dif > 0 && dif <= 100) {
                    extraHtml = `<div style="background:#fff3cd; color:#856404; padding:10px; border-radius:5px; margin-top:10px; font-size:13px; text-align:left;"><b>Atención:</b> Faltan $${dif} pesos.<br><label><input type="checkbox" id="chk-perdonar" checked> Asumir diferencia y cerrar factura</label></div>`;
                }
            } else {
                const saldoPendiente = deuda - montoBanco;
                // Alerta destacada para pagos parciales
                extraHtml = `<div style="background:#fff3cd; color:#d35400; padding:10px; border-radius:5px; margin-top:10px; font-size:13px; font-weight:bold; border: 1px solid #ffeeba; text-align:left;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Este pago corresponde a la mitad de la deuda.<br>
                                La factura quedará ABIERTA faltando un pago de $${saldoPendiente.toLocaleString('es-CL')}.
                             </div>`;
            }

            Swal.fire({
                title: titulo,
                html: `<b>Factura:</b> ${numDoc}<br><b>Deuda Total:</b> $${deuda.toLocaleString('es-CL')}<br><b>Abono:</b> $${montoBanco.toLocaleString('es-CL')}<br>${extraHtml}`,
                icon: iaTipo === 'perfecto' ? 'success' : 'info',
                showCancelButton: true, showDenyButton: true,
                confirmButtonColor: iaTipo === 'perfecto' ? '#4a2377' : '#d35400',
                denyButtonColor: '#17a2b8',
                confirmButtonText: 'Sí, aplicar pago', denyButtonText: 'Buscar otra'
            }).then((res) => {
                if (res.isConfirmed) {
                    const chk = document.getElementById('chk-perdonar');
                    let aplicar = (chk && chk.checked) ? deuda : montoBanco;
                    ejecutarPagosDistribuidos([factura], [idMovimiento], aplicar);
                } else if (res.isDenied) abrirModalBuscador(idMovimiento, cleanRut(rutBanco), montoBanco);
            });

        } else if (iaTipo === 'multiple') {
            const claves = JSON.parse(btn.getAttribute('data-ia-facturas'));
            const facturasComb = window.facturasAgrupadas.filter(f => claves.includes(f.clave_agrupacion));
            const sumaDeuda = Math.round(facturasComb.reduce((acc, f) => acc + parseFloat(f.deuda_actual), 0));
            const dif = sumaDeuda - montoBanco;

            let extraHtml = '';
            if (dif > 0 && dif <= 100) extraHtml = `<div style="background:#fff3cd; color:#856404; padding:10px; border-radius:5px; margin-top:10px; font-size:13px; text-align:left;"><b>Atención:</b> Faltan $${dif} pesos.<br><label><input type="checkbox" id="chk-perdonar-mult" checked> Asumir diferencia y cerrar todas</label></div>`;

            let lista = facturasComb.map(f => `<li style="padding: 4px 0; border-bottom: 1px solid #eee;"><b>${f.numero_factura ? 'Fac. N° ' + f.numero_factura : f.id_pedido}</b>: <span style="float:right; color:#333;">$${Math.round(parseFloat(f.deuda_actual)).toLocaleString('es-CL')}</span></li>`).join('');

            Swal.fire({
                title: '¡Match Múltiple!',
                html: `Este abono cubre ${facturasComb.length} facturas:<br>
                       <ul style="text-align:left; margin-top:10px; list-style:none; padding:0;">${lista}</ul>
                       <div style="text-align:right; font-weight:bold; color:#0c5460; font-size:14px; margin-top:5px; border-top:1px solid #ccc; padding-top:5px;">
                            Deuda Sumada: $${sumaDeuda.toLocaleString('es-CL')}<br>
                            Abono Banco: $${montoBanco.toLocaleString('es-CL')}
                       </div>
                       ${extraHtml}`,
                icon: 'info', showCancelButton: true, showDenyButton: true,
                confirmButtonColor: '#0c5460', denyButtonColor: '#17a2b8',
                confirmButtonText: 'Conciliar todas', denyButtonText: 'Buscar otras'
            }).then((res) => {
                if (res.isConfirmed) {
                    const chk = document.getElementById('chk-perdonar-mult');
                    let aplicar = (chk && chk.checked) ? sumaDeuda : montoBanco;
                    ejecutarPagosDistribuidos(facturasComb, [idMovimiento], aplicar);
                } else if (res.isDenied) abrirModalBuscador(idMovimiento, cleanRut(rutBanco), montoBanco);
            });

        } else if (iaTipo === 'combinado') {
            const factura = window.facturasAgrupadas.find(f => f.clave_agrupacion === btn.getAttribute('data-ia-factura'));
            const idsBancos = JSON.parse(btn.getAttribute('data-ia-bancos'));
            const bancosImplicados = window.abonosPendientes.filter(b => idsBancos.includes(b.id));

            const numDoc = factura.numero_factura ? `N° ${factura.numero_factura}` : `Pedido ${factura.id_pedido}`;
            const sumaBancos = Math.round(bancosImplicados.reduce((acc, b) => acc + parseFloat(b.saldo_disponible), 0));

            let listaBancos = bancosImplicados.map(b => `<li style="padding: 4px 0; border-bottom: 1px solid #eee;">Abono ${b.fecha_movimiento.split(' ')[0]}: <span style="float:right; color:#333;">$${Math.round(parseFloat(b.saldo_disponible)).toLocaleString('es-CL')}</span></li>`).join('');

            Swal.fire({
                title: '¡Match Combinado!',
                html: `Se han detectado <b>${bancosImplicados.length} abonos</b> que juntos cubren la factura <b>${numDoc}</b>:<br>
                       <ul style="text-align:left; margin-top:10px; list-style:none; padding:0;">${listaBancos}</ul>
                       <div style="text-align:right; font-weight:bold; color:#0d6efd; font-size:14px; margin-top:5px; border-top:1px solid #ccc; padding-top:5px;">
                            Deuda Factura: $${Math.round(parseFloat(factura.deuda_actual)).toLocaleString('es-CL')}<br>
                            Suma Abonos: $${sumaBancos.toLocaleString('es-CL')}
                       </div>`,
                icon: 'info', showCancelButton: true, showDenyButton: true,
                confirmButtonColor: '#0d6efd', denyButtonColor: '#17a2b8',
                confirmButtonText: 'Fusionar y Pagar', denyButtonText: 'Buscar otra'
            }).then((res) => {
                if (res.isConfirmed) ejecutarPagosDistribuidos([factura], idsBancos, sumaBancos);
                else if (res.isDenied) abrirModalBuscador(idMovimiento, cleanRut(rutBanco), montoBanco);
            });

        } else {
            abrirModalBuscador(idMovimiento, cleanRut(rutBanco), montoBanco);
        }
    };

    // 🔥 5. MODAL MANUAL (Con Insignias de Pago Parcial) 🔥
    function abrirModalBuscador(idMovimiento, rutBancoLimpio, montoBanco) {
        const fCliente = window.facturasAgrupadas.filter(f => cleanRut(f.rut_cliente) === rutBancoLimpio);
        const fOtros = window.facturasAgrupadas.filter(f => cleanRut(f.rut_cliente) !== rutBancoLimpio);
        let h = '<div style="text-align: left; font-size: 14px;"><h5 style="color:#d93025; border-bottom:1px solid #eee; padding-bottom:5px;">Facturas del mismo RUT:</h5>';
        h += fCliente.length === 0 ? '<p>No hay facturas para este RUT.</p>' : generarListaHtml(fCliente, idMovimiento, montoBanco);
        h += '<h5 style="color:#666; border-bottom:1px solid #eee; padding-bottom:5px; margin-top:20px;">Otras facturas:</h5>';
        h += fOtros.length === 0 ? '<p>No hay más facturas.</p>' : `<div style="max-height: 250px; overflow-y: auto;">${generarListaHtml(fOtros, idMovimiento, montoBanco)}</div>`;
        h += '</div>';
        Swal.fire({ title: 'Buscar Conciliación', html: h, width: '700px', showConfirmButton: false, showCloseButton: true });
    }

    function generarListaHtml(arr, idMov, montoBanco) {
        return arr.map(f => {
            let deuda = Math.round(parseFloat(f.deuda_actual));
            let aplicar = Math.min(montoBanco, deuda);
            let saldoRestante = deuda - aplicar;
            let txt = f.numero_factura ? `Factura N° ${f.numero_factura}` : `Pedido ${f.id_pedido}`;

            // Etiqueta si la factura ya venía pagada por la mitad
            let badgeParcialPrevio = f.estado_pago === 'Parcial' ? `<span style="background:#fff3cd; color:#856404; padding:2px 5px; border-radius:4px; font-size:10px; font-weight:bold; margin-left:5px;"><i class="fa-solid fa-clock"></i> Ya tiene abonos</span>` : '';

            // Etiqueta si al aplicarle esta plata, va a seguir debiendo
            let badgeFaltaPago = (saldoRestante > 100) ? `<br><span style="color:#d35400; font-size:11px; font-weight:bold; display:inline-block; margin-top:4px;"><i class="fa-solid fa-triangle-exclamation"></i> Faltará pagar $${saldoRestante.toLocaleString('es-CL')}</span>` : '';

            return `<div style="display:flex; justify-content:space-between; align-items:center; background:#fafafa; border:1px solid #ddd; padding:10px; border-radius:8px; margin-top:5px;">
                        <div>
                            <b>${f.cliente}</b><br>
                            <span style="color:#0d6efd; font-size:11px; font-weight:bold;">${txt}</span>${badgeParcialPrevio}
                            <span style="color:#d93025; font-size:12px; margin-left:10px; font-weight:bold;">Deuda actual: $${deuda.toLocaleString('es-CL')}</span>
                            ${badgeFaltaPago}
                        </div>
                        <button onclick="confirmarMatchManual('${f.clave_agrupacion}', ${idMov}, ${deuda}, ${montoBanco})" style="background:#28a745; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:bold; font-size:12px;">Aplicar $${aplicar.toLocaleString('es-CL')}</button>
                    </div>`;
        }).join('');
    }

    window.confirmarMatchManual = function (clave, idMov, deudaNum, montoBanco) {
        let aplicar = Math.min(montoBanco, deudaNum);
        let saldoRestante = deudaNum - aplicar;
        const factura = window.facturasAgrupadas.find(f => f.clave_agrupacion === clave);

        let msjExtra = "";
        if (saldoRestante > 100) {
            msjExtra = `<div style="background:#fff3cd; color:#d35400; padding:10px; border-radius:5px; margin-top:10px; font-size:13px; font-weight:bold; border: 1px solid #ffeeba; text-align:left;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Atención: Este pago es parcial.<br>
                            Faltará pagar $${saldoRestante.toLocaleString('es-CL')} para cerrar la factura.
                        </div>`;
        } else if (saldoRestante > 0 && saldoRestante <= 100) {
            msjExtra = `<div style="background:#fff3cd; color:#856404; padding:10px; border-radius:5px; margin-top:10px; font-size:13px; text-align:left;">
                            <b>Atención:</b> Faltan $${saldoRestante} pesos.<br><label><input type="checkbox" id="chk-perdonar-manual" checked> Asumir diferencia y cerrar factura</label>
                        </div>`;
        }

        Swal.fire({ title: '¿Confirmar Conciliación?', html: `Se aplicarán <b>$${aplicar.toLocaleString('es-CL')}</b> a la factura seleccionada.<br>${msjExtra}`, icon: 'question', showCancelButton: true, confirmButtonColor: '#28a745', confirmButtonText: 'Sí, aplicar pago' })
            .then((res) => {
                if (res.isConfirmed) {
                    const chk = document.getElementById('chk-perdonar-manual');
                    let montoFinal = (chk && chk.checked) ? deudaNum : aplicar;
                    ejecutarPagosDistribuidos([factura], [idMov], montoFinal);
                }
            });
    };

    // 🔥 6. EL MOTOR DISTRIBUIDOR UNIVERSAL 🔥
    async function ejecutarPagosDistribuidos(facturasArr, idsBancosArr, montoAAplicarTotal) {
        Swal.fire({ title: 'Sincronizando...', text: 'Distribuyendo pago...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        let errores = 0;
        const esperar = (ms) => new Promise(resolve => setTimeout(resolve, ms));

        let productos = [];
        facturasArr.forEach(f => { productos = productos.concat(f.productos_internos); });

        for (let idBanco of idsBancosArr) {
            let bancoObj = window.abonosPendientes.find(b => b.id == idBanco);
            let saldoBanco = Math.round(parseFloat(bancoObj.saldo_disponible));

            for (let prod of productos) {
                if (saldoBanco <= 0) break;
                let deudaProd = Math.round(parseFloat(prod.deuda_actual));
                if (deudaProd <= 0) continue;

                let aplicar = Math.min(deudaProd, saldoBanco);

                const fd = new FormData();
                fd.append('action', 'conciliar_pago_tabolango');
                fd.append('id_movimiento', idBanco);
                fd.append('id_pedido', prod.id_interno);
                fd.append('monto_aplicado', aplicar);

                try {
                    const res = await fetch(wpData.siteUrl + '/wp-admin/admin-ajax.php', { method: 'POST', body: fd });
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            saldoBanco -= aplicar;
                            prod.deuda_actual -= aplicar;
                        } else errores++;
                    } catch (e) { errores++; }
                } catch (e) { errores++; }

                await esperar(300);
            }
        }

        if (errores === 0) Swal.fire({ title: '¡Exitoso!', icon: 'success', timer: 1500, showConfirmButton: false }).then(() => location.reload());
        else Swal.fire('Atención', `Se aplicó el pago, pero hubo ${errores} advertencias. Revisa el historial.`, 'warning').then(() => location.reload());
    }

    // --- Controles de Menú e Historial ---
    const btnSync = document.getElementById('btn-sync-fintoc');
    if (btnSync) btnSync.addEventListener('click', function () {
        Swal.fire({ title: 'Conectando con Fintoc...', didOpen: () => Swal.showLoading() });
        const fd = new FormData();
        fd.append('action', 'sincronizar_fintoc');
        fetch(wpData.siteUrl + '/wp-admin/admin-ajax.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
            if (data.status === 'success') {
                if (data.nuevos > 0) Swal.fire('¡Actualizado!', `Se descargaron ${data.nuevos} abonos.`, 'success').then(() => location.reload());
                else Swal.fire('Al Día', 'No hay transferencias nuevas.', 'info');
            } else Swal.fire('Error', data.message, 'error');
        });
    });

    window.descartarMovimiento = function (idMov, event) {
        event.stopPropagation();
        Swal.fire({ title: '¿Descartar Ingreso?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Sí, descartar' })
            .then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Procesando...', didOpen: () => Swal.showLoading() });
                    const fd = new FormData();
                    fd.append('action', 'descartar_movimiento_tabolango');
                    fd.append('id_movimiento', idMov);
                    fetch(wpData.siteUrl + '/wp-admin/admin-ajax.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
                        if (data.status === 'success') location.reload();
                    });
                }
            });
    };

    window.deshacerConciliacion = function (idsConciliaciones) {
        Swal.fire({ title: '¿Deshacer Conciliación?', text: 'Se reversará el pago de todos los productos de esta factura.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', confirmButtonText: 'Sí, reversar' })
            .then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({ title: 'Reversando...', didOpen: () => Swal.showLoading() });
                    const fd = new FormData();
                    fd.append('action', 'deshacer_conciliacion_tabolango');
                    fd.append('ids_conciliaciones', idsConciliaciones);
                    fetch(wpData.siteUrl + '/wp-admin/admin-ajax.php', { method: 'POST', body: fd }).then(res => res.json()).then(data => {
                        if (data.status === 'success') location.reload();
                        else Swal.fire('Error', data.message, 'error');
                    });
                }
            });
    };

    window.verDocumento = function (url) { if (url) window.open(url, '_blank'); };

    // 🔥 8. VER COMPAÑEROS DE PAGO MÚLTIPLE (EL OJO) 🔥
    window.verCompanerosDePago = function (idMovimiento) {
        if (!window.historialConciliado) return;

        const companeros = window.historialConciliado.filter(h => h.id_movimiento == idMovimiento);

        if (companeros.length <= 1) {
            Swal.fire({
                title: 'Abono Único',
                text: 'Esta transferencia bancaria se usó únicamente para pagar esta factura. No tiene compañeros compartidos.',
                icon: 'info',
                confirmButtonColor: '#0d6efd'
            });
            return;
        }

        let sumaUsada = 0;
        const montoBancoOriginal = parseFloat(companeros[0].monto_original_banco) || 0;

        const fechaRaw = companeros[0].fecha_movimiento;
        let fechaLimpia = "desconocida";
        if (fechaRaw) {
            const partesFecha = fechaRaw.split(' ')[0].split('-');
            if (partesFecha.length === 3) fechaLimpia = `${partesFecha[2]}/${partesFecha[1]}/${partesFecha[0]}`;
        }

        let listaHtml = companeros.map(c => {
            let apl = parseFloat(c.monto_total_aplicado);
            sumaUsada += apl;
            const numDoc = c.numero_factura ? `Fac. N° ${c.numero_factura}` : c.id_pedido;

            // Etiqueta inteligente de estado
            const saldoFaltante = Math.round(parseFloat(c.total_factura_real) - parseFloat(c.pagado_factura_real));
            let badgeEstado = (saldoFaltante > 0)
                ? `<br><span style="color:#d35400; font-size:11px; background:#fff3cd; padding:2px 4px; border-radius:4px;"><b>Parcial</b> (Falta $${saldoFaltante.toLocaleString('es-CL')})</span>`
                : `<br><span style="color:#155724; font-size:11px; background:#d4edda; padding:2px 4px; border-radius:4px;">Pagada 100%</span>`;

            return `<li style="padding: 10px 0; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:14px; text-align: left;">
                    <b>${numDoc}</b> <small style="color:#666;">- ${c.cliente}</small>
                    ${badgeEstado}
                </span>
                <span style="color:#28a745; font-weight:bold; font-size:15px;">$${apl.toLocaleString('es-CL')}</span>
            </li>`;
        }).join('');

        Swal.fire({
            title: 'Detalle del Pago Múltiple',
            html: `Del abono de <b>$${montoBancoOriginal.toLocaleString('es-CL')}</b> del <b>${fechaLimpia}</b>, se pagó:<br><ul style="text-align:left; margin-top:15px; list-style:none; padding:0;">${listaHtml}</ul><div style="text-align:right; margin-top:10px; font-size:13px; color:#666; border-top:1px solid #ccc; padding-top:5px;"><b>Total utilizado:</b> $${sumaUsada.toLocaleString('es-CL')}</div>`,
            icon: 'info', confirmButtonColor: '#0d6efd', confirmButtonText: 'Cerrar'
        });
    };
});