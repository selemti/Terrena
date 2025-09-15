<?php
namespace Terrena\Modules\Personal;

class PersonalController {
  public function view() {
    $title = 'Personal';
    ob_start();
    require __DIR__ . '/../../views/personal/index.php';
    $content = ob_get_clean();
    require __DIR__ . '/../../views/layout.php';
  }
}
