<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    public static function loginHelp(Request $request, Response $response) {
        $response->getBody()->write('Método no permitido. Use POST con { "username": "string", "password": "string" } para autenticarse.');
        return $response->withStatus(405)->withHeader('Allow', 'POST');
    }

    public static function login(Request $request, Response $response) {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return J($response, ['error' => 'Invalid JSON'], 400);
            }
            
            if ($data['username'] === 'selemti' && $data['password'] === '1234') {
                $payload = ['sub' => 1, 'name' => 'Selem Tecnologías de Información', 'role' => 'TI'];
                $jwt = Auth::generateJWT($payload);
                
                return J($response, ['token' => $jwt, 'user' => $payload]);
            } else {
                return J($response, ['error' => 'Unauthorized'], 401);
            }
        } catch (Exception $e) {
            error_log("Error en auth/login: " . $e->getMessage());
            return J($response, ['error' => 'Error interno'], 500);
        }
    }
}