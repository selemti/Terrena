<?php
namespace Terrena\Modules\Recetas;

class RecetasController {
  public function view() {
    $title = 'Recetas & Costos';
    ob_start();
    require __DIR__ . '/../../views/recetas/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
