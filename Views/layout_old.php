<?php
// layout.php
// Este layout envuelve todas las vistas. Requiere Bootstrap 5, FontAwesome y Chart.js en <head>.
declare(strict_types=1);
use function Terrena\Core\asset;
use function Terrena\Core\url;

$base = $GLOBALS['__BASE__'] ?? '/';

// Views/layout.php
// (fragmento inicial recomendado)
if (!function_exists('render_layout')) {
  function render_layout(string $title, string $content): void {
    $base = $GLOBALS['__BASE__'] ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SelemTI POS | Terrena</title>
  <script>
    window.__BASE__ = '<?= htmlspecialchars($GLOBALS['__BASE__'] ?? '') ?>';
  </script>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

  <!-- Terrena CSS -->
  <link href="/terrena/Terrena/assets/css/terrena.css" rel="stylesheet">
</head>
<body>
  <div class="container-fluid p-0 d-flex" style="min-height:100vh">
    <!-- Sidebar -->
    <aside class="sidebar flex-column" id="sidebar">
<div class="logo-brand mb-3 d-flex align-items-center justify-content-center">
  
<a href="/terrena/Terrena/dashboard" class="text-decoration-none">
    <img src="/terrena/Terrena/assets/img/logo.svg" id="logoImg" alt="Terrena" style="height:44px">
  </a>
  
</div>
<hr style="margin:0">
      <nav class="nav flex-column gap-1">
        <a class="nav-link <?php echo ($active ?? '')==='dashboard'?'active':'' ?>" href="/terrena/Terrena/dashboard">
          <i class="fa-solid fa-gauge"></i> <span class="label">Dashboard</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='cortes'?'active':'' ?>" href="/terrena/Terrena/caja/cortes">
          <i class="fa-solid fa-cash-register"></i> <span class="label">Cortes de Caja</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='inventario'?'active':'' ?>" href="/terrena/Terrena/inventario">
          <i class="fa-solid fa-boxes-stacked"></i> <span class="label">Inventario</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='compras'?'active':'' ?>" href="/terrena/Terrena/compras">
          <i class="fa-solid fa-truck"></i> <span class="label">Compras</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='recetas'?'active':'' ?>" href="/terrena/Terrena/recetas">
          <i class="fa-solid fa-bowl-food"></i> <span class="label">Recetas</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='produccion'?'active':'' ?>" href="/terrena/Terrena/produccion">
          <i class="fa-solid fa-industry"></i> <span class="label">Producción</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='reportes'?'active':'' ?>" href="/terrena/Terrena/reportes">
          <i class="fa-solid fa-chart-column"></i> <span class="label">Reportes</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='config'?'active':'' ?>" href="/terrena/Terrena/admin">
          <i class="fa-solid fa-gear"></i> <span class="label">Configuración</span>
        </a>
        <a class="nav-link <?php echo ($active ?? '')==='personal'?'active':'' ?>" href="/terrena/Terrena/personal">
          <i class="fa-solid fa-user-group"></i> <span class="label">Personal</span>
        </a>
      </nav>
      <button class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex ms-2" id="sidebarCollapse" aria-label="Colapsar menú">
    <i class="fa-solid fa-angles-left"></i>
  </button>
	</aside>

    <!-- Contenido principal -->
    <main class="main-content flex-grow-1">
      <!-- Top bar sticky -->
      <div class="top-bar sticky-top">
        <div class="d-flex align-items-center gap-2">
          <!-- Móvil: hamburguesa -->
          <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggleMobile" aria-label="Menú">
            <i class="fa-solid fa-bars"></i>
          </button>
          <!-- Desktop: colapsar/expandir -->

          <h1 class="top-bar-title mb-0"><?php echo $title ?? '<i class="fa-solid fa-gauge"></i>Dashboard' ?></h1>
        </div>

        <div class="d-flex align-items-center gap-3">
          <!-- Reloj/Fecha (ocultos en móvil por CSS) -->
          <div class="text-secondary small">
            <i class="fa-regular fa-clock me-1"></i><span id="live-clock">--:--</span>
          </div>
          <div class="text-secondary small">
            <i class="fa-regular fa-calendar me-1"></i><span id="live-date">--/--/----</span>
          </div>

          <!-- Alertas header -->
          <div class="dropdown">
            <button class="btn btn-outline-secondary position-relative" data-bs-toggle="dropdown">
              <i class="fa-regular fa-bell"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="hdr-alerts-badge">0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end p-0" style="min-width:320px">
              <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                <strong>Alertas</strong>
                <a href="/terrena/Terrena/reportes" class="link-more small">Ver todas <i class="fa-solid fa-chevron-right ms-1"></i></a>
              </div>
              <div id="hdr-alerts-list" class="py-1"></div>
            </div>
          </div>

          <!-- Usuario -->
          <div class="dropdown">
            <button class="btn btn-light d-inline-flex align-items-center gap-2" data-bs-toggle="dropdown">
              <span class="user-profile-icon"><i class="fa-solid fa-user"></i></span>
              <span>Juan Pérez</span>
              <i class="fa-solid fa-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/terrena/Terrena/personal">Mi perfil</a></li>
              <li><a class="dropdown-item" href="/terrena/Terrena/admin">Configuración</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="/terrena/Terrena/logout">Cerrar sesión</a></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- AQUÍ se imprime el contenido de cada vista -->
      <?php echo $content ?? '' ?>

      <!-- Footer / Barra de estado -->
      <footer class="status-bar mt-auto">
        <div class="container-status">
          <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-store"></i>
            <span>Sucursal: <strong>PRINCIPAL</strong></span>
          </div>
          <div class="ms-auto d-flex align-items-center gap-3">
            <span id="live-clock-bottom" class="text-secondary">--:--</span>
          </div>
        </div>
      </footer>
    </main>
  </div>

  <!-- Bootstrap JS + Terrena JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/terrena/Terrena/assets/js/terrena.js"></script>
</body>
</html>
