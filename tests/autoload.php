<?php

require_once __DIR__ . '/../autoload.php';

spl_autoload_register(function ($className) {
    if (substr($className, 0, 6) != 'tests\\') {
        return;
    }
    $path = __DIR__ . str_replace('\\', '/', substr($className, 5)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
