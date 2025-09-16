<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Carga config.php (acepta /api/config.php o ../config.php)
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../config.php'] as $c) {
  if (is_file($c)) { require_once $c; break; }
}

require __DIR__ . '/../helpers.php';
require __DIR__ . '/../middleware/AuthMiddleware.php';

// Controllers
require __DIR__ . '/controllers/Inventario/AuthController.php';
require __DIR__ . '/controllers/Inventario/HealthController.php';
/*require __DIR__ . '/controllers/Inventario/SesionesController.php';
require __DIR__ . '/controllers/Inventario/PreCorteController.php';
require __DIR__ . '/controllers/Inventario/PostCorteController.php';
require __DIR__ . '/controllers/Inventario/ConciliacionController.php';
require __DIR__ . '/controllers/Inventario/FormasPagoController.php';
require __DIR__ . '/controllers/Inventario/InventariosController.php';*/

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();
/* ⇩⇩ AJUSTA si tu carpeta cambia ⇩⇩ */
$app->setBasePath('/terrena/Terrena/api');

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

/* ========== RUTAS ========== */

// Root
$app->get('/', function (Request $req, Response $res) {
  return J($res, ['ok' => true, 'app' => 'Terrena POS Admin API']);
});

// Health
$app->get('/health', [HealthController::class, 'check']);



$app->run();
