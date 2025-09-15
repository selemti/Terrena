<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConciliacionController {
    public static function getBySesion(Request $request, Response $response, array $args) {
        $sid = (int)$args['sesion_id'];
        
        try {
            $st = pdo()->prepare("SELECT * FROM selemti.vw_conciliacion_sesion WHERE sesion_id = :sid");
            $st->execute([':sid' => $sid]);
            $row = $st->fetch();
            
            return $row ? J($response, ['ok' => true, 'data' => $row]) : 
                         J($response, ['ok' => false, 'error' => 'not_found'], 404);
        } catch(Throwable $e) {
            return J($response, ['ok' => false, 'error' => 'view_missing', 'message' => $e->getMessage()], 500);
        }
    }
}