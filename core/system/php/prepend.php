<?php

/** GumPress - MIT License */

$is_wp_cli = (isset($GLOBALS['argv'][0]) && stripos($GLOBALS['argv'][0], 'wp-cli') !== false);
if ($is_wp_cli) {
	return;
}

$fp = null;
try {
	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		return true;
	});
	$fs = __DIR__ . '/../../vscodium/node.exe';
	if (file_exists($fs)) {
		$fp = @fopen($fs, "r+b");
		if (!$fp && function_exists('xdebug_connect_to_client')) {
			xdebug_connect_to_client();
		}
	}
	process_mu_plugin();
	process_wp_config();
}
catch (\Throwable $ex) {
}		
finally {
	restore_error_handler();
	if (is_resource($fp)) {
		fclose($fp);
	}
}

function process_mu_plugin()
{
	$mu = getenv("GP_MUHOOK");
	if ($mu && !file_exists($mu)) {
		@mkdir(dirname($mu), 0755, true);
		file_put_contents($mu, '<?php defined("ABSPATH") || die; $mu = getenv("GP_MUFILE"); if ($mu && file_exists($mu)) require_once $mu; ?>', LOCK_EX);
	}
}

function process_wp_config()
{
	$wp_config = getenv("GP_WP_CONFIG");
	if (!$wp_config || !file_exists($wp_config)) return;

	$curr_content = file_get_contents($wp_config);
	$next_content = $curr_content;

	$toUpsert = [
		'DB_NAME'     => getenv('GP_DB_NAME'),
		'DB_USER'     => getenv('GP_DB_USER'),
		'DB_PASSWORD' => getenv('GP_DB_PASSWORD'),
		'DB_HOST'     => getenv('GP_DB_HOST'),
		'WP_TEMP_DIR' => getenv('TEMP'),
	];
	$toInsert = [];
	foreach ($toUpsert as $key => $raw) {
		$val		= var_export($raw, true);
		$pattern	= "/define\s*\(\s*(['\"])" . preg_quote($key) . "\\1\s*,\s*.*?\s*\)\s*;/i";
		$replace	= "define( '$key', $val );";
		if (preg_match($pattern, $next_content)) {
			$next_content = preg_replace($pattern, $replace, $next_content);
		} else {
			$toInsert[] = $replace;
		}
	}
	if (!empty($toInsert)) {
		$injection = "\r\n" . implode("\r\n", $toInsert) . "\r\n\r\n";
		if (preg_match('/^<\?php\s*/i', $next_content, $matches)) {
			$next_content = substr_replace($next_content, $injection, strlen($matches[0]), 0);
		} 
	}
	if ($next_content !== $curr_content) {
		file_put_contents($wp_config, $next_content, LOCK_EX);
		clearstatcache(true, $wp_config);
		if (function_exists('opcache_invalidate')) {
			@opcache_invalidate($wp_config, true);
		}
		if (!headers_sent()) {
			header("Refresh:0");
			exit;
		}
	}
}
