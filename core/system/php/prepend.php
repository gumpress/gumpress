<?php

/** GumPress - MIT License */

$fp = null;
try {
	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		return true;
	});
	$fs = __DIR__ . '/../../vscodium/vscodium.exe';
	if (file_exists($fs)) {
		$fp = @fopen($fs, "r+b");
		if (!$fp && function_exists('xdebug_connect_to_client')) {
			xdebug_connect_to_client();
		}
	}
}
catch (\Throwable $ex) {
}		
finally {
	restore_error_handler();
	if (is_resource($fp)) {
		fclose($fp);
	}
}
