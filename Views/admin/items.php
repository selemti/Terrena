<h1 class="h4 mb-3">Items & KDS</h1>

<ul class="nav nave nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabItem">Items</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabKDS">KDS Matrix</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPrecios">Precios por sucursal</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMods">Modificadores</button></li>
</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tabItem">
    <div class="d-flex flex-wrap gap-2 justify-content-between mb-2">
      <input class="form-control form-control-sm" placeholder="Buscar item…">
      <div class="d-flex gap-2">
        <select class="form-select form-select-sm">
          <option>Todas las sucursales</option><option>PRINCIPAL</option>
        </select>
        <button class="btn btn-sm btn-primary">Nuevo item</button>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Item</th><th>Impuesto</th><th>Precio</th><th>Se vende en</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <tr>
            <td>Latte 12oz</td><td>16%</td><td>$ 48.00</td>
            <td><span class="badge bg-secondary">PRINCIPAL</span></td>
            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

<div class="tab-pane fade" id="tabKDS">
  <div class="card-vo p-3">
    <div class="d-flex gap-2 mb-2">
      <select class="form-select form-select-sm w-auto"><option>Sucursal</option><option>PRINCIPAL</option></select>
      <button class="btn btn-sm btn-primary">Nueva estación</button>
    </div>
    <table class="table table-sm">
      <thead><tr><th>Estación</th><th>Descripción</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
        <tr><td>Barista</td><td>Bebidas calientes/frías</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
        <tr><td>Cocina Fría</td><td>Sandwich, ensaladas</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
      </tbody>
    </table>
  </div>
</div>

  <div class="tab-pane fade" id="tabPrecios">
  <div class="card-vo p-3">
    <div class="d-flex gap-2 mb-2">
      <select class="form-select form-select-sm w-auto"><option>Item</option><option>Latte 12oz</option></select>
      <button class="btn btn-sm btn-primary">Agregar precio</button>
    </div>
    <table class="table table-sm">
      <thead><tr><th>Item</th><th>Sucursal</th><th class="text-end">Precio</th><th class="text-end">Acciones</th></tr></thead>
      <tbody><tr><td>Latte 12oz</td><td>PRINCIPAL</td><td class="text-end">$48.00</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr></tbody>
    </table>
  </div>
</div>
<div class="tab-pane fade" id="tabMods">
  <div class="card-vo p-3">
    <div class="d-flex gap-2 mb-2">
      <select class="form-select form-select-sm w-auto"><option>Item</option><option>Latte 12oz</option></select>
      <button class="btn btn-sm btn-primary">Nuevo modificador</button>
    </div>
    <table class="table table-sm">
      <thead><tr><th>Grupo</th><th>Opción</th><th class="text-end">Precio</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
        <tr><td>Leche</td><td>Almendra</td><td class="text-end">$6.00</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
      </tbody>
    </table>
  </div>
</div>


</div>
