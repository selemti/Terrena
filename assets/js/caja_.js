/* assets/js/caja.js — Administración de Cajas
 * Compatible con Views/caja/cortes.php + API:
 *   - GET  /api/caja/cajas.php?date=YYYY-MM-DD          (listado)
 *   - POST /api/caja/precorte_create.php                 (inicia o reusa precorte)
 *   - POST /api/caja/precorte_update.php?id=:precorte_id (denoms + no-efectivo)
 *   - GET  /api/caja/precorte_totales.php?id=:precorte_id(vista de conciliación)
 *
 * Esquema de BD: selemti.* (ver Selemti_corte.sql). No se cambian clases/estilos del HTML.
 * Incluye logs de depuración para ver parámetros enviados/recibidos.
 */
(function(){
  'use strict';

  // =========================
  // Config
  // =========================
  const BASE = (window.__BASE__ || '').replace(/\/+$/,''); // ej: /terrena/Terrena
  const API  = `${BASE}/api/caja`;
  const api  = {
    cajas:            (qs='') => `${API}/cajas.php${qs?`?${qs}`:''}`,
    precorte_create:  ()      => `${API}/precorte_create.php`,
    precorte_update:  (id)    => `${API}/precorte_update.php?id=${encodeURIComponent(id)}`,
    precorte_totales: (id)    => `${API}/precorte_totales.php?id=${encodeURIComponent(id)}`
  };

  // feature-flag para pruebas locales (si quieres ver payloads en consola)
  const DEBUG = true;

  // Denominaciones MXN (puedes ajustar si manejan centavos adicionales)
  const DENOMS = [1000,500,200,100,50,20,10,5,2,1,0.5];
  const MXN    = new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'});

  // =========================
  // Helpers
  // =========================
  const $  = (s, r) => (r||document).querySelector(s);
  const esc= (x) => String(x).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  function log(...args){ if (DEBUG) console.log('[caja.js]', ...args); }
  function err(...args){ console.error('[caja.js]', ...args); }

  async function GET(url){
    log('GET', url);
    const r = await fetch(url,{credentials:'same-origin'});
    const text = await r.text();
    const ct = (r.headers.get('Content-Type')||'').toLowerCase();
    if (!r.ok){ err('GET error', r.status, text); throw new Error(`GET ${url} → ${r.status}. ${text.slice(0,300)}`); }
    if (!ct.includes('application/json')){ err('GET no-JSON', text); throw new Error(`Respuesta no JSON: ${text.slice(0,300)}`); }
    const j = JSON.parse(text);
    log('GET OK', url, j);
    return j;
  }

  async function POST_FORM(url, data){
    const body = new URLSearchParams();
    Object.entries(data||{}).forEach(([k,v])=> body.append(k, String(v==null?'':v)));
    log('POST', url, Object.fromEntries(body.entries()));
    const r = await fetch(url,{method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8','Accept':'application/json'},body});
    const text = await r.text();
    const ct = (r.headers.get('Content-Type')||'').toLowerCase();
    if (!r.ok){ err('POST error', r.status, text); throw new Error(`POST ${url} → ${r.status}. ${text.slice(0,360)}`); }
    if (!ct.includes('application/json')){ err('POST no-JSON', text); throw new Error(`Respuesta no JSON: ${text.slice(0,360)}`); }
    const j = JSON.parse(text);
    log('POST OK', url, j);
    return j;
  }

  function normalizeDate(input){
    const s = String(input||'').trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
    const m = s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    return m ? `${m[3]}-${m[2]}-${m[1]}` : s;
  }
  function todayISO(){
    const d=new Date(),mm=String(d.getMonth()+1).padStart(2,'0'),dd=String(d.getDate()).padStart(2,'0');
    return `${d.getFullYear()}-${mm}-${dd}`;
  }
  function currentDate(){
    const f = document.querySelector('#filtroFecha') || document.querySelector('#fecha');
    return normalizeDate(f?.value || todayISO());
  }

  function toast(msg, kind){
    const n = document.createElement('div');
    n.className = 'cz-toast';
    Object.assign(n.style,{position:'fixed',right:'12px',bottom:'12px',zIndex:1080,
      background: kind==='err'?'#dc3545':(kind==='ok'?'#198754':'#333'),
      color:'#fff',padding:'10px 12px',borderRadius:'6px',boxShadow:'0 3px 10px rgba(0,0,0,.2)',opacity:'0',transition:'opacity .2s'});
    n.textContent = msg;
    document.body.appendChild(n);
    requestAnimationFrame(()=> n.style.opacity='1');
    setTimeout(()=>{ n.style.opacity='0'; setTimeout(()=> n.remove(),200); }, 3000);
  }

  // =========================
  // Estado & elementos
  // =========================
  const els = {
    // listado principal
    tbody:       document.querySelector('#tablaCajas tbody') || document.querySelector('#tbl_cajas tbody'),
    badgeFecha:  document.querySelector('#badgeFecha'),
    // KPIs (si existen)
    kpiAbiertas: document.querySelector('#kpiAbiertas'),
    kpiPrecortes:document.querySelector('#kpiPrecortes'),
    kpiConcil:   document.querySelector('#kpiConcil'),
    kpiDifProm:  document.querySelector('#kpiDifProm'),

    // wizard (todos opcionales/defensivo)
    modal:              document.getElementById('czModalPrecorte'),
    stepBar:            document.getElementById('czStepBar'),
    step1:              document.getElementById('czStep1'),
    step2:              document.getElementById('czStep2'),
    step3:              document.getElementById('czStep3'),

    // paso 1
    tablaDenomsBody:    document.querySelector('#czTablaDenoms tbody'),
    precorteTotal:      document.getElementById('czPrecorteTotal'),
    chipFondo:          document.getElementById('czChipFondo'),
    efEsperadoInfo:     document.getElementById('czEfectivoEsperado'), // label/valor informativo
    declCredito:        document.getElementById('czDeclCardCredito'),
    declDebito:         document.getElementById('czDeclCardDebito'),
    declTransfer:       document.getElementById('czDeclTransfer'),
    notasPaso1:         document.getElementById('czNotes'),
    btnGuardarPrecorte: document.getElementById('czBtnGuardarPrecorte'),
    btnContinuarConc:   document.getElementById('czBtnContinuarConciliacion'),
    inputPrecorteId:    document.getElementById('cz_precorte_id'),

    // paso 2
    bannerFaltaCorte:   document.getElementById('czBannerFaltaCorte'),
    btnSincronizarPOS:  document.getElementById('czBtnSincronizarPOS'),
    concGrid:           document.getElementById('czConciliacionGrid'),
    concNotas:          document.getElementById('czConciliacionNotas'),
    concNotasLabel:     document.getElementById('czConciliacionNotasLabel'),
    btnIrPostcorte:     document.getElementById('czBtnIrPostcorte'),

    // paso 3
    corteResumen:       document.getElementById('czCorteResumen'),
    depFolio:           document.getElementById('czDepFolio'),
    depCuenta:          document.getElementById('czDepCuenta'),
    depEvidencia:       document.getElementById('czDepEvidencia'),
    notasCierre:        document.getElementById('czNotasCierre'),
    btnCerrarSesion:    document.getElementById('czBtnCerrarSesion'),
  };

  const state = {
    date: null,
    data: [],
    // sesión/wizard
    sesion: { store:0, terminal:0, user:0, bdate:'', opening:0 },
    precorteId: null,
    denoms: new Map(), // denom -> cantidad
    decl: { credito:0, debito:0, transfer:0 },
    pasoGuardado: false,
    step: 1,
  };

  // =========================
  // Listado principal
  // =========================
  async function cargarTabla(){
    // FIX: usar 'date' (no 'fecha')
    const qs = new URLSearchParams({ date: currentDate() }).toString();
    const j  = await GET(api.cajas(qs)); // { ok, date, terminals: [...] }
    state.date = j?.date || currentDate();
    state.data = Array.isArray(j?.terminals) ? j.terminals : [];
    if (els.badgeFecha) els.badgeFecha.textContent = state.date;
    renderKPIs();
    renderTabla();
  }

  function renderKPIs(){
    els.kpiAbiertas  && (els.kpiAbiertas.textContent  = state.data.filter(x=>x?.status?.activa).length);
    els.kpiPrecortes && (els.kpiPrecortes.textContent = 0);
    els.kpiConcil    && (els.kpiConcil.textContent    = 0);
    els.kpiDifProm   && (els.kpiDifProm.textContent   = MXN.format(0));
  }

  function puedeWizard(r){
    // Regla conservadora: terminal activa + asignada + con assigned_user
    return !!(r?.status?.activa && r?.status?.asignada && r?.assigned_user);
  }

  function renderAcciones(r){
    if (!puedeWizard(r)) return '';
    const store = (document.querySelector('#filtroSucursal')?.value) || 1;
    const bdate = r?.window?.day || currentDate();
    // FIX: usar opening_float cuando esté presente; si no, opening_balance
    const opening = Number((r.opening_float ?? r.opening_balance) || 0);
    return `
      <button class="btn btn-sm btn-primary"
              data-caja-action="wizard"
              data-store="${esc(store)}"
              data-terminal="${esc(r.id)}"
              data-user="${esc(r.assigned_user)}"
              data-bdate="${esc(bdate)}"
              data-opening="${esc(opening)}"
              title="Abrir Wizard">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
      </button>`;
  }

  function renderTabla(){
    if (!els.tbody) return;
    els.tbody.innerHTML = '';
    state.data.forEach(r=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${esc(r.location ?? '—')}</td>
        <td>${esc(r.name ?? r.id ?? '—')}</td>
        <td>${esc(r.assigned_name ?? '—')}</td>
        <td>${esc(r?.window?.day ?? state.date ?? '—')}</td>
        <td>${r?.status?.asignada ? (r?.status?.activa ? '<span class="badge bg-success">Asignada</span>':'<span class="badge bg-info">Asignada</span>') : '<span class="badge bg-secondary">Cerrada</span>'}</td>
        <td class="text-end">${MXN.format(Number(r.opening_balance||0))}</td>
        <td class="text-end">${MXN.format(Number(r?.sales?.assigned_total ?? r?.sales?.terminal_total ?? 0))}</td>
        <td class="text-end">${MXN.format(0)}</td>
        <td class="text-end">
          <div class="d-flex flex-wrap gap-2">${renderAcciones(r)}</div>
        </td>`;
      els.tbody.appendChild(tr);
    });
    document.querySelectorAll('[data-caja-action="wizard"]').forEach(btn=>{
      btn.addEventListener('click', abrirWizard);
    });
  }

  // =========================
  // Wizard
  // =========================
  function setStep(n){
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
    if (els.btnGuardarPrecorte) els.btnGuardarPrecorte.classList.toggle('d-none', n!==1);
    if (els.btnContinuarConc)   els.btnContinuarConc.classList.toggle('d-none', n!==1);
    if (els.btnIrPostcorte)     els.btnIrPostcorte.classList.toggle('d-none', n!==2);
    if (els.btnCerrarSesion)    els.btnCerrarSesion.classList.toggle('d-none', n!==3);
  }

  function bindDenoms(){
    if (!els.tablaDenomsBody) return;
    els.tablaDenomsBody.innerHTML = '';
    state.denoms.clear();
    DENOMS.forEach(den=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>$${den}</td>
        <td><input type="number" min="0" step="1" class="form-control form-control-sm cz-qty" data-denom="${den}" value="0" inputmode="numeric"></td>
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
      // forzamos 0 explícito
      inp.addEventListener('blur',()=>{ if (inp.value==='') { inp.value='0'; inp.dispatchEvent(new Event('input')); }});
    });
  }

  function recalcPaso1(){
    // total efectivo declarado = suma denoms
    let totalEf = 0;
    state.denoms.forEach((qty,den)=> totalEf += den*qty);
    if (els.precorteTotal) els.precorteTotal.textContent = MXN.format(totalEf);

    // Validaciones "Guardar"
    const credito  = Number(els.declCredito?.value || 0);
    const debito   = Number(els.declDebito?.value  || 0);
    const transfer = Number(els.declTransfer?.value|| 0);

    const okDenoms = totalEf > 0;
    const okNE     = [credito,debito,transfer].every(v=> !Number.isNaN(v) && v>=0);

    if (els.btnGuardarPrecorte) els.btnGuardarPrecorte.disabled = !(okDenoms && okNE);
    if (els.btnContinuarConc)   els.btnContinuarConc.disabled   = !state.pasoGuardado;

    // Info de fondo (readonly) + esperado (si quieres, aquí mostramos orientativo)
    if (els.chipFondo) els.chipFondo.textContent = MXN.format(state.sesion.opening||0);
    if (els.efEsperadoInfo){
      // Sólo informativo: fondo + ventas POS - retiros (si backend expone), por ahora muestra fondo
      els.efEsperadoInfo.textContent = MXN.format((state.sesion.opening||0));
    }
  }

  async function abrirWizard(ev){
    const btn = ev.currentTarget;
    const store    = parseInt(btn.dataset.store||'0',10);
    const terminal = parseInt(btn.dataset.terminal||'0',10);
    const user     = parseInt(btn.dataset.user||'0',10);
    const bdate    = String(btn.dataset.bdate||'').trim();
    const opening  = Number(btn.dataset.opening||0);

    // Guarda en estado
    state.sesion = { store, terminal, user, bdate, opening };
    state.precorteId = null;
    state.pasoGuardado = false;

    // Validación mínima
    if (!store || !terminal || !user || !bdate){
      toast('Faltan store/terminal/usuario para abrir el wizard','err');
      return;
    }

    // Paso 1: crear (o reusar) precorte en backend
    try{
      const payload = { bdate, store_id:store, terminal_id:terminal, user_id:user };
      const j = await POST_FORM(api.precorte_create(), payload);
      // { ok:true, sesion_id, precorte_id }
      if (!j?.ok || !j?.precorte_id){
        toast('No se pudo iniciar precorte','err');
        return;
      }
      state.precorteId = j.precorte_id;
      if (els.inputPrecorteId) els.inputPrecorteId.value = String(state.precorteId);

      // Render paso 1 y abrir modal (solo si existe en el DOM)
      if (els.modal){
        bindDenoms();
        // Prellenar "0" explícito en declarados
        ['declCredito','declDebito','declTransfer'].forEach(id=>{
          const el = els[id];
          if (el){
            el.value = (el.value===''? '0' : el.value);
            el.addEventListener('input', ()=>{ if (el.value==='') el.value='0'; recalcPaso1(); });
            el.addEventListener('blur',  ()=>{ if (el.value==='') el.value='0'; recalcPaso1(); });
          }
        });
        recalcPaso1();
        setStep(1);

        // abre modal (Bootstrap 5)
        try {
          const inst = bootstrap.Modal.getOrCreateInstance(els.modal, { backdrop:'static', keyboard:false });
          inst.show();
        } catch(e){ /* si no hay bootstrap, igual no rompemos */ }
      }

    }catch(e){
      err('Error iniciando precorte:', e.message);
      toast(`Error iniciando precorte: ${e.message}`,'err');
    }
  }

  async function guardarPrecorte(){
    if (!state.precorteId){
      toast('No hay precorte activo','err');
      return;
    }
    // Construir payload: denoms + declarados
    let totalEf = 0;
    const denoms = [];
    state.denoms.forEach((qty,den)=>{ denoms.push({den,qty}); totalEf += den*qty; });

    const credito  = Number(els.declCredito?.value || 0);
    const debito   = Number(els.declDebito?.value  || 0);
    const transfer = Number(els.declTransfer?.value|| 0);
    const notas    = String(els.notasPaso1?.value || '');

    // Confirmación con resumen (como pediste)
    const resumen = `Efectivo: ${MXN.format(totalEf)} · TC: ${MXN.format(credito)} · TD: ${MXN.format(debito)} · Transf: ${MXN.format(transfer)}\n¿Guardar precorte?`;
    if (!window.confirm(resumen)) return;

    try{
      const payload = {
        // Enviamos como form-urlencoded:
        // denoms_json: [{"den":100,"qty":1},...]
        denoms_json: JSON.stringify(denoms),
        declarado_credito: credito,
        declarado_debito:  debito,
        declarado_transfer: transfer,
        notas
      };
      const j = await POST_FORM(api.precorte_update(state.precorteId), payload);
      if (!j?.ok){ toast('No se pudo guardar precorte','err'); return; }
      state.pasoGuardado = true;
      if (els.btnContinuarConc) els.btnContinuarConc.disabled = false;
      toast('Precorte guardado','ok');

      // Avanzar automáticamente al paso 2 y sincronizar POS
      setStep(2);
      await sincronizarPOS(true);

    }catch(e){
      err('Error guardando precorte:', e.message);
      toast(`Error guardando precorte: ${e.message}`,'err');
    }
  }

  async function sincronizarPOS(auto=false){
    if (!state.precorteId){
      toast('No hay precorte activo','err'); return;
    }
    if (els.bannerFaltaCorte) els.bannerFaltaCorte.classList.add('d-none');
    if (els.concGrid) els.concGrid.innerHTML = '<div class="text-muted small">Sincronizando con POS...</div>';

    try{
      const j = await GET(api.precorte_totales(state.precorteId));
      // Esperamos algo tipo: { ok:true, data:{ efectivo:{declarado, sistema, diferencia}, ... }, opening_float, ... }
      if (!j?.ok){
        if (els.bannerFaltaCorte) els.bannerFaltaCorte.classList.remove('d-none');
        if (!auto) toast('Falta cierre en POS o no hay datos','err');
        return;
      }
      const d = j.data || {};
      renderConciliacion(d, j);

      // Habilitar botón para ir al postcorte si cuadra o notas obligatorias cumplidas
      if (els.btnIrPostcorte) els.btnIrPostcorte.disabled = false;

    }catch(e){
      err('Sincronización POS error:', e.message);
      if (els.bannerFaltaCorte) els.bannerFaltaCorte.classList.remove('d-none');
      if (!auto) toast(`Sincronización fallida: ${e.message}`,'err');
    }
  }

  function badgeVeredicto(diff){
    const ad = Math.abs(Number(diff||0));
    if (ad === 0) return '<span class="badge bg-success">CUADRA</span>';
    if (ad <= 10) return '<span class="badge bg-warning text-dark">±10</span>';
    return '<span class="badge bg-danger">DIF</span>';
    // (Regla de notas obligatorias sólo la reforzamos en back al cerrar)
  }

  function rowConc(name, declarado, sistema){
    const diff = Number(declarado||0) - Number(sistema||0);
    return `
      <tr>
        <td>${esc(name)}</td>
        <td class="text-end">${MXN.format(Number(declarado||0))}</td>
        <td class="text-end">${MXN.format(Number(sistema||0))}</td>
        <td class="text-end">${MXN.format(diff)}</td>
        <td class="text-center">${badgeVeredicto(diff)}</td>
      </tr>`;
  }

  function renderConciliacion(d, raw){
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
          <th>Categoria</th><th class="text-end">Declarado</th><th class="text-end">Sistema</th><th class="text-end">Diferencia</th><th class="text-center">Estado</th>
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

  // =========================
  // Bind de botones del modal (si existen)
  // =========================
  function bindModalButtons(){
    if (els.btnGuardarPrecorte){
      els.btnGuardarPrecorte.addEventListener('click', guardarPrecorte);
      els.btnGuardarPrecorte.disabled = true; // se habilita con validación
    }
    if (els.btnContinuarConc){
      els.btnContinuarConc.addEventListener('click', ()=> setStep(2));
      els.btnContinuarConc.disabled = true; // sólo tras guardar
    }
    if (els.btnSincronizarPOS){
      els.btnSincronizarPOS.addEventListener('click', ()=> sincronizarPOS(false));
    }
    if (els.btnIrPostcorte){
      els.btnIrPostcorte.addEventListener('click', ()=> setStep(3));
      els.btnIrPostcorte.disabled = true;
    }
    if (els.btnCerrarSesion){
      els.btnCerrarSesion.addEventListener('click', ()=>{
        // Aquí sólo UI; el cierre real lo harán con tu endpoint cuando lo tengamos
        toast('Sesión cerrada (demo UI).', 'ok');
        try { bootstrap.Modal.getInstance(els.modal)?.hide(); } catch(_){}
        // Recarga el listado para ver cambios
        cargarTabla().catch(()=>{});
      });
    }
  }

  // =========================
  // Init
  // =========================
  document.addEventListener('DOMContentLoaded', ()=>{
    // Cargar listado
    cargarTabla().catch(e=> toast(e.message,'err'));

    // Modal: si existe, prepara listeners
    bindModalButtons();
  });

})();
