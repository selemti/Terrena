<?php
 
// Cortes de caja: vista montada sobre el layout común
// Usa el mismo patrón que dashboard: $title + ob_start() + render_layout()

$base  = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$title = '<i class="fa-solid fa-cash-register me-2"></i></i> <span class="label">Administración de cajas</span>';
$parts = __DIR__ . '/parts';
// comienza buffer del contenido de la vista
ob_start();
?>
<main class="main-content flex-grow-1">

<div class="dashboard-grid">
<div class="d-flex align-items-center justify-content-between mb-2">
  <h3 class="mb-0"></h3>
  <div>
    <a href="<?= $base ?>/dashboard" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-left me-1"></i> Regresar
    </a>
  </div>
</div>
<!-- ====== Nav tabs ====== -->
<ul class="nav nave nav-tabs" id="tabsCortesCaja" role="tablist">
  <!-- Tabs ORIGINALES (usa tus iconos/textos exactamente como los tenías) -->
  <li class="nav-item" role="presentation">
    <a class="nav-link active" id="tab-listado" data-bs-toggle="tab" href="#pane-listado" role="tab">
      <i class="fa-solid fa-list-ul me-1"></i> Listado / Selección
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-precorte" data-bs-toggle="tab" href="#pane-precorte" role="tab">
      <i class="fa-solid fa-calculator me-1"></i> Precorte
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-corte" data-bs-toggle="tab" href="#pane-corte" role="tab">
      <i class="fa-regular fa-clipboard me-1"></i> Corte
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-postcorte" data-bs-toggle="tab" href="#pane-postcorte" role="tab">
      <i class="fa-solid fa-clipboard-check me-1"></i> Postcorte
    </a>
  </li>

  <!-- NUEVAS pestañas -->
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-descuentos" data-bs-toggle="tab" href="#pane-descuentos" role="tab">
      <i class="fa-solid fa-tags me-1"></i> Descuentos
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-anulaciones" data-bs-toggle="tab" href="#pane-anulaciones" role="tab">
      <i class="fa-regular fa-circle-xmark me-1"></i> Anulaciones
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-retiros" data-bs-toggle="tab" href="#pane-retiros" role="tab">
      <i class="fa-solid fa-wallet me-1"></i> Retiros
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-tarjetas" data-bs-toggle="tab" href="#pane-tarjetas" role="tab">
      <i class="fa-regular fa-credit-card me-1"></i> Tarjetas
    </a>
  </li>
  <li class="nav-item" role="presentation">
    <a class="nav-link" id="tab-otros" data-bs-toggle="tab" href="#pane-otros" role="tab">
      <i class="fa-solid fa-money-check-dollar me-1"></i> Otros pagos
    </a>
  </li>

  <li class="nav-item ms-auto" role="presentation">
    <a class="nav-link" id="tab-historico" data-bs-toggle="tab" href="#pane-historico" role="tab">
      <i class="fa-regular fa-clock me-1"></i> Histórico
    </a>
  </li>
</ul>

<!-- ====== Contenedor de panes ====== -->
<div class="tab-content pt-3" id="tabsCortesCajaContent">


  <!-- === PANE: LISTADO / SELECCIÓN === -->
  <div class="tab-pane fade show active" id="pane-listado" role="tabpanel" aria-labelledby="tab-listado">
    <!-- Pega aquí TU HTML completo de "Listado / Selección" (tabla de cajas del día, botones Abrir precorte/Ir a precorte/Ir a corte, etc.) -->
    <!-- Ejemplo: -->

<?php if (is_file($parts.'/_descuentos.php')) include $parts.'/_cajas.php'; ?> 
	
    <!--  -->
  </div>

  <!-- === PANE: PRECORTE === -->
    <!-- PRECORTE -->
    <div class="tab-pane fade" id="pane-precorte" role="tabpanel" aria-labelledby="tab-precorte">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0"><i class="fa-solid fa-calculator me-2"></i> Precorte (Conteo rápido)</h5>
            <small class="text-muted">Captura denominaciones y declarados</small>
          </div>

          <form action="<?= $base ?>/caja/cortes/precorte" method="post" id="form-precorte" class="row g-3">
            <input type="hidden" name="precorte_id" id="precorte-id" value="">
            <input type="hidden" name="store_id" id="precorte-store" value="">
            <input type="hidden" name="terminal_id" id="precorte-terminal" value="">

            <div class="col-12 col-lg-6">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-3">Conteo de efectivo</h6>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="tabla-denominaciones">
                      <thead>
                        <tr>
                          <th>Denominación</th>
                          <th class="text-center">Piezas</th>
                          <th class="text-end">Importe</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $denos = [1000,500,200,100,50,20,10,5,2,1,0.5];
                        foreach ($denos as $d): ?>
                          <tr>
                            <td>$<?= number_format($d,2) ?></td>
                            <td class="text-center" style="width:120px;">
                              <input type="number" min="0" step="1" class="form-control form-control-sm text-center inp-dq" data-deno="<?= $d ?>" value="0">
                            </td>
                            <td class="text-end deno-imp" data-deno="<?= $d ?>">$0.00</td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                      <tfoot>
                        <tr>
                          <th colspan="2" class="text-end">Total efectivo contado:</th>
                          <th class="text-end" id="total-efectivo">$0.00</th>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-6">
              <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-3">Declarados</h6>
                  <div class="row g-4">
                    <div class="col-12 col-md-4">
                      <label class="form-label">Fondo de Caja</label>
                      <input type="text" class="form-control" name="decl_fondo" id="decl_fondo" value="2500.00">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label">Efectivo</label>
                      <input type="text" class="form-control" name="decl_cash" id="decl-cash" value="0.00">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label">Tarjeta Debito</label>
					  <input type="text" class="form-control" name="decl_card_deb" id="ecl_card_deb" value="0.00">
                    </div>
                    <div class="col-12 col-md-4">
                      <label class="form-label">Tarjeta Credito</label>
					  <input type="text" class="form-control" name="decl_card_deb" id="ecl_card_deb" value="0.00">
                    </div>					
                    <div class="col-12 col-md-4">
                      <label class="form-label">Transferencia</label>
                      <input type="text" class="form-control" name="decl_transfer" id="decl-transfer" value="0.00">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Observaciones</label>
                      <textarea name="notes" class="form-control" rows="2" placeholder="Notas (opcional)"></textarea>
                    </div>
                  </div>

                  <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">
                      <i class="fa-regular fa-floppy-disk me-1"></i> Guardar precorte
                    </button>
                  </div>

                </div>
              </div>
            </div>

          </form>
        </div>
      </div>
    </div>

  <!-- === PANE: CORTE === -->
  <div class="tab-pane fade" id="pane-corte" role="tabpanel" aria-labelledby="tab-corte">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0"><i class="fa-regular fa-clipboard me-2"></i> Corte</h5>
            <small class="text-muted">Comparación declarado vs sistema</small>
          </div>

          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <div class="card border-0 shadow-sm">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-2">Declarado</h6>
                  <ul class="list-unstyled mb-0" id="res-declarado">
                    <li class="d-flex justify-content-between"><span>Efectivo</span><strong id="res-decl-cash">$0.00</strong></li>
                    <li class="d-flex justify-content-between"><span>Tarjeta</span><strong id="res-decl-card">$0.00</strong></li>
                    <li class="d-flex justify-content-between"><span>Transferencia</span><strong id="res-decl-transfer">$0.00</strong></li>
                    <li class="d-flex justify-content-between border-top pt-2 mt-2"><span>Total</span><strong id="res-decl-total">$0.00</strong></li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4">
              <div class="card border-0 shadow-sm">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-2">Sistema</h6>
                  <ul class="list-unstyled mb-0" id="res-sistema">
                    <li class="d-flex justify-content-between"><span>Efectivo</span><strong id="res-sys-cash">$0.00</strong></li>
                    <li class="d-flex justify-content-between"><span>Tarjeta</span><strong id="res-sys-card">$0.00</strong></li>
                    <li class="d-flex justify-content-between"><span>Transferencia</span><strong id="res-sys-transfer">$0.00</strong></li>
                    <li class="d-flex justify-content-between border-top pt-2 mt-2"><span>Total</span><strong id="res-sys-total">$0.00</strong></li>
                  </ul>

                  <div class="d-grid mt-3">
                    <!-- Este botón en vivo haría fetch a /caja/precorte/:id/sistema -->
                    <button class="btn btn-outline-primary" id="btn-cargar-sistema">
                      <i class="fa-solid fa-database me-1"></i> Cargar totales del sistema
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12 col-lg-4">
              <div class="card border-0 shadow-sm">
                <div class="card-body">
                  <h6 class="text-uppercase text-muted mb-2">Diferencia</h6>
                  <div class="d-flex justify-content-between">
                    <span>Declarado - Sistema</span>
                    <strong id="res-diff" class="text-body-emphasis">$0.00</strong>
                  </div>

                  <div class="d-grid gap-2 mt-3">
                    <form action="<?= $base ?>/caja/cortes/cerrar" method="post" id="form-cerrar-corte">
                      <input type="hidden" name="precorte_id" id="cerrar-precorte-id" value="">
                      <button class="btn btn-success" type="submit">
                        <i class="fa-solid fa-lock me-1"></i> Cerrar corte
                      </button>
                    </form>

                    <form action="<?= $base ?>/caja/cerrar-tickets-cero" method="post" id="form-cerrar-cero">
                      <input type="hidden" name="store_id" id="cerrar-cero-store" value="">
                      <input type="hidden" name="terminal_id" id="cerrar-cero-terminal" value="">
                      <button class="btn btn-outline-warning" type="submit">
                        <i class="fa-solid fa-receipt me-1"></i> Cerrar tickets en $0
                      </button>
                    </form>

                    <button class="btn btn-primary" id="btn-conciliar">
                      <i class="fa-solid fa-scale-balanced me-1"></i> Conciliar
                    </button>
                  </div>

                </div>
              </div>
            </div>
          </div> <!-- /row -->
        </div>
      </div>
  </div>

  <!-- === PANE: POSTCORTE === -->
  <div class="tab-pane fade" id="pane-postcorte" role="tabpanel" aria-labelledby="tab-postcorte">
    <!-- Pega aquí TU HTML completo de "Postcorte" -->
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0"><i class="fa-solid fa-clipboard-check me-2"></i> Postcorte</h5>
            <small class="text-muted">Notas y cierre final</small>
          </div>

          <form action="<?= $base ?>/caja/cortes/postcorte" method="post" id="form-postcorte" class="row g-3">
            <input type="hidden" name="precorte_id" id="post-precorte-id" value="">
            <div class="col-12 col-lg-8">
              <label class="form-label">Notas/Observaciones</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Validado por supervisor, diferencias justificadas, etc."></textarea>
            </div>
            <div class="col-12 col-lg-4 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit">
                <i class="fa-regular fa-floppy-disk me-1"></i> Guardar postcorte
              </button>
            </div>
          </form>
        </div>
      </div>
  </div>

  <!-- === NUEVO: DESCUENTOS (partial) === -->
  <div class="tab-pane fade" id="pane-descuentos" role="tabpanel" aria-labelledby="tab-descuentos">
    <?php if (is_file($parts.'/_descuentos.php')) include $parts.'/_descuentos.php'; ?>
  </div>

  <!-- === NUEVO: ANULACIONES (partial) === -->
  <div class="tab-pane fade" id="pane-anulaciones" role="tabpanel" aria-labelledby="tab-anulaciones">
    <?php if (is_file($parts.'/_anulaciones.php')) include $parts.'/_anulaciones.php'; ?>
  </div>

  <!-- === NUEVO: RETIROS (partial) === -->
  <div class="tab-pane fade" id="pane-retiros" role="tabpanel" aria-labelledby="tab-retiros">
    <?php if (is_file($parts.'/_retiros.php')) include $parts.'/_retiros.php'; ?>
  </div>

  <!-- === NUEVO: TARJETAS (partial) === -->
  <div class="tab-pane fade" id="pane-tarjetas" role="tabpanel" aria-labelledby="tab-tarjetas">
    <?php if (is_file($parts.'/_tarjetas.php')) include $parts.'/_tarjetas.php'; ?>
  </div>

  <!-- === NUEVO: OTROS PAGOS (partial) === -->
  <div class="tab-pane fade" id="pane-otros" role="tabpanel" aria-labelledby="tab-otros">
    <?php if (is_file($parts.'/_otros.php')) include $parts.'/_otros.php'; ?>
  </div>

  <!-- === PANE: HISTÓRICO === -->
  <div class="tab-pane fade" id="pane-historico" role="tabpanel" aria-labelledby="tab-historico">
</div>

<div class="container-fluid" style="display:none">

  <div class="row g-3">
    <!-- Col izquierda -->
    <div class="col-12 col-xl-8">
      <div class="card-vo">
        <ul class="nav nav-pills mb-3" id="cortesTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-precorte" data-bs-toggle="pill" data-bs-target="#pane-precorte" type="button" role="tab" aria-controls="pane-precorte" aria-selected="true">
              <i class="fa-solid fa-list-check me-1"></i> Precorte (Conteo rápido)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-listado" data-bs-toggle="pill" data-bs-target="#pane-listado" type="button" role="tab" aria-controls="pane-listado" aria-selected="false">
              <i class="fa-solid fa-clipboard-list me-1"></i> Listado / Selección
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-postcorte" data-bs-toggle="pill" data-bs-target="#pane-postcorte" type="button" role="tab" aria-controls="pane-postcorte" aria-selected="false">
              <i class="fa-solid fa-box-archive me-1"></i> Postcorte (Cierre)
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-historico" data-bs-toggle="pill" data-bs-target="#pane-historico" type="button" role="tab" aria-controls="pane-historico" aria-selected="false">
              <i class="fa-regular fa-clock me-1"></i> Histórico
            </button>
          </li>
        </ul>

        <div class="tab-content" id="cortesTabsContent">
          <!-- PRECORTE -->
          <div class="tab-pane fade show active" id="pane-precorte" role="tabpanel" aria-labelledby="tab-precorte" tabindex="0">
            <h5 class="mb-2">Conteo rápido</h5>
            <p class="text-muted small mb-3">Selecciona la terminal abierta y captura el conteo por denominaciones. (Maquetación; la lógica de BD se cablea después.)</p>

            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label">Terminal abierta</label>
                <select class="form-select">
                  <!-- Placeholder según datos que nos compartiste -->
                  <option value="">Seleccionar...</option>
                  <option value="102">102 - Principal (PRINCIPAL)</option>
                  <option value="101">101 - Principal (PRINCIPAL)</option>
                  <option value="201">201 - NB (NB)</option>
                  <option value="301">301 - Torre (TORRE)</option>
                  <option value="401">401 - Terrena (TERRENA)</option>
                  <option value="9939">SelemTI (SelemTI)</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label">Responsable (opcional)</label>
                <input type="text" class="form-control" placeholder="Nombre del cajero">
              </div>
            </div>

            <hr class="my-3">

            <div class="row g-3">
              <div class="col-6 col-sm-4 col-lg-3">
                <label class="form-label">Billetes $1000</label>
                <input type="number" min="0" class="form-control" placeholder="0">
              </div>
              <div class="col-6 col-sm-4 col-lg-3">
                <label class="form-label">Billetes $500</label>
                <input type="number" min="0" class="form-control" placeholder="0">
              </div>
              <div class="col-6 col-sm-4 col-lg-3">
                <label class="form-label">Billetes $200</label>
                <input type="number" min="0" class="form-control" placeholder="0">
              </div>
              <div class="col-6 col-sm-4 col-lg-3">
                <label class="form-label">Billetes $100</label>
                <input type="number" min="0" class="form-control" placeholder="0">
              </div>
              <div class="col-6 col-sm-4 col-lg-3">
                <label class="form-label">Billetes $50</label>
                <input type="number" min="0" class="form-control" placeholder="0">
              </div>
              <div class="col-6 col-sm-4 col-lg-3">
                <label class="form-label">Monedas (total $)</label>
                <input type="number" min="0" step="0.01" class="form-control" placeholder="0.00">
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-primary"><i class="fa-solid fa-play me-1"></i> Generar Precorte</button>
              <button class="btn btn-outline-secondary"><i class="fa-regular fa-circle-down me-1"></i> Exportar</button>
              <button class="btn btn-outline-secondary"><i class="fa-solid fa-print me-1"></i> Imprimir</button>
            </div>
          </div>

          <!-- LISTADO / SELECCIÓN -->
          <div class="tab-pane fade" id="pane-listado" role="tabpanel" aria-labelledby="tab-listado" tabindex="0">
            <h5 class="mb-2">Terminales y cajas</h5>
            <p class="text-muted small mb-3">Listado/selección de terminales por ubicación y estatus.</p>

            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Terminal</th>
                    <th>Ubicación</th>
                    <th>Cajón</th>
                    <th>Uso</th>
                    <th>Activo</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>102</td>
                    <td>102 - Principal</td>
                    <td>PRINCIPAL</td>
                    <td><span class="badge text-bg-success">Sí</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/caja/cortes">Abrir</a>
                    </td>
                  </tr>
                  <tr>
                    <td>101</td>
                    <td>101 - Principal</td>
                    <td>PRINCIPAL</td>
                    <td><span class="badge text-bg-success">Sí</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/caja/cortes">Abrir</a>
                    </td>
                  </tr>
                  <tr>
                    <td>201</td>
                    <td>201 - NB</td>
                    <td>NB</td>
                    <td><span class="badge text-bg-success">Sí</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/caja/cortes">Abrir</a>
                    </td>
                  </tr>
                  <tr>
                    <td>301</td>
                    <td>301 - Torre</td>
                    <td>TORRE</td>
                    <td><span class="badge text-bg-success">Sí</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/caja/cortes">Abrir</a>
                    </td>
                  </tr>
                  <tr>
                    <td>401</td>
                    <td>401 - Terrena</td>
                    <td>TERRENA</td>
                    <td><span class="badge text-bg-success">Sí</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/caja/cortes">Abrir</a>
                    </td>
                  </tr>
                  <tr>
                    <td>9939</td>
                    <td>SelemTI</td>
                    <td>SelemTI</td>
                    <td><span class="badge text-bg-success">Sí</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td><span class="badge text-bg-secondary">No</span></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="<?= $base ?>/caja/cortes">Abrir</a>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- POSTCORTE -->
          <div class="tab-pane fade" id="pane-postcorte" role="tabpanel" aria-labelledby="tab-postcorte" tabindex="0">
            <h5 class="mb-2">Postcorte (Cierre)</h5>
            <p class="text-muted small mb-3">Resumen por forma de pago, descuentos, anulaciones, retiros y variaciones.</p>

            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <div class="card-vo">
                  <h6 class="mb-2">Totales por forma de pago</h6>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <tbody>
                        <tr><td>Efectivo</td><td class="text-end">$ 0.00</td></tr>
                        <tr><td>Tarjeta</td><td class="text-end">$ 0.00</td></tr>
                        <tr><td>Transferencia</td><td class="text-end">$ 0.00</td></tr>
                        <tr><td>Crédito</td><td class="text-end">$ 0.00</td></tr>
                        <tr class="table-light"><th>Total</th><th class="text-end">$ 0.00</th></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div class="col-12 col-lg-6">
                <div class="card-vo">
                  <h6 class="mb-2">Variaciones</h6>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0">
                      <tbody>
                        <tr><td>Declarado vs Sistema</td><td class="text-end">$ 0.00</td></tr>
                        <tr><td>Retiros</td><td class="text-end">$ 0.00</td></tr>
                        <tr><td>Anulaciones</td><td class="text-end">$ 0.00</td></tr>
                        <tr><td>Descuentos</td><td class="text-end">$ 0.00</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-success"><i class="fa-solid fa-lock me-1"></i> Cerrar corte</button>
              <button class="btn btn-outline-secondary"><i class="fa-solid fa-print me-1"></i> Imprimir resumen</button>
            </div>
          </div>

          <!-- HISTÓRICO -->
          <div class="tab-pane fade" id="pane-historico" role="tabpanel" aria-labelledby="tab-historico" tabindex="0">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h5 class="mb-0">Histórico</h5>
              <div class="d-flex gap-2">
                <input type="date" class="form-control form-control-sm">
                <input type="date" class="form-control form-control-sm">
                <button class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-magnifying-glass me-1"></i> Buscar</button>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th>Fecha / Hora</th>
                    <th>Terminal</th>
                    <th>Usuario</th>
                    <th>Tickets</th>
                    <th>Total</th>
                    <th class="text-end">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>2025-09-02 13:25</td>
                    <td>102 - Principal</td>
                    <td>Juan Pérez</td>
                    <td>45</td>
                    <td class="text-end">$ 12,345.00</td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-secondary"><i class="fa-regular fa-eye"></i></button>
                      <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print"></i></button>
                    </td>
                  </tr>
                  <tr>
                    <td>2025-09-01 22:01</td>
                    <td>201 - NB</td>
                    <td>Ana Gómez</td>
                    <td>30</td>
                    <td class="text-end">$ 8,950.00</td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-secondary"><i class="fa-regular fa-eye"></i></button>
                      <button class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-print"></i></button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

        </div><!-- /.tab-content -->
      </div><!-- /.card-vo -->
    </div><!-- /.col-xl-8 -->

    <!-- Col derecha -->
    <div class="col-12 col-xl-4">
      <div class="sticky-col">
        <!-- Estatus de cajas (la tarjeta que pediste conservar) -->
        <div class="card-vo">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0"><i class="fa-solid fa-cash-register me-1"></i> Estatus de cajas</h5>
            <a href="<?= $base ?>/caja/cortes" class="link-more small">Ir a cortes <i class="fa-solid fa-chevron-right ms-1"></i></a>
          </div>
          <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
              <thead><tr><th>Sucursal</th><th>Estatus</th><th class="text-end">Vendido</th></tr></thead>
              <tbody id="kpi-registers">
                <tr>
                  <td>Principal</td>
                  <td><span class="badge text-bg-success">Abierto</span></td>
                  <td class="text-end">$3,250.50</td>
                </tr>
                <tr>
                  <td>NB</td>
                  <td><span class="badge text-bg-secondary">Cerrado</span></td>
                  <td class="text-end">-</td>
                </tr>
                <tr>
                  <td>Torre</td>
                  <td><span class="badge text-bg-success">Abierto</span></td>
                  <td class="text-end">$1,980.00</td>
                </tr>
                <tr>
                  <td>Terrena</td>
                  <td><span class="badge text-bg-secondary">Cerrado</span></td>
                  <td class="text-end">-</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Partials a la derecha -->
        <?php
          $partsDir = __DIR__ . '/parts';
          $partials = [
            ['file' => '_descuentos.php',  'title' => 'Descuentos',         'icon' => 'fa-solid fa-percent'],
            ['file' => '_anulaciones.php', 'title' => 'Anulaciones',        'icon' => 'fa-solid fa-ban'],
            ['file' => '_retiros.php',     'title' => 'Retiros de dinero',  'icon' => 'fa-solid fa-money-bill-transfer'],
            ['file' => '_tarjetas.php',    'title' => 'Pagos con tarjeta',  'icon' => 'fa-regular fa-credit-card'],
            ['file' => '_otros.php',       'title' => 'Otros movimientos',  'icon' => 'fa-regular fa-file-lines'],
          ];
          foreach ($partials as $p):
            $path = $partsDir . '/' . $p['file'];
        ?>
          <div class="card-vo">
            <h5 class="card-title mb-2"><i class="<?= htmlspecialchars($p['icon']) ?> me-2"></i><?= htmlspecialchars($p['title']) ?></h5>
            <?php if (is_file($path)) {
              include $path;
            } else { ?>
              <div class="text-muted small">Sin datos. (Falta partial: <code><?= htmlspecialchars($p['file']) ?></code>)</div>
            <?php } ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div><!-- /.col-xl-4 -->
  </div><!-- /.row -->
</div><!-- /.container-fluid -->
</div>


</main>


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