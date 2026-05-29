<?php

/** GumPress - MIT License */

declare(strict_types=1);

namespace WordPress\GumPress\Interops;

if (defined('WP_CLI') && WP_CLI) {
	return;
}

add_action('init'                      , __NAMESPACE__ . '\\init');
add_filter('determine_current_user'    , __NAMESPACE__ . '\\determine_current_user', 20);
add_action('http_api_curl'             , __NAMESPACE__ . '\\http_api_curl', 10, 3);
add_filter('http_request_args'			, __NAMESPACE__ . '\\http_request_args', 999, 1);
add_action('rest_api_init'					, __NAMESPACE__ . '\\rest_api_init', 10);

require_once __DIR__ . '/../../mcp-adapter/mcp-adapter.php';

function init()
{
	if ( is_user_logged_in()			  ) return;
	if ( basename( $_SERVER['SCRIPT_NAME'] ) === 'wp-login.php' ) {
		$admin_id = get_first_admin_id();
		if ( $admin_id ) {
			$user = get_userdata($admin_id);
			wp_set_current_user( $admin_id, $user->user_login );
			wp_set_auth_cookie( $admin_id, true );
			do_action( 'wp_login', $user->user_login, $user );
			$redirect_to = !empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url();
			wp_safe_redirect( $redirect_to );
			exit();
		}
	}
}

function determine_current_user($user_id)
{
	if ( $user_id ) return $user_id;
	if ( is_known_rest_request() ) {
		$admin_id = get_first_admin_id();
		if ( $admin_id ) {
			// Auth is injected via determine_current_user (no nonce check triggered)
			// but WordPress still requires cookie status to be explicitly confirmed!
			add_filter('rest_cookie_collect_status', '__return_true', 100);
			return $admin_id;
		}
	}
	return $user_id;
}

function is_known_rest_request()
{
	$auth_secret = getenv('GP_AUTH_SECRET') ?? '';
	if ( $auth_secret === '' ) return false;
	$auth_apikey = $_SERVER['HTTP_X_GUMPRESS_AUTH'] ?? '';
	if ( $auth_apikey !== '' ) {
		return hash_equals($auth_secret, $auth_apikey);
	}
	else {
		$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
		if ( stripos($auth_header, 'Basic ') === 0 ) {
			$decoded = base64_decode(substr($auth_header, 6));
			if ( $decoded && str_contains($decoded, ':') ) {
				list($__discard__, $auth_apikey) = explode(':', $decoded, 2);
				if ( hash_equals($auth_secret, $auth_apikey) ) {
					add_filter('wp_is_application_password_active', '__return_false', 999);
					return true;
				}
			}
		}
	}
	return false;
}

function http_api_curl($ch, $args, $url)
{
	curl_setopt($ch, CURLOPT_TIMEOUT, 333);
}

function http_request_args($args)
{
	$ca_bundle = ini_get('openssl.cafile') ?: ini_get('curl.cainfo');
	$args['sslcertificates'] = $ca_bundle;
	$args['sslverify'] = true;
	return $args;
}

function rest_api_init()
{
	register_rest_route('gumpress', '/execute/(?P<encoded>.+)', array(
		'methods'				 => 'GET',
		'callback'				 => __NAMESPACE__ . '\\safe_execute',
		'permission_callback' => function () {
			return ( is_user_logged_in() && current_user_can('manage_options') );
		}
	));
}

function safe_execute($request)
{
	$encodedPath = $request['encoded'];
	$remainder	 = strlen($encodedPath) % 4;
	if ( $remainder ) {
		$encodedPath .= str_repeat('=', 4 - $remainder);
	}
	$file_path = base64_decode(strtr($encodedPath, '-_', '+/'));
	$file_name = basename($file_path);
	$init_time = microtime(true);

	if ( !file_exists($file_path) ) {
		return;
	}

	while (ob_get_level() > 0) ob_end_clean(); 

	@ini_set('zlib.output_compression', '0');
	@ini_set('implicit_flush'			 , '1');
	ob_implicit_flush(true);

	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: no-cache');
	header('Connection: keep-alive');

	safe_execute_emit_header($file_name);

	register_shutdown_function(function () use ($file_name, $init_time) {
		$error = error_get_last();
		if ($error === null) return;
		$errors = [
			'errClass'   => resolve_error_type($error['type']),
			'errMessage' => $error['message'],
			'errFile'    => $error['file'],
			'errLine'    => $error['line'],
			'errOrigin'	 => 'C'
		];
		safe_execute_emit_footer($file_name, $init_time, $errors);
	});
	$errors = safe_require($file_path);
	safe_execute_emit_footer($file_name, $init_time, $errors);

	exit;
}

function safe_execute_emit_header($file_name)
{
	echo "\e[30;1m";
	$text = "Script \e[38;5;136m" . $file_name . "\e[30;1m started at " . date('H:i:s') . " (UTC) | \e[36;1mCTRL-C to cancel\e[30;1m";
	echo "⚡ " . $text . "\n";
	echo "\n";
}

function safe_execute_emit_footer($file_name, $init_time, $errors)
{
	if ($errors !== null) {
		$message = sprintf(
			"\e[96mPHP(%s) %s: \e[31m%s\e[96m\n### \e[37m%s\e[31m (%d)\n\e[96mSEE \e[30;1m%s\e[96m for details\e[30;1m\n",
			$errors['errOrigin' ],
			$errors['errClass'  ],
			$errors['errMessage'],
			$errors['errFile'	  ],
			$errors['errLine'	  ],
			realpath(__DIR__ . '/../root/public_html/wp-content/debug.log')
		);
		echo $message;
	}
	echo "\n";
	$text = "Script \e[38;5;136m" . $file_name . "\e[30;1m stopped at " . date('H:i:s') . " (UTC)" . " | Duration: " . round(microtime(true) - $init_time, 3) . " s";
	echo "⚡ " . $text . "\n";
	echo "\n";
}

function safe_require($file_path)
{
	$errors = null;
	try {
		set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errors) {
			$errors = [
				'errClass'	 => resolve_error_type($errno),
				'errMessage' => $errstr,
				'errFile'	 => $errfile,
				'errLine'	 => $errline,
				'errOrigin'	 => 'B'
			];
			return true;
		});
		require $file_path;
	}
	catch (\Throwable $ex) {
		$errors = [
			'errClass'	 => get_class($ex),
			'errMessage' => preg_replace('/, called in.*$/s', '', $ex->getMessage()),
			'errFile'	 => $ex->getFile(),
			'errLine'	 => $ex->getLine(),
			'errOrigin'	 => 'A'
		];
	}
	finally {
		restore_error_handler();
	}
	return $errors;
}

function resolve_error_type($errno)
{
	$error_types = [
		E_COMPILE_ERROR	  => 'Compile Error',
		E_COMPILE_WARNING	  => 'Compile Warning',
		E_CORE_ERROR		  => 'Core Error',
		E_CORE_WARNING		  => 'Core Warning',
		E_DEPRECATED		  => 'Deprecated',
		E_ERROR				  => 'Error',
		E_NOTICE				  => 'Notice',
		E_PARSE				  => 'Parse Error',
		E_RECOVERABLE_ERROR => 'Recoverable Error',
		E_STRICT				  => 'Strict Notice',
		E_USER_DEPRECATED	  => 'User Deprecated',
		E_USER_ERROR		  => 'User Error',
		E_USER_NOTICE		  => 'User Notice',
		E_USER_WARNING		  => 'User Warning',
		E_WARNING			  => 'Warning'
	];
	return $error_types[$errno] ?? 'Unknown Error (' . (int) $errno . ')';
}

function get_first_admin_id()
{
	if ( function_exists( 'get_users' ) ) {
		$admin_users = get_users( array(
			'role'    => 'administrator',
			'number'  => 1,
			'fields'  => 'ID',
			'orderby' => 'ID',
			'order'   => 'ASC',
		) );
		if ( !empty( $admin_users ) ) {
			return $admin_users[0];
		}
	}
	global $wpdb;
	$cap_key = $wpdb->prefix . 'capabilities';
	$admin_id = $wpdb->get_var($wpdb->prepare("
		SELECT user_id 
		FROM $wpdb->usermeta 
		WHERE meta_key = %s 
		AND meta_value LIKE %s 
		ORDER BY user_id ASC 
		LIMIT 1", 
		$cap_key, 
		'%administrator%'
	));
	return $admin_id ? (int) $admin_id : false;
}
