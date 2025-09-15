<?php
/* Terrena/views/recetas/index.php
 * Maquetación completa del módulo Inventario (sin BD)
 */
$title='<i class="fa-solid fa-boxes-stacked"></i> <span class="label">Recetas & Costos</span>';
?>
<main class="main-content flex-grow-1">
<div class="dashboard-grid">
<h1 class="h4 mb-3"></h1>

<div class="row g-3">
  <div class="col-12 col-lg-4">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white"><strong>Recetario</strong></div>
      <div class="card-body p-2">
        <input class="form-control form-control-sm mb-2" placeholder="Buscar receta...">
        <div class="list-group small">
          <a class="list-group-item list-group-item-action active">Latte</a>
          <a class="list-group-item list-group-item-action">Capuccino</a>
          <a class="list-group-item list-group-item-action">Chai Latte</a>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between">
        <strong>Editar receta: Latte</strong>
        <div class="d-flex gap-2">
          <button class="btn btn-sm btn-outline-secondary">Duplicar</button>
          <button class="btn btn-sm btn-primary">Guardar</button>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small">Nombre</label>
            <input class="form-control form-control-sm" value="Latte">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Porción</label>
            <input class="form-control form-control-sm" value="12 oz">
          </div>
          <div class="col-md-3">
            <label class="form-label small">Merma %</label>
            <input type="number" step="0.01" class="form-control form-control-sm" value="2.0">
          </div>
        </div>
        <div class="table-responsive mt-3">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Ingrediente</th><th class="text-end">Cant.</th><th>UDM</th><th class="text-end">Costo</th><th class="text-end">Subtotal</th><th></th></tr></thead>
            <tbody>
              <tr><td>Espresso</td><td class="text-end">0.03</td><td>Kg</td><td class="text-end">$ 320.00</td><td class="text-end">$ 9.60</td><td class="text-end"><button class="btn btn-sm btn-outline-danger">Quitar</button></td></tr>
              <tr><td>Leche entera</td><td class="text-end">0.25</td><td>L</td><td class="text-end">$ 22.50</td><td class="text-end">$ 5.63</td><td class="text-end"><button class="btn btn-sm btn-outline-danger">Quitar</button></td></tr>
            </tbody>
            <tfoot>
              <tr><th colspan="4" class="text-end">Costo teórico</th><th class="text-end">$ 15.23</th><th></th></tr>
            </tfoot>
          </table>
        </div>
        <div class="row g-2 mt-3">
          <div class="col-md-4">
            <label class="form-label small">Precio venta</label>
            <input class="form-control form-control-sm" value="$ 48.00">
          </div>
          <div class="col-md-4">
            <label class="form-label small">Margen estimado</label>
            <input class="form-control form-control-sm" value="68.3%" readonly>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</main>