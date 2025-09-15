<?php
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../config.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();
/* AJUSTA SI TU RUTA CAMBIA */
$app->setBasePath('/terrena/Terrena/api');

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

/* ========= HELPERS ========= */
function pdo(): PDO {
  static $pdo=null; if ($pdo) return $pdo;
  $dsn = 'pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME;
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}
function J(Response $r, $data, int $code=200): Response {
  $r->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
  return $r->withHeader('Content-Type','application/json')->withStatus($code);
}
function qp(Request $q, string $k, $def=null) { // query/body param
  $b = $q->getParsedBody() ?: [];
  $g = $q->getQueryParams() ?: [];
  return $b[$k] ?? $g[$k] ?? $def;
}
const DIFF_THRESHOLD = 10; // MXN (luego desde BD)

/* Conciliación sistema por sesión (tolerante a columnas) */
function vwConciliacionBySesion(int $sid): array {
  $sys = [
    'cash'=>0,'credit'=>0,'debit'=>0,'transfer'=>0,'custom'=>0,
    'retiros'=>0,'refunds_cash'=>0,'sistema_efectivo_esperado'=>0,'sistema_tarjetas'=>0
  ];
  $v = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
  $v->execute([':sid'=>$sid]);
  if ($row = $v->fetch()) {
    $cash     = (float)($row['sys_cash']     ?? $row['cash']     ?? 0);
    $credit   = (float)($row['sys_credit']   ?? $row['credit']   ?? 0);
    $debit    = (float)($row['sys_debit']    ?? $row['debit']    ?? 0);
    $transfer = (float)($row['sys_transfer'] ?? $row['transfer'] ?? 0);
    $custom   = (float)($row['sys_custom']   ?? $row['custom']   ?? 0);
    $sys['cash']=$cash; $sys['credit']=$credit; $sys['debit']=$debit;
    $sys['transfer']=$transfer; $sys['custom']=$custom;
    $sys['retiros']=(float)($row['retiros'] ?? 0);
    $sys['refunds_cash']=(float)($row['refunds_cash'] ?? 0);
    $sys['sistema_efectivo_esperado']=(float)($row['sistema_efectivo_esperado'] ?? 0);
    $sys['sistema_tarjetas'] = $credit + $debit + $transfer + $custom;
  }
  return $sys;
}

/* ========= HEALTH ========= */
$app->get('/health', function(Request $q, Response $r) {
  try { pdo()->query('SELECT 1'); return J($r, ['ok'=>true,'db'=>'ok','port'=>DB_PORT]); }
  catch(Throwable $e){ return J($r, ['ok'=>false,'error'=>'db','message'=>$e->getMessage()], 500); }
});

/* ========= SESIONES ========= */
/* GET /sesiones/activa?terminal_id=&usuario_id= */
$app->get('/sesiones/activa', function(Request $q, Response $r) {
  $tid = qp($q,'terminal_id'); $uid = qp($q,'usuario_id');
  if(!$tid || !$uid) return J($r, ['ok'=>false,'error'=>'missing_params (terminal_id, usuario_id)'],400);
  $st = pdo()->prepare("
    SELECT id, terminal_id, cajero_usuario_id, apertura_ts, cierre_ts, estatus, opening_float, closing_float
    FROM selemti.sesion_cajon
    WHERE terminal_id=:t AND cajero_usuario_id=:u
      AND estatus IN ('ACTIVA','LISTO_PARA_CORTE')
    ORDER BY apertura_ts DESC LIMIT 1
  ");
  $st->execute([':t'=>$tid, ':u'=>$uid]);
  $row = $st->fetch();
  return $row ? J($r, ['ok'=>true,'sesion'=>$row]) : J($r, ['ok'=>false,'error'=>'sesion_not_found'],404);
});

/* ========= PREFLIGHT (tickets abiertos) ========= */
$preflight = function(Request $q, Response $r) {
  $sid = qp($q,'sesion_id');
  if(!$sid) return J($r, ['ok'=>false,'error'=>'missing_params (sesion_id)'],400);

  // Sesión → terminal + apertura
  $s = pdo()->prepare("SELECT terminal_id, apertura_ts FROM selemti.sesion_cajon WHERE id=:id");
  $s->execute([':id'=>$sid]);
  $ses = $s->fetch();
  if(!$ses) return J($r,['ok'=>false,'error'=>'sesion_not_found'],404);

  // Abiertos SOLO en ventana de la sesión
  $tq = pdo()->prepare("
    SELECT count(*)::int AS n
    FROM public.ticket t
    WHERE t.terminal_id = :tid
      AND COALESCE(t.voided,false) = false
      AND t.closing_date IS NULL
      AND (COALESCE(t.paid,false) = false OR COALESCE(t.settled,false) = false)
      AND COALESCE(t.due_amount,0) > 0
      AND t.create_date >= :open_ts
  ");
  $tq->execute([':tid'=>$ses['terminal_id'], ':open_ts'=>$ses['apertura_ts']]);
  $abiertos = (int)$tq->fetch()['n'];

  return ($abiertos > 0)
    ? J($r, ['ok'=>false,'error'=>'tickets_open','tickets'=>$abiertos], 409)
    : J($r, ['ok'=>true,'tickets_abiertos'=>$abiertos,'bloqueo'=>false]);
};
// nueva + legacy
$app->get('/precorte/preflight', $preflight);
$app->get('/sprecorte/preflight', $preflight);

/* ========= PRECORTES ========= */
/* POST /precortes  (crear/actualizar) */
$app->post('/precortes', function(Request $q, Response $r) {
  $sid = qp($q,'sesion_id');
  $ef  = qp($q,'efectivo_detalle', []); // [{denominacion,cantidad}]
  $ot  = qp($q,'otros', []);            // [{tipo,monto,referencia?,evidencia_url?,notas?}]
  if(!$sid) return J($r, ['ok'=>false,'error'=>'missing_params (sesion_id)'],400);

  pdo()->beginTransaction();
  try {
    // Reutiliza/crea cabecera
    $st = pdo()->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $st->execute([':sid'=>$sid]);
    $precorte_id = $st->fetch()['id'] ?? null;
    if(!$precorte_id){
      $ins = pdo()->prepare("INSERT INTO selemti.precorte (sesion_id, estatus) VALUES (:sid,'PENDIENTE') RETURNING id");
      $ins->execute([':sid'=>$sid]);
      $precorte_id = (int)$ins->fetch()['id'];
    }

    // efectivo
    if (is_array($ef)) {
      pdo()->prepare("DELETE FROM selemti.precorte_efectivo WHERE precorte_id=:id")->execute([':id'=>$precorte_id]);
      $insEF = pdo()->prepare("
        INSERT INTO selemti.precorte_efectivo (precorte_id, denominacion, cantidad)
        VALUES (:pid, :den, :qty)
      ");
      foreach($ef as $row){
        if (!isset($row['denominacion'],$row['cantidad'])) continue;
        $insEF->execute([':pid'=>$precorte_id, ':den'=>$row['denominacion'], ':qty'=>$row['cantidad']]);
      }
    }

    // otros (si existe la tabla)
    $hasOtros = false;
    try { $x = pdo()->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch(); $hasOtros=!empty($x['t']); } catch(Throwable $e){}
    if ($hasOtros && is_array($ot)){
      pdo()->prepare("DELETE FROM selemti.precorte_otros WHERE precorte_id=:id")->execute([':id'=>$precorte_id]);
      $insOT = pdo()->prepare("
        INSERT INTO selemti.precorte_otros (precorte_id, tipo, monto, referencia, evidencia_url, notas)
        VALUES (:pid,:tipo,:monto,:ref,:url,:notas)
      ");
      foreach($ot as $row){
        if (!isset($row['tipo'],$row['monto'])) continue;
        $insOT->execute([
          ':pid'=>$precorte_id, ':tipo'=>$row['tipo'], ':monto'=>$row['monto'],
          ':ref'=>$row['referencia'] ?? null, ':url'=>$row['evidencia_url'] ?? null, ':notas'=>$row['notas'] ?? null
        ]);
      }
    }

    // Totales declarados
    $tEF = pdo()->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric AS s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
    $tEF->execute([':id'=>$precorte_id]);
    $declarado_ef = (float)$tEF->fetch()['s'];

    $declarado_ot = 0.0;
    if ($hasOtros){
      $tOT = pdo()->prepare("SELECT COALESCE(SUM(monto),0)::numeric AS s FROM selemti.precorte_otros WHERE precorte_id=:id");
      $tOT->execute([':id'=>$precorte_id]);
      $declarado_ot = (float)$tOT->fetch()['s'];
    } else if (is_array($ot)){
      foreach($ot as $row) if(isset($row['monto'])) $declarado_ot += (float)$row['monto'];
    }

    pdo()->commit();
    return J($r, ['ok'=>true,'precorte_id'=>$precorte_id,'estatus'=>'PENDIENTE','totales'=>[
      'efectivo'=>$declarado_ef,'otros'=>$declarado_ot
    ]]);
  } catch(Throwable $e){
    pdo()->rollBack();
    return J($r, ['ok'=>false,'error'=>'tx_failed','message'=>$e->getMessage()], 500);
  }
});

/* POST /precortes/{id}/enviar */
$app->post('/precortes/{id}/enviar', function(Request $q, Response $r, array $a) {
  $id = (int)$a['id'];
  $st = pdo()->prepare("UPDATE selemti.precorte SET estatus='ENVIADO' WHERE id=:id RETURNING id, estatus");
  $st->execute([':id'=>$id]);
  $row = $st->fetch();
  return $row ? J($r, ['ok'=>true,'precorte_id'=>$row['id'],'estatus'=>$row['estatus']])
              : J($r, ['ok'=>false,'error'=>'precorte_not_found'],404);
});

/* GET /precortes/{id}/resumen */
$app->get('/precortes/{id}/resumen', function(Request $q, Response $r, array $a) {
  $id = (int)$a['id'];

  // Sesión del precorte
  $p = pdo()->prepare("SELECT sesion_id FROM selemti.precorte WHERE id=:id");
  $p->execute([':id'=>$id]);
  $prec = $p->fetch();
  if(!$prec) return J($r, ['ok'=>false,'error'=>'precorte_not_found'],404);

  $sid = (int)$prec['sesion_id'];

  // Declarado
  $tEF = pdo()->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric AS s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
  $tEF->execute([':id'=>$id]);
  $decl_ef = (float)$tEF->fetch()['s'];

  $decl_ot = ['CREDITO'=>0,'DEBITO'=>0,'TRANSFER'=>0,'CUSTOM'=>0];
  try {
    $has = pdo()->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch();
    if(!empty($has['t'])){
      $qot = pdo()->prepare("SELECT tipo, COALESCE(SUM(monto),0)::numeric AS monto FROM selemti.precorte_otros WHERE precorte_id=:id GROUP BY tipo");
      $qot->execute([':id'=>$id]);
      foreach($qot as $row){ $decl_ot[$row['tipo']] = (float)$row['monto']; }
    }
  } catch(Throwable $e){}

  // Sistema (vista)
  $sys = vwConciliacionBySesion($sid);

  $dif = [
    'efectivo' => $decl_ef - $sys['sistema_efectivo_esperado'],
    'tarjetas' => array_sum($decl_ot) - $sys['sistema_tarjetas']
  ];

  return J($r, [
    'ok'=>true,
    'sesion'=>['id'=>$sid],
    'sistema'=>$sys,
    'declarado'=>['efectivo'=>$decl_ef,'otros'=>$decl_ot],
    'dif'=>$dif
  ]);
});

/* ========= POSTCORTES ========= */
/* POST /postcortes  (veredictos con umbral 10) */
$app->post('/postcortes', function(Request $q, Response $r) {
  $sid = qp($q,'sesion_id');
  $deE = (float)qp($q,'declarado_efectivo_fin', 0);
  $deT = (float)qp($q,'declarado_tarjetas_fin', 0);
  $not = qp($q,'notas', null);
  if(!$sid) return J($r, ['ok'=>false,'error':'missing_params (sesion_id)'],400);

  $sys = vwConciliacionBySesion((int)$sid);
  $sysE = (float)($sys['sistema_efectivo_esperado'] ?? 0);
  $sysT = (float)($sys['sistema_tarjetas'] ?? 0);

  $difE = $deE - $sysE;
  $difT = $deT - $sysT;

  $ver = function(float $dif): string {
    if (abs($dif) <= DIFF_THRESHOLD) return 'CUADRA';
    return $dif > 0 ? 'A_FAVOR' : 'EN_CONTRA';
  };
  $verE = $ver($difE);
  $verT = $ver($difT);

  try{
    $ins = pdo()->prepare("
      INSERT INTO selemti.postcorte
      (sesion_id, sistema_efectivo_esperado, declarado_efectivo, diferencia_efectivo, veredicto_efectivo,
       sistema_tarjetas, declarado_tarjetas, diferencia_tarjetas, veredicto_tarjetas, notas)
      VALUES
      (:sid,:sysE,:deE,:difE,:verE,:sysT,:deT,:difT,:verT,:notas)
      RETURNING id
    ");
    $ins->execute([
      ':sid'=>$sid, ':sysE'=>$sysE, ':deE'=>$deE, ':difE'=>$difE, ':verE'=>$verE,
      ':sysT'=>$sysT, ':deT'=>$deT, ':difT'=>$difT, ':verT'=>$verT, ':notas'=>$not
    ]);
    $pid = (int)$ins->fetch()['id'];
    return J($r, [
      'ok'=>true,'postcorte_id'=>$pid,
      'efectivo'=>['esperado'=>$sysE,'declarado'=>$deE,'dif'=>$difE,'veredicto'=>$verE],
      'tarjetas'=>['sistema'=>$sysT,'declarado'=>$deT,'dif'=>$difT,'veredicto'=>$verT]
    ]);
  } catch(Throwable $e){
    return J($r, ['ok'=>false,'error'=>'postcorte_failed','message'=>$e->getMessage()],500);
  }
});

/* ========= CONCILIACIÓN ========= */
$app->get('/conciliacion/{sesion_id}', function(Request $q, Response $r, array $a) {
  $sid = (int)$a['sesion_id'];
  $st  = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
  $st->execute([':sid'=>$sid]);
  $row = $st->fetch();
  return $row ? J($r, ['ok'=>true,'data'=>$row])
              : J($r, ['ok'=>false,'error'=>'not_found_for_session','sesion_id'=>$sid], 404);
});
$app->get('/conciliacion/by-precorte/{precorte_id}', function(Request $q, Response $r, array $a) {
  $pid = (int)$a['precorte_id'];
  $p = pdo()->prepare("SELECT sesion_id FROM selemti.precorte WHERE id=:id");
  $p->execute([':id'=>$pid]);
  $x = $p->fetch();
  if(!$x) return J($r, ['ok'=>false,'error'=>'precorte_not_found'],404);
  $sid = (int)$x['sesion_id'];
  $st  = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
  $st->execute([':sid'=>$sid]);
  $vw = $st->fetch();
  return $vw ? J($r, ['ok'=>true,'data'=>$vw])
             : J($r, ['ok'=>false,'error'=>'not_found_for_session','sesion_id'=>$sid], 404);
});

/* ========= ESTATUS ========= */
/* POST /precortes/{id}/estatus */
$app->post('/precortes/{id}/estatus', function(Request $q, Response $r, array $a) {
  $id  = (int)$a['id'];
  $est = strtoupper((string)qp($q,'estatus'));
  if(!in_array($est, ['PENDIENTE','ENVIADO','APROBADO','RECHAZADO'], true))
    return J($r, ['ok'=>false,'error'=>'bad_status'],400);

  $st = pdo()->prepare("UPDATE selemti.precorte SET estatus=:e WHERE id=:id RETURNING id, estatus");
  $st->execute([':e'=>$est, ':id'=>$id]);
  $row = $st->fetch();
  return $row ? J($r, ['ok'=>true,'precorte_id'=>$row['id'],'estatus'=>$row['estatus']])
              : J($r, ['ok'=>false,'error'=>'precorte_not_found'],404);
});

/* GET/POST /estatus?sesion_id= | precorte_id= */
$app->map(['GET','POST'], '/estatus', function(Request $q, Response $r) {
  $sid = qp($q,'sesion_id'); $pid = qp($q,'precorte_id');

  if(!$sid && $pid){
    $p = pdo()->prepare("SELECT sesion_id FROM selemti.precorte WHERE id=:id");
    $p->execute([':id'=>$pid]); $x=$p->fetch(); if(!$x) return J($r,['ok'=>false,'error'=>'precorte_not_found'],404);
    $sid = (int)$x['sesion_id'];
  }
  if(!$sid) return J($r,['ok'=>false,'error'=>'missing_params (sesion_id|precorte_id)'],400);

  $ses = pdo()->prepare("SELECT id, estatus, apertura_ts, cierre_ts, opening_float, closing_float FROM selemti.sesion_cajon WHERE id=:id");
  $ses->execute([':id'=>$sid]); $sesion = $ses->fetch();

  $pre = pdo()->prepare("SELECT id, estatus, creado_en FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
  $pre->execute([':sid'=>$sid]); $precorte = $pre->fetch();

  $pos = pdo()->prepare("SELECT id, veredicto_efectivo, veredicto_tarjetas, notas, creado_en FROM selemti.postcorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
  $pos->execute([':sid'=>$sid]); $postcorte = $pos->fetch();

  return J($r, ['ok'=>true,'sesion'=>$sesion,'precorte'=>$precorte,'postcorte'=>$postcorte]);
});

/* ========= FORMAS DE PAGO ========= */
$app->get('/formas-pago', function(Request $q, Response $r) {
  $st = pdo()->query("
    SELECT id,codigo,payment_type,transaction_type,payment_sub_type,custom_name,custom_ref,activo,prioridad,creado_en
    FROM selemti.formas_pago
    WHERE activo = true
    ORDER BY prioridad, id
  ");
  return J($r, ['ok'=>true,'items'=>$st->fetchAll()]);
});

/* ========= LEGACY /api/caja/*.php ========= */
/* precorte_create.php */
$app->map(['GET','POST'], '/caja/precorte_create.php', function(Request $q, Response $r){
  $bdate = qp($q,'bdate'); $tid = qp($q,'terminal_id'); $uid = qp($q,'user_id');
  if(!$tid || !$uid) return J($r, ['ok'=>false,'error'=>'missing_params (bdate?, terminal_id, user_id)'],400);

  // Sesión actual (activa|listo para corte)
  $st = pdo()->prepare("
    SELECT id FROM selemti.sesion_cajon
    WHERE terminal_id=:t AND cajero_usuario_id=:u
      AND estatus IN ('ACTIVA','LISTO_PARA_CORTE')
    ORDER BY apertura_ts DESC LIMIT 1
  ");
  $st->execute([':t'=>$tid, ':u'=>$uid]);
  $sid = $st->fetch()['id'] ?? null;
  if(!$sid) return J($r, ['ok'=>false,'error'=>'sesion_not_found'],404);

  // Reutiliza/crea precorte
  $pc = pdo()->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
  $pc->execute([':sid'=>$sid]); $pid = $pc->fetch()['id'] ?? null;
  if(!$pid){
    $ins = pdo()->prepare("INSERT INTO selemti.precorte (sesion_id, estatus) VALUES (:sid,'PENDIENTE') RETURNING id");
    $ins->execute([':sid'=>$sid]); $pid = (int)$ins->fetch()['id'];
  }
  return J($r, ['ok'=>true,'sesion_id'=>$sid,'precorte_id'=>$pid,'status'=>'PENDIENTE']);
});

/* precorte_update.php */
$app->map(['GET','POST'], '/caja/precorte_update.php', function(Request $q, Response $r){
  $pid = (int)(qp($q,'precorte_id') ?? qp($q,'id'));
  if(!$pid) return J($r, ['ok'=>false,'error'=>'missing_params (id|precorte_id)'],400);

  // efectivo: denoms_json o qty_*
  $ef = qp($q,'denoms_json') ?? qp($q,'denoms') ?? [];
  if (is_string($ef)) { $tmp=json_decode($ef,true); if (json_last_error()===JSON_ERROR_NONE) $ef=$tmp; }
  if (!is_array($ef)) { $ef=[]; }
  foreach($q->getQueryParams() as $k=>$v) if (strpos($k,'qty_')===0) {
    $den=(int)substr($k,4); $ef[]=['denominacion'=>$den,'cantidad'=>(int)$v];
  }

  // otros: declarado_credito/debito/transfer
  $otros = [];
  foreach (['CREDITO'=>'declarado_credito','DEBITO'=>'declarado_debito','TRANSFER'=>'declarado_transfer'] as $tipo=>$key){
    $val = qp($q,$key); if($val!==null && $val!=='') $otros[]=['tipo'=>$tipo,'monto'=>(float)$val];
  }

  pdo()->beginTransaction();
  try{
    pdo()->prepare("DELETE FROM selemti.precorte_efectivo WHERE precorte_id=:id")->execute([':id'=>$pid]);
    if($ef){
      $insEF = pdo()->prepare("INSERT INTO selemti.precorte_efectivo (precorte_id,denominacion,cantidad) VALUES (:pid,:den,:qty)");
      foreach($ef as $row){ if(!isset($row['denominacion'],$row['cantidad'])) continue;
        $insEF->execute([':pid'=>$pid, ':den'=>$row['denominacion'], ':qty'=>$row['cantidad']]);
      }
    }
    $hasOtros = !empty(pdo()->query("SELECT to_regclass('selemti.precorte_otros') t")->fetch()['t']);
    if($hasOtros){
      pdo()->prepare("DELETE FROM selemti.precorte_otros WHERE precorte_id=:id")->execute([':id'=>$pid]);
      $insOT = pdo()->prepare("INSERT INTO selemti.precorte_otros (precorte_id,tipo,monto) VALUES (:pid,:tipo,:monto)");
      foreach($otros as $o){ $insOT->execute([':pid'=>$pid, ':tipo'=>$o['tipo'], ':monto'=>$o['monto']]); }
    }
    $tEF = pdo()->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
    $tEF->execute([':id'=>$pid]); $de_ef = (float)$tEF->fetch()['s'];

    $de_ot = array_sum(array_column($otros,'monto'));
    if($hasOtros){
      $tOT = pdo()->prepare("SELECT COALESCE(SUM(monto),0)::numeric s FROM selemti.precorte_otros WHERE precorte_id=:id");
      $tOT->execute([':id'=>$pid]); $de_ot = (float)$tOT->fetch()['s'];
    }
    pdo()->commit();
    return J($r, ['ok'=>true,'precorte_id'=>$pid,'totales'=>['efectivo'=>$de_ef,'otros'=>$de_ot]]);
  } catch(Throwable $e){
    pdo()->rollBack();
    return J($r, ['ok'=>false,'error'=>'tx_failed','message'=>$e->getMessage()],500);
  }
});

/* precorte_totales.php */
$app->map(['GET','POST'], '/caja/precorte_totales.php', function(Request $q, Response $r){
  $pid = (int)(qp($q,'precorte_id') ?? qp($q,'id'));
  if(!$pid) return J($r, ['ok'=>false,'error'=>'missing_params (id|precorte_id)'],400);
  $p = pdo()->prepare("SELECT sesion_id FROM selemti.precorte WHERE id=:id");
  $p->execute([':id'=>$pid]); $x=$p->fetch(); if(!$x) return J($r, ['ok'=>false,'error'=>'precorte_not_found'],404);
  $sid = (int)$x['sesion_id'];

  $tEF = pdo()->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
  $tEF->execute([':id'=>$pid]); $decl_ef = (float)$tEF->fetch()['s'];

  $sys = vwConciliacionBySesion($sid);
  return J($r, [
    'ok'=>true,
    'sesion'=>['id'=>$sid],
    'data'=>[
      'efectivo'=>['declarado'=>$decl_ef,'sistema'=>$sys['sistema_efectivo_esperado']],
      'tarjeta_credito'=>['declarado'=>0,'sistema'=>$sys['credit']],
      'tarjeta_debito' =>['declarado'=>0,'sistema'=>$sys['debit']],
      'transferencias' =>['declarado'=>0,'sistema'=>$sys['transfer']]
    ]
  ]);
});

/* cortes.php → usa POST /postcortes */
$app->map(['GET','POST'], '/caja/cortes.php', function(Request $q, Response $r) use ($app){
  $sid = qp($q,'sesion_id') ?? qp($q,'id_sesion');
  $deE = qp($q,'declarado_efectivo_fin') ?? qp($q,'efectivo');
  $deT = qp($q,'declarado_tarjetas_fin') ?? qp($q,'tarjetas');
  $not = qp($q,'notas') ?? qp($q,'nota');
  $req = $q->withParsedBody(['sesion_id'=>$sid,'declarado_efectivo_fin'=>$deE,'declarado_tarjetas_fin'=>$deT,'notas'=>$not]);
  return $app->handle($req->withMethod('POST')->withUri($q->getUri()->withPath($app->getBasePath().'/postcortes')));
});

/* cajas.php → listado de terminales/sesiones (por fecha) */
$app->map(['GET','POST'], '/caja/cajas.php', function(Request $q, Response $r){
  $date = qp($q,'date') ?: date('Y-m-d');
  $tid  = qp($q,'tid'); // opcional: filtrar terminal

  // Buscar sesiones cuya apertura o cierre caiga en esa fecha
  $sql = "
    SELECT s.id AS sesion_id, s.terminal_id, s.cajero_usuario_id, s.apertura_ts, s.cierre_ts, s.estatus,
           s.opening_float, s.closing_float,
           t.name AS terminal_name
    FROM selemti.sesion_cajon s
    LEFT JOIN public.terminal t ON t.id = s.terminal_id
    WHERE (s.apertura_ts::date = :d OR (s.cierre_ts IS NOT NULL AND s.cierre_ts::date = :d))
  ";
  $args = [':d'=>$date];
  if ($tid) { $sql .= " AND s.terminal_id = :tid"; $args[':tid']=(int)$tid; }
  $sql .= " ORDER BY s.terminal_id, s.apertura_ts DESC";

  $rows = pdo()->prepare($sql);
  $rows->execute($args);

  $terminals = [];
  foreach ($rows as $s) {
    $sesion_id = (int)$s['sesion_id'];
    // Precorte (si existe)
    $pc = pdo()->prepare("SELECT id, estatus FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $pc->execute([':sid'=>$sesion_id]);
    $prec = $pc->fetch();

    // Postcorte
    $pos = pdo()->prepare("SELECT id FROM selemti.postcorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $pos->execute([':sid'=>$sesion_id]);
    $has_postcorte = (bool)$pos->fetch();

    // Totales sistema (vw)
    $sys = vwConciliacionBySesion($sesion_id);

    // DPR (existe registro en ventana)
    $has_pull = false;
    try {
      $dpr = pdo()->prepare("SELECT 1 FROM selemti.vw_sesion_dpr WHERE sesion_id=:sid LIMIT 1");
      $dpr->execute([':sid'=>$sesion_id]);
      $has_pull = (bool)$dpr->fetch();
    } catch(Throwable $e){}

    // Nombre de usuario (si está en users)
    $assigned_user_name = null;
    try {
      $u = pdo()->prepare("SELECT COALESCE(full_name, user_name, username) AS name FROM public.users WHERE auto_id=:id");
      $u->execute([':id'=>$s['cajero_usuario_id']]);
      $assigned_user_name = $u->fetch()['name'] ?? null;
    } catch(Throwable $e){}

    $terminals[] = [
      'id' => (int)$s['terminal_id'],
      'name' => $s['terminal_name'] ?? ('Terminal '.$s['terminal_id']),
      'assigned_user' => (int)$s['cajero_usuario_id'],
      'assigned_user_name' => $assigned_user_name,
      'sesion' => [
        'id'=>$sesion_id,
        'apertura_ts'=>$s['apertura_ts'],
        'cierre_ts'=>$s['cierre_ts'],
        'estatus'=>$s['estatus'],
        'opening_float'=>(float)$s['opening_float'],
        'closing_float'=>$s['closing_float']!==null ? (float)$s['closing_float'] : null
      ],
      'precorte' => $prec ? ['id'=>(int)$prec['id'],'estatus'=>$prec['estatus']] : null,
      'has_postcorte' => $has_postcorte,
      'ventas_pos' => [
        'cash'=>$sys['cash'], 'credit'=>$sys['credit'], 'debit'=>$sys['debit'],
        'transfer'=>$sys['transfer'], 'custom'=>$sys['custom']
      ],
      'dpr' => ['has_pull'=>$has_pull]
    ];
  }

  return J($r, ['ok'=>true,'date'=>$date,'terminals'=>$terminals]);
});

/* cajas_debug.php → igual que cajas, pero agrega “raw” de conciliación */
$app->map(['GET','POST'], '/caja/cajas_debug.php', function(Request $q, Response $r){
  $date = qp($q,'date') ?: date('Y-m-d');
  $tid  = qp($q,'tid');

  $sql = "
    SELECT s.id AS sesion_id, s.terminal_id, s.cajero_usuario_id, s.apertura_ts, s.cierre_ts, s.estatus,
           s.opening_float, s.closing_float,
           t.name AS terminal_name
    FROM selemti.sesion_cajon s
    LEFT JOIN public.terminal t ON t.id = s.terminal_id
    WHERE (s.apertura_ts::date = :d OR (s.cierre_ts IS NOT NULL AND s.cierre_ts::date = :d))
  ";
  $args = [':d'=>$date];
  if ($tid) { $sql .= " AND s.terminal_id = :tid"; $args[':tid']=(int)$tid; }
  $sql .= " ORDER BY s.terminal_id, s.apertura_ts DESC";
  $rows = pdo()->prepare($sql);
  $rows->execute($args);

  $terminals = [];
  foreach ($rows as $s) {
    $sesion_id = (int)$s['sesion_id'];

    $pc = pdo()->prepare("SELECT id, estatus FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $pc->execute([':sid'=>$sesion_id]);
    $prec = $pc->fetch();

    $pos = pdo()->prepare("SELECT id FROM selemti.postcorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $pos->execute([':sid'=>$sesion_id]);
    $has_postcorte = (bool)$pos->fetch();

    $vwStmt = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $vwStmt->execute([':sid'=>$sesion_id]);
    $vwrow = $vwStmt->fetch();
    $sys = vwConciliacionBySesion($sesion_id);

    $has_pull = false;
    try {
      $dpr = pdo()->prepare("SELECT 1 FROM selemti.vw_sesion_dpr WHERE sesion_id=:sid LIMIT 1");
      $dpr->execute([':sid'=>$sesion_id]);
      $has_pull = (bool)$dpr->fetch();
    } catch(Throwable $e){}

    $assigned_user_name = null;
    try {
      $u = pdo()->prepare("SELECT COALESCE(full_name, user_name, username) AS name FROM public.users WHERE auto_id=:id");
      $u->execute([':id'=>$s['cajero_usuario_id']]);
      $assigned_user_name = $u->fetch()['name'] ?? null;
    } catch(Throwable $e){}

    $terminals[] = [
      'id' => (int)$s['terminal_id'],
      'name' => $s['terminal_name'] ?? ('Terminal '.$s['terminal_id']),
      'assigned_user' => (int)$s['cajero_usuario_id'],
      'assigned_user_name' => $assigned_user_name,
      'sesion' => [
        'id'=>$sesion_id,'apertura_ts'=>$s['apertura_ts'],'cierre_ts'=>$s['cierre_ts'],
        'estatus'=>$s['estatus'],'opening_float'=>(float)$s['opening_float'],
        'closing_float'=>$s['closing_float']!==null ? (float)$s['closing_float'] : null
      ],
      'precorte' => $prec ? ['id'=>(int)$prec['id'],'estatus'=>$prec['estatus']] : null,
      'has_postcorte' => $has_postcorte,
      'ventas_pos' => [
        'cash'=>$sys['cash'], 'credit'=>$sys['credit'], 'debit'=>$sys['debit'],
        'transfer'=>$sys['transfer'], 'custom'=>$sys['custom']
      ],
      'dpr' => ['has_pull'=>$has_pull],
      'debug' => ['vw_conciliacion'=>$vwrow]
    ];
  }
  return J($r, ['ok'=>true,'date'=>$date,'terminals'=>$terminals]);
});

$app->run();
