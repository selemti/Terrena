<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SesionesController {
    public static function getActiva(Request $request, Response $response) {
        $tid = qp($request, 'terminal_id');
        $uid = qp($request, 'usuario_id');
        
        if(!$tid || !$uid) {
            return J($response, ['ok' => false, 'error' => 'missing_params (terminal_id, usuario_id)'], 400);
        }
        
        $st = pdo()->prepare("
            SELECT id, terminal_id, cajero_usuario_id, apertura_ts, cierre_ts, estatus, opening_float, closing_float
            FROM selemti.sesion_cajon
            WHERE terminal_id = :t AND cajero_usuario_id = :u
                AND estatus IN ('ACTIVA','LISTO_PARA_CORTE')
            ORDER BY apertura_ts DESC LIMIT 1
        ");
        
        $st->execute([':t' => $tid, ':u' => $uid]);
        $row = $st->fetch();
        
        return $row ? J($response, ['ok' => true, 'sesion' => $row]) : 
                     J($response, ['ok' => false, 'error' => 'sesion_not_found'], 404);
    }
}