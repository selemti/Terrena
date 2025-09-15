<?php
$base  = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
 $title='<i class="fa-solid fa-industry"></i> Producción'; 
 ob_start();
 ?>

<main class="main-content flex-grow-1">
<div class="dashboard-grid">

<ul class="nav nave nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabPlan">Planeación</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabOP">Órdenes de Producción</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tabPlan">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="row g-2 mb-2">
          <div class="col-12 col-md-3"><input type="date" class="form-control form-control-sm" value="<?=date('Y-m-d', strtotime('+1 day'))?>"></div>
          <div class="col-12 col-md-6"><input class="form-control form-control-sm" placeholder="Agregar item (ej. Torta de Pollo)"></div>
          <div class="col-12 col-md-3 text-end"><button class="btn btn-sm btn-primary">Agregar</button></div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th>Receta</th><th>Stock</th><th>Faltantes</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
              <tr><td>Torta de Pollo</td><td class="text-end">20</td><td>STD</td><td>OK</td><td>—</td><td class="text-end"><button class="btn btn-sm btn-success">Generar OP</button></td></tr>
              <tr><td>Torta de Jamón</td><td class="text-end">10</td><td>STD</td><td><span class="badge bg-warning">Faltantes</span></td><td>Pan: 10 pzas; Jamón: 0.5 kg</td><td class="text-end"><button class="btn btn-sm btn-outline-danger">Generar OC</button></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tabOP">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Órdenes de Producción (maqueta)</div>
      <button class="btn btn-sm btn-primary">Nueva OP</button>
	  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mdlOP">Detalle</button>

    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>OP</th><th>Producto</th><th class="text-end">Cant.</th><th>Estatus</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <tr><td>OP-001</td><td>Torta de Pollo</td><td class="text-end">20</td><td><span class="badge bg-info">En proceso</span></td><td class="text-end"><button class="btn btn-sm btn-success">Cerrar (backflush)</button></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
<!-- Modal OP -->
<div class="modal fade" id="mdlOP" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">OP-001 · Torta de Pollo (20)</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="row g-2">
          <div class="col-lg-6">
            <div class="card-vo p-3">
              <h5 class="card-title"><i class="fa-solid fa-list-check"></i> Picking-list</h5>
              <table class="table table-sm mb-0">
                <thead><tr><th>Insumo</th><th class="text-end">Teórico</th><th>UDM</th><th class="text-end">Exist.</th></tr></thead>
                <tbody>
                  <tr><td>Pan telera</td><td class="text-end">20</td><td>PZA</td><td class="text-end">80</td></tr>
                  <tr><td>Pollo cocido</td><td class="text-end">2.0</td><td>KG</td><td class="text-end">3.5</td></tr>
                  <tr><td>Lechuga</td><td class="text-end">0.8</td><td>KG</td><td class="text-end">1.2</td></tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card-vo p-3">
              <h5 class="card-title"><i class="fa-solid fa-scale-balanced"></i> Consumos reales / merma</h5>
              <table class="table table-sm">
                <thead><tr><th>Insumo</th><th class="text-end">Real</th><th>UDM</th><th class="text-end">Merma</th></tr></thead>
                <tbody>
                  <tr><td>Pollo cocido</td><td class="text-end"><input class="form-control form-control-sm text-end" value="2.1"></td><td>KG</td><td class="text-end">0.1</td></tr>
                </tbody>
              </table>
              <div class="text-end"><button class="btn btn-sm btn-primary">Guardar consumos</button></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-sm btn-success">Cerrar OP (backflush)</button>
      </div>
    </div>
  </div>
</div>

</div>
</main>