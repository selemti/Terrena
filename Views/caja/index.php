<?php
// Usa el $base global si ya lo tienes definido en layout/index
$base = $GLOBALS['__BASE__'] ?? '/terrena/Terrena';
?>
<div style="page">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0"><i class="fa-solid fa-cash-register me-2"></i> Caja</h3>
    <div>
      <a href="<?= $base ?>/dashboard" class="btn btn-outline-secondary btn-sm">
        <i class="fa-solid fa-arrow-left me-1"></i> Regresar
      </a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><i class="fa-regular fa-clipboard me-2"></i> Cortes de caja</h5>
          <p class="text-muted small mb-4">Apertura, Precorte, Corte, Postcorte, histórico.</p>
          <div class="mt-auto">
            <a class="btn btn-primary w-100" href="<?= $base ?>/caja/cortes">
              <i class="fa-solid fa-play me-1"></i> Ir a Cortes
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><i class="fa-regular fa-credit-card me-2"></i> Pagos con tarjeta</h5>
          <p class="text-muted small mb-4">Consulta transacciones, autorizaciones y totales por tipo.</p>
          <div class="mt-auto">
            <a class="btn btn-outline-primary w-100" href="<?= $base ?>/caja/cortes#pane-tarjetas" data-bs-toggle="tab" data-target="#pane-tarjetas">
              <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Ver tarjetas
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><i class="fa-solid fa-wallet me-2"></i> Retiros</h5>
          <p class="text-muted small mb-4">Registro y control de retiros (Pay Out / Drawer Bleed).</p>
          <div class="mt-auto">
            <a class="btn btn-outline-primary w-100" href="<?= $base ?>/caja/cortes#pane-retiros" data-bs-toggle="tab" data-target="#pane-retiros">
              <i class="fa-solid fa-arrow-right-to-bracket me-1"></i> Ver retiros
            </a>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6 col-xl-3">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><i class="fa-solid fa-chart-line me-2"></i> Reportes de caja</h5>
          <p class="text-muted small mb-4">Cortes por fecha, cajero, sucursal. Exportables.</p>
          <div class="mt-auto">
            <a class="btn btn-outline-secondary w-100" href="<?= $base ?>/reportes">
              <i class="fa-regular fa-file-lines me-1"></i> Ir a reportes
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
</div>