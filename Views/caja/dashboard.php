<?php ob_start(); ?>
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 m-0">Dashboard</h1>
  <form class="d-flex gap-2">
    <input type="date" name="from" value="<?=htmlspecialchars($_GET['from'] ?? date('Y-m-d'))?>" class="form-control form-control-sm">
    <input type="date" name="to"   value="<?=htmlspecialchars($_GET['to']   ?? date('Y-m-d'))?>" class="form-control form-control-sm">
    <input type="text" name="branch" placeholder="Sucursal (opcional)" value="<?=htmlspecialchars($_GET['branch'] ?? '')?>" class="form-control form-control-sm" />
    <button class="btn btn-sm text-white" style="background:var(--green-dark)">Aplicar</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-12 col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="small text-muted">Ventas (rango)</div>
        <div class="display-6 fw-bold">$ <?=$total_sales?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="small text-muted">Tickets</div>
        <div class="h2 fw-bold"><?=$tickets?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card shadow-sm border-0">
      <div class="card-body">
        <div class="small text-muted">Ticket promedio</div>
        <div class="h2 fw-bold">$ <?=$avg_ticket?></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white">
        <strong>Mix por forma de pago</strong>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Método</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
              <?php foreach($mix as $m): ?>
                <tr>
                  <td><?=$m['pay_method']?></td>
                  <td class="text-end">$ <?=number_format($m['amount'],2)?></td>
                </tr>
              <?php endforeach; if(empty($mix)): ?>
                <tr><td colspan="2" class="text-center text-muted small">Sin datos</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white">
        <strong>Top 10 productos</strong>
      </div>
      <div class="card-body">
        <div class="table-responsive" style="max-height: 340px;">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Producto</th><th class="text-end">Cant.</th><th class="text-end">Ventas</th></tr></thead>
            <tbody>
              <?php foreach($top as $p): ?>
                <tr>
                  <td><?=$p['product_name']?></td>
                  <td class="text-end"><?=number_format($p['qty'])?></td>
                  <td class="text-end">$ <?=number_format($p['sales'],2)?></td>
                </tr>
              <?php endforeach; if(empty($top)): ?>
                <tr><td colspan="3" class="text-center text-muted small">Sin datos</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Ventas por sucursal (rango)</strong>
        <span class="small text-muted">Ordenado por total</span>
      </div>
      <div class="card-body">
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Sucursal</th><th class="text-end">Tickets</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <?php foreach($byBranch as $b): ?>
              <tr>
                <td><?=$b['branch_key']?></td>
                <td class="text-end"><?=number_format($b['tickets'])?></td>
                <td class="text-end">$ <?=number_format($b['total'],2)?></td>
              </tr>
            <?php endforeach; if(empty($byBranch)): ?>
              <tr><td colspan="3" class="text-center text-muted small">Sin datos</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mt-1">
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white"><strong>Estado del último corte por sucursal</strong></div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Sucursal</th><th>Shift</th><th>Status</th><th>Abrió</th><th>Cerró</th></tr></thead>
            <tbody>
              <?php foreach($lastShift as $s): ?>
              <tr>
                <td><?=$s['branch_key']?></td>
                <td>#<?=$s['shift_id']?></td>
                <td><span class="badge <?= $s['status']==='POSTCLOSED'?'bg-success':($s['status']==='CLOSED'?'bg-primary':'bg-warning') ?>">
                    <?=$s['status']?>
                </span></td>
                <td><?= $s['opened_at'] ?></td>
                <td><?= $s['closed_at'] ?? '—' ?></td>
              </tr>
              <?php endforeach; if(empty($lastShift)): ?>
              <tr><td colspan="5" class="text-center text-muted small">Sin datos</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* toque corporativo mínimo si ya usas voceo.css */
.card-header { border-bottom: 1px solid rgba(0,0,0,.05); }
.btn[style*="--green-dark"], .bg-brand { background: var(--green-dark); color: #fff; }
</style>

<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
