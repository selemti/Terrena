<?php
/* Debe incluirse ANTES de los <script type="module" ...> de caja.
   Requisitos que usa wizard.js:
   - Modal:  #wizardPrecorte  (data-role="precorte")
   - Steps:  #step1, #step2, #step3
   - Barra:  .progress-bar  (data-role="stepbar")
   - Botones: #btnGuardarPrecorte, #btnContinuarConc, #btnSincronizarPOS, #btnIrPostcorte, #btnCerrarSesion
   - Denoms tbody: #tablaDenomsBody
   - Totales/inputs: #precorteTotal, #declCredito, #declDebito, #declTransfer, #notasPaso1
   - Chips/info: [data-role="chip-fondo"], [data-role="ef-esperado"]
   - Conciliación: #concGrid, [data-role="banner-falta-corte"]
   - Hidden id: #precorteId (data-role="precorte-id")
*/
?>
<input type="hidden" id="precorteId" data-role="precorte-id" value="">

<div class="modal fade" id="wizardPrecorte" data-role="precorte" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">Precorte</h5>
          <small class="text-muted" style="display:none">Fondo: <span data-role="chip-fondo">$0.00</span> · Efectivo esperado: <span data-role="ef-esperado">$0.00</span></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">

        <!-- Progreso -->
        <div class="progress mb-3" style="height: 8px;">
          <div class="progress-bar" role="progressbar" data-role="stepbar" style="width: 33%;" aria-valuenow="33" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <!-- STEP 1: Declaración -->
        <section id="step1">
          <div class="row g-3">
            <div class="col-md-7">
              <div class="card">
                <div class="card-header py-2"><strong>Efectivo por denominación</strong></div>
                <div class="card-body p-2">
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead>
                        <tr><th>Denominación</th><th>Cantidad</th><th class="text-end">Importe</th></tr>
                      </thead>
                      <tbody id="tablaDenomsBody"></tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer py-2 d-flex justify-content-between align-items-center">
                  <span class="text-muted small">Total efectivo declarado</span>
                  <strong id="precorteTotal">$0.00</strong>
                </div>
              </div>
            </div>

            <div class="col-md-5">
              <div class="card">
                <div class="card-header py-2"><strong>No efectivo</strong></div>
                <div class="card-body">
                  <div class="mb-2">
                    <label class="form-label mb-1 moneda" for="declCredito">Tarjeta crédito</label>
                    <input id="declCredito" type="number" step="0.01" min="0" class="form-control form-control-sm moneda" data-decimals="2" data-negative="0" placeholder="0.00">
                  </div>
                  <div class="mb-2">
                    <label class="form-label mb-1" for="declDebito">Tarjeta débito</label>
                    <input id="declDebito" type="number" step="0.01" min="0" class="form-control form-control-sm moneda" data-decimals="2" data-negative="0"  placeholder="0.00">
                  </div>
                  <div class="mb-2">
                    <label class="form-label mb-1" for="declTransfer">Transferencias</label>
                    <input id="declTransfer" type="number" step="0.01" min="0" class="form-control form-control-sm moneda" data-decimals="2" data-negative="0"  placeholder="0.00">
                  </div>
                  <div class="mb-2">
                    <label class="form-label mb-1" for="notasPaso1">Notas</label>
                    <textarea id="notasPaso1" class="form-control form-control-sm" rows="3" placeholder="Observaciones del precorte..."></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- STEP 2: Conciliación -->
		<section id="step2" data-step="2" class="d-none">
		  <div class="alert alert-warning d-none" data-role="banner-falta-corte">
			<div class="d-flex align-items-center gap-2">
			  <i class="fa-solid fa-triangle-exclamation"></i>
			  <div>
				<strong>Falta realizar el corte en Floreant POS.</strong><br>
				Realiza el Drawer Pull Report y luego pulsa <em>Sincronizar</em>.
			  </div>
			  <!-- OJO: sin id (usa data-action); el botón del footer ya tiene id="btnSincronizarPOS" -->
			  <button type="button" class="btn btn-sm btn-primary ms-auto" data-action="sincronizar-pos">
				Sincronizar
			  </button>
			</div>
		  </div>

		  <!-- Aquí se pinta la conciliación -->
		  <div id="concGrid" data-role="conc-grid"></div>
		</section>

        <!-- STEP 3: Postcorte (placeholder) -->
        <section id="step3" class="d-none">
          <div class="alert alert-info mb-2">Listo para proceder con postcorte / cierre final.</div>
          <p class="text-muted small mb-0">Este paso puede conectarse con tu flujo de postcorte según reglas del proyecto.</p>
        </section>

      </div>

      <div class="modal-footer">
        <button id="btnGuardarPrecorte"   type="button" class="btn btn-primary">Guardar precorte</button>
        <button id="btnContinuarConc"     type="button" class="btn btn-outline-primary">Continuar</button>
        <button id="btnSincronizarPOS"    type="button" class="btn btn-secondary">Sincronizar POS</button>
        <button id="btnIrPostcorte"       type="button" class="btn btn-success">Ir a Postcorte</button>
        <button id="btnCerrarSesion"      type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
