<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PreCorteController {

  // ⇨ HAZLA PÚBLICA para que PostCorteController pueda reutilizar la conexión
  public static function p(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $candidates = [
      __DIR__ . '/../../config.php',   // /api/config.php
      __DIR__ . '/../../../config.php' // /config.php
    ];
    foreach ($candidates as $c) {
      if (is_file($c)) {
        $r = require $c;
        if ($r instanceof PDO) { $pdo = $r; return $pdo; }
      }
    }
    if (function_exists('pdo')) {
      $pdo = pdo();
      if ($pdo instanceof PDO) return $pdo;
    }
    throw new RuntimeException('No fue posible inicializar PDO en PreCorteController.');
  }

  /* ===== Preflight (bloqueos por tickets abiertos) ===== */
  public static function preflight(Request $request, Response $response, array $args = []) : Response {
    $db  = self::p();
    $sid = isset($args['sesion_id']) ? (int)$args['sesion_id'] : (int) qp($request, 'sesion_id', 0);
    if (!$sid) return J($response, ['ok'=>false,'error'=>'missing_sesion_id'], 400);

    $st = $db->prepare("SELECT terminal_id FROM selemti.sesion_cajon WHERE id=:id");
    $st->execute([':id'=>$sid]);
    $row = $st->fetch();
    if (!$row) return J($response, ['ok'=>false,'error'=>'sesion_not_found'], 404);
    $tid = (int)$row['terminal_id'];

    $t = $db->prepare("
      SELECT COUNT(*) AS c
      FROM public.ticket
      WHERE terminal_id=:tid
        AND closing_date IS null 
    ");
    $t->execute([':tid'=>$tid]);
    $open = (int)($t->fetch()['c'] ?? 0);

    $blocked = $open > 0;
    return J($response, [
      'ok'=>!$blocked,
      'tickets_abiertos'=>$open,
      'bloqueo'=>$blocked
    ], 200);
  }

  /* ===== Crear/Actualizar encabezado (upsert) ===== */
  public static function createOrUpdate(Request $request, Response $response, array $args = []) : Response {
    $db  = self::p();
    $sid = (int) qp($request, 'sesion_id', 0);

    $precorte_id_in = (int) qp($request, 'id', 0);
    if (!$sid && $precorte_id_in) {
      $tmp = $db->prepare("SELECT sesion_id FROM selemti.precorte WHERE id=:id");
      $tmp->execute([':id'=>$precorte_id_in]);
      $sid = (int)($tmp->fetch()['sesion_id'] ?? 0);
    }

    if (!$sid) {
      $tid   = (int) qp($request, 'terminal_id', 0);
      $uid   = (int) qp($request, 'user_id', 0);
      $bdate = (string) (qp($request, 'bdate') ?? qp($request, 'date') ?? '');
      if (!$tid || !$uid || !$bdate) {
        return J($response, ['ok'=>false,'error'=>'missing_params (sesion_id) OR (terminal_id,user_id,bdate)'], 400);
      }
      $q = $db->prepare("
        SELECT id FROM selemti.sesion_cajon
        WHERE terminal_id=:tid AND cajero_usuario_id=:uid
          AND (apertura_ts::date=:d OR (cierre_ts IS NOT NULL AND cierre_ts::date=:d))
        ORDER BY apertura_ts DESC LIMIT 1
      ");
      $q->execute([':tid'=>$tid, ':uid'=>$uid, ':d'=>$bdate]);
      $sid = (int)($q->fetch()['id'] ?? 0);
      if (!$sid) {
        $q2 = $db->prepare("
          SELECT id FROM selemti.sesion_cajon
          WHERE terminal_id=:tid AND cajero_usuario_id=:uid AND estatus='ACTIVA'
          ORDER BY apertura_ts DESC LIMIT 1
        ");
        $q2->execute([':tid'=>$tid, ':uid'=>$uid]);
        $sid = (int)($q2->fetch()['id'] ?? 0);
      }
      if (!$sid) return J($response, ['ok'=>false,'error'=>'sesion_not_found','detail'=>compact('tid','uid','bdate')], 404);
    }

    $pre = self::preflight($request, new \Slim\Psr7\Response(), ['sesion_id'=>$sid]);
    $preData = json_decode((string)$pre->getBody(), true);
    if (!$preData['ok']) return J($response, $preData, 409);

    $st = $db->prepare("SELECT id, estatus FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $st->execute([':sid'=>$sid]);
    $row = $st->fetch();

    $precorte_id = (int)($row['id'] ?? 0);
    $estatus     = (string)($row['estatus'] ?? '');

    $ya_existia = $precorte_id > 0;
    if (!$ya_existia) {
      $ins = $db->prepare("INSERT INTO selemti.precorte (sesion_id, estatus) VALUES (:sid,'PENDIENTE') RETURNING id, estatus");
      $ins->execute([':sid'=>$sid]);
      $r         = $ins->fetch();
      $precorte_id = (int)$r['id'];
      $estatus     = (string)$r['estatus'];
    }

    return J($response, [
      'ok'=>true,
      'precorte_id'=>$precorte_id,
      'sesion_id'=>$sid,
      'estatus'=>$estatus,
      'ya_existia'=>$ya_existia
    ]);
  }

  /* ===== Resumen (REST) ===== */
public static function resumen(Request $request, Response $response, array $args) {
  $db        = self::p();
  $id        = isset($args['id']) ? (int)$args['id'] : (int) qp($request, 'id', 0);
  $sesion_id = isset($args['sesion_id']) ? (int)$args['sesion_id'] : (int) qp($request, 'sesion_id', 0);

  try {
    if (!$id && $sesion_id) {
      $s = $db->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
      $s->execute([':sid'=>$sesion_id]);
      $id = (int)($s->fetch()['id'] ?? 0);
      if (!$id) return J($response, ['ok'=>false,'error'=>'precorte_not_found','sesion_id'=>$sesion_id], 404);

      if (!self::hasPOSCutBySesion($db, $sesion_id)) {
        return J($response, [
          'ok'=>false, 'error'=>'pos_cut_missing', 'require_pos_cut'=>true,
          'sesion_id'=>$sesion_id, 'precorte_id'=>$id
        ], 412);
      }
      return J($response, ['ok'=>true, 'sesion'=>['id'=>$sesion_id], 'precorte_id'=>$id, 'has_pos_cut'=>true]);
    }

    if (!$id) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);

    $p = $db->prepare("SELECT p.sesion_id FROM selemti.precorte p WHERE p.id=:id");
    $p->execute([':id'=>$id]);
    $row = $p->fetch();
    if(!$row) return J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);
    $sid = (int)$row['sesion_id'];

    if (!self::hasPOSCutBySesion($db, $sid)) {
      return J($response, [
        'ok'=>false,'error'=>'pos_cut_missing','require_pos_cut'=>true,
        'sesion_id'=>$sid,'precorte_id'=>$id
      ], 412);
    }

    // Declarado
    $tEF = $db->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
    $tEF->execute([':id'=>$id]);
    $decl_ef = (float)($tEF->fetch()['s'] ?? 0);

    $decl_ot = ['CREDITO'=>0,'DEBITO'=>0,'TRANSFER'=>0,'CUSTOM'=>0,'GIFT_CERT'=>0];
    $hasOtros = !empty($db->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch()['t']);
    if ($hasOtros){
      $qot = $db->prepare("
        SELECT UPPER(tipo) AS tipo, COALESCE(SUM(monto),0)::numeric AS monto
        FROM selemti.precorte_otros
        WHERE precorte_id=:id
        GROUP BY UPPER(tipo)
      ");
      $qot->execute([':id'=>$id]);
      foreach ($qot as $r){
        $k = $r['tipo'];
        if (isset($decl_ot[$k])) $decl_ot[$k] = (float)$r['monto'];
      }
    }

    // Sistema + fallback transfer
    $vw = $db->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $vw->execute([':sid'=>$sid]);
    $sys = [
      'cash'=>0,'credit'=>0,'debit'=>0,'transfer'=>0,'custom'=>0,'gift'=>0,
      'retiros'=>0,'refunds_cash'=>0,'sistema_efectivo_esperado'=>0,'sistema_tarjetas'=>0
    ];
    if ($r = $vw->fetch()){
      $cash     = (float)($r['sys_cash']     ?? $r['cash']     ?? 0);
      $credit   = (float)($r['sys_credito']  ?? $r['sys_credit']   ?? $r['credit']   ?? 0);
      $debit    = (float)($r['sys_debito']   ?? $r['sys_debit']    ?? $r['debit']    ?? 0);
      $transfer = (float)($r['sys_transfer'] ?? $r['transfer'] ?? 0);
      if ($transfer <= 0.0001) $transfer = self::sysTransfersFromTransactions($db, $sid);
      $custom   = (float)($r['sys_custom']   ?? $r['custom']   ?? 0);
      $gift     = (float)($r['sys_gift']     ?? 0);

      $sys['cash']=$cash; $sys['credit']=$credit; $sys['debit']=$debit; $sys['transfer']=$transfer; $sys['custom']=$custom; $sys['gift']=$gift;
      $sys['retiros']=(float)($r['retiros'] ?? 0);
      $sys['refunds_cash']=(float)($r['refunds_cash'] ?? $r['reembolsos_efectivo'] ?? 0);
      $sys['sistema_efectivo_esperado']=(float)($r['sistema_efectivo_esperado'] ?? 0);
      $sys['sistema_tarjetas']=$credit+$debit+$transfer+$custom+$gift;
    } else {
      $sys['transfer'] = self::sysTransfersFromTransactions($db, $sid);
      $sys['sistema_tarjetas'] = $sys['transfer'];
    }

    $dif = [
      'efectivo' => round($decl_ef - $sys['sistema_efectivo_esperado'], 2),
      'tarjetas' => round(array_sum($decl_ot) - $sys['sistema_tarjetas'], 2)
    ];

    return J($response, [
      'ok'=>true,
      'sesion'=>['id'=>$sid],
      'sistema'=>$sys,
      'declarado'=>['efectivo'=>$decl_ef,'otros'=>$decl_ot],
      'dif'=>$dif,
      'umbral'=>defined('DIFF_THRESHOLD') ? DIFF_THRESHOLD : 10,
      'has_pos_cut'=>true
    ]);
  } catch (\Throwable $e) {
    return J($response, ['ok'=>false,'error'=>'internal_error','detail'=>$e->getMessage()], 500);
  }
}

  /* ===== Legacy (wizard) ===== */
public static function resumenLegacy(Request $request, Response $response, array $args = []) {
  if (!function_exists('qp')) {
    function qp(Request $r, $k, $def=null){ $q=$r->getQueryParams(); $p=(array)$r->getParsedBody(); return $q[$k]??$p[$k]??$def; }
  }
  if (!function_exists('J')) {
    function J(Response $res, $arr, $code=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($code); $res->getBody()->write(json_encode($arr)); return $res; }
  }

  $db = self::p();

  // Parámetros / resolver precorte
  $precorte_id = isset($args['id']) ? (int)$args['id'] : (int) qp($request, 'id', 0);
  $sesion_id   = isset($args['sesion_id']) ? (int)$args['sesion_id'] : (int) qp($request, 'sesion_id', 0);
  $terminal_id = (int) qp($request, 'terminal_id', 0);
  $user_id     = (int) qp($request, 'user_id', 0);

  try {
    if (!$precorte_id) {
      if ($sesion_id > 0) {
        $s = $db->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
        $s->execute([':sid'=>$sesion_id]);
        $precorte_id = (int)($s->fetch()['id'] ?? 0);
      } elseif ($terminal_id>0 && $user_id>0) {
        $s = $db->prepare("
          SELECT id FROM selemti.sesion_cajon
          WHERE terminal_id=:t AND cajero_usuario_id=:u AND cierre_ts IS NULL
          ORDER BY apertura_ts DESC LIMIT 1
        ");
        $s->execute([':t'=>$terminal_id, ':u'=>$user_id]);
        $sid = (int)($s->fetch()['id'] ?? 0);
        if ($sid>0) {
          $p = $db->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
          $p->execute([':sid'=>$sid]);
          $precorte_id = (int)($p->fetch()['id'] ?? 0);
          $sesion_id = $sid;
        }
      }
    }
    if (!$precorte_id) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);

    // Sesión del precorte
    $p = $db->prepare("SELECT p.sesion_id FROM selemti.precorte p WHERE p.id=:id");
    $p->execute([':id'=>$precorte_id]);
    $row = $p->fetch();
    if(!$row) return J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);
    $sid = (int)$row['sesion_id'];

    // Gate DPR
    if (!self::hasPOSCutBySesion($db, $sid)) {
      return J($response, [
        'ok'=>false,'error'=>'pos_cut_missing','require_pos_cut'=>true,
        'sesion_id'=>$sid,'precorte_id'=>$precorte_id
      ], 412);
    }

    // opening_float
    $sinfo = $db->prepare("SELECT opening_float FROM selemti.sesion_cajon WHERE id=:sid");
    $sinfo->execute([':sid'=>$sid]);
    $opening_float = (float)($sinfo->fetch()['opening_float'] ?? 0);

    // declarado efectivo
    $tEF = $db->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
    $tEF->execute([':id'=>$precorte_id]);
    $decl_ef = (float)($tEF->fetch()['s'] ?? 0);

    // declarados otros
    $decl_credito = 0.0; $decl_debito = 0.0; $decl_transfer = 0.0;
    $hasOtros = !empty($db->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch()['t']);
    if($hasOtros){
      $qot = $db->prepare("
        SELECT UPPER(tipo) AS tipo, COALESCE(SUM(monto),0)::numeric AS monto
        FROM selemti.precorte_otros WHERE precorte_id=:id
        GROUP BY UPPER(tipo)
      ");
      $qot->execute([':id'=>$precorte_id]);
      foreach($qot as $r){
        if ($r['tipo']==='CREDITO')  $decl_credito  = (float)$r['monto'];
        if ($r['tipo']==='DEBITO')   $decl_debito   = (float)$r['monto'];
        if ($r['tipo']==='TRANSFER') $decl_transfer = (float)$r['monto'];
      }
    }

    // sistema (vista + Fallback exacto para transferencias)
    $vw = $db->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $vw->execute([':sid'=>$sid]);

    $sysE=0;$sysC=0;$sysD=0;$sysT=0;
    if($r = $vw->fetch()){
      $sysE = (float)($r['sistema_efectivo_esperado'] ?? 0);
      $sysC = (float)($r['sys_credito']  ?? $r['sys_credit'] ?? 0);
      $sysD = (float)($r['sys_debito']   ?? $r['sys_debit']  ?? 0);
      $sysT = (float)($r['sys_transfer'] ?? $r['transfer']   ?? 0);
      if ($sysT <= 0.0001) $sysT = self::sysTransfersFromTransactions($db, $sid);
    } else {
      $sysT = self::sysTransfersFromTransactions($db, $sid);
    }

    $data = [
      'efectivo'        => ['declarado'=>$decl_ef,      'sistema'=>$sysE],
      'tarjeta_credito' => ['declarado'=>$decl_credito, 'sistema'=>$sysC],
      'tarjeta_debito'  => ['declarado'=>$decl_debito,  'sistema'=>$sysD],
      'transferencias'  => ['declarado'=>$decl_transfer,'sistema'=>$sysT],
    ];

    return J($response, [
      'ok'=>true,
      'data'=>$data,
      'opening_float'=>$opening_float,
      'precorte_id'=>$precorte_id,
      'sesion_id'=>$sid,
      'has_pos_cut'=>true
    ]);
  } catch (\Throwable $e) {
    return J($response, ['ok'=>false,'error'=>'internal_error','detail'=>$e->getMessage()], 500);
  }
}

  /* ===== Update legacy ===== */
  public static function updateLegacy(Request $request, Response $response, array $args = []) : Response {
    $db = self::p();
    if (!function_exists('qp')) {
      function qp(Request $r, $k, $def=null){ $q=$r->getQueryParams(); $p=(array)$r->getParsedBody(); return $q[$k]??$p[$k]??$def; }
    }
    if (!function_exists('J')) {
      function J(Response $res, $arr, $code=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($code); $res->getBody()->write(json_encode($arr)); return $res; }
    }

    $precorte_id = (int)($args['id'] ?? qp($request,'id',0));
    if ($precorte_id <= 0) return J($response, ['ok'=>false,'error'=>'missing_precorte_id'], 400);

    $p = (array)($request->getParsedBody() ?? []);
    $denoms_json       = (string)($p['denoms_json'] ?? '[]');
    $decl_credito_raw  = (string)($p['declarado_credito']  ?? '0');
    $decl_debito_raw   = (string)($p['declarado_debito']   ?? '0');
    $decl_transfer_raw = (string)($p['declarado_transfer'] ?? '0');
    $notas             = trim((string)($p['notas'] ?? ''));

    $toNum = static function($s) {
      $s = trim((string)$s);
      if ($s === '') return 0.0;
      if (preg_match('/^\d{1,3}(\.\d{3})*(,\d+)?$/', $s)) { $s = str_replace('.', '', $s); $s = str_replace(',', '.', $s); }
      else { $s = str_replace(',', '', $s); }
      return (float)$s;
    };

    $decl_credito  = round($toNum($decl_credito_raw),  2);
    $decl_debito   = round($toNum($decl_debito_raw),   2);
    $decl_transfer = round($toNum($decl_transfer_raw), 2);

    $denoms = json_decode($denoms_json, true);
    if (!is_array($denoms)) $denoms = [];
    $total_efectivo = 0.0;
    foreach ($denoms as $row) {
      $den = isset($row['den']) ? (float)$row['den'] : (float)($row['denominacion'] ?? 0);
      $qty = isset($row['qty']) ? (int)$row['qty'] : (int)($row['cantidad'] ?? 0);
      if ($den > 0 && $qty > 0) $total_efectivo += $den * $qty;
    }
    $total_otros = $decl_credito + $decl_debito + $decl_transfer;

    $db->beginTransaction();
    try {
      $t_ef = self::hasTable($db, 'selemti', 'precorte_efectivo');

      if ($t_ef) {
        $db->prepare("DELETE FROM selemti.precorte_efectivo WHERE precorte_id=:id")
           ->execute([':id'=>$precorte_id]);

        $has_denominacion = self::hasColumn($db, 'selemti', 'precorte_efectivo', 'denominacion');
        $has_cantidad     = self::hasColumn($db, 'selemti', 'precorte_efectivo', 'cantidad');
        $has_subtotal     = self::hasColumn($db, 'selemti', 'precorte_efectivo', 'subtotal');

        if ($has_denominacion && $has_cantidad && $has_subtotal && !empty($denoms)) {
          $insEf = $db->prepare("
            INSERT INTO selemti.precorte_efectivo
              (precorte_id, denominacion, cantidad, subtotal)
            VALUES
              (:id, :den, :qty, :sub)
          ");
          foreach ($denoms as $row) {
            $den = isset($row['den']) ? (float)$row['den'] : (float)($row['denominacion'] ?? 0);
            $qty = isset($row['qty']) ? (int)$row['qty'] : (int)($row['cantidad'] ?? 0);
            if ($den > 0 && $qty > 0) $insEf->execute([':id'=>$precorte_id, ':den'=>$den, ':qty'=>$qty, ':sub'=>$den*$qty]);
          }
        }
      }

      $db->prepare("UPDATE selemti.precorte SET declarado_efectivo = :tef WHERE id=:id")
         ->execute([':tef'=>$total_efectivo, ':id'=>$precorte_id]);

      $t_ot = self::hasTable($db, 'selemti', 'precorte_otros');
      if ($t_ot) {
        $db->prepare("DELETE FROM selemti.precorte_otros WHERE precorte_id=:id")
           ->execute([':id'=>$precorte_id]);

        $has_notas_ot = self::hasColumn($db, 'selemti', 'precorte_otros', 'notas');

        if ($has_notas_ot) {
          $insOt = $db->prepare("
            INSERT INTO selemti.precorte_otros (precorte_id, tipo, monto, notas)
            VALUES (:id, :tipo, :monto, :notas)
          ");
          if ($decl_credito  > 0) $insOt->execute([':id'=>$precorte_id, ':tipo'=>'CREDITO',  ':monto'=>$decl_credito,  ':notas'=>$notas]);
          if ($decl_debito   > 0) $insOt->execute([':id'=>$precorte_id, ':tipo'=>'DEBITO',   ':monto'=>$decl_debito,   ':notas'=>$notas]);
          if ($decl_transfer > 0) $insOt->execute([':id'=>$precorte_id, ':tipo'=>'TRANSFER', ':monto'=>$decl_transfer, ':notas'=>$notas]);
        } else {
          $insOt = $db->prepare("
            INSERT INTO selemti.precorte_otros (precorte_id, tipo, monto)
            VALUES (:id, :tipo, :monto)
          ");
          if ($decl_credito  > 0) $insOt->execute([':id'=>$precorte_id, ':tipo'=>'CREDITO',  ':monto'=>$decl_credito ]);
          if ($decl_debito   > 0) $insOt->execute([':id'=>$precorte_id, ':tipo'=>'DEBITO',   ':monto'=>$decl_debito  ]);
          if ($decl_transfer > 0) $insOt->execute([':id'=>$precorte_id, ':tipo'=>'TRANSFER', ':monto'=>$decl_transfer]);
        }
      }

      $db->prepare("UPDATE selemti.precorte SET declarado_otros = :totros WHERE id=:id")
         ->execute([':totros'=>$total_otros, ':id'=>$precorte_id]);

      if ($notas !== '' && self::hasColumn($db, 'selemti', 'precorte', 'notas')) {
        $db->prepare("UPDATE selemti.precorte SET notas=:n WHERE id=:id")
           ->execute([':n'=>$notas, ':id'=>$precorte_id]);
      }

      $db->commit();

      $tot = $db->prepare("SELECT declarado_efectivo, declarado_otros, COALESCE(notas,'') AS notas FROM selemti.precorte WHERE id=:id");
      $tot->execute([':id'=>$precorte_id]);
      $t = $tot->fetch(\PDO::FETCH_ASSOC) ?: ['declarado_efectivo'=>0,'declarado_otros'=>0,'notas'=>''];

      return J($response, [
        'ok'=>true,
        'precorte_id'=>$precorte_id,
        'declarado_efectivo'=>(float)$t['declarado_efectivo'],
        'declarado_otros'   =>(float)$t['declarado_otros'],
        'notas'             =>$t['notas'],
      ]);

    } catch (\Throwable $e) {
      if ($db->inTransaction()) $db->rollBack();
      return J($response, ['ok'=>false,'error'=>'update_failed','detail'=>$e->getMessage()], 500);
    }
  }

  /* ==== Helpers (privados) ==== */
  private static function hasTable(\PDO $db, string $schema, string $table): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=:s AND table_name=:t LIMIT 1");
    $q->execute([':s'=>$schema, ':t'=>$table]);
    return (bool)$q->fetchColumn();
  }
  private static function hasColumn(\PDO $db, string $schema, string $table, string $column): bool {
    $q = $db->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema=:s AND table_name=:t AND column_name=:c LIMIT 1");
    $q->execute([':s'=>$schema, ':t'=>$table, ':c'=>$column]);
    return (bool)$q->fetchColumn();
  }

  /* ===== Estatus (GET/POST/PATCH) ===== */
  public static function statusLegacy(Request $request, Response $response, array $args = []) : Response {
    $db = self::p();

    if (!function_exists('qp')) {
      function qp(Request $r, $k, $def=null){ $q=$r->getQueryParams(); $p=(array)$r->getParsedBody(); return $q[$k]??$p[$k]??$def; }
    }
    if (!function_exists('J')) {
      function J(Response $res, $arr, $code=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($code); $res->getBody()->write(json_encode($arr)); return $res; }
    }

    $precorte_id = 0;
    if (!empty($args['id'])) $precorte_id = (int)$args['id'];
    if (!$precorte_id)       $precorte_id = (int) qp($request, 'id', 0);
    if ($precorte_id <= 0)   return J($response, ['ok'=>false,'error'=>'missing_precorte_id'], 400);

    $p = (array)($request->getParsedBody() ?? []);
    $sesion_estatus   = trim((string)($p['sesion_estatus']   ?? ''));
    $precorte_estatus = trim((string)($p['precorte_estatus'] ?? ''));
    $nota             = trim((string)($p['nota'] ?? $p['notas'] ?? ''));
    $requiere_aut     = !empty($p['requiere_autorizacion']) ? 1 : 0;
    $autorizado       = !empty($p['autorizado']) ? 1 : 0;
    $autorizado_por   = trim((string)($p['autorizado_por'] ?? ''));

    $sx = $db->prepare("SELECT sesion_id, estatus FROM selemti.precorte WHERE id=:id");
    $sx->execute([':id'=>$precorte_id]);
    $row = $sx->fetch(PDO::FETCH_ASSOC);
    if (!$row) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);
    $sesion_id = (int)$row['sesion_id'];
    $estatusDB = (string)$row['estatus'];

    if ($request->getMethod() === 'GET' && empty($p)) {
      return J($response, ['ok'=>true,'id'=>$precorte_id,'sesion_id'=>$sesion_id,'estatus'=>$estatusDB]);
    }

    try {
      $db->beginTransaction();

      $sets   = [];
      $params = [':id'=>$precorte_id];

      if ($precorte_estatus !== '') { $sets[] = "estatus = :pe"; $params[':pe'] = $precorte_estatus; }

      $has_notas  = self::hasColumn($db,'selemti','precorte','notas');
      $has_req    = self::hasColumn($db,'selemti','precorte','requiere_autorizacion');
      $has_aut    = self::hasColumn($db,'selemti','precorte','autorizado');
      $has_autpor = self::hasColumn($db,'selemti','precorte','autorizado_por');
      $has_auten  = self::hasColumn($db,'selemti','precorte','autorizado_en');

      if ($nota !== '' && $has_notas) { $sets[] = "notas = :nota"; $params[':nota'] = $nota; }
      if ($requiere_aut && $has_req)   { $sets[] = "requiere_autorizacion = TRUE"; }

      if ($autorizado && $has_aut) {
        $sets[] = "autorizado = TRUE";
        if ($precorte_estatus==='') { $sets[] = "estatus = 'APROBADO'"; }
        if ($has_autpor && $autorizado_por!==''){ $sets[] = "autorizado_por = :ap"; $params[':ap']=$autorizado_por; }
        if ($has_auten) { $sets[] = "autorizado_en = now()"; }
      }

      if ($sets) {
        $sql = "UPDATE selemti.precorte SET ".implode(', ',$sets)." WHERE id=:id";
        $db->prepare($sql)->execute($params);
      }

      if ($sesion_estatus !== '') {
        $db->prepare("UPDATE selemti.sesion_cajon SET estatus=:se WHERE id=:sid")
           ->execute([':se'=>$sesion_estatus, ':sid'=>$sesion_id]);
      }

      $db->commit();
      return J($response, [
        'ok'=>true,
        'precorte_id'=>$precorte_id,
        'sesion_id'=>$sesion_id,
        'precorte_estatus'=>$precorte_estatus ?: null,
        'sesion_estatus'=>$sesion_estatus ?: null
      ]);
    } catch (\Throwable $e) {
      if ($db->inTransaction()) $db->rollBack();
      if (stripos($e->getMessage(), 'column') !== false && stripos($e->getMessage(), 'does not exist') !== false) {
        return J($response, ['ok'=>true,'soft'=>true,'warning'=>'optional_columns_missing'], 200);
      }
      return J($response, ['ok'=>false,'error'=>'status_update_failed','detail'=>$e->getMessage()], 500);
    }
  }

  // GET (compat Slim4: sin ->write())
  public static function statusGet(Request $request, Response $response, array $args = []) : Response {
    $db = self::p();
    if (!function_exists('J')) { function J(Response $res,$a,$c=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($c); $res->getBody()->write(json_encode($a)); return $res; } }
    $id = (int)($args['id'] ?? ($request->getQueryParams()['id'] ?? 0));
    if ($id<=0) return J($response, ['ok'=>false,'error'=>'missing_precorte_id'], 400);

    $st = $db->prepare("SELECT id,sesion_id,estatus FROM selemti.precorte WHERE id=:id");
    $st->execute([':id'=>$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);

    return J($response, ['ok'=>true,'id'=>(int)$r['id'],'sesion_id'=>(int)$r['sesion_id'],'estatus'=>$r['estatus']]);
  }

  public static function statusPost(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Message\ResponseInterface $response,array $args = []) : \Psr\Http\Message\ResponseInterface {
    // (tu implementación actual ya es Slim4-safe)
    return self::statusLegacy($request, $response, $args);
  }

  public static function statusSet(Request $request, Response $response, array $args) {
    $db  = self::p();
    $id  = isset($args['id']) ? (int)$args['id'] : (int) qp($request, 'id', 0);
    $est = strtoupper((string) qp($request,'estatus',''));
    if (!$id)  return J($response, ['ok'=>false,'error'=>'missing_id'], 400);
    if (!in_array($est,['PENDIENTE','ENVIADO','APROBADO','RECHAZADO'], true))
      return J($response, ['ok'=>false,'error'=>'bad_status'], 400);

    $st = $db->prepare("UPDATE selemti.precorte SET estatus=:e WHERE id=:id RETURNING id, estatus");
    $st->execute([':e'=>$est, ':id'=>$id]);
    $row = $st->fetch();

    return $row
      ? J($response, ['ok'=>true,'precorte_id'=>(int)$row['id'],'estatus'=>$row['estatus']])
      : J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);
  }

  public static function enviar(Request $request, Response $response, array $args) {
    $db = self::p();
    $id = isset($args['id']) ? (int)$args['id'] : 0;
    if (!$id) return J($response, ['ok'=>false,'error'=>'missing_id'], 400);
    $st = $db->prepare("UPDATE selemti.precorte SET estatus='ENVIADO' WHERE id=:id RETURNING id, estatus");
    $st->execute([':id'=>$id]);
    $row = $st->fetch();
    return $row ? J($response, ['ok'=>true,'precorte_id'=>$row['id'],'estatus'=>$row['estatus']])
                : J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);
  }

  public static function createLegacy(Request $request, Response $response, array $args = []){
		$db = self::p();

		// Body
		$p = $request->getParsedBody() ?? [];
		$bdate      = trim((string)($p['bdate'] ?? ''));         // YYYY-MM-DD
		$store_id   = (int)($p['store_id'] ?? 0);
		$terminal_id= (int)($p['terminal_id'] ?? 0);
		$user_id    = (int)($p['user_id'] ?? 0);
		$sesion_id  = (int)($p['sesion_id'] ?? 0);               // ← opcional (MEJOR si lo mandas)

		try{
			// Resolver sesion_id si no viene
			if ($sesion_id <= 0) {
				if (!$terminal_id) return J($response, ['ok'=>false,'error'=>'missing_terminal_id'], 400);
				if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bdate)) $bdate = date('Y-m-d');

				$d0 = $bdate.' 00:00:00';
				$d1 = date('Y-m-d', strtotime($bdate.' +1 day')).' 00:00:00';
				$qS = $db->prepare("
					SELECT id FROM selemti.sesion_cajon
					WHERE terminal_id=:tid
					  AND apertura_ts <  :d1
					  AND COALESCE(cierre_ts, :d1) >= :d0
					ORDER BY apertura_ts DESC
					LIMIT 1
				");
				$qS->execute([':tid'=>$terminal_id, ':d0'=>$d0, ':d1'=>$d1]);
				$sesion_id = (int)($qS->fetchColumn() ?: 0);
				if ($sesion_id <= 0) return J($response, ['ok'=>false,'error'=>'sesion_not_found'], 404);
			}

			// Idempotente: si ya hay precorte para la sesión, regresar el último
			$qP = $db->prepare("SELECT id, estatus, creado_en FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
			$qP->execute([':sid'=>$sesion_id]);
			if ($row = $qP->fetch(\PDO::FETCH_ASSOC)) {
				return J($response, [
					'ok'=>true,
					'precorte_id'=>(int)$row['id'],
					'estatus'=>$row['estatus'] ?? null,
					'creado_en'=>$row['creado_en'] ?? null,
					'ya_existia'=>true
				]);
			}

			// Crear nuevo
			$ins = $db->prepare("INSERT INTO selemti.precorte(sesion_id, estatus) VALUES(:sid, 'PENDIENTE') RETURNING id, creado_en");
			$ins->execute([':sid'=>$sesion_id]);
			$new = $ins->fetch(\PDO::FETCH_ASSOC);

			return J($response, [
				'ok'=>true,
				'precorte_id'=>(int)$new['id'],
				'estatus'=>'PENDIENTE',
				'creado_en'=>$new['creado_en'] ?? null,
				'ya_existia'=>false
			]);
		} catch (\Throwable $e){
			return J($response, ['ok'=>false,'error'=>'create_failed','detail'=>$e->getMessage()], 500);
		}
	}


  // … helpers DPR / vistas (sin cambios) …
	/* Verifica si hay Drawer Pull Report en la ventana de la sesión */
	private static function hasDPR(PDO $db, int $terminal_id, string $a, string $b): bool {
		try {
			if (!$terminal_id || !$a || !$b) return false;
			$q = $db->prepare("
				SELECT 1
				FROM public.drawer_pull_report
				WHERE terminal_id=:t AND report_time BETWEEN :a AND :b
				LIMIT 1
			");
			$q->execute([':t'=>$terminal_id, ':a'=>$a, ':b'=>$b]);
			return (bool)$q->fetchColumn();
		} catch (\Throwable $e) {
			return false;
		}
	}

	/** Lee datos básicos de la sesión */
	private static function sesionInfo(\PDO $db, int $sid): array {
	  $q = $db->prepare("SELECT id, terminal_id, apertura_ts, cierre_ts FROM selemti.sesion_cajon WHERE id=:sid");
	  $q->execute([':sid'=>$sid]);
	  return $q->fetch(\PDO::FETCH_ASSOC) ?: [];
	}
	/* ===== Utilitario: ¿ya hay DPR (POS cut) para la sesión? ===== */
/* Vista auxiliar por sesión (nombre con 'Session') */
private static function hasPOSCutBySession(PDO $db, int $sesion_id): bool {
  try {
    // si la vista no existe, devuelve false sin romper
    $reg = $db->query("SELECT to_regclass('selemti.vw_sesion_dpr')")->fetchColumn();
    if (!$reg) return false;
    $q = $db->prepare("SELECT 1 FROM selemti.vw_sesion_dpr WHERE sesion_id = :sid LIMIT 1");
    $q->execute([':sid'=>$sesion_id]);
    return (bool)$q->fetchColumn();
  } catch (\Throwable $e) {
    return false;
  }
}
	/******************** HELPER: ¿ya hay DPR para la sesión? ********************/
/** ¿Ya existe DPR para terminal+ventana? */
private static function hasPOSCut(\PDO $db, int $terminalId, string $aperturaTs, string $finTs): bool {
  try {
    if (!$terminalId || !$aperturaTs || !$finTs) return false;
    $sql = "
      SELECT 1
      FROM public.drawer_pull_report
      WHERE terminal_id = :t
        AND (report_time AT TIME ZONE current_setting('TIMEZONE')) >= :a::timestamptz
        AND (report_time AT TIME ZONE current_setting('TIMEZONE')) <  :b::timestamptz
      LIMIT 1
    ";
    $st = $db->prepare($sql);
    $st->execute([':t'=>$terminalId, ':a'=>$aperturaTs, ':b'=>$finTs]);
    return (bool)$st->fetchColumn();
  } catch (\Throwable $e) {
    return false;
  }
}

/* Vista auxiliar por sesión (nombre con 'Sesion' que usas en el código) */
private static function hasPOSCutBySesion(\PDO $db, int $sesion_id): bool {
  try {
    $reg = $db->query("SELECT to_regclass('selemti.vw_sesion_dpr')")->fetchColumn();
    if (!$reg) return false;
    $st = $db->prepare("SELECT 1 FROM selemti.vw_sesion_dpr WHERE sesion_id=:sid LIMIT 1");
    $st->execute([':sid'=>$sesion_id]);
    return (bool)$st->fetchColumn();
  } catch (\Throwable $e) {
    return false;
  }
}


/** Recalcula y guarda los totales agregados del precorte. */
	private static function recomputePrecorteTotals(PDO $db, int $precorte_id): void {
		$sql = "
			UPDATE selemti.precorte p
			SET
				declarado_efectivo = COALESCE((
					SELECT SUM(subtotal)::numeric FROM selemti.precorte_efectivo WHERE precorte_id = p.id
				),0),
				declarado_otros = COALESCE((
					SELECT SUM(monto)::numeric FROM selemti.precorte_otros WHERE precorte_id = p.id
				),0)
			WHERE p.id = :id
		";
		$db->prepare($sql)->execute([':id' => $precorte_id]);
	}
	/** Suma pagos por tipo directo de public.transactions en la ventana de la sesión */
	private static function sumTransfersFromPOS(\PDO $db, int $sesion_id): float {
		// Lee ventana de la sesión
		$s = $db->prepare("SELECT terminal_id, apertura_ts, COALESCE(cierre_ts, now()) fin FROM selemti.sesion_cajon WHERE id=:sid");
		$s->execute([':sid'=>$sesion_id]);
		$row = $s->fetch(\PDO::FETCH_ASSOC);
		if (!$row) return 0.0;

		// Filtro: CUSTOM_PAYMENT llamados 'Transferencia' (y variantes), o algún backend que use payment_type='TRANSFER'
		$q = $db->prepare("
			SELECT COALESCE(SUM(t.amount),0)::numeric AS s
			FROM public.transactions t
			WHERE t.reg = :term
				AND (t.transaction_time AT TIME ZONE current_setting('TIMEZONE')) >= :a::timestamptz
				AND (t.transaction_time AT TIME ZONE current_setting('TIMEZONE')) <  :b::timestamptz
				AND t.transaction_type = 'CREDIT'
				AND (
							t.payment_type = 'TRANSFER'
					 OR (t.payment_type = 'CUSTOM_PAYMENT' AND UPPER(t.custom_payment_name) IN ('TRANSFERENCIA','TRANSFER','TRANSFERENCIAS'))
				)
		");
		$q->execute([
			':term' => (int)$row['terminal_id'],
			':a'    => (string)$row['apertura_ts'],
			':b'    => (string)$row['fin'],
		]);
		return (float)$q->fetchColumn();
	}

// ==== NUEVO ====
// Suma de transferencias (CUSTOM PAYMENT / CREDIT / nombre 'Transferencia') en la ventana de la sesión.
// Compara en la misma escala que transactions.transaction_time (timestamp without time zone)
// y filtra por terminal_id si la columna existe.
private static function sysTransfersFromTransactions(PDO $db, int $sesion_id): float {
  // 1) Datos de la sesión (apertura, fin, terminal)
  $q = $db->prepare("
    SELECT terminal_id,
           apertura_ts,
           COALESCE(cierre_ts, (apertura_ts::date + INTERVAL '1 day')) AS fin
    FROM selemti.sesion_cajon
    WHERE id = :sid
  ");
  $q->execute([':sid' => $sesion_id]);
  $s = $q->fetch(PDO::FETCH_ASSOC);
  if (!$s) return 0.0;

  $terminalId = (int)$s['terminal_id'];
  $a = (string)$s['apertura_ts']; // timestamptz
  $b = (string)$s['fin'];         // timestamptz

  // 2) Algunas instalaciones tienen payment_sub_type y otras solo payment_type.
  $hasSubType = self::hasColumn($db, 'public', 'transactions', 'payment_sub_type');
  $hasTermCol = self::hasColumn($db, 'public', 'transactions', 'terminal_id');

  // 3) WHERE dinámico para soportar ambas variantes
  $whereTipo =
    $hasSubType
      ? "payment_sub_type = 'CUSTOM PAYMENT'"
      : "payment_type     = 'CUSTOM_PAYMENT'";

  // 4) Nombre exacto (y cubrimos el typo común 'Tranferencia')
  //    Usamos UPPER para no depender de mayúsculas/minúsculas.
  $nombreOk = "UPPER(custom_payment_name) IN ('TRANSFERENCIA','TRANFERENCIA')";

  // 5) Ventana: pasamos apertura/fin como timestamptz y los convertimos a timestamp local,
  //    que es justamente el tipo de transactions.transaction_time.
  $timeGate = "
    transaction_time >= (:a::timestamptz AT TIME ZONE current_setting('TIMEZONE'))
    AND transaction_time <  (:b::timestamptz AT TIME ZONE current_setting('TIMEZONE'))
  ";

  $sql = "
    SELECT COALESCE(SUM(amount),0)::numeric AS s
    FROM public.transactions
    WHERE {$whereTipo}
      AND transaction_type = 'CREDIT'
      AND {$nombreOk}
      AND {$timeGate}
  ";

  if ($hasTermCol) {
    $sql .= " AND terminal_id = :t";
  }

  $st = $db->prepare($sql);
  $params = [':a' => $a, ':b' => $b];
  if ($hasTermCol) $params[':t'] = $terminalId;

  $st->execute($params);
  return (float)($st->fetchColumn() ?: 0.0);
}

}
