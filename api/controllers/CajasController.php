<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CajasController {

  /** Resuelve PDO sin tocar tu core */
  private static function p(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    // 1) /api/config.php o /config.php deben devolver un PDO
    $candidates = [
      __DIR__ . '/../config.php',
      __DIR__ . '/../../config.php'
    ];
    foreach ($candidates as $c) {
      if (is_file($c)) {
        $r = require $c;
        if ($r instanceof PDO) { $pdo = $r; return $pdo; }
      }
    }

    // 2) Fallback a tu core/database.php (expone $pdo o función pdo())
    if (is_file(__DIR__.'/../core/database.php')) {
      require_once __DIR__.'/../core/database.php';
      if (isset($pdo) && $pdo instanceof PDO) return $pdo;
      if (function_exists('pdo')) {
        $pdo = pdo();
        if ($pdo instanceof PDO) return $pdo;
      }
    }

    throw new RuntimeException('No fue posible inicializar PDO en CajasController.');
  }

  /**
   * GET/POST /caja/cajas.php?date=YYYY-MM-DD
   * Respuesta compatible con tu JS legacy.
   */
  public static function cajas(Request $request, Response $response, array $args = [])
  {
    // Fallbacks por si no están cargados en este contexto
    if (!function_exists('qp')) {
      function qp(Request $r, $k, $def=null){ $q=$r->getQueryParams(); $p=(array)$r->getParsedBody(); return $q[$k]??$p[$k]??$def; }
    }
    if (!function_exists('J')) {
      function J(Response $res, $arr, $code=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($code); $res->getBody()->write(json_encode($arr)); return $res; }
    }

    try {
      $db = self::p();

      // Fecha consultada
      $date = trim((string) (qp($request, 'date', date('Y-m-d'))));
      if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
      $d0 = $date.' 00:00:00';
      $d1 = date('Y-m-d', strtotime($date.' +1 day')).' 00:00:00';

      // Terminales + última sesión que cruza el día (por terminal)
      $sql = "
        SELECT
          t.id,
          t.name,
          COALESCE(t.location,'')                       AS location,
          t.assigned_user,
          (u.first_name||' '||u.last_name)              AS assigned_name,
          COALESCE(t.opening_balance,0)::numeric(12,2)  AS opening_balance,
          COALESCE(t.current_balance,0)::numeric(12,2)  AS current_balance,
          s.id                                          AS sesion_id,
          s.apertura_ts,
          s.cierre_ts,
          s.opening_float::numeric(12,2)                AS opening_float,
          s.closing_float::numeric(12,2)                AS closing_float,
          (s.cierre_ts IS NULL)                         AS sesion_activa,
          s.estatus                                     AS sesion_estatus
        FROM public.terminal t
        LEFT JOIN public.users u
               ON u.auto_id = t.assigned_user
        LEFT JOIN (
          SELECT DISTINCT ON (terminal_id)
                 id, terminal_id, apertura_ts, cierre_ts, opening_float, closing_float, estatus
          FROM selemti.sesion_cajon
          WHERE apertura_ts <  :d1
            AND COALESCE(cierre_ts, :d1) >= :d0
          ORDER BY terminal_id, apertura_ts DESC
        ) s ON s.terminal_id = t.id
        ORDER BY t.id";
      $st = $db->prepare($sql);
      $st->execute([':d0'=>$d0, ':d1'=>$d1]);

      // Último DPR <= fin de día por terminal (para ventas desde t0)
      $sqlT0 = "
        SELECT dpr.terminal_id, MAX(dpr.report_time) AS t0
        FROM public.drawer_pull_report dpr
        WHERE dpr.report_time <= (:dte::date + INTERVAL '1 day' - INTERVAL '1 second')
        GROUP BY dpr.terminal_id";
      $stT0 = $db->prepare($sqlT0);
      $stT0->execute([':dte'=>$date]);
      $t0Rows = $stT0->fetchAll(PDO::FETCH_KEY_PAIR); // terminal_id => t0

      // Ventas por vendedor (desde t0 a fin de día)
      $sqlSellers = "
        SELECT
          t.terminal_id,
          u.auto_id AS user_id,
          (TRIM(COALESCE(u.first_name,'')||' '||COALESCE(u.last_name,''))) AS name,
          SUM(
            CASE
              WHEN COALESCE(t.voided, FALSE) = TRUE THEN 0
              WHEN UPPER(COALESCE(t.transaction_type,'')) IN ('REFUND','VOID','RETURN') THEN 0
              ELSE COALESCE(t.amount,0)
            END
          ) AS total
        FROM public.transactions t
        JOIN public.users u ON u.auto_id = t.user_id
        WHERE t.transaction_time BETWEEN :t0 AND (:dte::date + INTERVAL '1 day' - INTERVAL '1 second')
        GROUP BY t.terminal_id, u.auto_id, name";

      // DPR dentro de la ventana de la sesión (para Paso 2)
      $stDPRSesion = $db->prepare("
        SELECT MAX(report_time) AS dpr_time
        FROM public.drawer_pull_report
        WHERE terminal_id = :t
          AND report_time BETWEEN :a AND :b
      ");

      $terminals = [];
      foreach ($st as $r) {
        $tid           = (int)$r['sesion_id'] ? (int)$r['id'] : (int)$r['id'];
        $sid           = $r['sesion_id'] ? (int)$r['sesion_id'] : null;
        $apertura_ts   = $r['apertura_ts'] ?? null;
        $cierre_ts     = $r['cierre_ts'] ?: null;
        $estatusSesion = (string)($r['sesion_estatus'] ?? '');
        $saldo         = (float)($r['current_balance'] ?? 0);
        $openFloat     = isset($r['opening_float']) ? (float)$r['opening_float'] : null;
        $closeFloat    = isset($r['closing_float']) ? (float)$r['closing_float'] : null;
        $activaSesion  = (bool)$r['sesion_activa']; // (cierre_ts IS NULL)

        // === Regla CAJA ABIERTA (robusta) ===
        // abierta SI: sesión viva Y (saldo != 0  Ó  (saldo==0 Y opening_float==0))
        $activa = $activaSesion && ( ($saldo != 0.0) || ($saldo == 0.0 && $openFloat === 0.0) );

        // Ventana de ventas: desde t0 (último DPR del día) a fin del día
        $t0  = $t0Rows[$r['id']] ?? ($date.' 00:00:00');
        $stV = $db->prepare($sqlSellers);
        $stV->execute([':t0'=>$t0, ':dte'=>$date]);

        $vend = [];
        $terminal_total = 0.0;
        $assignedUser   = $r['assigned_user'] !== null ? (int)$r['assigned_user'] : null;
        $assignedName   = $r['assigned_user'] !== null ? ($r['assigned_name'] ?? null) : null;
        while ($row = $stV->fetch(PDO::FETCH_ASSOC)) {
          if ((int)$row['terminal_id'] !== (int)$r['id']) continue;
          $amt = (float)$row['total'];
          $terminal_total += $amt;
          $vend[] = [
            'user_id' => (int)$row['user_id'],
            'name'    => $row['name'],
            'total'   => round($amt, 2),
          ];
        }

        // División cajero asignado / otros
        $assigned_total = 0.0;
        $others_total   = 0.0;
        foreach ($vend as $v) {
          if ($assignedUser !== null && $v['user_id'] === $assignedUser) $assigned_total += (float)$v['total'];
          else $others_total += (float)$v['total'];
        }

        // === DPR dentro de la ventana de la SESIÓN (para Paso 2) ===
        $dpr_time = null;
        if ($apertura_ts) {
          $a = $apertura_ts;
          $b = $cierre_ts ?: ($date.' 23:59:59');
          $stDPRSesion->execute([':t'=>$r['id'], ':a'=>$a, ':b'=>$b]);
          $dpr_time = $stDPRSesion->fetchColumn() ?: null;
        }
        $hayDPRSesion = !empty($dpr_time);

        // === Stage por prioridad ===
        // POSTCORTE (cuando exista postcorte) > PRECORTE (sin DPR) > CORTE (con DPR) > ACTIVA
        // Nota: si tienes tabla selemti.postcorte puedes consultar aquí; dejamos heurística por DPR.
        $stage = null;
        if ($estatusSesion === 'POSTCORTE') {
          $stage = 'POSTCORTE';
        } elseif ($sid && $estatusSesion && in_array($estatusSesion, ['PENDIENTE','ENVIADO','LISTO_PARA_CORTE'], true) && !$hayDPRSesion) {
          $stage = 'PRECORTE';
        } elseif ($hayDPRSesion) {
          $stage = 'CORTE';
        } elseif ($estatusSesion === 'ACTIVA') {
          $stage = 'ACTIVA';
        }

        $terminals[] = [
          'id'              => (int)$r['id'],
          'name'            => (string)$r['name'],
          'location'        => (string)$r['location'],
          'opening_balance' => (float)$r['opening_balance'],
          'current_balance' => (float)$r['current_balance'],
          'has_cash_drawer' => null,  // (no lo traemos aquí; lo dejamos null para no romper)
          'in_use'          => null,
          'active'          => null,

          'assigned_user'   => $assignedUser,
          'assigned_name'   => $assignedName ?: null,

          'opening_float'   => $openFloat,
          'closing_float'   => $closeFloat,
          'sesion_estatus'  => $estatusSesion,

          'status'          => [
            'activa'           => $activa,
            'asignada'         => ($assignedUser !== null),
            'sesion_id'        => $sid ?: null,
            'listo_para_corte' => ($estatusSesion === 'LISTO_PARA_CORTE'),
          ],
          'sesion'          => $sid ? ['id'=>$sid] : null,

          'stage'           => $stage,
          'pos'             => ['dpr_time' => $dpr_time ?: null],

          'sales'           => [
            'terminal_total' => round($terminal_total, 2),
            'assigned_total' => round($assigned_total, 2),
            'others_total'   => round($others_total, 2),
            'sellers'        => $vend,
          ],
          'window'          => [
            't0'  => $t0,
            't1'  => $date.' 23:59:59',
            'day' => $date,
          ],
        ];
      }

      return J($response, ['ok'=>true, 'date'=>$date, 'terminals'=>$terminals]);

    } catch (\Throwable $e) {
      return J($response, ['ok'=>false, 'error'=>'server_error', 'detail'=>$e->getMessage()], 500);
    }
  }

  /** /caja/cajas_debug.php */
  public static function cajasDebug(Request $req, Response $res): Response {
    return self::cajas($req, $res);
  }
}
