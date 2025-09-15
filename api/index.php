<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Carga config.php (acepta /api/config.php o ../config.php)
foreach ([__DIR__ . '/config.php', __DIR__ . '/../config.php'] as $c) {
  if (is_file($c)) { require_once $c; break; }
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/middleware/AuthMiddleware.php';

// Controllers
require __DIR__ . '/controllers/AuthController.php';
require __DIR__ . '/controllers/HealthController.php';
require __DIR__ . '/controllers/SesionesController.php';
require __DIR__ . '/controllers/PreCorteController.php';
require __DIR__ . '/controllers/PostCorteController.php';
require __DIR__ . '/controllers/ConciliacionController.php';
require __DIR__ . '/controllers/FormasPagoController.php';
require __DIR__ . '/controllers/CajasController.php';

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

// Auth
$app->get('/auth/login',  [AuthController::class, 'loginHelp']);
$app->post('/auth/login', [AuthController::class, 'login']);

// Sesiones
$app->get('/sesiones/activa', [SesionesController::class, 'getActiva']);

/* ===== Precorte (nuevo) ===== */
$app->get ('/precorte/preflight',                       [PreCorteController::class, 'preflight']);
$app->map(['GET','POST'], '/precortes',                 [PreCorteController::class, 'createOrUpdate']);
$app->post('/precortes/{id:\d+}/enviar',                [PreCorteController::class, 'enviar']);
$app->get ('/precortes/{id:\d+}/resumen',               [PreCorteController::class, 'resumen']);
$app->get ('/precortes/{id:\d+}/status',                [PreCorteController::class, 'statusGet']);
$app->map(['POST','PATCH'], '/precortes/{id:\d+}/status',[PreCorteController::class, 'statusSet']);

/* ===== LEGACY sprecorte/* (compatibilidad) ===== */
$app->map(['GET','POST'], '/sprecorte/preflight[/{sesion_id:\d+}]',        [PreCorteController::class, 'preflight']);
$app->map(['GET','POST'], '/sprecorte/totales[/{id:\d+}]',                 [PreCorteController::class, 'resumen']);
$app->map(['GET','POST'], '/sprecorte/totales/sesion[/{sesion_id:\d+}]',   [PreCorteController::class, 'resumen']);
$app->map(['GET','POST'], '/sprecorte/create[/{id:\d+}]',                  [PreCorteController::class, 'createOrUpdate']);
$app->map(['GET','POST'], '/sprecorte/update[/{id:\d+}]',                  [PreCorteController::class, 'updateLegacy']);

/* ===== LEGACY caja/* (wizard anterior) ===== */
$app->map(['GET','POST'], '/caja/cajas.php',            [CajasController::class, 'cajas']);
$app->map(['GET','POST'], '/caja/cajas_debug.php',      [CajasController::class, 'cajasDebug']);

$app->map(['GET','POST'], '/caja/precorte_totales.php', [PreCorteController::class, 'resumenLegacy']);
$app->map(['GET','POST'], '/caja/precorte_preflight.php',[PreCorteController::class, 'preflight']);

/* ÚNICA definición (idempotente) para crear/recuperar precorte */
$app->map(['POST'], '/caja/precorte_create.php',        [PreCorteController::class, 'createLegacy']);

/* Actualizar captura (POST solamente) */
$app->map(['POST'], '/caja/precorte_update.php',        [PreCorteController::class, 'updateLegacy']);

/* Estatus: GET consulta / POST|PATCH actualiza — acepta /.../id o ?id= */
$app->get('/caja/precorte_status.php[/{id:\d+}]',                   [PreCorteController::class, 'statusGet']);
$app->map(['POST','PATCH'], '/caja/precorte_status.php[/{id:\d+}]', [PreCorteController::class, 'statusLegacy']);

// Conciliación
$app->get('/conciliacion/{sesion_id:\d+}', [ConciliacionController::class, 'getBySesion']);

// Formas de pago
$app->get('/formas-pago', [FormasPagoController::class, 'getAll']);

$app->run();
