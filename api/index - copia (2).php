<?php
declare(strict_types=1);

require __DIR__ . '/../config.php'; // Asegura que se cargue primero

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Terrena\Core\Auth;
use Terrena\Core\Database;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
/* ⇩⇩ AJUSTA si tu carpeta cambia ⇩⇩ */
$app->setBasePath('/terrena/Terrena/api');

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);


// Ruta de prueba (GET)
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Hello World from Terrena POS Admin API v1');
    return $response;
});

// Ruta GET para /auth/login (mensaje de ayuda)
$app->get('/auth/login', function (Request $request, Response $response) {
    $response->getBody()->write('Método no permitido. Use POST con { "username": "string", "password": "string" } para autenticarse.');
    return $response->withStatus(405)->withHeader('Allow', 'POST');
});

// Ruta POST para /auth/login
$app->post('/auth/login', function (Request $request, Response $response) {
    try {
        $data = json_decode($request->getBody()->getContents(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $response->withStatus(400)->withJson(['error' => 'Invalid JSON']);
        }
        if ($data['username'] === 'selemti' && $data['password'] === '1234') {
            $payload = ['sub' => 1, 'name' => 'Selem Tecnologías de Información', 'role' => 'TI'];
            $jwt = Auth::generateJWT($payload);
            $response->getBody()->write(json_encode(['token' => $jwt, 'user' => $payload]));
        } else {
            return $response->withStatus(401)->withJson(['error' => 'Unauthorized']);
        }
    } catch (Exception $e) {
        error_log("Error en auth/login: " . $e->getMessage());
        return $response->withStatus(500)->withJson(['error' => 'Error interno']);
    }
    return $response->withHeader('Content-Type', 'application/json');
});

// Ejemplo de API protegida con JWT
$app->get('/caja/terminals', function (Request $request, Response $response) {
    try {
        $db = Database::pdo();
        $stmt = $db->prepare("SELECT id, terminal_id FROM selemti.sesion_cajon WHERE estatus = 'ACTIVA'");
        $stmt->execute();
        $terminals = $stmt->fetchAll();
        return $response->withJson($terminals);
    } catch (Exception $e) {
        error_log("Error en caja/terminals: " . $e->getMessage());
        return $response->withStatus(500)->withJson(['error' => 'Error interno']);
    }
})->add(function ($request, $handler) {
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        return (new Response())->withStatus(401)->withJson(['error' => 'No token provided']);
    }
    $token = str_replace('Bearer ', '', $authHeader);
    try {
        Auth::validateJWT($token);
    } catch (\Exception $e) {
        return (new Response())->withStatus(401)->withJson(['error' => 'Invalid token']);
    }
    return $handler->handle($request);
});



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
const DIFF_THRESHOLD = 10; // MXN fijo (luego a BD)

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
/* GET /precorte/preflight?sesion_id= */
$app->get('/precorte/preflight', function(Request $q, Response $r) {
  $sid = qp($q,'sesion_id');
  if(!$sid) return J($r, ['ok'=>false,'error'=>'missing_params (sesion_id)'],400);

  // Detecta terminal de la sesión
  $s = pdo()->prepare("SELECT terminal_id FROM selemti.sesion_cajon WHERE id=:id");
  $s->execute([':id'=>$sid]);
  $ses = $s->fetch();
  if(!$ses) return J($r,['ok'=>false,'error'=>'sesion_not_found'],404);

  // ⚠️ Criterio de “abierto” varía por instalación de Floreant.
  // Usa el que tengas; aquí un intento común: closing_date IS NULL y voided=false.
  $abiertos = 0;
  try {
    $tq = pdo()->prepare("
      SELECT count(*)::int AS n
      FROM public.ticket t
      WHERE t.terminal_id=:tid
        AND COALESCE(t.voided,false)=false
        AND t.closing_date IS NULL
    ");
    $tq->execute([':tid'=>$ses['terminal_id']]);
    $abiertos = (int)$tq->fetch()['n'];
  } catch(Throwable $e) {
    // Si tu esquema usa otro campo (paid/status), ajusta el WHERE de arriba.
    $abiertos = 0;
  }
  $bloqueo = $abiertos > 0;
  return $bloqueo
    ? J($r, ['ok'=>false,'error'=>'tickets_open','tickets'=>$abiertos], 409)
    : J($r, ['ok'=>true,'tickets_abiertos'=>$abiertos,'bloqueo'=>false]);
});

/* ========= PRECORTES ========= */
/* POST /precortes  (crear/actualizar) */
$app->post('/precortes', function(Request $q, Response $r) {
  $sid = qp($q,'sesion_id');
  $ef  = qp($q,'efectivo_detalle', []); // [{denominacion,cantidad}]
  $ot  = qp($q,'otros', []);            // [{tipo,monto,referencia?,evidencia_url?,notas?}]
  if(!$sid) return J($r, ['ok'=>false,'error'=>'missing_params (sesion_id)'],400);

  // Reutiliza o crea cabecera
  pdo()->beginTransaction();
  try {
    $st = pdo()->prepare("SELECT id FROM selemti.precorte WHERE sesion_id=:sid ORDER BY id DESC LIMIT 1");
    $st->execute([':sid'=>$sid]);
    $precorte_id = $st->fetch()['id'] ?? null;
    if(!$precorte_id){
      $ins = pdo()->prepare("INSERT INTO selemti.precorte (sesion_id, estatus) VALUES (:sid,'PENDIENTE') RETURNING id");
      $ins->execute([':sid'=>$sid]);
      $precorte_id = (int)$ins->fetch()['id'];
    }

    /* efectivo_detalle → borra e inserta */
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

    /* otros (si existe la tabla) */
    $hasOtros = false;
    try {
      $x = pdo()->query("SELECT to_regclass('selemti.precorte_otros') AS t")->fetch();
      $hasOtros = !empty($x['t']);
    } catch(Throwable $e){}
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

  // Sistema (vista de conciliación)
  $sys = [
    'cash'=>0,'credit'=>0,'debit'=>0,'transfer'=>0,'custom'=>0,'retiros'=>0,'refunds_cash'=>0,
    'sistema_efectivo_esperado'=>0,'sistema_tarjetas'=>0
  ];
  try {
    $v = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $v->execute([':sid'=>$sid]);
    if ($row = $v->fetch()) {
      // Ajusta a tus nombres reales de columnas en la vista:
      $sys['cash']     = (float)($row['sys_cash'] ?? $row['cash'] ?? 0);
      $sys['credit']   = (float)($row['sys_credit'] ?? 0);
      $sys['debit']    = (float)($row['sys_debit'] ?? 0);
      $sys['transfer'] = (float)($row['sys_transfer'] ?? 0);
      $sys['custom']   = (float)($row['sys_custom'] ?? 0);
      $sys['retiros']  = (float)($row['retiros'] ?? 0);
      $sys['refunds_cash'] = (float)($row['refunds_cash'] ?? 0);
      $sys['sistema_efectivo_esperado'] = (float)($row['sistema_efectivo_esperado'] ?? 0);
      $sys['sistema_tarjetas'] = (float)(
        $row['sys_credit'] + $row['sys_debit'] + ($row['sys_transfer'] ?? 0) + ($row['sys_custom'] ?? 0)
      );
    }
  } catch(Throwable $e){ /* si no existe la vista, devolvemos ceros */ }

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
  if(!$sid) return J($r, ['ok'=>false,'error'=>'missing_params (sesion_id)'],400);

  // Sistema (desde vista)
  $sysE = 0.0; $sysT = 0.0;
  try {
    $v = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $v->execute([':sid'=>$sid]);
    if ($row = $v->fetch()) {
      $sysE = (float)($row['sistema_efectivo_esperado'] ?? 0);
      $sysT = (float)(($row['sys_credit'] ?? 0)+($row['sys_debit'] ?? 0)+($row['sys_transfer'] ?? 0)+($row['sys_custom'] ?? 0));
    }
  } catch(Throwable $e){}

  $difE = $deE - $sysE;
  $difT = $deT - $sysT;

  $ver = function(float $dif): string {
    if (abs($dif) <= DIFF_THRESHOLD) return 'CUADRA';
    return $dif > 0 ? 'A_FAVOR' : 'EN_CONTRA';
  };
  $verE = $ver($difE);
  $verT = $ver($difT);

  // Inserta postcorte
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
/* GET /conciliacion/{sesion_id} */
$app->get('/conciliacion/{sesion_id}', function(Request $q, Response $r, array $a) {
  $sid = (int)$a['sesion_id'];
  try{
    $st = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id=:sid");
    $st->execute([':sid'=>$sid]);
    $row = $st->fetch();
    return $row ? J($r, ['ok'=>true,'data'=>$row]) : J($r,['ok'=>false,'error'=>'not_found'],404);
  } catch(Throwable $e){
    return J($r, ['ok'=>false,'error'=>'view_missing','message'=>$e->getMessage()],500);
  }
});

/* ========= FORMAS DE PAGO ========= */
/* GET /formas-pago */
$app->get('/formas-pago', function(Request $q, Response $r) {
  $st = pdo()->query("
    SELECT id,codigo,payment_type,transaction_type,payment_sub_type,custom_name,custom_ref,activo,prioridad,creado_en
    FROM selemti.formas_pago
    WHERE activo = true
    ORDER BY prioridad, id
  ");
  return J($r, ['ok'=>true,'items'=>$st->fetchAll()]);
});


$app->run();