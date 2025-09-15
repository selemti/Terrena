<?php $title='<i class="fa-solid fa-chart-column"></i> Centro de reportes'; ?>




<main class="main-content flex-grow-1">
<div class="dashboard-grid">

<h1 class="h4 mb-3"></h1>
<div class="row g-3">
  <div class="col-md-4"><div class="card-vo p-3 h-100">
    <h5 class="card-title"><i class="fa-solid fa-chart-simple"></i> Ventas</h5>
    <ul class="list-unstyled mb-3 small">
      <li><a href="#" class="text-decoration-none">Ventas por día / hora</a></li>
      <li><a href="#" class="text-decoration-none">Por categoría / producto</a></li>
      <li><a href="#" class="text-decoration-none">Por forma de pago</a></li>
    </ul>
    <button class="btn btn-sm btn-outline-secondary">Programar envío</button>
  </div></div>
  <div class="col-md-4"><div class="card-vo p-3 h-100">
    <h5 class="card-title"><i class="fa-solid fa-boxes-stacked"></i> Inventario</h5>
    <ul class="list-unstyled mb-3 small">
      <li><a href="#" class="text-decoration-none">Abasto (min/max)</a></li>
      <li><a href="#" class="text-decoration-none">Rotación y mermas</a></li>
      <li><a href="#" class="text-decoration-none">Valorización (CPP)</a></li>
    </ul>
    <button class="btn btn-sm btn-outline-secondary">Programar envío</button>
  </div></div>
  <div class="col-md-4"><div class="card-vo p-3 h-100">
    <h5 class="card-title"><i class="fa-solid fa-money-bill-trend-up"></i> Finanzas</h5>
    <ul class="list-unstyled mb-3 small">
      <li><a href="reportes/pnl" class="text-decoration-none">Estado de resultados (P&L)</a></li>
      <li><a href="reportes/flujo" class="text-decoration-none">Flujo de efectivo</a></li>
      <li><a href="#" class="text-decoration-none">Comisiones TC</a></li>
    </ul>
    <button class="btn btn-sm btn-outline-secondary">Programar envío</button>
  </div></div>
</div>

<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label small">Reporte</label>
        <select class="form-select form-select-sm">
          <option>Ventas por período</option>
          <option>Mix por forma de pago</option>
          <option>Kardex</option>
          <option>Compras por proveedor</option>
          <option>Estado de resultados simple</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Sucursal</label>
        <select class="form-select form-select-sm"><option>Todas</option><option>PRINCIPAL</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label small">Desde</label>
        <input type="date" class="form-control form-control-sm">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Hasta</label>
        <input type="date" class="form-control form-control-sm">
      </div>
      <div class="col-12 text-end">
        <button class="btn btn-sm text-white" style="background:var(--green-dark)">Generar</button>
        <button class="btn btn-sm btn-outline-secondary">Exportar</button>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-header bg-white"><strong>Resultado</strong></div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Col A</th><th>Col B</th><th class="text-end">Col C</th></tr></thead>
        <tbody><tr><td>-</td><td>-</td><td class="text-end">-</td></tr></tbody>
      </table>
    </div>
  </div>
</div>
<h2 class="h6 mt-4">Finanzas</h2>
<div class="row g-2">
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="small text-muted">Utilidad (P&L) periodo</div>
        <div class="h4">$ 0.00</div>
        <div class="small text-muted">Ingresos - Costos - Gastos</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="small text-muted">Flujo de efectivo</div>
        <div class="h4">$ 0.00</div>
        <div class="small text-muted">Ventas vs Depósitos</div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="small text-muted">Comisiones TC</div>
        <div class="h4">$ 0.00</div>
        <div class="small text-muted">Tarjetas débito/crédito</div>
      </div>
    </div>
  </div>
</div>
</div>
</main>
