<?php
namespace Terrena\Modules\Admin;

class ItemsController {
  public function view() {
    $title = 'Items & KDS';
    ob_start();
    require __DIR__ . '/../../views/admin/items.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
