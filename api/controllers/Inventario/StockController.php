<?php
declare(strict_types=1);

namespace Terrena\Selemti\Api\Controllers\Inventario;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class StockController
{
    public function ping(Request $req, Response $res): Response {
        $res->getBody()->write(json_encode(['ok' => true, 'service' => 'StockController', 'ts' => date('c')]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
