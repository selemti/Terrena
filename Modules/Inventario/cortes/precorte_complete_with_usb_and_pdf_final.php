```php
<?php
require_once 'db.php';
require_once 'vendor/autoload.php';
session_start();

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector; // Linux USB
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector; // Windows USB
use Dompdf\Dompdf;

// Autenticación
$user_id = $_SESSION['user_id'] ?? 1;
$user_type = $_SESSION['user_type'] ?? 'CASHIER';
if (!in_array($user_type, ['CASHIER', 'SUPERVISOR'])) {
    die(json_encode(['error' => 'Acceso no autorizado']));
}

// Conexión a PostgreSQL
$conn = pg_connect("host=localhost port=5432 dbname=floreantpos user=postgres password=your_password");
if (!$conn) {
    die(json_encode(['error' => 'Error de conexión a la base de datos']));
}

// Parámetros
$terminal_id = $_GET['terminal_id'] ?? 1;
$cashier_user_id = $_GET['cashier_user_id'] ?? $user_id;
$from_ts = $_GET['from_ts'] ?? date('Y-m-d 00:00:00');
$to_ts = $_GET['to_ts'] ?? date('Y-m-d H:i:s');

// Configuración de impresora
$printer_device = PHP_OS === 'WINNT' ? 'EPSON_TM_T20II' : '/dev/usb/lp0';
$auto_print_pdf = true;

// Obtener umbrales de configuración
$cfg = pg_query($conn, "SELECT key, value FROM public.pc_cfg WHERE key IN ('voids_max', 'discounts_max_mxn')");
$thresholds = [];
while ($row = pg_fetch_assoc($cfg)) {
    $thresholds[$row['key']] = $row['value'];
}
$voids_max = $thresholds['voids_max'] ?? 5;
$discounts_max = $thresholds['discounts_max_mxn'] ?? 100;

// Función para imprimir en térmica
function printPrecorteThermal($precorte_id, $dpr_id, $terminal_id, $cashier_user_id, $from_ts, $to_ts, $opening_cash, $system, $counted_cash, $declared_credit, $declared_debit, $declared_custom, $notes, $tarjetas, $customs, $conn) {
    try {
        $connector = PHP_OS === 'WINNT' 
            ? new WindowsPrintConnector("EPSON_TM_T20II")
            : new FilePrintConnector("/dev/usb/lp0");
        $printer = new Printer($connector);
        $printer->initialize();
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
        $printer->setEmphasis(true);
        $printer->text("=== PRECORTE DE CAJA ===\n");
        $printer->setEmphasis(false);
        $printer->selectPrintMode();
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Folio: $precorte_id\n");
        if ($dpr_id) {
            $printer->text("DPR: $dpr_id\n");
        }
        $printer->text("Terminal: $terminal_id\n");
        $printer->text("Cajero: $cashier_user_id\n");
        $printer->text("Fecha: $from_ts - $to_ts\n");
        $printer->text("Fondo Inicial: $opening_cash MXN\n");
        $printer->text("Efectivo Sistema: " . ($system['sys_cash'] ?? 0) . " MXN\n");
        $printer->text("Efectivo Contado: $counted_cash MXN\n");
        $printer->text("Diferencia Efectivo: " . ($counted_cash - ($system['sys_cash'] + $opening_cash)) . " MXN\n");
        $printer->text("Crédito Declarado: $declared_credit MXN\n");
        $printer->text("Débito Declarado: $declared_debit MXN\n");
        $printer->text("Custom Declarado: $declared_custom MXN\n");
        $printer->text("--- Detalle Tarjetas ---\n");
        foreach ($tarjetas as $tarjeta) {
            $printer->text("{$tarjeta['method']} ({$tarjeta['brand']}, {$tarjeta['bank_terminal']}): {$tarjeta['total']} MXN\n");
        }
        $printer->text("--- Pagos Custom ---\n");
        foreach ($customs as $custom) {
            $printer->text("{$custom['custom_name']} ({$custom['custom_ref']}): {$custom['total']} MXN\n");
        }
        if ($notes) {
            $printer->text("Notas: $notes\n");
        }
        $printer->text("=======================\n");
        $printer->cut();
        $printer->pulse(); // Abre gaveta
        $printer->close();
        return ['status' => 'printed_thermal'];
    } catch (Exception $e) {
        return ['error' => 'Error al imprimir en térmica: ' . $e->getMessage()];
    }
}

// Función para generar PDF
function generatePrecortePDF($precorte_id, $dpr_id, $terminal_id, $cashier_user_id, $from_ts, $to_ts, $opening_cash, $system, $counted_cash, $declared_credit, $declared_debit, $declared_custom, $notes, $tarjetas, $customs, $action = 'download', $auto_print = false) {
    $dompdf = new Dompdf();
    $dompdf->setPaper('B7', 'portrait');
    ob_start();
    ?>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; font-weight: bold; }
        .row { margin: 5px 0; }
    </style>
    <div class="header">=== PRECORTE DE CAJA ===</div>
    <div class="row">Folio: <?php echo $precorte_id; ?></div>
    <?php if ($dpr_id): ?>
        <div class="row">DPR: <?php echo $dpr_id; ?></div>
    <?php endif; ?>
    <div class="row">Terminal: <?php echo $terminal_id; ?></div>
    <div class="row">Cajero: <?php echo $cashier_user_id; ?></div>
    <div class="row">Fecha: <?php echo $from_ts . ' - ' . $to_ts; ?></div>
    <div class="row">Fondo Inicial: <?php echo $opening_cash; ?> MXN</div>
    <div class="row">Efectivo Sistema: <?php echo $system['sys_cash'] ?? 0; ?> MXN</div>
    <div class="row">Efectivo Contado: <?php echo $counted_cash; ?> MXN</div>
    <div class="row">Diferencia Efectivo: <?php echo $counted_cash - ($system['sys_cash'] + $opening_cash); ?> MXN</div>
    <div class="row">Crédito Declarado: <?php echo $declared_credit; ?> MXN</div>
    <div class="row">Débito Declarado: <?php echo $declared_debit; ?> MXN</div>
    <div class="row">Custom Declarado: <?php echo $declared_custom; ?> MXN</div>
    <div class="row">--- Detalle Tarjetas ---</div>
    <?php foreach ($tarjetas as $tarjeta): ?>
        <div class="row"><?php echo htmlspecialchars("{$tarjeta['method']} ({$tarjeta['brand']}, {$tarjeta['bank_terminal']}): {$tarjeta['total']} MXN"); ?></div>
    <?php endforeach; ?>
    <div class="row">--- Pagos Custom ---</div>
    <?php foreach ($customs as $custom): ?>
        <div class="row"><?php echo htmlspecialchars("{$custom['custom_name']} ({$custom['custom_ref']}): {$custom['total']} MXN"); ?></div>
    <?php endforeach; ?>
    <?php if ($notes): ?>
        <div class="row">Notas: <?php echo htmlspecialchars($notes); ?></div>
    <?php endif; ?>
    <div class="header">=======================</div>
    <?php
    $html = ob_get_clean();
    $dompdf->loadHtml($html);
    $dompdf->render();
    
    if ($action === 'download') {
        header("Content-type: application/pdf");
        header("Content-Disposition: attachment; filename=precorte_$precorte_id.pdf");
        echo $dompdf->output();
        exit;
    } elseif ($action === 'print' && $auto_print) {
        $pdf_path = sys_get_temp_dir() . "/precorte_$precorte_id.pdf";
        file_put_contents($pdf_path, $dompdf->output());
        if (PHP_OS === 'WINNT') {
            exec("print /d:\\\\.\\EPSON_TM_T20II \"$pdf_path\"");
        } else {
            exec("lp -d EPSON_TM_T20II \"$pdf_path\"");
        }
        return ['status' => 'printed_pdf'];
    }
    return ['status' => 'pdf_generated'];
}

// Manejo de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Cerrar tickets en $0
    if ($action === 'close_zero') {
        $result = pg_query_params($conn, "SELECT public.close_zero_tickets($1, $2, $3, $4)",
            [$terminal_id, $cashier_user_id, $from_ts, $to_ts]);
        $closed_count = pg_fetch_result($result, 0, 0);
        echo json_encode(['closed_count' => $closed_count]);
        exit;
    }

    // Cobrar ticket
    if ($action === 'charge') {
        $ticket_id = $_POST['ticket_id'];
        $payment_type = $_POST['payment_type'];
        $amount = $_POST['amount'];
        $custom_name = $_POST['custom_name'] ?? '';
        $card_type = $_POST['card_type'] ?? '';
        $card_reader = $_POST['card_reader'] ?? '';

        pg_query($conn, "BEGIN");
        $result = pg_query_params($conn, "
            INSERT INTO public.transactions (
                ticket_id, terminal_id, user_id, payment_type, amount, custom_payment_name, card_type, card_reader, transaction_time
            ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, now())
            RETURNING id",
            [$ticket_id, $terminal_id, $cashier_user_id, $payment_type, $amount, $custom_name, $card_type, $card_reader]);
        if ($result) {
            pg_query_params($conn, "
                UPDATE public.ticket
                SET closed = true, paid = true, modified_time = now()
                WHERE id = $1",
                [$ticket_id]);
            pg_query($conn, "COMMIT");
            echo json_encode(['status' => 'charged', 'ticket_id' => $ticket_id]);
        } else {
            pg_query($conn, "ROLLBACK");
            echo json_encode(['error' => 'Error al registrar pago']);
        }
        exit;
    }

    // Anular ticket
    if ($action === 'void') {
        $ticket_id = $_POST['ticket_id'];
        $reason_id = $_POST['reason_id'];
        $notes = $_POST['notes'] ?? '';

        pg_query($conn, "BEGIN");
        $result = pg_query_params($conn, "
            UPDATE public.ticket
            SET voided = true, closed = true, modified_time = now()
            WHERE id = $1",
            [$ticket_id]);
        if ($result) {
            pg_query_params($conn, "
                INSERT INTO public.action_history (action_time, action_name, description, user_id, terminal_id)
                VALUES (now(), 'VOID_TICKET', $1, $2, $3)",
                ["Anulación: $notes (Razón ID: $reason_id)", $cashier_user_id, $terminal_id]);
            pg_query($conn, "COMMIT");
            echo json_encode(['status' => 'voided', 'ticket_id' => $ticket_id]);
        } else {
            pg_query($conn, "ROLLBACK");
            echo json_encode(['error' => 'Error al anular ticket']);
        }
        exit;
    }

    // Guardar precorte
    if ($action === 'save_precorte') {
        // Validar tickets abiertos
        $open_tickets = pg_query_params($conn, "
            SELECT open_tickets_cnt FROM public.fn_precorte_alertas($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $open_count = pg_fetch_result($open_tickets, 0, 'open_tickets_cnt');
        if ($open_count > 0 && $user_type !== 'SUPERVISOR') {
            die(json_encode(['error' => 'No puede realizar precorte con tickets abiertos']));
        }

        // Validar umbrales
        $alerts = pg_query_params($conn, "
            SELECT voided_tickets_cnt, total_discounts
            FROM public.fn_precorte_alertas($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $alert_data = pg_fetch_assoc($alerts);
        $voided_count = $alert_data['voided_tickets_cnt'] ?? 0;
        $total_discounts = $alert_data['total_discounts'] ?? 0;

        if ($voided_count > $voids_max || $total_discounts > $discounts_max) {
            if ($user_type !== 'SUPERVISOR') {
                die(json_encode(['error' => "Umbrales excedidos: $voided_count anulaciones (>$voids_max), $total_discounts MXN en descuentos (>$discounts_max)"]));
            } else {
                pg_query_params($conn, "
                    INSERT INTO public.action_history (action_time, action_name, description, user_id, terminal_id)
                    VALUES (now(), 'THRESHOLD_OVERRIDE', $1, $2, $3)",
                    ["Override de umbrales: $voided_count anulaciones, $total_discounts MXN descuentos", $user_id, $terminal_id]);
            }
        }

        // Validar conteo rápido
        $opening_cash = $_POST['opening_cash'] ?? 0;
        $counted_cash = $_POST['counted_cash'] ?? 0;
        $declared_credit = $_POST['declared_credit'] ?? 0;
        $declared_debit = $_POST['declared_debit'] ?? 0;
        $declared_custom = $_POST['declared_custom'] ?? 0;
        $notes = $_POST['notes'] ?? '';
        $denoms = $_POST['denoms'] ?? [];

        $total_counted = 0;
        foreach ($denoms as $denom => $qty) {
            $total_counted += $denom * $qty;
        }
        if (abs($total_counted - $counted_cash) > 0.01) {
            die(json_encode(['error' => 'Conteo rápido no coincide con total de efectivo']));
        }

        // Guardar precorte
        pg_query($conn, "BEGIN");
        $result = pg_query_params($conn, "
            INSERT INTO public.pc_precorte (
                terminal_id, terminal_location, cashier_user_id, from_ts, to_ts,
                opening_cash, system_cash, system_credit, system_debit, system_custom,
                system_payouts, counted_cash, declared_credit, declared_debit, declared_custom,
                status, created_by, notes, supervisor_id
            )
            SELECT $1, t.location, $2, $3, $4, $5, sys_cash, sys_credit, sys_debit, sys_custom_total,
                   sys_payouts, $6, $7, $8, $9, 'SUBMITTED', $2, $10, $11
            FROM public.fn_precorte_sistema($3, $4, $1, $2) s
            JOIN public.terminal t ON t.id = $1
            RETURNING id, dpr_id",
            [$terminal_id, $cashier_user_id, $from_ts, $to_ts, $opening_cash,
             $counted_cash, $declared_credit, $declared_debit, $declared_custom, $notes,
             ($user_type === 'SUPERVISOR' ? $user_id : null)]);
        if ($result) {
            $precorte = pg_fetch_assoc($result);
            $precorte_id = $precorte['id'];
            $dpr_id = $precorte['dpr_id'];
            foreach ($denoms as $denom => $qty) {
                $subtotal = $denom * $qty;
                if ($subtotal > 0) {
                    pg_query_params($conn, "
                        INSERT INTO public.pc_precorte_cash_count (precorte_id, denom, qty, subtotal)
                        VALUES ($1, $2, $3, $4)",
                        [$precorte_id, $denom, $qty, $subtotal]);
                }
            }
            pg_query($conn, "COMMIT");

            // Obtener datos del sistema
            $system_data = pg_query_params($conn, "
                SELECT * FROM public.fn_precorte_sistema($1, $2, $3, $4)",
                [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
            $system = pg_fetch_assoc($system_data) ?: [];

            // Obtener detalle de tarjetas
            $tarjetas_data = pg_query_params($conn, "
                SELECT * FROM public.fn_precorte_tarjetas_detalle($1, $2, $3, $4)",
                [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
            $tarjetas = pg_fetch_all($tarjetas_data) ?: [];

            // Obtener detalle de custom payments
            $customs_data = pg_query_params($conn, "
                SELECT * FROM public.fn_precorte_customs($1, $2, $3, $4)",
                [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
            $customs = pg_fetch_all($customs_data) ?: [];

            // Imprimir en térmica
            $print_thermal_result = printPrecorteThermal($precorte_id, $dpr_id, $terminal_id, $cashier_user_id, $from_ts, $to_ts, $opening_cash, $system, $counted_cash, $declared_credit, $declared_debit, $declared_custom, $notes, $tarjetas, $customs, $conn);

            // Generar PDF
            if ($auto_print_pdf) {
                $print_pdf_result = generatePrecortePDF($precorte_id, $dpr_id, $terminal_id, $cashier_user_id, $from_ts, $to_ts, $opening_cash, $system, $counted_cash, $declared_credit, $declared_debit, $declared_custom, $notes, $tarjetas, $customs, 'print', true);
            } else {
                $print_pdf_result = ['status' => 'pdf_skipped'];
            }

            echo json_encode(array_merge(
                ['status' => 'precorte_saved', 'precorte_id' => $precorte_id],
                $print_thermal_result,
                $print_pdf_result
            ));
        } else {
            pg_query($conn, "ROLLBACK");
            echo json_encode(['error' => 'Error al guardar precorte']);
        }
        exit;
    }

    // Descargar PDF
    if ($action === 'download_pdf') {
        $precorte_id = $_POST['precorte_id'];
        $system_data = pg_query_params($conn, "
            SELECT * FROM public.fn_precorte_sistema($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $system = pg_fetch_assoc($system_data) ?: [];
        $tarjetas_data = pg_query_params($conn, "
            SELECT * FROM public.fn_precorte_tarjetas_detalle($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $tarjetas = pg_fetch_all($tarjetas_data) ?: [];
        $customs_data = pg_query_params($conn, "
            SELECT * FROM public.fn_precorte_customs($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $customs = pg_fetch_all($customs_data) ?: [];
        $precorte_data = pg_query_params($conn, "
            SELECT opening_cash, counted_cash, declared_credit, declared_debit, declared_custom, notes, dpr_id
            FROM public.pc_precorte
            WHERE id = $1",
            [$precorte_id]);
        $precorte = pg_fetch_assoc($precorte_data);
        generatePrecortePDF($precorte_id, $precorte['dpr_id'], $terminal_id, $cashier_user_id, $from_ts, $to_ts, $precorte['opening_cash'], $system, $precorte['counted_cash'], $precorte['declared_credit'], $precorte['declared_debit'], $precorte['declared_custom'], $precorte['notes'], $tarjetas, $customs, 'download');
        exit;
    }

    // Imprimir PDF
    if ($action === 'print_pdf') {
        $precorte_id = $_POST['precorte_id'];
        $system_data = pg_query_params($conn, "
            SELECT * FROM public.fn_precorte_sistema($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $system = pg_fetch_assoc($system_data) ?: [];
        $tarjetas_data = pg_query_params($conn, "
            SELECT * FROM public.fn_precorte_tarjetas_detalle($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $tarjetas = pg_fetch_all($tarjetas_data) ?: [];
        $customs_data = pg_query_params($conn, "
            SELECT * FROM public.fn_precorte_customs($1, $2, $3, $4)",
            [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
        $customs = pg_fetch_all($customs_data) ?: [];
        $precorte_data = pg_query_params($conn, "
            SELECT opening_cash, counted_cash, declared_credit, declared_debit, declared_custom, notes, dpr_id
            FROM public.pc_precorte
            WHERE id = $1",
            [$precorte_id]);
        $precorte = pg_fetch_assoc($precorte_data);
        $result = generatePrecortePDF($precorte_id, $precorte['dpr_id'], $terminal_id, $cashier_user_id, $from_ts, $to_ts, $precorte['opening_cash'], $system, $precorte['counted_cash'], $precorte['declared_credit'], $precorte['declared_debit'], $precorte['declared_custom'], $precorte['notes'], $tarjetas, $customs, 'print', true);
        echo json_encode($result);
        exit;
    }
}

// Listar tickets abiertos
$open_tickets = pg_query_params($conn, "
    SELECT * FROM public.fn_open_tickets($1, $2, $3, $4)",
    [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
$tickets = pg_fetch_all($open_tickets) ?: [];

// Obtener razones de anulación
$reasons = pg_query($conn, "SELECT id, name FROM public.payout_reasons");
$reason_list = pg_fetch_all($reasons) ?: [];

// Obtener datos del sistema
$system_data = pg_query_params($conn, "
    SELECT * FROM public.fn_precorte_sistema($1, $2, $3, $4)",
    [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
$system = pg_fetch_assoc($system_data) ?: [];

// Obtener fondo inicial
$opening_cash = pg_query_params($conn, "
    SELECT COALESCE(opening_balance, 0) AS opening_cash
    FROM public.terminal
    WHERE id = $1",
    [$terminal_id]);
$opening_cash = pg_fetch_result($opening_cash, 0, 'opening_cash') ?: 0;

// Validar umbrales
$alerts = pg_query_params($conn, "
    SELECT voided_tickets_cnt, total_discounts
    FROM public.fn_precorte_alertas($1, $2, $3, $4)",
    [$from_ts, $to_ts, $terminal_id, $cashier_user_id]);
$alert_data = pg_fetch_assoc($alerts);
$voided_count = $alert_data['voided_tickets_cnt'] ?? 0;
$total_discounts = $alert_data['total_discounts'] ?? 0;
$threshold_alert = ($voided_count > $voids_max || $total_discounts > $discounts_max)
    ? "Alerta: $voided_count anulaciones (>$voids_max), $total_discounts MXN en descuentos (>$discounts_max)"
    : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Precorte de Caja con Conteo Rápido</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .error { color: red; }
        .success { color: green; }
        .alert { color: orange; font-weight: bold; }
    </style>
    <script>
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('input[name^="denoms"]').forEach(input => {
                const denom = parseFloat(input.name.match(/\[([\d.]+)\]/)[1]);
                const qty = parseInt(input.value) || 0;
                total += denom * qty;
            });
            document.getElementById('counted_cash').value = total.toFixed(2);
            const sys_cash = parseFloat(<?php echo json_encode($system['sys_cash'] ?? 0); ?>);
            const opening_cash = parseFloat(<?php echo json_encode($opening_cash); ?>);
            const expected = (sys_cash + opening_cash).toFixed(2);
            const diff = (total - expected).toFixed(2);
            document.getElementById('cash_diff').innerText = `Diferencia: ${diff} MXN`;
            document.getElementById('cash_diff').style.color = diff == 0 ? 'green' : 'red';
        }
    </script>
</head>
<body>
    <h1>Precorte de Caja</h1>
    <form method="GET">
        <label>Terminal ID: <input type="number" name="terminal_id" value="<?php echo $terminal_id; ?>" required></label><br>
        <label>Cajero ID: <input type="number" name="cashier_user_id" value="<?php echo $cashier_user_id; ?>" required></label><br>
        <label>Desde: <input type="datetime-local" name="from_ts" value="<?php echo str_replace(' ', 'T', $from_ts); ?>" required></label><br>
        <label>Hasta: <input type="datetime-local" name="to_ts" value="<?php echo str_replace(' ', 'T', $to_ts); ?>"></label><br>
        <button type="submit">Ver Tickets Abiertos</button>
    </form>

    <?php if ($threshold_alert): ?>
        <p class="alert"><?php echo $threshold_alert; ?><?php if ($user_type !== 'SUPERVISOR'): ?> (Requiere autorización de supervisor)</p><?php endif; ?>
    <?php endif; ?>

    <h2>Tickets Abiertos (<?php echo count($tickets); ?>)</h2>
    <?php if (count($tickets) > 0): ?>
        <form method="POST">
            <input type="hidden" name="terminal_id" value="<?php echo $terminal_id; ?>">
            <input type="hidden" name="cashier_user_id" value="<?php echo $cashier_user_id; ?>">
            <input type="hidden" name="from_ts" value="<?php echo $from_ts; ?>">
            <input type="hidden" name="to_ts" value="<?php echo $to_ts; ?>">
            <input type="hidden" name="action" value="close_zero">
            <button type="submit">Cerrar Tickets en $0</button>
        </form>
        <table>
            <tr>
                <th>Ticket ID</th>
                <th>Total</th>
                <th>Descuento</th>
                <th>¿En $0?</th>
                <th>Acción</th>
            </tr>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?php echo $ticket['ticket_id']; ?></td>
                    <td><?php echo $ticket['total_amount']; ?></td>
                    <td><?php echo $ticket['discount_amount']; ?></td>
                    <td><?php echo $ticket['is_zero'] ? 'Sí' : 'No'; ?></td>
                    <td>
                        <?php if (!$ticket['is_zero']): ?>
                            <form method="POST">
                                <input type="hidden" name="terminal_id" value="<?php echo $terminal_id; ?>">
                                <input type="hidden" name="cashier_user_id" value="<?php echo $cashier_user_id; ?>">
                                <input type="hidden" name="from_ts