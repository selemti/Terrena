<h2 class="h5 mb-3">Unidades, Conversiones y Presentaciones</h2>

<ul class="nav nave nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUdm">Unidades</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabConv">Conversiones</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPres">Presentaciones</button></li>
</ul>

<div class="tab-content">
  <!-- UDM -->
  <div class="tab-pane fade show active" id="tabUdm">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Catálogo de unidades (maqueta)</div>
      <button class="btn btn-sm btn-primary">Nueva UDM</button>
    </div>
    <table class="table table-sm align-middle">
      <thead><tr><th>Clave</th><th>Nombre</th><th>Tipo</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
        <tr><td>KG</td><td>Kilogramo</td><td>Masa</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
        <tr><td>L</td><td>Litro</td><td>Volumen</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
        <tr><td>ML</td><td>Mililitro</td><td>Volumen</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
        <tr><td>PZA</td><td>Pieza</td><td>Conteo</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Conversiones -->
  <div class="tab-pane fade" id="tabConv">
    <div class="alert alert-info small">Define equivalencias exactas (ej. 1 L = 1000 ML; 1 CAJA = 12 PZA).</div>
    <table class="table table-sm align-middle">
      <thead><tr><th>Desde</th><th class="text-end">Factor</th><th>Hacia</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
        <tr><td>L</td><td class="text-end">1000</td><td>ML</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
        <tr><td>CAJA12</td><td class="text-end">12</td><td>PZA</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
        <tr><td>KG</td><td class="text-end">1000</td><td>G</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr>
      </tbody>
    </table>
  </div>

  <!-- Presentaciones -->
  <div class="tab-pane fade" id="tabPres">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Presentaciones por ítem (compra ↔ stock base ↔ receta).</div>
      <button class="btn btn-sm btn-primary">Nueva presentación</button>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr><th>Ítem</th><th>Compra</th><th>→ Base</th><th>Receta</th><th>→ Base</th><th>UDM base</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <tr>
            <td>Leche entera 1.5L (caja x12)</td>
            <td>1 CAJA12</td><td>12 × 1.5 L = 18,000 ML</td>
            <td>200 ML</td><td>200 ML</td><td>ML</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td>
          </tr>
          <tr>
            <td>Tortilla maíz (costal 20 kg)</td>
            <td>1 COSTAL20KG</td><td>20 KG = 20,000 G</td>
            <td>1 PZA</td><td>1 PZA = 25 G</td><td>G</td>
            <td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>
