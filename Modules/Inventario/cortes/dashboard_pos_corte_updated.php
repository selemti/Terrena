<?php
require_once '../config.php';
require_once '../vendor/autoload.php';
session_start();

use Dompdf\Dompdf;

// Autenticación
$user_id = $_SESSION['user_id'] ?? 1;
$user_type = $_SESSION['user_type'] ?? 'CASHIER';
if (!in_array($user_type, ['CASHIER', 'SUPERVISOR'])) {
    die(json_encode(['error' => 'Acceso no autorizado']));
}

// Conexión a PostgreSQL
$conn = pg_connect("host=localhost port=5433 dbname=pos user=postgres password=T3rr3n4#p0s");
if (!$conn) {
    die(json_encode(['error' => 'Error de conexión a la base de datos']));
}

// Parámetros
$terminal_id = $_GET['terminal_id'] ?? 1;
$cashier_user_id = $_GET['cashier_user_id'] ?? $user_id;
$from_ts = $_GET['from_ts'] ?? date('Y-m-d 00:00:00', strtotime('-7 days'));
$to_ts = $_GET['to_ts'] ?? date('Y-m-d H:i:s');

// Obtener KPIs
$kpis = pg_query_params($conn, "
    SELECT * FROM public.vw_post_corte_kpis
    WHERE terminal_id = $1 AND cashier_user_id = $2 AND from_ts >= $3 AND to_ts <= $4
    ORDER BY from_ts DESC",
    [$terminal_id, $cashier_user_id, $from_ts, $to_ts]);
$kpi_list = pg_fetch_all($kpis) ?: [];

// Obtener detalle de tarjetas por precorte
$tarjetas = [];
foreach ($kpi_list as $kpi) {
    $tarjetas_data = pg_query_params($conn, "
        SELECT * FROM public.fn_precorte_tarjetas_detalle($1, $2, $3, $4)",
        [$kpi['from_ts'], $kpi['to_ts'], $terminal_id, $cashier_user_id]);
    $tarjetas[$kpi['precorte_id']] = pg_fetch_all($tarjetas_data) ?: [];
}

// Generar PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export_pdf') {
    $dompdf = new Dompdf();
    $dompdf->setPaper('A4', 'portrait');
    ob_start();
    ?>
    <h1>Dashboard Pos-Corte</h1>
    <p>Terminal: <?php echo $terminal_id; ?> | Cajero: <?php echo $cashier_user_id; ?></p>
    <p>Rango: <?php echo $from_ts; ?> - <?php echo $to_ts; ?></p>
    <table style="border-collapse: collapse; width: 100%;">
        <tr>
            <th style="border: 1px solid #000; padding: 8px;">Precorte ID</th>
            <th style="border: 1px solid #000; padding: 8px;">Fecha</th>
            <th style="border: 1px solid #000; padding: 8px;">Tiempo a Corte (min)</th>
            <th style="border: 1px solid #000; padding: 8px;">Dif. Efectivo</th>
            <th style="border: 1px solid #000; padding: 8px;">Dif. Crédito</th>
            <th style="border: 1px solid #000; padding: 8px;">Dif. Débito</th>
            <th style="border: 1px solid #000; padding: 8px;">Dif. Custom</th>
            <th style="border: 1px solid #000; padding: 8px;">Anulaciones</th>
            <th style="border: 1px solid #000; padding: 8px;">Descuentos (MXN)</th>
            <th style="border: 1px solid #000; padding: 8px;">Tarjetas</th>
        </tr>
        <?php foreach ($kpi_list as $kpi): ?>
            <tr>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['precorte_id']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['from_ts'] . ' - ' . $kpi['to_ts']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo round($kpi['minutes_to_cut'], 2); ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['cash_diff']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['credit_diff']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['debit_diff']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['custom_diff']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['voided_tickets_cnt']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;"><?php echo $kpi['total_discounts']; ?></td>
                <td style="border: 1px solid #000; padding: 8px;">
                    <?php foreach ($tarjetas[$kpi['precorte_id']] as $tarjeta): ?>
                        <?php echo htmlspecialchars("{$tarjeta['method']} ({$tarjeta['brand']}, {$tarjeta['bank_terminal']}): {$tarjeta['total']} MXN"); ?><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php
    $html = ob_get_clean();
    $dompdf->loadHtml($html);
    $dompdf->render();
    header("Content-type: application/pdf");
    header("Content-Disposition: inline; filename=dashboard_report.pdf");
    echo $dompdf->output();
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Pos-Corte</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Dashboard Pos-Corte</h1>
    <form method="GET">
        <label>Terminal ID: <input type="number" name="terminal_id" value="<?php echo $terminal_id; ?>" required></label><br>
        <label>Cajero ID: <input type="number" name="cashier_user_id" value="<?php echo $cashier_user_id; ?>" required></label><br>
        <label>Desde: <input type="datetime-local" name="from_ts" value="<?php echo str_replace(' ', 'T', $from_ts); ?>" required></label><br>
        <label>Hasta: <input type="datetime-local" name="to_ts" value="<?php echo str_replace(' ', 'T', $to_ts); ?>"></label><br>
        <button type="submit">Filtrar</button>
    </form>
    <form method="POST">
        <input type="hidden" name="action" value="export_pdf">
        <button type="submit">Exportar a PDF</button>
    </form>
    <table>
        <tr>
            <th>Precorte ID</th>
            <th>Fecha</th>
            <th>Tiempo a Corte (min)</th>
            <th>Dif. Efectivo</th>
            <th>Dif. Crédito</th>
            <th>Dif. Débito</th>
            <th>Dif. Custom</th>
            <th>Anulaciones</th>
            <th>Descuentos (MXN)</th>
            <th>Tarjetas</th>
        </tr>
        <?php foreach ($kpi_list as $kpi): ?>
            <tr>
                <td><?php echo $kpi['precorte_id']; ?></td>
                <td><?php echo $kpi['from_ts'] . ' - ' . $kpi['to_ts']; ?></td>
                <td><?php echo round($kpi['minutes_to_cut'], 2); ?></td>
                <td><?php echo $kpi['cash_diff']; ?></td>
                <td><?php echo $kpi['credit_diff']; ?></td>
                <td><?php echo $kpi['debit_diff']; ?></td>
                <td><?php echo $kpi['custom_diff']; ?></td>
                <td><?php echo $kpi['voided_tickets_cnt']; ?></td>
                <td><?php echo $kpi['total_discounts']; ?></td>
                <td>
                    <?php foreach ($tarjetas[$kpi['precorte_id']] as $tarjeta): ?>
                        <?php echo htmlspecialchars("{$tarjeta['method']} ({$tarjeta['brand']}, {$tarjeta['bank_terminal']}): {$tarjeta['total']} MXN"); ?><br>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>