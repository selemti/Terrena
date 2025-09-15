<?php
namespace Terrena\Modules\Inventario;

class InventarioController {
  public function view() {
    $title = 'Inventario';
    ob_start();
    require __DIR__ . '/../../views/inventario/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
