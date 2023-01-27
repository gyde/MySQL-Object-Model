<?php

namespace tests;

spl_autoload_register(function ($className) {
    static $includeSegments = null;
    if ($includeSegments === null) {
        $includeSegments = explode(PATH_SEPARATOR, __DIR__);
    }

    $nLength = strlen(__NAMESPACE__);
    if (substr($className, 0, $nLength + 1) != __NAMESPACE__ . '\\') {
        return;
    }
    $message = 'Classname: ' . $className . "\n";

    print_r(PATH_SEPARATOR);

    $basename = str_replace('\\', '/', substr($className, $nLength + 1)) . '.php';
    foreach ($includeSegments as $includeSegment) {

        print $includeSegment . "\n";
        $filename = $includeSegment . '/' . $basename;
        $message .= 'filename: ' . $filename;

        if (file_exists($filename)) {
            require_once($filename);
            print($message . " included\n");
            return;
        }

        $message .= "\n";
    }

    print($message."Not found\n\n");
});
