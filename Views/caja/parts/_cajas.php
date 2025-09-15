<?php
/**
 * Cajas del día (Listado / Selección)
 * - No altera tu Database.php.
 * - Fuente de datos: 1) API /api/caja/cajas.php  2) fallback: $terminals ya inyectado
 * - Oculta ventas hasta precorte/corte/postcorte (o ?revelar=1).
 */

use Terrena\Core\Database;

if (!class_exists('\Terrena\Core\Database')) {
  require_once __DIR__ . '/../../../Core/Database.php';
}

// -------------------- helpers de vista --------------------
function _v_money($n) {
  if ($n === null) return '$0.00';
  return '$' . number_format((float)$n, 2, '.', ',');
}
function _v_text($s, $def='—') {
  $s = trim((string)$s);
  return $s === '' ? $def : htmlspecialchars($s);
}
function _v_stage_badge($stage) {
  if (!$stage) return '—';
  $s = strtolower((string)$stage);
  $map = [
    'precorte'  => 'badge-precorte',
    'corte'     => 'badge-corte',
    'postcorte' => 'badge-postcorte'
  ];
  $cls = $map[$s] ?? 'badge-soft';
  return '<span class="badge '.$cls.'">'.htmlspecialchars(ucfirst($s)).'</span>';
}
function _v_status_badge(bool $activa, bool $asignada) {
  if ($activa)   return '<span class="badge text-bg-success">Activa</span>';
  if ($asignada) return '<span class="badge text-bg-info">Asignada</span>';
  return '<span class="badge text-bg-secondary">Inactiva</span>';
}
function _v_mask_money($n) {
  // pequeños bullets para “oculto”
  return '<span class="text-muted" title="Se mostrará a partir del precorte">• • •</span>';
}

// -------------------- base y fecha --------------------
$base = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$date = date('Y-m-d');

// Si ?revelar=1, fuerza mostrar montos (útil para pruebas)
$forceReveal = isset($_GET['revelar']) && $_GET['revelar'] == '1';

$terminals = [];
$error     = null;

// -------------------- carga de datos --------------------
try {
  // 1) Intento por API
  $apiUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . $base . '/api/caja/cajas.php';

  $ctx  = stream_context_create(['http' => ['timeout' => 2]]);
  $json = @file_get_contents($apiUrl, false, $ctx);
  if ($json === false) {
    // 2) Fallback: si el controlador ya definió $terminals, úsalo
    if (!isset($GLOBALS['terminals'])) {
      throw new \RuntimeException('API no disponible y no hay datos locales.');
    }
    $terminals = $GLOBALS['terminals'];
  } else {
    $data = json_decode($json, true);
    if (!is_array($data) || !($data['ok'] ?? false)) {
      // 2) Fallback local
      if (!isset($GLOBALS['terminals'])) {
        throw new \RuntimeException('API respondió con error.');
      }
      $terminals = $GLOBALS['terminals'];
    } else {
      $terminals = $data['terminals'] ?? [];
    }
  }
} catch (\Throwable $e) {
  $error = $e->getMessage();
  if (isset($GLOBALS['terminals']) && is_array($GLOBALS['terminals'])) {
    $terminals = $GLOBALS['terminals']; // último recurso
  } else {
    $terminals = [];
  }
}
?>

<div class="card card-vo">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
      <h5 class="card-title mb-0">
        <i class="fa-solid fa-cash-register me-2"></i>
        Cajas del día
        <small class="text-muted">(<?= htmlspecialchars($date) ?> · ventana de corte/asignación)</small>
      </h5>
      <a href="<?= $base ?>/caja/cortes" class="link-more small">Ir a cortes <i class="fa-solid fa-chevron-right ms-1"></i></a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-warning py-2 px-3 mb-3">
        No fue posible obtener datos de POS. <code><?= htmlspecialchars($error) ?></code>
      </div>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="table table-hover align-middle mb-0 responsive-cajas">
        <thead class="table-light sticky-head">
          <tr>
            <th style="width:42px">Seleccionar</th>
            <th>Sucursal</th>
            <th>Caja</th>
            <th>Cajero</th>
            <th>Estatus</th>
            <th>Etapa</th>
            <th class="text-end" style="min-width:220px">Vendido (corte actual)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($terminals)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Sin terminales</td></tr>
          <?php else: ?>
            <?php foreach ($terminals as $t): ?>
              <?php
                $tid   = (int)($t['id'] ?? 0);
                $loc   = $t['location'] ?? '';
                $name  = $t['name'] ?? '';
                $assN  = $t['assigned_name'] ?? null;
                $act   = (bool)($t['status']['activa']   ?? false);
                $asg   = (bool)($t['status']['asignada'] ?? false);
                $stage = $t['stage'] ?? null;

                $canReveal = $forceReveal || in_array(strtolower((string)$stage), ['precorte','corte','postcorte'], true);

                $terminal_total = (float)($t['sales']['terminal_total'] ?? 0);
                $assigned_total = (float)($t['sales']['assigned_total'] ?? 0);
                $others_total   = (float)($t['sales']['others_total'] ?? 0);
                $sellers        = is_array($t['sales']['sellers'] ?? null) ? $t['sales']['sellers'] : [];

                $badgeStatus = _v_status_badge($act, $asg);
                $badgeStage  = _v_stage_badge($stage);

                // Bloque de detalle (solo si canReveal)
                ob_start();
                if ($canReveal) {
                  ?>
                  <div class="cell-sub">
                    Cajero: <?= _v_money($assigned_total) ?>
                    <span class="text-muted">(otros: <?= _v_money($others_total) ?>)</span>
                  </div>
                  <?php if (!empty($sellers)): ?>
                    <div class="sellers-inline mt-1">
                      <?php foreach ($sellers as $s): ?>
                        <span class="seller-chip" title="<?= htmlspecialchars($s['name']) ?>">
                          <?= htmlspecialchars($s['name']) ?> (<?= _v_money($s['total']) ?>)
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <?php
                } else {
                  ?>
                  <div class="cell-sub text-muted">
                    <i class="fa-regular fa-eye-slash me-1"></i> Visible a partir del precorte
                  </div>
                  <?php
                }
                $detailHtml = ob_get_clean();
              ?>
              <tr>
                <td>
                  <input class="form-check-input js-row-picker" type="radio" name="selTerminal" value="<?= $tid ?>">
                </td>

                <td><div class="fw-600"><?= _v_text($loc) ?></div></td>

                <td>
                  <div class="fw-600">
                    #<?= $tid ?>
                    <span class="text-muted">— <?= _v_text($name, '-') ?></span>
                  </div>
                </td>

                <td><?= $assN ? _v_text($assN) : '—' ?></td>

                <td><?= $badgeStatus ?></td>

                <td><?= $badgeStage ?></td>

                <td class="text-end">
                  <div class="fw-700">
                    <?= $canReveal ? _v_money($terminal_total) : _v_mask_money($terminal_total) ?>
                  </div>
                  <?= $detailHtml ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <button id="btnAbrirPrecorte"  class="btn btn-primary" disabled>
        <i class="fa-solid fa-folder-plus me-1"></i> Abrir precorte
      </button>
      <button id="btnIrPrecorte"     class="btn btn-outline-primary" disabled>
        <i class="fa-regular fa-clipboard me-1"></i> Ir a Precorte
      </button>
      <button id="btnIrCorte"        class="btn btn-outline-warning" disabled>
        <i class="fa-regular fa-square-check me-1"></i> Ir a Corte
      </button>
    </div>
  </div>
</div>

<script>
// Habilitar botones al seleccionar una fila
(function(){
  const pickers = document.querySelectorAll('.js-row-picker');
  const b1 = document.getElementById('btnAbrirPrecorte');
  const b2 = document.getElementById('btnIrPrecorte');
  const b3 = document.getElementById('btnIrCorte');
  if (!pickers.length || !b1 || !b2 || !b3) return;

  pickers.forEach(r => r.addEventListener('change', () => {
    b1.disabled = b2.disabled = b3.disabled = false;
  }));
})();
</script>