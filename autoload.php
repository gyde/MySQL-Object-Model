<?php

spl_autoload_register(function ($className) {
    if (substr($className, 0, 9) != 'Gyde\\Mom\\') {
        return;
    }
    $path = __DIR__ . '/src/' . str_replace('\\', '/', substr($className, 9)) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
