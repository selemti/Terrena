<?php
declare(strict_types=1);
//require_once __DIR__ . '/../layout.php';

// Comienza a maquetar el dashboard (sin BD por ahora)
ob_start();
//$base = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
//if ($base === '/' || $base === '\\') { $base = ''; }
?>
<div class="dashboard-grid">

  <!-- Filtros -->
  <div class="filters-bar mb-3">
    <div class="d-flex align-items-center gap-2">
      <i class="fa-solid fa-filter text-muted"></i><strong>Filtros:</strong>
    </div>
<div class="d-flex align-items-center gap-2 flex-wrap flex-md-nowrap">
  <label class="text-muted small">Desde</label>
  <input id="start-date" type="date" class="form-control form-control-sm">
  <label class="text-muted small ms-sm-2">Hasta</label>
  <input id="end-date" type="date" class="form-control form-control-sm">
  <button id="apply-filters" class="btn btn-filter btn-sm"><i class="fa-solid fa-check me-1"></i>Aplicar</button>
</div>
  </div>

  <!-- KPIs (5 columnas en desktop) -->
  <div class="kpi-grid mb-3">
    <div class="card-kpi">
      <h5 class="card-title"><i class="fa-solid fa-sack-dollar"></i> Ventas de hoy</h5>
      <div class="kpi-value" id="kpi-sales-today">$0.00</div>
      <div class="text-muted small">vs. ayer <span class="text-success">+4.2%</span> · <span class="text-muted">125 transacciones</span></div>
    </div>
    <div class="card-kpi">
      <h5 class="card-title"><i class="fa-solid fa-star"></i> Producto estrella</h5>
      <div class="kpi-value" id="kpi-star-product">Latte Vainilla</div>
      <div class="text-muted small">Ventas: <strong>$350.25</strong></div>
    </div>
    <div class="card-kpi">
      <h5 class="card-title"><i class="fa-solid fa-tags"></i> Productos vendidos</h5>
      <div class="kpi-value" id="kpi-items-sold">1,284</div>
      <div class="text-muted small">vs. ayer <span class="text-danger">-1.1%</span></div>
    </div>
    <div class="card-kpi">
      <h5 class="card-title"><i class="fa-solid fa-receipt"></i> Ticket promedio</h5>
      <div class="kpi-value" id="kpi-avg-ticket">$98.20</div>
      <div class="text-muted small">vs. ayer <span class="text-success">+2.7%</span></div>
    </div>
    <div class="card-kpi">
      <h5 class="card-title"><i class="fa-solid fa-bell"></i> Alertas</h5>
      <div class="kpi-value" id="kpi-alerts">5</div>
      <div class="text-muted small"><a class="link-more" href="<?= $base ?>/reportes">Ver todas <i class="fa-solid fa-chevron-right"></i></a></div>
    </div>
  </div>

  <div class="row g-3">
    <!-- Tendencia de ventas -->
    <div class="col-12 col-xl-7">
      <div class="chart-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-chart-line"></i> Tendencia de ventas (7 días)</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver detalle <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="chart-wrapper">
          <canvas id="salesTrendChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Estatus de cajas (reemplaza Alertas en tablero) -->
    <div class="col-12 col-xl-5">
      <div class="card-vo">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-cash-register"></i> Estatus de cajas</h5>
          <a href="<?= $base ?>/caja/cortes" class="link-more small">Ir a cortes <i class="fa-solid fa-chevron-right ms-1"></i></a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Sucursal</th><th>Estatus</th><th class="text-end">Vendido</th></tr></thead>
            <tbody id="kpi-registers">
              <!-- Se llena por JS -->
              <tr><td>Principal</td><td><span class="badge text-bg-secondary">—</span></td><td class="text-end">-</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Ventas por hora (apilada por sucursal) -->
    <div class="col-12 col-xl-7">
      <div class="chart-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-business-time"></i> Ventas por hora</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver todo <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="chart-wrapper">
          <canvas id="salesByHourChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Formas de pago (dona) -->
    <div class="col-12 col-xl-5">
      <div class="chart-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-circle-notch"></i> Formas de pago</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver todo <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="chart-wrapper">
          <canvas id="paymentChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Ventas por sucursal (apilada por tipo) -->
    <div class="col-12 col-xl-7">
      <div class="chart-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-store"></i> Ventas por sucursal (por tipo)</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver todo <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="chart-wrapper">
          <canvas id="branchPaymentsChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Top 5 productos (apilada horizontal por sucursal) -->
    <div class="col-12 col-xl-5">
      <div class="chart-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-ranking-star"></i> Top 5 de productos</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver todo <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="chart-wrapper">
          <canvas id="topProductsChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Actividad reciente -->
    <div class="col-12 col-xl-7">
      <div class="card-vo">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-regular fa-clock"></i> Actividad reciente</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver todo <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <ul id="activity-list" class="list-unstyled mb-0">
          <!-- JS -->
        </ul>
      </div>
    </div>

    <!-- Órdenes recientes -->
    <div class="col-12 col-xl-5">
      <div class="card-vo">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0"><i class="fa-solid fa-list-check"></i> Órdenes recientes</h5>
          <a class="link-more" href="<?= $base ?>/reportes">Ver todo <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr><th>#Ticket</th><th>Sucursal</th><th>Hora</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody id="orders-table">
              <!-- JS -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
<?php
$content = ob_get_clean();
//render_layout('Dashboard', $content, 'dashboard');
