<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PostCorteController {

  // Reutiliza la misma conexión que PreCorteController
  protected static function p(): \PDO {
    return PreCorteController::p();
  }

  // POST /postcortes   (body: precorte_id)   |   POST /postcortes?id=##
  public static function create(Request $request, Response $response, array $args = []) : Response {
    $db = self::p();

    // helpers locales
    if (!function_exists('qp')) { function qp($r,$k,$def=null){ $q=$r->getQueryParams(); $p=(array)$r->getParsedBody(); return $p[$k]??$q[$k]??$def; } }
    if (!function_exists('J'))  { function J($res,$a,$c=200){ $res=$res->withHeader('Content-Type','application/json')->withStatus($c); $res->getBody()->write(json_encode($a)); return $res; } }

    // acepta body.precorte_id o query.id
    $precorte_id = (int) qp($request, 'precorte_id', (int)qp($request,'id',0));
    if ($precorte_id <= 0) return J($response, ['ok'=>false,'error'=>'missing_precorte_id'], 400);

    try {
      // Sesión del precorte (FK)
      $sid = (int)$db->query("SELECT sesion_id FROM selemti.precorte WHERE id={$precorte_id}")->fetchColumn();
      if (!$sid) return J($response, ['ok'=>false,'error'=>'precorte_not_found'], 404);

      // Declarado efectivo
      try {
        $ef = (float)$db->query("SELECT COALESCE(SUM(subtotal),0) FROM selemti.precorte_efectivo WHERE precorte_id={$precorte_id}")->fetchColumn();
      } catch (\Throwable $e) {
        $ef = (float)$db->query("SELECT COALESCE(SUM(denominacion*cantidad),0) FROM selemti.precorte_efectivo WHERE precorte_id={$precorte_id}")->fetchColumn();
      }

      // Declarado tarjetas (CREDITO+DEBITO)
      $tar = (float)$db->query("
        SELECT COALESCE(SUM(monto),0)
        FROM selemti.precorte_otros
        WHERE precorte_id={$precorte_id} AND UPPER(tipo) IN ('CREDITO','DEBITO','DÉBITO')
      ")->fetchColumn();

      // NOT NULL → sistema en 0 (ajustaremos luego si ya tienes fuente)
      $sefe = 0.00; // sistema_efectivo_esperado
      $star = 0.00; // sistema_tarjetas

      $difE = $ef  - $sefe;
      $difT = $tar - $star;
      $ver  = function($d){ return $d==0.0 ? 'CUADRA' : ($d>0 ? 'A_FAVOR' : 'EN_CONTRA'); };

      $st = $db->prepare("
        INSERT INTO selemti.postcorte
        (sesion_id, sistema_efectivo_esperado, declarado_efectivo, diferencia_efectivo, veredicto_efectivo,
         sistema_tarjetas, declarado_tarjetas, diferencia_tarjetas, veredicto_tarjetas,
         creado_en, creado_por, notas)
        VALUES
        (:sid, :sefe, :defe, :dife, :vefe,
               :star, :dtar, :ditar, :vtar,
               now(), :usr, :notas)
        RETURNING id
      ");
      $st->execute([
        ':sid'  => $sid,
        ':sefe' => $sefe, ':defe' => $ef,  ':dife' => $difE, ':vefe' => $ver($difE),
        ':star' => $star, ':dtar' => $tar, ':ditar' => $difT, ':vtar' => $ver($difT),
        ':usr'  => 1, ':notas' => ''
      ]);
      $id = (int)$st->fetchColumn();

      return J($response, ['ok'=>true,'postcorte_id'=>$id,'sesion_id'=>$sid], 200);

    } catch (\Throwable $e) {
      // opcional: auditar error
      try {
        $a = $db->prepare("INSERT INTO selemti.auditoria(quien,que,payload) VALUES(1,'postcorte.create_error',:p)");
        $a->execute([':p'=>json_encode(['precorte_id'=>$precorte_id,'msg'=>$e->getMessage()])]);
      } catch (\Throwable $e2) {}
      return J($response, ['ok'=>false,'error'=>'postcorte_insert_failed','detail'=>$e->getMessage()], 500);
    }
  }
}
