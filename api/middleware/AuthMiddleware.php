<?php
declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Terrena\Core\Auth; // <-- IMPORTANTE

class AuthMiddleware {
    public function __invoke(Request $request, RequestHandler $handler): Response {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $res = new \Slim\Psr7\Response();
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'no_token']));
            return $res->withStatus(401)->withHeader('Content-Type','application/json');
        }

        $token = substr($authHeader, 7);

        try {
            Auth::validateJWT($token);
        } catch (\Throwable $e) {
            $res = new \Slim\Psr7\Response();
            $res->getBody()->write(json_encode(['ok'=>false,'error'=>'invalid_token']));
            return $res->withStatus(401)->withHeader('Content-Type','application/json');
        }

        return $handler->handle($request);
    }
}
