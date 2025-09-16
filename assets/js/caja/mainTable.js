// assets/js/caja/mainTable.js
import { api, MXN } from './config.js';
import { $, esc, GET, currentDate } from './helpers.js';
import { els, state } from './state.js';

export async function cargarTabla(){
  const qs = new URLSearchParams({ date: currentDate() }).toString();
  const j  = await GET(api.cajas(qs)); // { ok, date, terminals: [...] }
  state.date = j?.date || currentDate();
  state.data = Array.isArray(j?.terminals) ? j.terminals : [];

  if (els.badgeFecha) els.badgeFecha.textContent = state.date;
  renderKPIs();
  renderTabla();
}

export function renderKPIs(){
  els.kpiAbiertas  && (els.kpiAbiertas.textContent  = state.data.filter(x=>x?.status?.activa).length);
  els.kpiPrecortes && (els.kpiPrecortes.textContent = 0);
  els.kpiConcil    && (els.kpiConcil.textContent    = 0);
  els.kpiDifProm   && (els.kpiDifProm.textContent   = MXN.format(0));
}

// mainTable.js
export function puedeWizard(r){
  const activoAsignado = !!(r?.status?.activa && r?.status?.asignada && r?.assigned_user);
  const habilitadoPaso3 = !!(r?.flags?.precorte_listo && r?.flags?.sin_postcorte);
  return activoAsignado || habilitadoPaso3;
}


// usar fallback de usuario de la sesión para abrir el wizard  :contentReference[oaicite:1]{index=1}
export function renderAcciones(r){
  if (!puedeWizard(r)) return '';
  const store    = (document.querySelector('#filtroSucursal')?.value) || 1;
  const bdate    = r?.window?.day || state.date || currentDate();
  const opening  = Number((r.opening_float ?? r.opening_balance) || 0);
  const sesionId = (r?.sesion?.id ?? r?.status?.sesion_id ?? '');
  const userId   = r.assigned_user ?? r?.sesion?.cajero_usuario_id; // fallback

  return `
    <button class="btn btn-sm btn-primary"
            data-caja-action="wizard"
            data-store="${esc(store)}"
            data-terminal="${esc(r.id)}"
            data-user="${esc(userId)}"
            data-bdate="${esc(bdate)}"
            data-opening="${esc(opening)}"
            data-sesion="${esc(sesionId)}"
            title="Abrir Wizard">
      <i class="fa-solid fa-wand-magic-sparkles"></i>
    </button>`;
}



// badge Estado: mostrar “Validación” si aplica paso 3 sin asignación  :contentReference[oaicite:2]{index=2}
export function renderTabla(){
  if (!els.tbody) return;
  els.tbody.innerHTML = '';

  state.data.forEach(r=>{
    const enValidacion = (!!r?.flags?.precorte_listo && !!r?.flags?.sin_postcorte) && !r?.status?.asignada;
    const badgeEstado = r?.status?.asignada
      ? (r?.status?.activa ? '<span class="badge bg-success">Asignada</span>'
                           : '<span class="badge bg-info">En Corte</span>')
      : (enValidacion ? '<span class="badge bg-warning text-dark">Validación</span>'
                      : '<span class="badge bg-secondary">Cerrada</span>');

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${esc(r.location ?? '—')}</td>
      <td>${esc(r.name ?? r.id ?? '—')}</td>
      <td>${esc(r.assigned_name ?? '—')}</td>
      <td>${esc(r?.window?.day ?? state.date ?? '—')}</td>
      <td>${badgeEstado}</td>
      <td class="text-end">${MXN.format(Number(r.opening_balance||0))}</td>
      <td class="text-end">${MXN.format(Number(r?.sales?.assigned_total ?? r?.sales?.terminal_total ?? 0))}</td>
      <td class="text-end">${MXN.format(0)}</td>
      <td class="text-end"><div class="d-flex flex-wrap gap-2">${renderAcciones(r)}</div></td>`;
    els.tbody.appendChild(tr);
  });

  els.tbody.querySelectorAll('[data-caja-action="wizard"]').forEach(btn=>{
    if (btn.dataset.bound === '1') return;
    btn.dataset.bound = '1';
    btn.addEventListener('click', (ev)=> importarWizard(ev));
  });
}

export async function importarWizard(ev){
  const { abrirWizard } = await import('./wizard.js');
  abrirWizard(ev);
}
