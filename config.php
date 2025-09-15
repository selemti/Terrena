<?php
declare(strict_types=1);

/* ====== BLOQUE GLOBAL (autoload + guard) ====== */
namespace {
  if (defined('TERRENA_CONFIG_INCLUDED')) {
    // Ya cargado: aún así devolvemos PDO para quien haga `require 'config.php'`
    return \Terrena\Core\Database::pdo();
  }
  define('TERRENA_CONFIG_INCLUDED', 1);

  foreach ([__DIR__.'/vendor/autoload.php', __DIR__.'/../vendor/autoload.php'] as $a) {
    if (is_file($a)) { require_once $a; break; }
  }
  @date_default_timezone_set('America/Mexico_City');
}

/* ====== NÚCLEO ====== */
namespace Terrena\Core {
  use Dotenv\Dotenv;
  use PDO;

  final class Config {
    public static function loadEnv(?string $dir=null): void {
      if (!class_exists(Dotenv::class)) return;
      $dir = $dir ?: dirname(__DIR__);
      try { Dotenv::createImmutable($dir)->safeLoad(); } catch (\Throwable $e) {}
    }
    public static function env(string $k, $default=null) {
      $v = $_ENV[$k] ?? getenv($k);
      return ($v===false || $v===null) ? $default : $v;
    }
    public static function db(): array {
      self::loadEnv(dirname(__DIR__));
      $host = self::env('DB_HOST', defined('DB_HOST') ? \DB_HOST : '127.0.0.1');
      $port = self::env('DB_PORT', defined('DB_PORT') ? \DB_PORT : '5433');
      $name = self::env('DB_NAME', defined('DB_NAME') ? \DB_NAME : 'pos');
      $user = self::env('DB_USER', defined('DB_USER') ? \DB_USER : 'postgres');
      $pass = self::env('DB_PASS', defined('DB_PASS') ? \DB_PASS : 'T3rr3n4#p0s');
      return compact('host','port','name','user','pass');
    }
  }

  final class Database {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
      if (self::$pdo instanceof PDO) return self::$pdo;
      $c = Config::db();
      $dsn = "pgsql:host={$c['host']};port={$c['port']};dbname={$c['name']}";
      $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);
      return self::$pdo = $pdo;
    }
  }
}

/* ====== COMPAT/LEGACY (global) ====== */
namespace {
  $cfg = \Terrena\Core\Config::db();
  foreach (['HOST'=>'host','PORT'=>'port','NAME'=>'name','USER'=>'user','PASS'=>'pass'] as $CK=>$k) {
    $const = 'DB_'.$CK; if (!defined($const)) define($const, $cfg[$k]);
  }
  if (!isset($pdo) || !($pdo instanceof \PDO)) {
    $pdo = \Terrena\Core\Database::pdo();
  }
  if (!class_exists('Config', false)) {
    class_alias(\Terrena\Core\Config::class, 'Config');
  }

  // IMPORTANTE: devolver PDO para los módulos legacy
  return $pdo;
}
