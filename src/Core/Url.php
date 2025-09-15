<?php
declare(strict_types=1);

namespace Terrena\Core;

function base_url(): string
{
    static $base = null;
    if ($base !== null) return $base;

    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $base = $scriptDir === '' ? '/' : $scriptDir;

    $GLOBALS['__BASE__'] = $base;
    return $base;
}

function url(string $path = '', array $params = []): string
{
    $base = base_url();
    $path = '/' . ltrim($path, '/');
    $url = $base === '/' ? $path : $base . $path;

    if ($params) {
        $query = http_build_query($params);
        $url .= '?' . $query;
    }

    return $url;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}