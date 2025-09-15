// assets/js/moneda.js
(() => {
  if (!window.Cleave) { console.warn('[moneda] Falta cleave.min.js'); return; }

  const init = (el) => {
    if (!el || el._cleave) return;
    // opciones por data-attrs
    const dec   = parseInt(el.dataset.decimals || '2', 10);
    const negOK = el.dataset.negative === '1' ? false : true; // default: solo positivos
    el.type = 'text'; el.inputMode = 'decimal'; el.placeholder ||= '0';

    el._cleave = new Cleave(el, {
      numeral: true,
      numeralThousandsGroupStyle: 'thousand',
      numeralDecimalScale: isNaN(dec) ? 2 : dec,
      numeralDecimalMark: '.',
      delimiter: ',',
      numeralPositiveOnly: negOK
    });

    // marcar que el usuario lo tocó (para tus validaciones)
    const mark = () => (el.dataset.touched = '1');
    el.addEventListener('input', mark);
    el.addEventListener('blur',  mark);
    el.addEventListener('focus', () => { el.select?.(); });
  };

  const scan = (root = document) => {
    root.querySelectorAll('input.moneda').forEach(init);
  };

  // observa contenido dinámico (modales, vistas AJAX)
  const mo = new MutationObserver((muts) => {
    muts.forEach(m => {
      m.addedNodes.forEach(n => {
        if (n.nodeType !== 1) return;
        if (n.matches?.('input.moneda')) init(n);
        n.querySelectorAll?.('input.moneda').forEach(init);
      });
    });
  });
  mo.observe(document.documentElement, { childList: true, subtree: true });

  // primera pasada
  document.addEventListener('DOMContentLoaded', () => scan());

  // helpers globales
  window.Moneda = {
    scan,
    ensure: init,
    raw: (el) => {
      if (!el) return 0;
      if (el._cleave?.getRawValue) return Number(el._cleave.getRawValue() || 0);
      return Number(String(el.value || '').replace(/,/g,'').trim() || 0);
    },
    set: (el, n) => {
      if (!el) return;
      const v = Number(n || 0).toFixed(parseInt(el.dataset.decimals || '2', 10));
      if (el._cleave) el._cleave.setRawValue(v);
      else el.value = v;
      el.dispatchEvent(new Event('input', { bubbles:true }));
    }
  };
})();
