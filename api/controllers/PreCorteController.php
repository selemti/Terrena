<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PreCorteController {

  /** Resuelve PDO sin tocar tu core (intenta /api/config.php, luego /config.php; si no, usa pdo() existente) */
  private static function p(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $candidates = [
      __DIR__ . '/../config.php',   // /api/config.php
      __DIR__ . '/../../config.php' // /config.php
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

    // terminal de la sesión
    $st = $db->prepare("SELECT terminal_id FROM selemti.sesion_cajon WHERE id=:id");
    $st->execute([':id'=>$sid]);
    $row = $st->fetch();
    if (!$row) return J($response, ['ok'=>false,'error'=>'sesion_not_found'], 404);
    $tid = (int)$row['terminal_id'];

    // tickets abiertos en esa terminal (no pagados, no void)
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

    // si vino id de precorte, resolver sesión
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
        SELECT id
        FROM selemti.sesion_cajon
        WHERE terminal_id = :tid
          AND cajero_usuario_id = :uid
          AND (apertura_ts::date = :d OR (cierre_ts IS NOT NULL AND cierre_ts::date = :d))
        ORDER BY apertura_ts DESC
        LIMIT 1
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

    // Bloqueo por tickets abiertos
    $pre = self::preflight($request, new \Slim\Psr7\Response(), ['sesion_id'=>$sid]);
    $preData = json_decode((string)$pre->getBody(), true);
    if (!$preData['ok']) return J($response, $preData, 409);

    // Upsert encabezado
    $st = $db->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $st->execute([':sid'=>$sid]);
    $precorte_id = (int)($st->fetch()['id'] ?? 0);

    if (!$precorte_id) {
      $ins = $db->prepare("INSERT INTO selemti.precorte (sesion_id, estatus) VALUES (:sid,'PENDIENTE') RETURNING id");
      $ins->execute([':sid'=>$sid]);
      $precorte_id = (int)$ins->fetch()['id'];
    }

    return J($response, ['ok'=>true,'precorte_id'=>$precorte_id,'sesion_id'=>$sid]);
  }

  /* ===== Resumen (nuevo diseño; acepta id o sesion_id) ===== */
public static function resumen(Request $request, Response $response, array $args) {
  $db        = self::p();
  $id        = isset($args['id']) ? (int)$args['id'] : (int) qp($request, 'id', 0);
  $sesion_id = isset($args['sesion_id']) ? (int)$args['sesion_id'] : (int) qp($request, 'sesion_id', 0);

  try {
    // --- Modo "solo sesion_id": se usa como gate (no necesitamos totales aquí)
    if (!$id && $sesion_id) {
      // ¿hay precorte para esa sesión?
      $s = $db->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
      $s->execute([':sid'=>$sesion_id]);
      $id = (int)($s->fetch()['id'] ?? 0);
      if (!$id) return J($response, ['ok'=>false,'error'=>'precorte_not_found','sesion_id'=>$sesion_id], 404);

      // Ventana de sesión para validar DPR
      $ss = $db->prepare("SELECT terminal_id, apertura_ts, COALESCE(cierre_ts, :hoyfin) AS cierre_ts
                          FROM selemti.sesion_cajon WHERE id=:sid");
      $ss->execute([':sid'=>$sesion_id, ':hoyfin'=>date('Y-m-d 23:59:59')]);
      $rowS = $ss->fetch();
      if (!$rowS) return J($response, ['ok'=>false,'error'=>'sesion_not_found'], 404);

      $tid = (int)$rowS['terminal_id'];
      $a   = (string)$rowS['apertura_ts'];
      $b   = (string)$rowS['cierre_ts'];

      if (!self::hasPOSCut($db, $tid, $a, $b)) {
        return J($response, [
          'ok'=>false, 'error'=>'pos_cut_missing', 'require_pos_cut'=>true,
          'sesion_id'=>$sesion_id, 'precorte_id'=>$id
        ], 412);
      }

      // Gate OK: con esto el front sabe que ya hay DPR y puede abrir Paso 2
      return J($response, ['ok'=>true, 'sesion'=>['id'=>$sesion_id], 'precorte_id'=>$id, 'has_pos_cut'=>true]);
    }

    // --- Desde aquí, flujo original con precorte_id
    if (!$id) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);

    // Sesión + ventana y terminal del precorte
    $p = $db->prepare("SELECT p.sesion_id, s.terminal_id,
                              s.apertura_ts, COALESCE(s.cierre_ts, :hoyfin) AS cierre_ts
                       FROM selemti.precorte p
                       JOIN selemti.sesion_cajon s ON s.id=p.sesion_id
                       WHERE p.id=:id");
    $p->execute([':id'=>$id, ':hoyfin'=>date('Y-m-d 23:59:59')]);
    $rowP = $p->fetch();
    if(!$rowP) return J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);

    $sid = (int)$rowP['sesion_id'];
    $tid = (int)$rowP['terminal_id'];
    $a   = (string)$rowP['apertura_ts'];
    $b   = (string)$rowP['cierre_ts'];

    // (Opcional pero recomendado) Gate DPR también cuando piden por id
    if (!self::hasPOSCut($db, $tid, $a, $b)) {
      return J($response, [
        'ok'=>false,'error'=>'pos_cut_missing','require_pos_cut'=>true,
        'sesion_id'=>$sid,'precorte_id'=>$id
      ], 412);
    }

    // ===== Declarado efectivo
    $tEF = $db->prepare("SELECT COALESCE(SUM(subtotal),0)::numeric s FROM selemti.precorte_efectivo WHERE precorte_id=:id");
    $tEF->execute([':id'=>$id]);
    $decl_ef = (float)($tEF->fetch()['s'] ?? 0);

    // ===== Declarado no efectivo (si existe tabla)
    $decl_ot = ['CREDITO'=>0,'DEBITO'=>0,'TRANSFER'=>0,'CUSTOM'=>0,'GIFT_CERT'=>0];
    $hasOtros = !empty($db->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch()['t']);
    if($hasOtros){
      $qot = $db->prepare("SELECT UPPER(tipo) AS tipo, COALESCE(SUM(monto),0)::numeric AS monto
                           FROM selemti.precorte_otros WHERE precorte_id=:id GROUP BY UPPER(tipo)");
      $qot->execute([':id'=>$id]);
      foreach($qot as $r){
        $k = $r['tipo'];
        if (isset($decl_ot[$k])) $decl_ot[$k] = (float)$r['monto'];
      }
    }

    // ===== Sistema vía vista
    $vw = $db->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $vw->execute([':sid'=>$sid]);
    $sys = [
      'cash'=>0,'credit'=>0,'debit'=>0,'transfer'=>0,'custom'=>0,'gift'=>0,
      'retiros'=>0,'refunds_cash'=>0,'sistema_efectivo_esperado'=>0,'sistema_tarjetas'=>0
    ];
    if($r = $vw->fetch()){
      $cash     = (float)($r['sys_cash']     ?? $r['cash']     ?? 0);
      $credit   = (float)($r['sys_credito']  ?? $r['sys_credit']   ?? $r['credit']   ?? 0);
      $debit    = (float)($r['sys_debito']   ?? $r['sys_debit']    ?? $r['debit']    ?? 0);
      $transfer = (float)($r['sys_transfer'] ?? $r['transfer'] ?? 0);
      $custom   = (float)($r['sys_custom']   ?? $r['custom']   ?? 0);
      $gift     = (float)($r['sys_gift']     ?? 0);

      $sys['cash']=$cash; $sys['credit']=$credit; $sys['debit']=$debit; $sys['transfer']=$transfer; $sys['custom']=$custom; $sys['gift']=$gift;
      $sys['retiros']=(float)($r['retiros'] ?? 0);
      $sys['refunds_cash']=(float)($r['refunds_cash'] ?? $r['reembolsos_efectivo'] ?? 0);
      $sys['sistema_efectivo_esperado']=(float)($r['sistema_efectivo_esperado'] ?? 0);
      $sys['sistema_tarjetas']=$credit+$debit+$transfer+$custom+$gift;
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
      'umbral'=>DIFF_THRESHOLD,
      'has_pos_cut'=>true
    ]);

  } catch (\Throwable $e) {
    return J($response, ['ok'=>false,'error'=>'internal_error','detail'=>$e->getMessage()], 500);
  }
}


/* ===== Legacy: payload para wizard (espera j.data.*) ===== */
/* ===== Legacy: payload para wizard (espera j.data.*) ===== */
public static function resumenLegacy(Request $request, Response $response, array $args = []) {
  if (!function_exists('qp')) {
    function qp(Request $r, $k, $def=null){ $q=$r->getQueryParams(); $p=(array)$r->getParsedBody(); return $q[$k]??$p[$k]??$def; }
  }
  if (!function_exists('J')) {
    function J(Response $res, $arr, $code=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($code); $res->getBody()->write(json_encode($arr)); return $res; }
  }

  $db = self::p();

  // Parámetros soportados
  $precorte_id = isset($args['id']) ? (int)$args['id'] : (int) qp($request, 'id', 0);
  $sesion_id   = isset($args['sesion_id']) ? (int)$args['sesion_id'] : (int) qp($request, 'sesion_id', 0);
  $terminal_id = (int) qp($request, 'terminal_id', 0);
  $user_id     = (int) qp($request, 'user_id', 0);

  try {
    // Resolver precorte si no lo mandan
    if (!$precorte_id) {
      if ($sesion_id > 0) {
        $s = $db->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
        $s->execute([':sid'=>$sesion_id]);
        $precorte_id = (int)($s->fetch()['id'] ?? 0);
      } elseif ($terminal_id>0 && $user_id>0) {
        $s = $db->prepare("SELECT id FROM selemti.sesion_cajon WHERE terminal_id=:t AND cajero_usuario_id=:u AND cierre_ts IS NULL ORDER BY apertura_ts DESC LIMIT 1");
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

    // Sesión + terminal + ventana del precorte
    $p = $db->prepare("SELECT p.sesion_id, s.terminal_id,
                              s.apertura_ts, COALESCE(s.cierre_ts, :hoyfin) AS cierre_ts
                       FROM selemti.precorte p
                       JOIN selemti.sesion_cajon s ON s.id=p.sesion_id
                       WHERE p.id=:id");
    $p->execute([':id'=>$precorte_id, ':hoyfin'=>date('Y-m-d 23:59:59')]);
    $rowP = $p->fetch();
    if(!$rowP) return J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);

    $sid = (int)$rowP['sesion_id'];
    $tid = (int)$rowP['terminal_id'];
    $a   = (string)$rowP['apertura_ts'];
    $b   = (string)$rowP['cierre_ts'];

    // Gate DPR
    if (!self::hasPOSCut($db, $tid, $a, $b)) {
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

    // declarado otros (si existe tabla)
    $decl_credito = 0.0; $decl_debito = 0.0; $decl_transfer = 0.0;
    $hasOtros = !empty($db->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch()['t']);
    if($hasOtros){
      $qot = $db->prepare("SELECT UPPER(tipo) AS tipo, COALESCE(SUM(monto),0)::numeric AS monto
                           FROM selemti.precorte_otros WHERE precorte_id=:id GROUP BY UPPER(tipo)");
      $qot->execute([':id'=>$precorte_id]);
      foreach($qot as $r){
        if ($r['tipo']==='CREDITO')  $decl_credito  = (float)$r['monto'];
        if ($r['tipo']==='DEBITO')   $decl_debito   = (float)$r['monto'];
        if ($r['tipo']==='TRANSFER') $decl_transfer = (float)$r['monto'];
      }
    }

    // sistema
    $vw = $db->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $vw->execute([':sid'=>$sid]);
    $sysE=0;$sysC=0;$sysD=0;$sysT=0;
    if($r = $vw->fetch()){
      $sysE = (float)($r['sistema_efectivo_esperado'] ?? 0);
      $sysC = (float)($r['sys_credito']  ?? $r['sys_credit'] ?? 0);
      $sysD = (float)($r['sys_debito']   ?? $r['sys_debit']  ?? 0);
      $sysT = (float)($r['sys_transfer'] ?? 0);
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

  /* ===== Legacy: update con denoms_json + declarado_* ===== */
 public static function updateLegacy(Request $request, Response $response, array $args = []){
    // helpers de tu proyecto (qp/J) asumidos existentes
    $db = self::p();

    // 1) Resolver ID
    $precorte_id = 0;
    if (!empty($args['id'])) $precorte_id = (int)$args['id'];
    if (!$precorte_id)      $precorte_id = (int) qp($request, 'id', 0);
    if ($precorte_id <= 0)  return J($response, ['ok'=>false,'error'=>'missing_precorte_id'], 400);

    // 2) Cargar body
    $p = $request->getParsedBody() ?? [];

    // denoms_json puede venir como string o array
    $denoms_json = $p['denoms_json'] ?? '[]';
    if (is_array($denoms_json)) $denoms_json = json_encode($denoms_json);
    $denoms = json_decode((string)$denoms_json, true);
    if (!is_array($denoms)) $denoms = [];

    // montos no-efectivo (acepta alias antiguos)
    $decl_credito  = (float) ($p['declarado_credito']  ?? $p['decl_credito']  ?? 0);
    $decl_debito   = (float) ($p['declarado_debito']   ?? $p['decl_debito']   ?? 0);
    $decl_transfer = (float) ($p['declarado_transfer'] ?? $p['decl_transfer'] ?? 0);
    $notas         = trim((string)($p['notas'] ?? ''));

    try{
      // 3) Validaciones rápidas
      $q = $db->prepare("SELECT 1 FROM selemti.precorte WHERE id=:id");
      $q->execute([':id'=>$precorte_id]);
      if (!$q->fetch()) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);

      $db->beginTransaction();

      // 4) Reemplazar efectivo
      $db->prepare("DELETE FROM selemti.precorte_efectivo WHERE precorte_id=:id")
         ->execute([':id'=>$precorte_id]);

      if (!empty($denoms)){
        $insEf = $db->prepare("
          INSERT INTO selemti.precorte_efectivo(precorte_id, denominacion, cantidad, subtotal)
          VALUES(:id, :den, :qty, :sub)
        ");
        foreach ($denoms as $r){
          $den = (float)($r['den'] ?? $r['denominacion'] ?? 0);
          $qty = (int)  ($r['qty'] ?? $r['cantidad'] ?? 0);
          if ($den <= 0 || $qty <= 0) continue;
          $insEf->execute([':id'=>$precorte_id, ':den'=>$den, ':qty'=>$qty, ':sub'=>$den*$qty]);
        }
      }

      // 5) Reemplazar no-efectivo
      //    (si no existe la tabla por deploys antiguos, omite silenciosamente)
      $hasOtros = !empty($db->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch()['t']);
      if ($hasOtros){
        $db->prepare("DELETE FROM selemti.precorte_otros WHERE precorte_id=:id")
           ->execute([':id'=>$precorte_id]);
        $insOt = $db->prepare("
          INSERT INTO selemti.precorte_otros(precorte_id, tipo, monto)
          VALUES(:id, :tipo, :monto)
        ");
        $insOt->execute([':id'=>$precorte_id, ':tipo'=>'CREDITO',  ':monto'=>$decl_credito]);
        $insOt->execute([':id'=>$precorte_id, ':tipo'=>'DEBITO',   ':monto'=>$decl_debito]);
        $insOt->execute([':id'=>$precorte_id, ':tipo'=>'TRANSFER', ':monto'=>$decl_transfer]);
      }

      // 6) Notas (opcional)
      if ($notas !== ''){
        $db->prepare("UPDATE selemti.precorte SET notas=:n WHERE id=:id")
           ->execute([':n'=>$notas, ':id'=>$precorte_id]);
      }

      $db->commit();
      return J($response, ['ok'=>true, 'precorte_id'=>$precorte_id]);
    } catch (\Throwable $e){
      if ($db->inTransaction()) $db->rollBack();
      // devolver detalle para que lo veas en consola (POST_FORM ya lo imprime)
      return J($response, ['ok'=>false,'error'=>'update_failed','detail'=>$e->getMessage()], 500);
    }
  }
  /* ===== Estatus (GET/SET) ===== */
  public static function statusGet(Request $request, Response $response, array $args) {
    $db = self::p();
    $id = isset($args['id']) ? (int)$args['id'] : (int) qp($request, 'id', 0);
    if (!$id) return J($response, ['ok'=>false,'error'=>'missing_id'], 400);

    $st = $db->prepare("SELECT id, estatus, creado_en FROM selemti.precorte WHERE id=:id");
    $st->execute([':id'=>$id]);
    $row = $st->fetch();

    return $row
      ? J($response, ['ok'=>true,'precorte_id'=>(int)$row['id'],'estatus'=>$row['estatus'],'creado_en'=>$row['creado_en']])
      : J($response, ['ok'=>false,'error'=>'precorte_not_found'],404);
  }
/* ===== Actualiza estatus de sesión y precorte (legacy) =====
 * POST/PATCH /api/caja/precorte_status.php/{id?}
 * body: sesion_estatus, precorte_estatus, (opcional) nota
 */
	public static function statusLegacy(Request $request, Response $response, array $args = [])
	{
		$db = self::p();

		// ID: args -> query -> path (/caja/precorte_status.php/6)
		$precorte_id = (int)($args['id'] ?? 0);
		if (!$precorte_id) $precorte_id = (int) qp($request, 'id', 0);
		if (!$precorte_id) {
			$path = $request->getUri()->getPath(); // ej: /terrena/Terrena/api/caja/precorte_status.php/6
			if (preg_match('~(?:^|/)caja/precorte_status\.php/(\d+)(?:$|[/?])~', $path, $m)) {
				$precorte_id = (int)$m[1];
			}
		}
		if ($precorte_id <= 0) return J($response, ['ok'=>false,'error'=>'missing_precorte_id'], 400);

		// body
		$p = $request->getParsedBody() ?? [];
		$sesion_estatus   = trim((string)($p['sesion_estatus']   ?? ''));
		$precorte_estatus = trim((string)($p['precorte_estatus'] ?? ''));
		$nota             = trim((string)($p['nota'] ?? $p['notas'] ?? ''));

		try{
			// sesion de ese precorte
			$q = $db->prepare("SELECT sesion_id FROM selemti.precorte WHERE id=:id");
			$q->execute([':id'=>$precorte_id]);
			$row = $q->fetch();
			if (!$row) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);
			$sesion_id = (int)$row['sesion_id'];

			$db->beginTransaction();

			// precorte.estatus (+ nota)
			if ($precorte_estatus !== '' || $nota !== '') {
				$sql = "UPDATE selemti.precorte
						   SET estatus = COALESCE(NULLIF(:pe,''), estatus)"
					 . ($nota !== '' ? ", notas = :nota" : "")
					 . " WHERE id = :id";
				$st = $db->prepare($sql);
				$params = [':pe'=>$precorte_estatus, ':id'=>$precorte_id];
				if ($nota !== '') $params[':nota'] = $nota;
				$st->execute($params);
			}

			// sesion_cajon.estatus
			if ($sesion_estatus !== '') {
				$db->prepare("UPDATE selemti.sesion_cajon SET estatus = :se WHERE id = :sid")
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
		} catch (\Throwable $e){
			if ($db->inTransaction()) $db->rollBack();
			return J($response, ['ok'=>false,'error'=>'status_update_failed','detail'=>$e->getMessage()], 500);
		}
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

  /* ===== (opcional) Enviar ===== */
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

/* Verifica si hay Drawer Pull Report en la ventana de la sesión */

private static function hasDPR(PDO $db, int $terminal_id, string $a, string $b): bool {
  if (!$terminal_id || !$a) return false;
  $q = $db->prepare("SELECT 1
                     FROM public.drawer_pull_report
                     WHERE terminal_id=:t AND report_time BETWEEN :a AND :b
                     LIMIT 1");
  $q->execute([':t'=>$terminal_id, ':a'=>$a, ':b'=>$b]);
  return (bool)$q->fetchColumn();
}

/******** DPR gate ********/
private static function hasPOSCut(PDO $db, int $terminal_id, string $a, string $b): bool {
  $q = $db->prepare("SELECT 1 FROM public.drawer_pull_report
                     WHERE terminal_id=:t AND report_time BETWEEN :a AND :b
                     LIMIT 1");
  $q->execute([':t'=>$terminal_id, ':a'=>$a, ':b'=>$b]);
  return (bool)$q->fetchColumn();
}



}


