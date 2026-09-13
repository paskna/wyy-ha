<?php

// Keep deployment failures readable before Laravel and Composer are loaded.
if (PHP_VERSION_ID < 80300) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Server nicht kompatibel: Diese Anwendung benoetigt PHP 8.3 oder neuer.';
    exit;
}

$missing = array_values(array_filter([
    extension_loaded('openssl') ? null : 'openssl',
    extension_loaded('mbstring') ? null : 'mbstring',
    extension_loaded('fileinfo') ? null : 'fileinfo',
    extension_loaded('pdo') ? null : 'pdo',
    extension_loaded('xml') ? null : 'xml',
]));

if ($missing !== []) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Server nicht kompatibel: Fehlende PHP-Erweiterungen: '.implode(', ', $missing).'.';
    exit;
}

if (! is_file(__DIR__.'/../vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Installation unvollstaendig: vendor/autoload.php fehlt. Bitte das vollstaendige Release-Paket hochladen.';
    exit;
}
