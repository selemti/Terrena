<?php $title='<i class="fa-solid fa-gear"></i> Configurarión'; ?>
<main class="main-content flex-grow-1">
<div class="dashboard-grid">

<ul class="nav nave nav-tabs mb-3">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabUsers">Usuarios</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabRoles">Roles & Permisos</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSuc">Sucursales & Terminales</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabParams">Parámetros</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabCfg">Configuración</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPOS">Integración POS</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabJobs">Jobs & Log</button></li>

</ul>

<div class="tab-content">
  <div class="tab-pane fade show active" id="tabUsers">
    <div class="d-flex justify-content-between mb-2">
      <div class="small text-muted">Usuarios (maqueta)</div>
      <button class="btn btn-sm btn-primary">Nuevo usuario</button>
    </div>
    <table class="table table-sm">
      <thead><tr><th>Usuario</th><th>Rol</th><th>Sucursal</th><th class="text-end">Acciones</th></tr></thead>
      <tbody><tr><td>jperez</td><td>Supervisor</td><td>PRINCIPAL</td><td class="text-end"><button class="btn btn-sm btn-outline-secondary">Editar</button></td></tr></tbody>
    </table>
  </div>

  <div class="tab-pane fade" id="tabRoles">
    <div class="alert alert-info">Configura permisos granulares (ver/editar/aprobar, ver costos, reset folio…)</div>
  </div>

  <div class="tab-pane fade" id="tabSuc">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Sucursal"></div>
          <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Timezone (p.ej. America/Mexico_City)"></div>
          <div class="col-md-4"><button class="btn btn-sm btn-primary">Agregar</button></div>
        </div>
        <hr>
        <div class="small text-muted">Listado (maqueta)</div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item d-flex justify-content-between align-items-center">
            PRINCIPAL <span class="badge bg-secondary">KDS habilitado</span>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="tab-pane fade" id="tabParams">
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label small">Moneda</label>
        <input class="form-control form-control-sm" value="MXN">
      </div>
      <div class="col-md-3">
        <label class="form-label small">IVA %</label>
        <input class="form-control form-control-sm" value="16">
      </div>
      <div class="col-md-3">
        <label class="form-label small">Redondeo</label>
        <select class="form-select form-select-sm">
          <option>0.01</option><option>0.10</option><option>1.00</option>
        </select>
      </div>
      <div class="col-md-3 d-flex align-items-end justify-content-end">
        <button class="btn btn-sm btn-primary">Guardar</button>
      </div>
    </div>
  </div>
  <div class="tab-pane fade" id="tabCfg">
    <div class="card-vo p-3">
      <div class="row g-2">
        <div class="col-md-4"><label class="form-label small">Moneda</label><input class="form-control form-control-sm" value="MXN"></div>
        <div class="col-md-4"><label class="form-label small">IVA (%)</label><input class="form-control form-control-sm" value="16"></div>
        <div class="col-md-4"><label class="form-label small">Zona horaria</label><input class="form-control form-control-sm" value="America/Mexico_City"></div>
      </div>
      <div class="text-end mt-2"><button class="btn btn-sm btn-primary">Guardar</button></div>
    </div>
  </div>
  <div class="tab-pane fade" id="tabPOS">
    <div class="card-vo p-3 mb-3">
      <h5 class="card-title"><i class="fa-solid fa-plug"></i> Endpoints & credenciales</h5>
      <div class="row g-2">
        <div class="col-md-6"><input class="form-control form-control-sm" placeholder="URL API POS"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Usuario"></div>
        <div class="col-md-3"><input class="form-control form-control-sm" placeholder="Password" type="password"></div>
      </div>
      <div class="text-end mt-2"><button class="btn btn-sm btn-outline-secondary">Probar</button></div>
    </div>
    <div class="card-vo p-3">
      <h5 class="card-title"><i class="fa-solid fa-object-group"></i> Mapeo de datos</h5>
      <div class="row g-2">
        <div class="col-md-4"><select class="form-select form-select-sm"><option>Categorías POS → Categorías Terrena</option></select></div>
        <div class="col-md-4"><select class="form-select form-select-sm"><option>Items POS → Items Terrena</option></select></div>
        <div class="col-md-4"><select class="form-select form-select-sm"><option>Impuestos POS → Impuestos Terrena</option></select></div>
      </div>
      <div class="text-end mt-2"><button class="btn btn-sm btn-primary">Guardar mapeo</button></div>
    </div>
  </div>
  <div class="tab-pane fade" id="tabJobs">
    <div class="card-vo p-3">
      <h5 class="card-title"><i class="fa-regular fa-clock"></i> Sincronizaciones</h5>
      <table class="table table-sm">
        <thead><tr><th>Job</th><th>Frecuencia</th><th>Última</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
          <tr><td>Importar ventas</td><td>cada 5 min</td><td>hoy 12:03</td><td class="text-end">
            <div class="btn-group btn-group-sm"><button class="btn btn-outline-secondary">Ejecutar</button><button class="btn btn-outline-secondary">Log</button></div>
          </td></tr>
        </tbody>
      </table>
    </div>
</div>
</div>


</div>
</main>