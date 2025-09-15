<?php
// Ruta base y ruta de partials
$base  = $GLOBALS['__BASE__'] ?? '/terrena/Terrena';
$parts = __DIR__ . '/parts';
?>

<!-- ====== Encabezado que ya tienes (deja igual) ====== -->
<div class="d-flex align-items-center justify-content-between mb-2">
  <h3 class="mb-0"><i class="fa-solid fa-cash-register me-2"></i> Cortes de caja</h3>
  <div>
    <a href="<?= $base ?>/dashboard" class="btn btn-outline-secondary btn-sm">
      <i class="fa-solid fa-arrow-left me-1"></i> Regresar
    </a>
  </div>
</div>

<!-- ====== Nav tabs ====== -->
<ul class="nav nav-tabs" id="tabsCortesCaja" role="tablist">
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
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0"><i class="fa-solid fa-store me-2"></i> Cajas del día (ejemplo)</h5>
            <small class="text-muted">Estos datos se poblarán desde BD en corto</small>
          </div>

          <div class="table-responsive">
            <table class="table align-middle table-hover mb-0">
              <thead>
                <tr>
                  <th>Seleccionar</th>
                  <th>Sucursal</th>
                  <th>Terminal</th>
                  <th>Cajero</th>
                  <th>Estatus</th>
                  <th>Etapa</th>
                  <th class="text-end">Vendido (sistema)</th>
                </tr>
              </thead>
              <tbody id="tabla-cajas">
                <!-- Ejemplo estático. Sustituiremos por datos de BD -->
                <?php
                // ejemplo fijo por sucursal (en vivo vendrá del POS/auxiliares)
                $ejemplo = [
                  ['sucursal'=>'Principal','terminal'=>1,'cajero'=>'Juan','estatus'=>'Abierto','etapa'=>'precorte','vendido'=>3250.50],
                  ['sucursal'=>'NB','terminal'=>1,'cajero'=>'María','estatus'=>'Cerrado','etapa'=>'-','vendido'=>0],
                  ['sucursal'=>'Torre','terminal'=>2,'cajero'=>'Luis','estatus'=>'Abierto','etapa'=>'corte','vendido'=>1980.00],
                  ['sucursal'=>'Terrena','terminal'=>1,'cajero'=>'Ana','estatus'=>'Cerrado','etapa'=>'-','vendido'=>0],
                ];
                foreach ($ejemplo as $i=>$row): ?>
                <tr>
                  <td>
                    <input type="radio" name="selCaja" value="<?= $row['sucursal'].'|'.$row['terminal'] ?>" <?= $i===0?'checked':'' ?>>
                  </td>
                  <td><?= htmlspecialchars($row['sucursal']) ?></td>
                  <td>#<?= (int)$row['terminal'] ?></td>
                  <td><?= htmlspecialchars($row['cajero']) ?></td>
                  <td>
                    <?php if ($row['estatus']==='Abierto'): ?>
                      <span class="badge text-bg-success">Abierto</span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary">Cerrado</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($row['etapa']==='precorte'): ?>
                      <span class="badge bg-info-subtle text-info-emphasis">Precorte</span>
                    <?php elseif ($row['etapa']==='corte'): ?>
                      <span class="badge bg-warning-subtle text-warning-emphasis">Corte</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary-emphasis">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">$<?= number_format((float)$row['vendido'],2) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex gap-2 mt-3">
            <form action="<?= $base ?>/caja/cortes/abrir" method="post" class="d-inline">
              <input type="hidden" name="store_id" id="form-abrir-store">
              <input type="hidden" name="terminal_id" id="form-abrir-terminal">
              <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-folder-plus me-1"></i> Abrir precorte
              </button>
            </form>

            <button type="button" class="btn btn-outline-secondary" id="ir-a-precorte">
              <i class="fa-solid fa-calculator me-1"></i> Ir a Precorte
            </button>
            <button type="button" class="btn btn-outline-warning" id="ir-a-corte">
              <i class="fa-regular fa-clipboard me-1"></i> Ir a Corte
            </button>
          </div>

        </div>
      </div>

	
    <!-- <?php include __DIR__.'/tu_listado_original.php'; ?>  -->
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
        <!-- HISTÓRICO -->
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0"><i class="fa-regular fa-clock me-2"></i> Histórico (ejemplo)</h5>
            <div>
              <button class="btn btn-sm btn-outline-secondary"><i class="fa-regular fa-file-excel me-1"></i> Exportar</button>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Fecha</th><th>Sucursal</th><th>Terminal</th><th>Cajero</th><th>Declarado</th><th>Sistema</th><th>Diferencia</th><th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>2025-09-01</td><td>Principal</td><td>#1</td><td>Juan</td>
                  <td>$5,000.00</td><td>$4,990.00</td><td class="text-danger">-$10.00</td>
                  <td><span class="badge text-bg-success">Cerrado</span></td>
                </tr>
                <tr>
                  <td>2025-08-31</td><td>Torre</td><td>#2</td><td>Luis</td>
                  <td>$3,000.00</td><td>$3,000.00</td><td>$0.00</td>
                  <td><span class="badge text-bg-success">Cerrado</span></td>
                </tr>
              </tbody>
            </table>
          </div>

        </div>
      </div>
  </div>
</div>
