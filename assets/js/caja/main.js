// js/caja/main.js
import { cargarTabla } from './mainTable.js';
import { bindModalButtons, abrirWizard } from './wizard.js';
import { toast } from './helpers.js';

// Compatibilidad: onclick="abrirWizard(event)"
window.abrirWizard = abrirWizard;

if (!window.__CAJA_BOOTED__) {
  window.__CAJA_BOOTED__ = true;
  document.addEventListener('DOMContentLoaded', () => {
    bindModalButtons(); // idempotente
    cargarTabla().catch(e => {
      console.error(e);
      toast(e.message || 'Error cargando tabla', 'err', 9000, 'Error');
    });
  });
} else {
  console.debug('[caja] ya inicializado, se ignora duplicado');
}
