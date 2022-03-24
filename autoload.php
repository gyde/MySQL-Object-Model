<?php

namespace Gyde\MOM;

function MOMAutoload($className)
{
    static $includeSegments = null;

    if ($includeSegments === null) {
        $includeSegments = explode(PATH_SEPARATOR, __DIR__);
    }

    $message = 'Classname: ' . $className . "\n";

    $basename = str_replace('\\', '/', $className) . '.php';
    foreach ($includeSegments as $includeSegment) {
        $filename = $includeSegment . '/' . $basename;
        $message .= 'filename: ' . $filename;

        if (file_exists($filename)) {
            $message .= ' included';
            require_once($filename);
            break;
        }

        $message .= "\n";
    }

    //error_log($message);
}

spl_autoload_register('MOMAutoload');
