<?php
declare(strict_types=1);

namespace Terrena\Selemti\Api\Controllers\Inventario;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ProduccionController
{
    public function ping(Request $req, Response $res): Response {
        $res->getBody()->write(json_encode(['ok' => true, 'service' => 'ProduccionController', 'ts' => date('c')]));
        return $res->withHeader('Content-Type', 'application/json');
    }
}
