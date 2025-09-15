<?php
namespace Terrena\Modules\Produccion;

class ProduccionController {
  public function view() {
    $title = 'Producción';
    ob_start();
    require __DIR__ . '/../../views/produccion/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
