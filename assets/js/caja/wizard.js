// assets/js/caja/wizard.js
import { BASE, DENOMS, MXN, api } from './config.js';
import { $, GET, GET_SOFT, POST_FORM, toast } from './helpers.js';
import { els, state } from './state.js';

/* =============== STEP / UI =============== */
export function setStep(n){
  state.step = n;
  if (!els.modal) return;

  if (els.step1) els.step1.classList.toggle('d-none', n!==1);
  if (els.step2) els.step2.classList.toggle('d-none', n!==2);
  if (els.step3) els.step3.classList.toggle('d-none', n!==3);

  if (els.stepBar){
    const pct = n===1?33:(n===2?66:100);
    els.stepBar.style.width = pct+'%';
    els.stepBar.setAttribute('aria-valuenow', String(pct));
  }

  // visibilidad por paso
  if (els.btnGuardarPrecorte) els.btnGuardarPrecorte.classList.toggle('d-none', n!==1);
  if (els.btnContinuarConc)   els.btnContinuarConc.classList.toggle('d-none', n!==1);
  if (els.btnIrPostcorte)     els.btnIrPostcorte.classList.toggle('d-none', n!==2);
  if (els.btnCerrarSesion)    els.btnCerrarSesion.classList.toggle('d-none', n!==3);
  if (els.btnSincronizarPOS)  els.btnSincronizarPOS.classList.toggle('d-none', n!==2);

  // seguridad: no permitir continuar si no se ha guardado
  if (els.btnContinuarConc) els.btnContinuarConc.disabled = !state.pasoGuardado;
}

function ensureModalRefs(){
  const modal = document.querySelector('#czModalPrecorte, #modalPrecorte, #wizardPrecorte, .modal[data-role="precorte"]');
  els.modal = modal || null;
  if (!els.modal) return false;

  // steps + barra
  els.step1  = els.modal.querySelector('#czStep1,#step1,[data-step="1"]');
  els.step2  = els.modal.querySelector('#czStep2,#step2,[data-step="2"]');
  els.step3  = els.modal.querySelector('#czStep3,#step3,[data-step="3"]');
  els.stepBar= els.modal.querySelector('#czStepBar,.progress-bar,[data-role="stepbar"]');

  // botones (incluye cz*)
  els.btnGuardarPrecorte = els.modal.querySelector('#czBtnGuardarPrecorte,#btnGuardarPrecorte,[data-action="guardar-precorte"]');
  els.btnContinuarConc   = els.modal.querySelector('#czBtnContinuarConciliacion,#btnContinuarConc,[data-action="continuar-conc"]');
  els.btnSincronizarPOS  = els.modal.querySelector('#czBtnSincronizarPOS,#btnSincronizarPOS,[data-action="sincronizar-pos"]');
  els.btnIrPostcorte     = els.modal.querySelector('#czBtnIrPostcorte,#btnIrPostcorte,[data-action="ir-postcorte"]');
  els.btnCerrarSesion    = els.modal.querySelector('#czBtnCerrarSesion,#btnCerrarSesion,[data-action="cerrar-sesion"]');
  els.btnAutorizar       = els.modal.querySelector('[data-action="autorizar-corte"]');

  // campos paso 1
  els.tablaDenomsBody = els.modal.querySelector('#czTablaDenoms tbody,#tablaDenomsBody,[data-role="denoms-body"]');
  els.precorteTotal   = els.modal.querySelector('#czPrecorteTotal,#precorteTotal,[data-role="precorte-total"]');
  els.declCredito     = els.modal.querySelector('#czDeclCardCredito,#declCredito,[data-role="decl-credito"]');
  els.declDebito      = els.modal.querySelector('#czDeclCardDebito,#declDebito,[data-role="decl-debito"]');
  els.declTransfer    = els.modal.querySelector('#czDeclTransfer,#declTransfer,[data-role="decl-transfer"]');
  els.notasPaso1      = els.modal.querySelector('#czNotes,#notasPaso1,[data-role="notas-paso1"]');

  // paso 2
  els.chipFondo       = els.modal.querySelector('#czChipFondo,[data-role="chip-fondo"]');
  els.efEsperadoInfo  = els.modal.querySelector('#czEfectivoEsperado,[data-role="ef-esperado"]');
  els.concGrid        = els.modal.querySelector('#czConciliacionGrid,#concGrid,[data-role="conc-grid"]');
  els.bannerFaltaCorte= els.modal.querySelector('#czBannerFaltaCorte,[data-role="banner-falta-corte"]');

  // hidden
  els.inputPrecorteId = document.querySelector('#cz_precorte_id,#precorteId,[data-role="precorte-id"]');
  return true;
}

/* === ÚNICO handler para “Ir a Postcorte” (sin duplicados) === */
function bindIrPostcorteUnique(){
  if (!els || !els.btnIrPostcorte) return;

  const old = els.btnIrPostcorte;
  const clone = old.cloneNode(true);
  old.replaceWith(clone);
  els.btnIrPostcorte = clone;

  els.btnIrPostcorte.disabled = false;

  els.btnIrPostcorte.addEventListener('click', async (e)=>{
    e.preventDefault(); e.stopPropagation();
    if (els.btnIrPostcorte.__busy) return;
    els.btnIrPostcorte.__busy = true;

    try {
      if (state.necesitaAut && !state.autorizado){
        const ok = await confirmElegante(
          'Autorización requerida',
          '<p>Existe diferencia mayor al umbral. ¿Autorizar y continuar?</p>',
          'Cancelar','Autorizar'
        );
        if (!ok) return;

        try {
          await POST_FORM(`${BASE}/api/caja/precorte_status.php?id=${state.precorteId}`, {
            autorizado: 1, autorizado_por: 'admin'
          });
        } catch(_) { /* backend legacy: continuar */ }

        state.autorizado = true;
      }

      await irAPostcorte();   // 👈 importante
    } finally {
      els.btnIrPostcorte.__busy = false; // 👈 siempre se libera
    }
  });
}


/* =============== DENOMS (Paso 1) =============== */
export function bindDenoms(){
  if (!els.tablaDenomsBody) return;
  els.tablaDenomsBody.innerHTML = '';
  state.denoms.clear();
  DENOMS.forEach(den=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>$${den}</td>
      <td><input type="number" min="0" step="1" class="form-control form-control-sm cz-qty" data-denom="${den}" value="" inputmode="numeric" placeholder="0"></td>
      <td class="text-end cz-amt" data-denom="${den}">${MXN.format(0)}</td>`;
    els.tablaDenomsBody.appendChild(tr);
  });
  els.tablaDenomsBody.querySelectorAll('.cz-qty').forEach(inp=>{
    inp.addEventListener('input',()=>{
      const denom = Number(inp.dataset.denom);
      const qty   = Math.max(0, parseInt(inp.value||'0',10) || 0);
      state.denoms.set(denom, qty);
      const amt = denom * qty;
      const cell= els.tablaDenomsBody.querySelector(`.cz-amt[data-denom="${denom}"]`);
      if (cell) cell.textContent = MXN.format(amt);
      recalcPaso1();
    });
  });
}

export function recalcPaso1(){
  let totalEf = 0;
  state.denoms.forEach((qty,den)=> totalEf += den*qty);
  if (els.precorteTotal) els.precorteTotal.textContent = MXN.format(totalEf);

  // no-efectivo: el usuario debe tocar los 3 campos (>=0)
  const camposNE = [els.declCredito, els.declDebito, els.declTransfer];
  const filledNE = camposNE.every(el=>{
    if (!el) return false;
    const touched = el.dataset.touched === '1';
    const raw = String(el.value ?? '').trim();
    const num = Number(raw);
    return touched && raw !== '' && !Number.isNaN(num) && num >= 0;
  });

  const okDenoms = totalEf > 0;
  if (els.btnGuardarPrecorte) els.btnGuardarPrecorte.disabled = !(okDenoms && filledNE);
  if (els.btnContinuarConc)   els.btnContinuarConc.disabled   = !state.pasoGuardado;

  if (els.chipFondo)      els.chipFondo.textContent      = MXN.format(state.sesion.opening||0);
  if (els.efEsperadoInfo) els.efEsperadoInfo.textContent = MXN.format(state.sesion.opening||0);
}

/* =============== ABRIR WIZARD =============== */
export async function abrirWizard(ev){
  ev?.preventDefault?.();

  const btn = (
    ev?.currentTarget?.closest?.('[data-caja-action="wizard"]') ||
    ev?.target?.closest?.('[data-caja-action="wizard"]')
  );
  if (!btn){ toast('No se pudo resolver el botón del wizard','err', 8000, 'UI'); return; }
  if (btn.__busy) return; btn.__busy = true;

  const d = (k)=> (btn.dataset && k in btn.dataset) ? btn.dataset[k] : btn.getAttribute?.(`data-${k}`);
  const store    = parseInt(d('store')||'0',10);
  const terminal = parseInt(d('terminal')||'0',10);
  const user     = parseInt(d('user')||'0',10);
  const bdate    = String(d('bdate')||'').trim();
  const opening  = Number(d('opening')||0);
  const sesion   = parseInt(d('sesion')||'0',10);

  state.sesion = { store, terminal, user, bdate, opening };
  state.precorteId = null;
  state.pasoGuardado = false;
  state.necesitaAut = false; state.autorizado = false;

  if (!store || !terminal || !user || !bdate){
    toast('Faltan store/terminal/usuario','err', 12000, 'Datos incompletos', {sticky:true});
    btn.__busy = false; return;
  }

  try{
    // 0) Preflight
    if (sesion){
      const pre = await GET(`${BASE}/api/sprecorte/preflight?sesion_id=${encodeURIComponent(sesion)}`);
      if (pre?.bloqueo){
        const n = pre.tickets || pre.tickets_abiertos || 0;
        mostrarAvisoElegante('Corte bloqueado',`Hay <b>${n}</b> ticket(s) abiertos. Cierra/cancela y vuelve a intentar.`,
          ()=> abrirWizard({ currentTarget: btn }));
        btn.__busy = false; return;
      }
    }

    // 1) Crear/recuperar precorte
    const payload = { bdate, store_id:store, terminal_id:terminal, user_id:user, sesion_id: sesion || '' };
    const j = await POST_FORM(api.precorte_create(), payload);
    if (!j?.ok || !j?.precorte_id){
      toast('No se pudo iniciar/recuperar precorte','err',12000,'Error',{sticky:true});
      btn.__busy=false; return;
    }
    state.precorteId = j.precorte_id;
    els.inputPrecorteId && (els.inputPrecorteId.value = String(state.precorteId));

    // 2) UI
    if (!ensureModalRefs()){
      fallbackModal(`Precorte #${state.precorteId} listo, pero no se encontró el modal real. Incluye <code>_wizard_modals.php</code>.`);
      btn.__busy=false; return;
    }
    wireDelegates();
    bindModalButtons();

    // 3) Decidir paso por estatus (ENVIADO ⇒ Paso 2)
    let est = (j?.estatus || '').toUpperCase();
    try{
      const st = await GET_SOFT(`${BASE}/api/caja/precorte_status.php?id=${state.precorteId}`);
      if (st?.estatus) est = String(st.estatus).toUpperCase();
    }catch(_){}

    const abrirPaso2 = (est === 'ENVIADO');
    if (abrirPaso2){
      setStep(2);
      try { window.bootstrap?.Modal.getOrCreateInstance(els.modal,{backdrop:'static',keyboard:false}).show(); } catch(_){}
      await sincronizarPOS(true);        // puede mostrar banner si falta DPR
      bindIrPostcorteUnique();           // único handler
      btn.__busy = false; return;
    }

    // Paso 1
    [els.declCredito, els.declDebito, els.declTransfer].forEach(el=>{
      if (!el) return;
      el.addEventListener('input', ()=>{ el.dataset.touched='1'; recalcPaso1(); });
      el.addEventListener('blur',  ()=>{ el.dataset.touched='1'; recalcPaso1(); });
      el.placeholder = '0';
    });
    bindDenoms();
    recalcPaso1();
    setStep(1);
    try { window.bootstrap?.Modal.getOrCreateInstance(els.modal,{backdrop:'static',keyboard:false}).show(); } catch(_){}
    bindIrPostcorteUnique();             // por si el usuario llega a Paso 2 después

  } catch(e){
    if (e?.status === 409 && e?.payload?.tickets_abiertos != null){
      const n = e.payload.tickets_abiertos;
      mostrarAvisoElegante('Corte bloqueado',`Hay <b>${n}</b> ticket(s) abiertos. Cierra/cancela y vuelve a intentar.`,
        ()=> abrirWizard({ currentTarget: btn }));
    } else {
      toast(`Error iniciando precorte: ${e.message}`, 'err', 15000, 'Error', {sticky:true});
    }
  } finally {
    btn.__busy = false;
  }
}

/* =============== GUARDAR (Paso 1) =============== */
async function guardarPrecorte(){
  if (!state.precorteId){ toast('No hay precorte activo','err',9000,'Error'); return; }

  // Montos capturados
  let totalEf = 0; const denoms = [];
  state.denoms.forEach((qty,den)=>{ if (qty>0){ denoms.push({den,qty}); totalEf += den*qty; }});
  const credito  = Number(els.declCredito?.value);
  const debito   = Number(els.declDebito?.value);
  const transfer = Number(els.declTransfer?.value);
  const notas    = String(els.notasPaso1?.value||'').trim();
  const esperado = Number(state.sesion.opening||0);
  const totalNoEf = (credito||0)+(debito||0)+(transfer||0);
  const totalDecl = totalEf + totalNoEf;

  const html = `
    <div class="mb-2">Se guardará el precorte <b>#${state.precorteId}</b> con:</div>
    <table class="table table-sm align-middle mb-2">
      <tbody>
        <tr><td>Efectivo</td><td class="text-end fw-semibold">${MXN.format(totalEf)}</td></tr>
        <tr><td>Tarjeta crédito</td><td class="text-end">${MXN.format(credito)}</td></tr>
        <tr><td>Tarjeta débito</td><td class="text-end">${MXN.format(debito)}</td></tr>
        <tr><td>Transferencias</td><td class="text-end">${MXN.format(transfer)}</td></tr>
        <tr class="table-light">
          <th>Total declarado</th><th class="text-end">${MXN.format(totalDecl)}</th>
        </tr>
      </tbody>
    </table>
    ${notas ? `<div class="small text-muted">Notas: ${notas}</div>` : '' }
  `;
  const ok = await confirmElegante('Confirmar guardado', html, 'Cancelar', 'Guardar');
  if (!ok) return;

  try{
    // Persistir (precorte_efectivo + precorte_otros)
    const payload = {
      denoms_json: JSON.stringify(denoms),
      declarado_credito:  credito,
      declarado_debito:   debito,
      declarado_transfer: transfer,
      notas
    };
    const j = await POST_FORM(api.precorte_update(state.precorteId), payload);
    if (!j?.ok){ toast('No se pudo guardar precorte','err', 9000, 'Error'); return; }

    // Estatus: sesión → LISTO_PARA_CORTE, precorte → ENVIADO (tolerante a 500)
    try {
      await POST_FORM(`${BASE}/api/caja/precorte_status.php?id=${state.precorteId}`, {
        sesion_estatus:'LISTO_PARA_CORTE', precorte_estatus:'ENVIADO'
      });
    } catch(_){}

    state.pasoGuardado = true;
    els.btnContinuarConc && (els.btnContinuarConc.disabled = false);
    toast('Precorte guardado','ok',6000,'Listo');

    setStep(2);
    await sincronizarPOS(true);
    bindIrPostcorteUnique();

  }catch(e){
    toast(`Error guardando precorte: ${e.message}`,'err',9000,'Error');
  }
}

/* =============== Paso 2: Conciliación =============== */
export async function sincronizarPOS(auto=false){
  if (!state.precorteId){ toast('No hay precorte activo','err', 9000, 'Error'); return; }
  els.bannerFaltaCorte?.classList.add('d-none');
  if (els.concGrid) els.concGrid.innerHTML = '<div class="text-muted small">Sincronizando con POS…</div>';

  // usa GET_SOFT para que un 412 no truene
  const j = await GET_SOFT(api.precorte_totales(state.precorteId));

  if (!j?.ok){
    // 412 suave: mostrar banner
    els.bannerFaltaCorte?.classList.remove('d-none');
    if (!auto){
      mostrarAvisoElegante(
        'Falta realizar el corte en POS',
        'Aún no hay Drawer Pull Report en Floreant POS. Realiza el corte y pulsa <b>Sincronizar</b>.',
        ()=> sincronizarPOS(false)
      );
    } else {
      if (els.concGrid) els.concGrid.innerHTML = '<div class="small text-muted">Esperando corte en POS…</div>';
    }
    return;
  }

  // OK con DPR ⇒ pintar conciliación
  const d = j.data || {};
  renderConciliacion(d, j);
  await evaluarAutorizacion(d);
  bindIrPostcorteUnique();
}

function badgeVeredicto(diff){
  const ad = Math.abs(Number(diff||0));
  if (ad === 0) return '<span class="badge bg-success">CUADRA</span>';
  if (ad <= 10) return '<span class="badge bg-warning text-dark">±10</span>';
  return '<span class="badge bg-danger">DIF</span>';
}
function rowConc(name, declarado, sistema){
  const diff = Number(declarado||0) - Number(sistema||0);
  return `
    <tr>
      <td>${name}</td>
      <td class="text-end">${MXN.format(Number(declarado||0))}</td>
      <td class="text-end">${MXN.format(Number(sistema||0))}</td>
      <td class="text-end">${MXN.format(diff)}</td>
      <td class="text-center">${badgeVeredicto(diff)}</td>
    </tr>`;
}
export function renderConciliacion(d, raw){
  if (!els.concGrid) return;
  const efectivo_decl = Number(d?.efectivo?.declarado||0);
  const efectivo_sys  = Number(d?.efectivo?.sistema  ||0);
  const credito_decl  = Number(d?.tarjeta_credito?.declarado||0);
  const credito_sys   = Number(d?.tarjeta_credito?.sistema  ||0);
  const debito_decl   = Number(d?.tarjeta_debito?.declarado ||0);
  const debito_sys    = Number(d?.tarjeta_debito?.sistema   ||0);
  const transf_decl   = Number(d?.transferencias?.declarado ||0);
  const transf_sys    = Number(d?.transferencias?.sistema   ||0);

  const html = `
    <table class="table table-sm align-middle mb-2">
      <thead><tr>
        <th>Categoría</th><th class="text-end">Declarado</th><th class="text-end">Sistema</th><th class="text-end">Diferencia</th><th class="text-center">Estado</th>
      </tr></thead>
      <tbody>
        ${rowConc('Efectivo', efectivo_decl, efectivo_sys)}
        ${rowConc('Tarjeta Crédito', credito_decl, credito_sys)}
        ${rowConc('Tarjeta Débito',  debito_decl,  debito_sys)}
        ${rowConc('Transferencias',  transf_decl,  transf_sys)}
      </tbody>
    </table>
    <div class="small text-muted">Fondo de caja (opening_float): ${MXN.format(Number(raw?.opening_float||state.sesion.opening||0))}</div>
  `;
  els.concGrid.innerHTML = html;
}

function diffsMayores10(d){
  const par = [
    ['efectivo', d?.efectivo],
    ['tarjeta_credito', d?.tarjeta_credito],
    ['tarjeta_debito',  d?.tarjeta_debito],
    ['transferencias',  d?.transferencias]
  ];
  return par.some(([_,v]) => Math.abs(Number(v?.declarado||0) - Number(v?.sistema||0)) > 10);
}
async function evaluarAutorizacion(d){
  const requiere = diffsMayores10(d);
  state.necesitaAut = !!requiere;

  // notifica pero NO bloquees si falla
  try{
    await POST_FORM(`${BASE}/api/caja/precorte_status.php?id=${state.precorteId}`, {
      requiere_autorizacion: requiere ? 1 : 0
    });
  }catch(_){}

  // habilitar botón (handler único ya hace el gateo)
  if (els.btnIrPostcorte) els.btnIrPostcorte.disabled = false;
}

/* =============== Bind botones reales =============== */
export function bindModalButtons(){
  if (!els.modal) return;
  if (els.modal.dataset.bound === '1') return;
  els.modal.dataset.bound = '1';

  const stop = (fn)=> (ev)=>{ ev.preventDefault(); ev.stopPropagation(); fn(); };

  if (els.btnGuardarPrecorte){
    els.btnGuardarPrecorte.addEventListener('click', stop(guardarPrecorte));
    els.btnGuardarPrecorte.disabled = true;
  }

  if (els.btnContinuarConc){
    els.btnContinuarConc.addEventListener('click', (ev)=>{
      ev.preventDefault(); ev.stopPropagation();
      if (!state.pasoGuardado){
        toast('Primero guarda el precorte.','warn', 5000, 'Validación');
        return;
      }
      setStep(2);
    });
    els.btnContinuarConc.disabled = true;
  }

  if (els.btnSincronizarPOS){
    els.btnSincronizarPOS.addEventListener('click', stop(()=> sincronizarPOS(false)));
  }

  // NO bindeamos aquí btnIrPostcorte (se hace con bindIrPostcorteUnique)

  if (els.btnAutorizar){
    els.btnAutorizar.addEventListener('click', async (ev)=>{
      ev.preventDefault(); ev.stopPropagation();
      const nota = prompt('Nota de autorización (requerida):','');
      if (!nota || !nota.trim()){ toast('La nota es obligatoria','warn',7000,'Validación'); return; }
      try{
        await POST_FORM(`${BASE}/api/caja/precorte_status.php?id=${state.precorteId}`, {
          autorizado_por:'admin', nota: nota.trim(), autorizado: 1
        });
        state.autorizado = true;
        toast('Corte autorizado','ok',6000,'Listo');
        els.btnIrPostcorte && (els.btnIrPostcorte.disabled = false);
      }catch(e){
        toast(`No se pudo autorizar: ${e.message}`,'err',9000,'Error');
      }
    });
  }

  if (els.btnCerrarSesion){
    els.btnCerrarSesion.addEventListener('click', stop(()=>{
      try { bootstrap.Modal.getInstance(els.modal)?.hide(); } catch(_){}
      toast('Sesión cerrada.', 'ok', 6000, 'Listo');
      import('./mainTable.js').then(m => m.cargarTabla().catch(()=>{}));
    }));
  }
}

/* =============== Paso 3: Postcorte =============== */
async function irAPostcorte(){
  if (!state.precorteId){ toast('No hay precorte activo','err',9000,'Error'); return; }

  try{
    const j = await POST_FORM(`${BASE}/api/postcortes`, { precorte_id: state.precorteId });
    if (!j?.ok) throw new Error(j?.error || 'No se pudo generar el Post-corte');
    setStep(3);
    toast(`Post-corte #${j.postcorte_id} generado`, 'ok', 6000, 'Listo');
    els.btnCerrarSesion?.focus?.();
  }catch(e){
    toast(`Error generando Post-corte: ${e.message}`,'err',9000,'Error');
  }
}

/* =============== Fallback delegado (si no hay botones reales) =============== */
function wireDelegates(){
  if (!els.modal) return;
  if (els.__wired) return; els.__wired = true;

  const hasBtns = !!(els.btnGuardarPrecorte || els.btnContinuarConc || els.btnSincronizarPOS || els.btnIrPostcorte);
  if (hasBtns) return;

  els.modal.querySelectorAll(
    '#czBtnGuardarPrecorte,#btnGuardarPrecorte,[data-action="guardar-precorte"],' +
    '#czBtnContinuarConciliacion,#btnContinuarConc,[data-action="continuar-conc"],' +
    '#czBtnSincronizarPOS,#btnSincronizarPOS,[data-action="sincronizar-pos"],' +
    '#czBtnIrPostcorte,#btnIrPostcorte,[data-action="ir-postcorte"]'
  ).forEach(b=> b.setAttribute('type','button'));

  els.modal.addEventListener('click', (e)=>{
    const save = e.target.closest('#czBtnGuardarPrecorte,#btnGuardarPrecorte,[data-action="guardar-precorte"]');
    if (save){ e.preventDefault(); guardarPrecorte(); return; }

    const cont = e.target.closest('#czBtnContinuarConciliacion,#btnContinuarConc,[data-action="continuar-conc"]');
    if (cont){
      e.preventDefault();
      if (!state.pasoGuardado){ toast('Primero guarda el precorte.','warn',5000,'Validación'); return; }
      setStep(2); return;
    }

    const sync = e.target.closest('#czBtnSincronizarPOS,#btnSincronizarPOS,[data-action="sincronizar-pos"]');
    if (sync){ e.preventDefault(); sincronizarPOS(false); return; }

    const go3  = e.target.closest('#czBtnIrPostcorte,#btnIrPostcorte,[data-action="ir-postcorte"]');
    if (go3){
      e.preventDefault();
      if (state.necesitaAut && !state.autorizado){ toast('Requiere autorización previa','warn',7000,'Validación'); return; }
      irAPostcorte(); return;
    }
  });
}

/* =============== Diálogos bonitos =============== */
function mostrarAvisoElegante(titulo, htmlCuerpo, onRetry){
  if (window.bootstrap?.Modal) {
    const el = document.createElement('div');
    el.className = 'modal fade'; el.tabIndex = -1;
    el.innerHTML = `
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">${titulo}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>
          <div class="modal-body"><p class="mb-0">${htmlCuerpo}</p></div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            <button type="button" class="btn btn-primary" data-role="retry">Reintentar</button>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);
    const m = new bootstrap.Modal(el, {backdrop:'static',keyboard:true});
    el.addEventListener('hidden.bs.modal', ()=> el.remove(), {once:true});
    el.querySelector('[data-role="retry"]').onclick = ()=>{ m.hide(); onRetry && onRetry(); };
    m.show();
    return;
  }
  const ov = document.createElement('div');
  ov.style.cssText='position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;padding:16px';
  ov.innerHTML = `
    <div style="background:#fff;max-width:520px;width:100%;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.25);overflow:hidden">
      <div style="padding:12px 16px;border-bottom:1px solid #eee;display:flex;gap:8px;align-items:center">
        <strong style="font-size:16px">${titulo}</strong>
        <span style="margin-left:auto;cursor:pointer;font-weight:bold" data-x>×</span>
      </div>
      <div style="padding:16px;font-size:14px">${htmlCuerpo}</div>
      <div style="padding:12px 16px;display:flex;gap:8px;justify-content:flex-end;background:#fafafa;border-top:1px solid #eee">
        <button type="button" data-x style="padding:6px 12px;border:1px solid #ddd;background:#fff;border-radius:8px;cursor:pointer">Cerrar</button>
        <button type="button" data-retry style="padding:6px 12px;border:0;background:#0d6efd;color:#fff;border-radius:8px;cursor:pointer">Reintentar</button>
      </div>
    </div>`;
  document.body.appendChild(ov);
  const close = ()=> ov.remove();
  ov.querySelector('[data-x]').onclick = close;
  ov.querySelector('[data-retry]').onclick = ()=>{ close(); onRetry && onRetry(); };
}

function confirmElegante(titulo, htmlCuerpo, txtCancel='Cancelar', txtOk='Aceptar'){
  return new Promise((resolve)=>{
    if (window.bootstrap?.Modal){
      const el = document.createElement('div');
      el.className = 'modal fade'; el.tabIndex=-1;
      el.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header" style="background: #f8f8f8; padding: 5px 18px;">
              <h5 class="modal-title" style="font-weight: bolder;">${titulo}</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">${htmlCuerpo}</div>
            <div class="modal-footer">
              <button class="btn btn-outline-secondary" data-role="cancel" type="button">${txtCancel}</button>
              <button class="btn btn-primary" data-role="ok" type="button">${txtOk}</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(el);
      const m = new bootstrap.Modal(el, {backdrop:'static', keyboard:true});
      const btnOk = el.querySelector('[data-role="ok"]');
      const btnCa = el.querySelector('[data-role="cancel"]');

      const setBusy = (v)=> {
        btnOk.disabled = v; btnCa.disabled = v;
        btnOk.innerHTML = v ? 'Guardando…' : txtOk;
      };
      const done = (v)=>{ try{ m.hide(); }catch(_){} el.addEventListener('hidden.bs.modal', ()=> el.remove(), {once:true}); resolve(v); };

      btnCa.onclick = ()=> done(false);
      btnOk.onclick = ()=> { setBusy(true); done(true); };
      el.addEventListener('keydown', (ev)=>{
        if (ev.key === 'Escape') { ev.preventDefault(); btnCa.click(); }
        if (ev.key === 'Enter')  { ev.preventDefault(); btnOk.click(); }
      });

      m.show();
      return;
    }
    resolve( confirm(`${titulo}\n\n${htmlCuerpo.replace(/<[^>]*>/g,'')}`) );
  });
}

/* =============== Fallback modal (debug) =============== */
function fallbackModal(html){
  let ov = document.getElementById('__debug_fallback_modal');
  if (!ov){
    ov = document.createElement('div');
    ov.id='__debug_fallback_modal';
    ov.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99999;display:flex;align-items:center;justify-content:center';
    ov.innerHTML = `
      <div style="background:#fff;max-width:720px;width:92%;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.25);overflow:hidden">
        <div style="padding:10px 14px;border-bottom:1px solid #eee;display:flex;align-items:center;gap:8px">
          <strong>Precorte</strong>
          <span style="margin-left:auto;cursor:pointer;font-weight:bold" id="__debug_fallback_close">×</span>
        </div>
        <div id="__debug_fallback_body" style="padding:14px;max-height:70vh;overflow:auto"></div>
      </div>`;
    document.body.appendChild(ov);
    ov.querySelector('#__debug_fallback_close').onclick=()=>ov.remove();
  }
  ov.querySelector('#__debug_fallback_body').innerHTML = html;
  ov.style.display='flex';
}

// acceso global (como lo usas en la tabla)
if (!window.abrirWizard) window.abrirWizard = abrirWizard;
