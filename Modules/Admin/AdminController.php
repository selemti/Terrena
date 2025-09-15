<?php
namespace Terrena\Modules\Admin;

class AdminController {
  public function view() {
    $title = 'Admin';
    ob_start();
    require __DIR__ . '/../../views/admin/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
