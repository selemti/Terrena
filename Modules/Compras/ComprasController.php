<?php
namespace Terrena\Modules\Compras;

class ComprasController {
  public function view() {
    $title = 'Compras';
    ob_start();
    require __DIR__ . '/../../views/compras/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
