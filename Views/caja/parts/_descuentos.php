<?php
$rows = $data['descuentos'] ?? [];
?>
<div class="card-vo">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="card-title mb-0"><i class="fa-solid fa-tags me-1"></i> Descuentos</h5>
    <a href="#" class="link-more small">Ver todo <i class="fa-solid fa-chevron-right ms-1"></i></a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr>
        <th>Ticket</th><th>Nombre</th><th>Tipo</th><th class="text-end">Valor</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="text-muted">Sin descuentos en la fecha/terminal seleccionada.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#<?= htmlspecialchars($r['ticket_id']) ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td><?= (int)$r['type'] === 1 ? '%':'' ?></td>
          <td class="text-end" data-amt="<?= (float)($r['value'] ?? 0) ?>">
            <?= number_format((float)($r['value'] ?? 0), 2) ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
