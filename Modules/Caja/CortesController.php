<?php
namespace Terrena\Modules\Caja;

use PDO;
use PDOException;

class CortesController
{
    /* ===========================
       VIEW: /caja/cortes
       - Carga terminales
       - Si hay terminal seleccionada (?terminal=ID&fecha=YYYY-MM-DD), trae:
         estado de caja, tickets abiertos, totales POS, descuentos, anulaciones, tarjetas, otros
       =========================== */
    public static function view(): void
    {
        $pdo = self::pdo();

        $fecha = isset($_GET['fecha']) && $_GET['fecha'] ? $_GET['fecha'] : date('Y-m-d');
        $terminalId = isset($_GET['terminal']) && is_numeric($_GET['terminal']) ? (int)$_GET['terminal'] : null;

        // 1) Terminales activas con cajón
        $terminales = self::qAll($pdo, "
            SELECT
              t.id   AS terminal_id,
              t.name AS terminal_name,
              t.location AS sucursal,
              t.has_cash_drawer,
              t.in_use,
              t.active
            FROM public.terminal t
            WHERE t.active = TRUE
              AND t.has_cash_drawer = TRUE
            ORDER BY t.location, t.name
        ");

        // 2) Enriquecer con last_tx y last_pull del día
        $enriquecidas = [];
        foreach ($terminales as $t) {
            $row = self::qOne($pdo, "
                WITH last_tx AS (
                  SELECT MAX(tr.transaction_time) AS last_tx_time
                  FROM public.transactions tr
                  WHERE tr.transaction_time::date = :f
                    AND tr.terminal_id = :tid
                ),
                last_pull AS (
                  SELECT MAX(dpr.report_time) AS last_pull_time
                  FROM public.drawer_pull_report dpr
                  WHERE dpr.report_time::date = :f
                    AND dpr.terminal_id = :tid
                )
                SELECT lt.last_tx_time, lp.last_pull_time
                FROM last_tx lt, last_pull lp
            ", [':f'=>$fecha, ':tid'=>$t['terminal_id']]);

            $lastTx   = $row['last_tx_time']   ?? null;
            $lastPull = $row['last_pull_time'] ?? null;

            $cajaAbierta = ($lastTx !== null) && ( $lastPull === null || $lastTx > $lastPull );
            $corteHecho  = ($lastPull !== null) && ( $lastTx === null || $lastTx <= $lastPull );

            $t['last_tx_time']   = $lastTx;
            $t['last_pull_time'] = $lastPull;
            $t['caja_abierta']   = $cajaAbierta;
            $t['corte_hecho']    = $corteHecho;

            $enriquecidas[] = $t;
        }

        // 3) Si hay terminal seleccionada, traer datasets para pestañas
        $ui = [
            'caja_abierta'     => false,
            'tickets_abiertos' => 0,
            'btn_precorte'     => false,
            'btn_corte'        => false,
            'btn_postcorte'    => false,
            'btn_retiros'      => false,
        ];

        $ticketsAbiertos = [];
        $totales = ['sys_cash'=>0,'sys_card'=>0,'sys_other'=>0,'sys_total'=>0];
        $descuentos = $anulaciones = $tarjetas = $otros = $retiros = [];

        if ($terminalId) {
            $estadoRow = self::qOne($pdo, "
                WITH last_tx AS (
                  SELECT MAX(tr.transaction_time) AS last_tx_time
                  FROM public.transactions tr
                  WHERE tr.transaction_time::date = :f
                    AND tr.terminal_id = :tid
                ),
                last_pull AS (
                  SELECT MAX(dpr.report_time) AS last_pull_time
                  FROM public.drawer_pull_report dpr
                  WHERE dpr.report_time::date = :f
                    AND dpr.terminal_id = :tid
                )
                SELECT lt.last_tx_time, lp.last_pull_time
                FROM last_tx lt, last_pull lp
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            $lastTx   = $estadoRow['last_tx_time']   ?? null;
            $lastPull = $estadoRow['last_pull_time'] ?? null;

            $cajaAbierta = ($lastTx !== null) && ( $lastPull === null || $lastTx > $lastPull );
            $corteHecho  = ($lastPull !== null) && ( $lastTx === null || $lastTx <= $lastPull );

            // Tickets abiertos (heurística por pagos): tickets del día sin transacción válida de cobro
            $ticketsAbiertos = self::qAll($pdo, "
                WITH paid_tickets AS (
                  SELECT DISTINCT tr.ticket_id
                  FROM public.transactions tr
                  WHERE tr.transaction_time::date = :f
                    AND tr.terminal_id = :tid
                    AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
                    AND tr.voided = FALSE
                )
                SELECT tk.id, tk.ticket_number
                FROM public.ticket tk
                WHERE tk.terminal_id = :tid
                  AND tk.create_date::date = :f
                  AND tk.id NOT IN (SELECT ticket_id FROM paid_tickets)
                ORDER BY tk.id DESC
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            // Totales POS por método
            $totales = self::qOne($pdo, "
                SELECT
                  COALESCE(SUM(CASE WHEN tr.payment_type = 'CASH' THEN tr.amount END),0)                                AS sys_cash,
                  COALESCE(SUM(CASE WHEN tr.payment_type IN ('CREDIT_CARD','DEBIT_CARD') THEN tr.amount END),0)         AS sys_card,
                  COALESCE(SUM(CASE WHEN tr.payment_type NOT IN ('CASH','CREDIT_CARD','DEBIT_CARD')
                                    OR tr.custom_payment_name IS NOT NULL
                               THEN tr.amount END),0)                                                                    AS sys_other,
                  COALESCE(SUM(tr.amount),0)                                                                             AS sys_total
                FROM public.transactions tr
                WHERE tr.transaction_time::date = :f
                  AND tr.terminal_id = :tid
                  AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
                  AND tr.voided = FALSE
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            // Descuentos
            $descuentos = self::qAll($pdo, "
                SELECT td.ticket_id, td.name, td.value, td.type, td.auto_apply, td.minimum_amount
                FROM public.ticket_discount td
                JOIN public.ticket tk ON tk.id = td.ticket_id
                WHERE tk.create_date::date = :f
                  AND tk.terminal_id = :tid
                ORDER BY td.id DESC
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            // Anulaciones / Devoluciones por transacción
            $anulaciones = self::qAll($pdo, "
                SELECT tr.ticket_id, tr.amount, tr.transaction_time, tr.transaction_type, tr.voided
                FROM public.transactions tr
                WHERE tr.transaction_time::date = :f
                  AND tr.terminal_id = :tid
                  AND tr.transaction_type IN ('REFUND','VOID')
                ORDER BY tr.transaction_time DESC
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            // Tarjetas
            $tarjetas = self::qAll($pdo, "
                SELECT tr.ticket_id, tr.card_type, tr.card_auth_code, tr.amount, tr.transaction_time
                FROM public.transactions tr
                WHERE tr.transaction_time::date = :f
                  AND tr.terminal_id = :tid
                  AND tr.payment_type IN ('CREDIT_CARD','DEBIT_CARD')
                  AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
                  AND tr.voided = FALSE
                ORDER BY tr.transaction_time DESC
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            // Otros pagos (transfer, crédito, etc.)
            $otros = self::qAll($pdo, "
                SELECT tr.ticket_id,
                       COALESCE(tr.custom_payment_name, tr.payment_type) AS metodo,
                       tr.custom_payment_ref AS referencia,
                       tr.amount,
                       tr.transaction_time
                FROM public.transactions tr
                WHERE tr.transaction_time::date = :f
                  AND tr.terminal_id = :tid
                  AND (
                    tr.payment_type NOT IN ('CASH','CREDIT_CARD','DEBIT_CARD')
                    OR tr.custom_payment_name IS NOT NULL
                  )
                  AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
                  AND tr.voided = FALSE
                ORDER BY tr.transaction_time DESC
            ", [':f'=>$fecha, ':tid'=>$terminalId]);

            $ui = [
                'caja_abierta'      => $cajaAbierta,
                'tickets_abiertos'  => count($ticketsAbiertos),
                'btn_precorte'      => $cajaAbierta,
                'btn_corte'         => $cajaAbierta && count($ticketsAbiertos) === 0,
                'btn_postcorte'     => !$cajaAbierta && (bool)$lastPull,
                'btn_retiros'       => $cajaAbierta,
            ];
        }

        // Pasa a la vista sin cambiar tu maquetación
        $data = [
            'fecha'            => $fecha,
            'terminales'       => $enriquecidas, // list/selección
            'terminal_id'      => $terminalId,

            'ui'               => $ui,
            'ticketsAbiertos'  => $ticketsAbiertos,
            'totales'          => $totales,

            'descuentos'       => $descuentos,
            'anulaciones'      => $anulaciones,
            'tarjetas'         => $tarjetas,
            'otros'            => $otros,
            'retiros'          => $retiros, // si decides crear módulo auxiliar
        ];

        // Tu vista actual
        require __DIR__ . '/../../Views/caja/cortes.php';
    }

    /* ===========================
       API (JSON)
       =========================== */

    public static function apiTerminals(): void
    {
        $pdo = self::pdo();
        $fecha = isset($_GET['fecha']) && $_GET['fecha'] ? $_GET['fecha'] : date('Y-m-d');

        $terminales = self::qAll($pdo, "
            SELECT t.id AS terminal_id, t.name AS terminal_name, t.location AS sucursal,
                   t.has_cash_drawer, t.in_use, t.active
            FROM public.terminal t
            WHERE t.has_cash_drawer = TRUE
            ORDER BY t.location, t.name
        ");

        $enriq = [];
        foreach ($terminales as $t) {
            $row = self::qOne($pdo, "
                WITH last_tx AS (
                  SELECT MAX(tr.transaction_time) AS last_tx_time
                  FROM public.transactions tr
                  WHERE tr.transaction_time::date = :f AND tr.terminal_id = :tid
                ),
                last_pull AS (
                  SELECT MAX(dpr.report_time) AS last_pull_time
                  FROM public.drawer_pull_report dpr
                  WHERE dpr.report_time::date = :f AND dpr.terminal_id = :tid
                )
                SELECT lt.last_tx_time, lp.last_pull_time
                FROM last_tx lt, last_pull lp
            ", [':f'=>$fecha, ':tid'=>$t['terminal_id']]);

            $lastTx   = $row['last_tx_time']   ?? null;
            $lastPull = $row['last_pull_time'] ?? null;
            $cajaAbierta = ($lastTx !== null) && ( $lastPull === null || $lastTx > $lastPull );
            $corteHecho  = ($lastPull !== null) && ( $lastTx === null || $lastTx <= $lastPull );

            $t['last_tx_time']   = $lastTx;
            $t['last_pull_time'] = $lastPull;
            $t['caja_abierta']   = $cajaAbierta;
            $t['corte_hecho']    = $corteHecho;
            $enriq[] = $t;
        }

        self::json($enriq);
    }

    public static function apiTerminalSummary(): void
    {
        $pdo = self::pdo();
        $fecha = isset($_GET['fecha']) && $_GET['fecha'] ? $_GET['fecha'] : date('Y-m-d');
        $terminalId = isset($_GET['terminal']) ? (int)$_GET['terminal'] : 0;

        // Estado
        $estadoRow = self::qOne($pdo, "
            WITH last_tx AS (
              SELECT MAX(tr.transaction_time) AS last_tx_time
              FROM public.transactions tr
              WHERE tr.transaction_time::date = :f AND tr.terminal_id = :tid
            ),
            last_pull AS (
              SELECT MAX(dpr.report_time) AS last_pull_time
              FROM public.drawer_pull_report dpr
              WHERE dpr.report_time::date = :f AND dpr.terminal_id = :tid
            )
            SELECT lt.last_tx_time, lp.last_pull_time
            FROM last_tx lt, last_pull lp
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        $lastTx   = $estadoRow['last_tx_time']   ?? null;
        $lastPull = $estadoRow['last_pull_time'] ?? null;
        $cajaAbierta = ($lastTx !== null) && ( $lastPull === null || $lastTx > $lastPull );
        $corteHecho  = ($lastPull !== null) && ( $lastTx === null || $lastTx <= $lastPull );

        // Tickets abiertos (heurística por pagos)
        $ticketsAbiertos = self::qAll($pdo, "
            WITH paid_tickets AS (
              SELECT DISTINCT tr.ticket_id
              FROM public.transactions tr
              WHERE tr.transaction_time::date = :f
                AND tr.terminal_id = :tid
                AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
                AND tr.voided = FALSE
            )
            SELECT tk.id, tk.ticket_number
            FROM public.ticket tk
            WHERE tk.terminal_id = :tid
              AND tk.create_date::date = :f
              AND tk.id NOT IN (SELECT ticket_id FROM paid_tickets)
            ORDER BY tk.id DESC
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        // Totales POS por método
        $totales = self::qOne($pdo, "
            SELECT
              COALESCE(SUM(CASE WHEN tr.payment_type = 'CASH' THEN tr.amount END),0)                                AS sys_cash,
              COALESCE(SUM(CASE WHEN tr.payment_type IN ('CREDIT_CARD','DEBIT_CARD') THEN tr.amount END),0)         AS sys_card,
              COALESCE(SUM(CASE WHEN tr.payment_type NOT IN ('CASH','CREDIT_CARD','DEBIT_CARD')
                                OR tr.custom_payment_name IS NOT NULL
                           THEN tr.amount END),0)                                                                    AS sys_other,
              COALESCE(SUM(tr.amount),0)                                                                             AS sys_total
            FROM public.transactions tr
            WHERE tr.transaction_time::date = :f
              AND tr.terminal_id = :tid
              AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
              AND tr.voided = FALSE
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        $resp = [
            'fecha'          => $fecha,
            'terminal_id'    => $terminalId,
            'caja_abierta'   => $cajaAbierta,
            'corte_hecho'    => $corteHecho,
            'last_tx_time'   => $lastTx,
            'last_pull_time' => $lastPull,
            'tickets_abiertos'=> $ticketsAbiertos,
            'totales'        => $totales,
        ];
        self::json($resp);
    }

    public static function apiDescuentos(): void
    {
        $pdo = self::pdo();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $terminalId = (int)($_GET['terminal'] ?? 0);

        $rows = self::qAll($pdo, "
            SELECT td.ticket_id, td.name, td.value, td.type, td.auto_apply, td.minimum_amount
            FROM public.ticket_discount td
            JOIN public.ticket tk ON tk.id = td.ticket_id
            WHERE tk.create_date::date = :f
              AND tk.terminal_id = :tid
            ORDER BY td.id DESC
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        self::json($rows);
    }

    public static function apiAnulaciones(): void
    {
        $pdo = self::pdo();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $terminalId = (int)($_GET['terminal'] ?? 0);

        $rows = self::qAll($pdo, "
            SELECT tr.ticket_id, tr.amount, tr.transaction_time, tr.transaction_type, tr.voided
            FROM public.transactions tr
            WHERE tr.transaction_time::date = :f
              AND tr.terminal_id = :tid
              AND tr.transaction_type IN ('REFUND','VOID')
            ORDER BY tr.transaction_time DESC
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        self::json($rows);
    }

    public static function apiTarjetas(): void
    {
        $pdo = self::pdo();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $terminalId = (int)($_GET['terminal'] ?? 0);

        $rows = self::qAll($pdo, "
            SELECT tr.ticket_id, tr.card_type, tr.card_auth_code, tr.amount, tr.transaction_time
            FROM public.transactions tr
            WHERE tr.transaction_time::date = :f
              AND tr.terminal_id = :tid
              AND tr.payment_type IN ('CREDIT_CARD','DEBIT_CARD')
              AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
              AND tr.voided = FALSE
            ORDER BY tr.transaction_time DESC
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        self::json($rows);
    }

    public static function apiOtros(): void
    {
        $pdo = self::pdo();
        $fecha = $_GET['fecha'] ?? date('Y-m-d');
        $terminalId = (int)($_GET['terminal'] ?? 0);

        $rows = self::qAll($pdo, "
            SELECT tr.ticket_id,
                   COALESCE(tr.custom_payment_name, tr.payment_type) AS metodo,
                   tr.custom_payment_ref AS referencia,
                   tr.amount,
                   tr.transaction_time
            FROM public.transactions tr
            WHERE tr.transaction_time::date = :f
              AND tr.terminal_id = :tid
              AND (
                tr.payment_type NOT IN ('CASH','CREDIT_CARD','DEBIT_CARD')
                OR tr.custom_payment_name IS NOT NULL
              )
              AND tr.transaction_type IN ('SALE','CAPTURE','PAYMENT')
              AND tr.voided = FALSE
            ORDER BY tr.transaction_time DESC
        ", [':f'=>$fecha, ':tid'=>$terminalId]);

        self::json($rows);
    }

    public static function apiRetiros(): void
    {
        // Opcional: si decides llevar retiros auxiliares (tabla propia)
        self::json(['ok'=>true,'rows'=>[]]);
    }

    /* ===========================
       Helpers
       =========================== */
    private static function pdo(): PDO
    {
        // Asume que config.php retorna un PDO.
        $pdo = require __DIR__ . '/../../config.php';
        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('config.php debe retornar PDO');
        }
        // Seguridad básica:
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private static function qAll(PDO $pdo, string $sql, array $params = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function qOne(PDO $pdo, string $sql, array $params = []): array
    {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private static function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
