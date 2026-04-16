<?php

/** GumPress - MIT License */

// Dynamic Database Detection (SQLite vs MariaDB)
$wp_content_dir = __DIR__ . '/../../../root/wordpress/public_html/wp-content';
$is_sqlite      = false;
$sqlite_path    = '';

// 1. Check presence of db.php with SQLite signature
if (file_exists($wp_content_dir . '/db.php')) {
	$db_php_content = file_get_contents($wp_content_dir . '/db.php');
	if (stripos($db_php_content, 'sqlite') !== false) {
		if (file_exists($wp_content_dir . '/database/.ht.sqlite')) {
			$sqlite_path = realpath($wp_content_dir . '/database/.ht.sqlite');
			$is_sqlite	 = true;
		}
	}
}

function adminer_object()
{
	global $is_sqlite, $sqlite_path;

	class AdminerCustomization extends Adminer\Plugins
	{
		private $sqlite_active;
		private $db_path;

		public function __construct($plugins, $is_sqlite, $sqlite_path) {
			parent::__construct($plugins);
			$this->sqlite_active = $is_sqlite;
			$this->db_path			= $sqlite_path;
		}

		function permanentLogin() {
			return 'gumpress';
		}

		function driver() {
			return $this->sqlite_active ? 'sqlite' : 'server';
		}

		function database() {
			if ($this->sqlite_active) {
				return $this->db_path;
			}
			return 'wordpress'; 
		}

		function head() {
			?>
				<style>
					#logout { display: none; }
					#dbs { display: none !important; }
					#content > h2:first-of-type { display: none; }
					#menu { padding-top: 10px; }
				</style>
			<?php
			return true;
		}
	}

	return new AdminerCustomization(array(), $is_sqlite, $sqlite_path);
}

// 2. Autologon
if ($_SERVER['QUERY_STRING'] === '' || empty($_COOKIE['adminer_permanent'])) {
	if ($is_sqlite) {
		$_POST['auth'] = [
			'driver'    => 'sqlite',
			'username'  => '',
			'db'        => $sqlite_path,
			'permanent' => 1
		];
	}
	else {
		$_POST['auth'] = [
			'driver'    => 'server',
			'username'  => getenv('GP_DB_USER'),
			'server'    => getenv('GP_DB_HOST'),
			'db'        => 'wordpress', 
			'permanent' => 1
		];
	 }
}

include 'adminer-5.4.2-en.php';
