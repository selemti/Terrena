<?php
$rows = $data['tarjetas'] ?? [];
?>
<div class="card-vo">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="card-title mb-0"><i class="fa-regular fa-credit-card me-1"></i> Tarjetas</h5>
    <a href="#" class="link-more small">Ver todo <i class="fa-solid fa-chevron-right ms-1"></i></a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr>
        <th>Ticket</th><th>Tipo</th><th>Auth</th><th>Hora</th><th class="text-end">Monto</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="text-muted">Sin pagos con tarjeta.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td>#<?= htmlspecialchars($r['ticket_id']) ?></td>
          <td><?= htmlspecialchars($r['card_type']) ?></td>
          <td><?= htmlspecialchars($r['card_auth_code']) ?></td>
          <td><?= htmlspecialchars($r['transaction_time']) ?></td>
          <td class="text-end" data-amt="<?= (float)$r['amount'] ?>">
            <?= number_format((float)$r['amount'], 2) ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
