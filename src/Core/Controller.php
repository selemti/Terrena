<?php
declare(strict_types=1);

namespace Terrena\Core;

abstract class Controller
{
    /**
     * Renderiza una vista capturando su salida en $content y aplica el layout.
     * $viewPath: ruta absoluta del archivo de vista (sin layout).
     * $vars: array asociativo para variables de la vista.
     * $isApi: si true, devuelve JSON en lugar de renderizar layout.
     */
    protected static function render(string $viewPath, array $vars = [], bool $isApi = false): void
    {
        if (!is_file($viewPath)) {
            http_response_code(500);
            echo "Vista no encontrada: {$viewPath}";
            return;
        }

        $title = $vars['title'] ?? '';
        $content = '';

        // Exponer variables a la vista
        (function () use ($viewPath, &$title, &$content, $vars) {
            extract($vars, EXTR_SKIP);
            ob_start();
            require $viewPath;
            $content = ob_get_clean() ?: '';
            if (!isset($title) || $title === '') $title = 'Terrena POS Admin';
        })();

        if ($isApi) {
            header('Content-Type: application/json');
            echo json_encode(['title' => $title, 'content' => $content]);
            return;
        }

        $layoutFile = dirname($viewPath) . '/layout.php';
        if (!is_file($layoutFile)) {
            http_response_code(500);
            echo "Layout no encontrado: {$layoutFile}";
            return;
        }

        require $layoutFile;
    }
}