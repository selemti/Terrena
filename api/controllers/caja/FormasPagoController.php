<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class FormasPagoController {
    public static function getAll(Request $request, Response $response) {
        $st = pdo()->query("
            SELECT id, codigo, payment_type, transaction_type, payment_sub_type, custom_name, custom_ref, activo, prioridad, creado_en
            FROM selemti.formas_pago
            WHERE activo = true
            ORDER BY prioridad, id
        ");
        
        return J($response, ['ok' => true, 'items' => $st->fetchAll()]);
    }
}