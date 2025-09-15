<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

if (!defined('DIFF_THRESHOLD')) define('DIFF_THRESHOLD', 10);

/** PDO unificado (toma de config.php o de Terrena\Core\Database) */
function pdo(): PDO {
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
  if (class_exists(\Terrena\Core\Database::class)) return \Terrena\Core\Database::pdo();
  throw new RuntimeException('PDO no disponible: incluye config.php antes.');
}

/** JSON out (Slim 4) */
function J(Response $r, array $data, int $code=200): Response {
  $r->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  return $r->withHeader('Content-Type','application/json')->withStatus($code);
}

/** Param (query/body); acepta GET y POST para pruebas */
function qp(Request $q, string $k, $def=null) {
  $b = $q->getParsedBody() ?: [];
  $g = $q->getQueryParams() ?: [];
  return $b[$k] ?? $g[$k] ?? $def;
}
