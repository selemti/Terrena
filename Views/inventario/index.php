<?php
/* Terrena/views/inventario/index.php
 * Maquetación completa del módulo Inventario (sin BD)
 * - Filtros (buscador, sucursal, categoría, estado)
 * - KPIs mini (placeholders)
 * - Tabla de stock (responsive)
 * - Movimiento rápido (offcanvas)
 * - Kardex (modal)
 * - Paginación (placeholder)
 */
$title='<i class="fa-solid fa-boxes-stacked"></i> <span class="label">Inventario</span>';
?>
          
<main class="main-content flex-grow-1">
<div class="dashboard-grid">

<!-- Filtros -->
<div class="card shadow-sm border-0 mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label small">Buscar producto / SKU</label>
        <input type="text" class="form-control form-control-sm" placeholder="Ej. 'Leche 1.5L' o 'SKU-0001'">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Sucursal</label>
        <select class="form-select form-select-sm">
          <option>Todas</option>
          <option>PRINCIPAL</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Categoría</label>
        <select class="form-select form-select-sm">
          <option>Todas</option>
          <option>Lácteos</option>
          <option>Panadería</option>
          <option>Café</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Estado</label>
        <select class="form-select form-select-sm">
          <option>Todos</option>
          <option>Con bajo stock</option>
          <option>Con caducidad próxima</option>
        </select>
      </div>
      <div class="col-6 col-md-2 text-end">
        <div class="d-grid d-md-flex gap-2">
          <button class="btn btn-sm text-white" style="background:var(--green-dark)" type="button">Filtrar</button>
          <button class="btn btn-sm btn-outline-secondary" type="button">Exportar</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- KPIs mini -->
<div class="row g-3 mb-2">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="small text-muted">Ítems distintos</div>
        <div class="h4 m-0">124</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="small text-muted">Valor inventario</div>
        <div class="h4 m-0">$ 235,100.00</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="small text-muted">Bajo stock</div>
        <div class="h4 m-0">9</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="small text-muted">Con caducidad &lt; 15 días</div>
        <div class="h4 m-0">3</div>
      </div>
    </div>
  </div>
</div>

<!-- Acciones rápidas -->
<div class="d-flex flex-wrap gap-2 justify-content-between mb-2">
  <div class="small text-muted d-flex align-items-center gap-2">
    <span class="badge rounded-pill text-bg-light">Vista: Stock</span>
    <!-- Si más adelante agregamos "Movimientos" o "Catálogo", podemos alternar aquí -->
  </div>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#mdlKardex">Ver Kardex</button>
    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#offMov">Movimiento rápido</button>
  </div>
</div>

<!-- Tabla de stock -->
<div class="card shadow-sm border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>SKU</th>
            <th>Producto</th>
            <th>UDM base</th>
            <th class="text-end">Existencia</th>
            <th class="text-end">Mín</th>
            <th class="text-end">Máx</th>
            <th class="text-end">Costo (base)</th>
            <th>Sucursal</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>SKU-0001</td>
            <td>Leche entera 1.5L (caja×12)</td>
            <td>ML</td>
            <td class="text-end">18,000.00</td>
            <td class="text-end">5,000</td>
            <td class="text-end">24,000</td>
            <td class="text-end">$ 0.0123</td>
            <td><span class="badge bg-secondary">PRINCIPAL</span></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#offMov">Mover</button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mdlKardex">Kardex</button>
                <button class="btn btn-outline-secondary">Editar</button>
              </div>
            </td>
          </tr>
          <tr>
            <td>SKU-0020</td>
            <td>Café en grano</td>
            <td>G</td>
            <td class="text-end">25,000.00</td>
            <td class="text-end">5,000</td>
            <td class="text-end">40,000</td>
            <td class="text-end">$ 0.3200</td>
            <td><span class="badge bg-secondary">PRINCIPAL</span></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#offMov">Mover</button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mdlKardex">Kardex</button>
                <button class="btn btn-outline-secondary">Editar</button>
              </div>
            </td>
          </tr>
          <tr>
            <td>SKU-0031</td>
            <td>Tortilla de maíz (costal 20kg)</td>
            <td>G</td>
            <td class="text-end text-danger">2,000.00</td>
            <td class="text-end">5,000</td>
            <td class="text-end">20,000</td>
            <td class="text-end">$ 0.0250</td>
            <td><span class="badge bg-secondary">PRINCIPAL</span></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#offMov">Mover</button>
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#mdlKardex">Kardex</button>
                <button class="btn btn-outline-secondary">Editar</button>
              </div>
            </td>
          </tr>
          <!-- ...más filas... -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pie de tabla: paginación / info -->
  <div class="card-footer bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
    <div class="small text-muted">Mostrando 1–15 de 124 items</div>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item disabled"><a class="page-link">«</a></li>
        <li class="page-item active"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#">3</a></li>
        <li class="page-item"><a class="page-link" href="#">»</a></li>
      </ul>
    </nav>
  </div>
</div>

<!-- Offcanvas: Movimiento rápido -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="offMov" style="max-width:420px">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title">Movimiento rápido</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <div class="mb-2">
      <label class="form-label small">Tipo</label>
      <select class="form-select form-select-sm">
        <option>Entrada</option>
        <option>Salida a producción</option>
        <option>Merma</option>
        <option>Ajuste</option>
        <option>Traspaso</option>
        <option>Devolución a proveedor</option>
      </select>
    </div>

    <div class="mb-2">
      <label class="form-label small">Producto</label>
      <input class="form-control form-control-sm" placeholder="Buscar / seleccionar…">
      <div class="form-text">Ej. 'Leche 1.5L (caja×12)'</div>
    </div>

    <div class="row g-2">
      <div class="col-6">
        <label class="form-label small">Cantidad</label>
        <input type="number" step="0.01" class="form-control form-control-sm text-end" value="0">
      </div>
      <div class="col-6">
        <label class="form-label small">UDM</label>
        <select class="form-select form-select-sm">
          <option>ML</option>
          <option>G</option>
          <option>PZA</option>
          <option>CAJA12</option>
        </select>
      </div>
    </div>

    <div class="row g-2 mt-1">
      <div class="col-6">
        <label class="form-label small">Sucursal origen</label>
        <select class="form-select form-select-sm"><option>PRINCIPAL</option></select>
      </div>
      <div class="col-6">
        <label class="form-label small">Sucursal destino</label>
        <select class="form-select form-select-sm"><option>PRINCIPAL</option></select>
      </div>
    </div>

    <div class="row g-2 mt-1">
      <div class="col-6">
        <label class="form-label small">Costo (opcional)</label>
        <input type="number" step="0.0001" class="form-control form-control-sm text-end" placeholder="0.0000">
        <div class="form-text">Para entradas/ajustes de costo</div>
      </div>
      <div class="col-6">
        <label class="form-label small">Lote / Caducidad</label>
        <input type="date" class="form-control form-control-sm">
      </div>
    </div>

    <div class="mb-2 mt-2">
      <label class="form-label small">Notas</label>
      <textarea class="form-control form-control-sm" rows="2" placeholder="Detalle del movimiento…"></textarea>
    </div>

    <div class="d-grid">
      <button class="btn btn-sm btn-primary">Guardar movimiento</button>
    </div>
  </div>
</div>

<!-- Modal: Kardex -->
<div class="modal fade" id="mdlKardex" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Kardex – SKU-0001 · Leche entera 1.5L (caja×12)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha/Hora</th>
                <th>Tipo</th>
                <th>Ref</th>
                <th class="text-end">Entrada</th>
                <th class="text-end">Salida</th>
                <th class="text-end">Saldo</th>
                <th class="text-end">Costo</th>
                <th>Notas</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>2025-08-30 09:15</td>
                <td>Entrada OC</td>
                <td>OC-0009</td>
                <td class="text-end">18,000.00</td>
                <td class="text-end">—</td>
                <td class="text-end">18,000.00</td>
                <td class="text-end">$ 0.0123</td>
                <td>Recepción parcial</td>
              </tr>
              <tr>
                <td>2025-08-30 10:40</td>
                <td>Salida prod.</td>
                <td>OP-001</td>
                <td class="text-end">—</td>
                <td class="text-end">2,400.00</td>
                <td class="text-end">15,600.00</td>
                <td class="text-end">$ 0.0123</td>
                <td>Lattes del día</td>
              </tr>
              <tr>
                <td>2025-08-30 18:10</td>
                <td>Merma</td>
                <td>—</td>
                <td class="text-end">—</td>
                <td class="text-end">300.00</td>
                <td class="text-end">15,300.00</td>
                <td class="text-end">$ 0.0123</td>
                <td>Caducidad</td>
              </tr>
              <!-- ... -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-sm btn-primary">Exportar</button>
      </div>
    </div>
  </div>
</div>

<!-- Ajustes pequeños de estilo para móvil -->
<style>
  @media (max-width: 575.98px) {
    .card .card-body .form-label { margin-bottom: .25rem; }
    .btn-group-sm > .btn, .btn-sm { padding: .35rem .5rem; }
  }
</style>
</div>
</main>