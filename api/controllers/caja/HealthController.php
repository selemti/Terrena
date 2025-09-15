<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController {
    public static function check(Request $request, Response $response) {
        try {
            pdo()->query('SELECT 1');
            return J($response, ['ok' => true, 'db' => 'ok', 'port' => DB_PORT]);
        } catch(Throwable $e) {
            return J($response, ['ok' => false, 'error' => 'db', 'message' => $e->getMessage()], 500);
        }
    }
}