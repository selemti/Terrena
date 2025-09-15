<?php
declare(strict_types=1);

namespace Terrena\Modules\Dashboard;

class DashboardController
{
    /**
     * Renderiza el dashboard SIN duplicar layout.
     * - La vista `Views/dashboard.php` SOLO define $title y $content (no incluye layout).
     * - Aquí incluimos el layout UNA sola vez y le pasamos esas variables.
     */
    public static function view(): void
    {
        // 1) Asegura que las variables existen
        $title = 'Dashboard';
        $content = '';

        // 2) Renderiza la vista y captura en $content
        //    Importante: la vista NO debe incluir layout.php adentro.
        //    Debe terminar con: $content = ob_get_clean();
        $viewFile = __DIR__ . '/../../Views/dashboard.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo "No se encontró la vista de Dashboard en: {$viewFile}";
            return;
        }

        // Aislar ámbito de variables de la vista
        // La vista usará $title (puede sobreescribirlo) y definirá $content
        (function () use ($viewFile, &$title, &$content) {
            // Puedes pasar más datos a la vista con variables locales aquí
            // $someData = ...;

            require $viewFile;

            // Si la vista no setea $content por alguna razón, evitar notices
            if (!isset($content)) {
                $content = '';
            }
            // Si la vista no setea $title, usar el que pusimos arriba
            if (!isset($title) || $title === '') {
                $title = 'Dashboard';
            }
        })();

        // 3) Incluir el layout UNA sola vez y volcar $content dentro
        $layoutFile = __DIR__ . '/../../Views/layout.php';
        if (!is_file($layoutFile)) {
            http_response_code(500);
            echo "No se encontró el layout en: {$layoutFile}";
            return;
        }

        // Variables útiles para el layout (si las usas)
        $GLOBALS['__PAGE__'] = 'dashboard';

        require $layoutFile;
    }
}
