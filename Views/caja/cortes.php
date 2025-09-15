<?php
// Cortes de caja: vista montada sobre el layout común
// Usa el mismo patrón que dashboard: $title + ob_start() + render_layout()
$base  = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$title = '<i class="fa-solid fa-cash-register me-2"></i></i> <span class="label">Administración de cajas</span>';
$parts = __DIR__ . '/parts';
// comienza buffer del contenido de la vista
//include $parts.'/_wizard_modals.php';
ob_start();
// Incluir modales del wizard (al final del body de esta vista)

$hoy = date('Y-m-d');
?>
<main class="main-content flex-grow-1">
  <div class="dashboard-grid"><!-- mantenemos grilla y estilos existentes -->

    <!-- Título + barra de filtros -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="d-flex align-items-center gap-2">
        <h2 class="mb-0">Cajas</h2>
        <span class="badge bg-secondary" id="badgeFecha"><?= htmlspecialchars($fecha ?? date('Y-m-d')) ?></span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <input id="filtroFecha" type="date" class="form-control form-control-sm" value="<?= htmlspecialchars($fecha ?? date('Y-m-d')) ?>">
        <select id="filtroSucursal" class="form-select form-select-sm" style="width:220px"></select>
        <select id="filtroEstado" class="form-select form-select-sm" style="width:200px">
          <option value="">Todas</option>
          <option value="ASIGNADA">Asignada</option>
          <option value="PRECORTE_REGISTRADO">Precorte</option>
          <option value="CORTE_POS_REGISTRADO">Corte POS</option>
          <option value="CONCILIADA">Conciliada</option>
          <option value="POSTCORTE_REGISTRADO">Postcorte</option>
          <option value="CERRADA">Cerrada</option>
        </select>
        <button id="btnRefrescar" class="btn btn-outline-secondary btn-sm">
          <i class="fa-solid fa-rotate"></i> Refrescar
        </button>
        <!-- Botón global para abrir Wizard (opcional; igual habrá botón por fila) -->
        <button class="btn btn-primary btn-sm"
                data-caja-action="wizard"
                data-store="<?= (int)($store_id ?? 1) ?>"
                data-terminal=""
                data-user="<?= (int)($_SESSION['user_id'] ?? 1) ?>"
                data-bdate="<?= htmlspecialchars($fecha ?? date('Y-m-d')) ?>">
          <i class="fa-solid fa-magic-wand-sparkles me-1"></i> Abrir Wizard
        </button>
      </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-3">
      <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Cajas abiertas</div><div class="kpi-value" id="kpiAbiertas">0</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Promedio diferencia</div><div class="kpi-value" id="kpiDifProm">$0.00</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Precortes hoy</div><div class="kpi-value" id="kpiPrecortes">0</div></div></div>
      <div class="col-6 col-md-3"><div class="kpi"><div class="kpi-label">Conciliadas hoy</div><div class="kpi-value" id="kpiConcil">0</div></div></div>
    </div>

    <!-- Tabla principal -->
    <div class="table-responsive shadow-sm rounded bg-white">
      <table class="table table-sm align-middle mb-0" id="tablaCajas">
        <thead class="table-light">
          <tr>
            <th>Sucursal</th>
            <th>Terminal</th>
            <th>Cajero</th>
            <th>Asignación</th>
            <th>Estado</th>
            <th class="text-end">Precorte</th>
            <th class="text-end">Ventas POS</th>
            <th class="text-end">Dif. Efectivo</th>
            <th style="width:280px">Acciones</th>
          </tr>
        </thead>
        <tbody><!-- filas por JS --></tbody>
      </table>
    </div>

    <!-- Panel detalle (lazy) -->
    <div class="mt-3" id="panelDetalle" hidden>
      <div class="card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Detalle de Caja</h5>
            <button class="btn btn-light btn-sm" id="btnOcultarDetalle">Ocultar</button>
          </div>
          <div id="detalleContenido" class="mt-3"><em>Selecciona una caja…</em></div>
        </div>
      </div>
    </div>

  </div><!-- /.dashboard-grid -->
</main>

<!-- CSS/JS del módulo (local al view, para no tocar layout) -->
<!--script src="<?= $base ?>/assets/js/caja.js"></script-->
<?php
if (is_file($parts.'/_wizard_modals.php')) {
  include $parts.'/_wizard_modals.php';
}
?>

<script type="module"  src="<?= $base ?>/assets/js/caja/main.js"></script>


<?php

// cerrar buffer y pintar con layout
$content = ob_get_clean();
require_once __DIR__ . '/../layout.php';

if (function_exists('render_layout')) {
  render_layout($title, $content);
} else {
  // Fallback (si por algo no carga layout)
  echo $content;
}