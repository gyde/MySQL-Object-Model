<?php
/*NAMESPACE*/

set_include_path('.');

function MOMAutoload ($className)
{
    static $includeSegments = NULL; 

	if ($includeSegments === NULL)
		$includeSegments = explode(PATH_SEPARATOR, get_include_path());

    $message = 'Classname: '.$className."\n";

	$basename = str_replace('\\', '/', $className).'.class.php';
	foreach ($includeSegments as $includeSegment)
	{
		$filename = $includeSegment.'/'.$basename;
		$message .= 'filename: '.$filename;

		if (file_exists($filename))
		{
			$message .= ' included'."\n";
			require_once($filename);
			break;
		}
		else
			$message .= "\n";
	}

	//error_log($message);
}

spl_autoload_register('MOMAutoload');

?>
