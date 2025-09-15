<?php
$rows = $data['retiros'] ?? [];
?>
<div class="card-vo">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="card-title mb-0"><i class="fa-solid fa-wallet me-1"></i> Retiros</h5>
    <a href="#" class="link-more small">Ver todo <i class="fa-solid fa-chevron-right ms-1"></i></a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr>
        <th>Tipo</th><th>Comentario</th><th>Fecha/Hora</th><th class="text-end">Monto</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="text-muted">Sin retiros registrados.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['tipo']) ?></td>
          <td><?= htmlspecialchars($r['comentario']) ?></td>
          <td><?= htmlspecialchars($r['created_at']) ?></td>
          <td class="text-end" data-amt="<?= (float)$r['monto'] ?>">
            <?= number_format((float)$r['monto'], 2) ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
