<?php
namespace Terrena\Modules\Reportes;

class ReportesController {
  public function view() {
    $title = 'Reportes';
    ob_start();
    require __DIR__ . '/../../views/reportes/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
