<?php
// Compras
$base  = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$title = '<i class="fa-solid fa-truck"></i> <span class="label">Compras</span>';
$parts = __DIR__ . '/parts';
// comienza buffer del contenido de la vista
ob_start();
?>




<main class="main-content flex-grow-1">
<div class="dashboard-grid">

<!--h1 class="h4 mb-3"><i class="fa-solid fa-truck"></i> Compras</h1-->
		   
<ul class="nav nave nav-tabs mb-3"> 
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tabReq">Requisiciones</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabOC">Órdenes de compra</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabRecep">Recepciones</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabProv">Proveedores</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabSugeridos">Sugeridos (Min/Max)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#tabFact">Facturación & Pagos</button></li>

</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tabReq">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Listado de requisiciones (maqueta)</div>
      <button class="btn btn-sm btn-primary">Nueva requisición</button>
    </div>
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Folio</th><th>Sucursal</th><th>Solicitó</th><th>Estatus</th><th class="text-end">Acciones</th></tr></thead>
          <tbody>
            <tr><td>REQ-0001</td><td>PRINCIPAL</td><td>J. Pérez</td><td><span class="badge bg-warning">Pendiente</span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Ver</button></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tabOC">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Órdenes de compra (maqueta)</div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary">Importar</button>
        <button class="btn btn-sm btn-primary">Nueva OC</button>
      </div>
    </div>
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>OC</th><th>Proveedor</th><th>Sucursal</th><th>Estatus</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <tr><td>OC-0009</td><td>Lácteos MX</td><td>PRINCIPAL</td><td><span class="badge bg-info">En tránsito</span></td><td class="text-end">$ 12,450.00</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tabRecep">
    <div class="alert alert-info">Recepciones (parcial/total) – solo maquetación.</div>
    <table class="table table-sm">
      <thead><tr><th>OC</th><th>Fecha</th><th>Recibió</th><th>Estatus</th></tr></thead>
      <tbody><tr><td>OC-0009</td><td>2025-08-15</td><td>M. López</td><td><span class="badge bg-success">Parcial</span></td></tr></tbody>
    </table>
  </div>

  <div class="tab-pane fade" id="tabProv">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Proveedores (maqueta)</div>
      <button class="btn btn-sm btn-primary">Nuevo proveedor</button>
    </div>
    <div class="card shadow-sm border-0">
      <div class="card-body p-0">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Proveedor</th><th>Contacto</th><th>Teléfono</th><th class="text-end">Acciones</th></tr></thead>
          <tbody>
            <tr><td>Lácteos MX</td><td>ventas@lacteos.mx</td><td>55 1234 5678</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="tab-pane fade" id="tabSugeridos">
  <div class="alert alert-secondary small">Sugerencia automática con base en min/max, lead time y producción planificada.</div>
  <table class="table table-sm align-middle">
    <thead><tr><th>Proveedor</th><th>Ítem</th><th class="text-end">Exist.</th><th class="text-end">Mín</th><th class="text-end">Máx</th><th class="text-end">Sugerido</th><th class="text-end">UDM compra</th><th class="text-end">Acciones</th></tr></thead>
    <tbody>
      <tr><td>Lácteos MX</td><td>Leche 1.5L (caja×12)</td><td class="text-end">6 cajas</td><td class="text-end">5</td><td class="text-end">12</td><td class="text-end">6</td><td class="text-end">CAJA12</td><td class="text-end"><button class="btn btn-sm btn-primary">Crear OC</button></td></tr>
    </tbody>
  </table>
</div>
<div class="tab-pane fade" id="tabFact">
  <div class="row g-2">
    <div class="col-lg-5">
      <div class="card-vo p-3">
        <h5 class="card-title"><i class="fa-regular fa-file-lines"></i> Facturas de compra</h5>
        <div class="d-flex gap-2 mb-2">
          <input class="form-control form-control-sm" placeholder="Folio / Proveedor">
          <button class="btn btn-sm btn-primary">Nueva factura</button>
        </div>
        <table class="table table-sm mb-0">
          <thead><tr><th>Folio</th><th>Proveedor</th><th class="text-end">Subtotal</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <tr><td>F-00021</td><td>Lácteos MX</td><td class="text-end">$1,800</td><td class="text-end">$288</td><td class="text-end">$2,088</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="card-vo p-3">
        <h5 class="card-title"><i class="fa-solid fa-money-check-dollar"></i> Pagos a proveedor</h5>
        <div class="row g-2 mb-2">
          <div class="col-4"><select class="form-select form-select-sm"><option>Proveedor</option></select></div>
          <div class="col-4"><input class="form-control form-control-sm" placeholder="Ref. pago"></div>
          <div class="col-4"><input type="date" class="form-control form-control-sm"></div>
        </div>
        <table class="table table-sm">
          <thead><tr><th>Factura</th><th>Vence</th><th class="text-end">Saldo</th><th class="text-end">Pagar</th></tr></thead>
          <tbody>
            <tr><td>F-00021</td><td>2025-09-05</td><td class="text-end">$2,088</td><td class="text-end"><input class="form-control form-control-sm text-end" value="2088"></td></tr>
          </tbody>
        </table>
        <div class="text-end"><button class="btn btn-sm btn-primary">Registrar pago</button></div>
      </div>
    </div>
  </div>
</div>

</div>
</div>
</main>
