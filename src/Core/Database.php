<?php
declare(strict_types=1);

namespace Terrena\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfgFile = dirname(__DIR__) . '/config.php';
        if (!is_file($cfgFile)) {
            throw new RuntimeException('Archivo config.php no encontrado.');
        }

        $pdo = null;
        $ret = require $cfgFile;

        if ($ret instanceof PDO) {
            self::$pdo = $ret;
        } elseif ($pdo instanceof PDO) {
            self::$pdo = $pdo;
        } elseif (defined('DB_HOST') && defined('DB_PORT') && defined('DB_NAME') && defined('DB_USER')) {
            self::$pdo = new PDO(
                "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME,
                DB_USER,
                defined('DB_PASS') ? DB_PASS : '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } else {
            throw new RuntimeException('Configuración de base de datos inválida en config.php.');
        }

        return self::$pdo;
    }

    public static function all(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    public static function val(string $sql, array $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: null;
    }
}