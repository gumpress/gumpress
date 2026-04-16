<?php

/** GumPress - MIT License */

require_once __DIR__ . '/../../../../../root/wordpress/public_html/wp-load.php';
require_once __DIR__ . '/wp-openapi.php';

use WPOpenAPI\Filters;
use WPOpenAPI\Filters\AddCallbackInfoToDescription;
use WPOpenAPI\Filters\FixWPCoreCollectionEndpoints;
use WPOpenAPI\SchemaGenerator;
use WPOpenAPI\View;
	
function buildOpenApiSchema() {
	global $wp_version;

	$siteInfo = array(
		'admin_email'     => get_option( 'admin_email' ),
		'blogname'        => get_option( 'blogname' ),
		'blogdescription' => get_option( 'blogdescription' ),
		'home'            => get_option( 'home' ),
		'wp_version'      => $wp_version,
	);

	$restServer = rest_get_server();
	$hooks      = Filters::getInstance();

	new AddCallbackInfoToDescription( $hooks, new View( 'callback' ), $restServer->get_routes() );
	new FixWPCoreCollectionEndpoints( $hooks );

	$schemaGenerator = new SchemaGenerator( $hooks, $siteInfo, $restServer );
	$schema = $schemaGenerator->generate( 'all' );
	// Clearing
	if (isset($schema['info'])) {
		$schema['info']['title']   = "";
		$schema['info']['contact'] = new stdClass();
	}
	// Generate a namespace-to-tag map removing 'wp-openapi' path
	if (isset($schema['paths']) && is_array($schema['paths'])) {
		foreach ($schema['paths'] as $path => &$methods) {
			if (strpos($path, '/gumpress/') === 0 || strpos($path, 'gumpress') !== false) {
				unset($schema['paths'][$path]);
				continue;
			}
			if (strpos($path, 'wp-openapi') !== false) {
				unset($schema['paths'][$path]);
				continue;
			}
			foreach ($methods as $method => &$operation) {
				if (preg_match('#/wp-json/([^/]+)/#', $path, $matches)) {
					$ns = $matches[1];
				} elseif (preg_match('#^/([^/]+)/#', $path, $matches)) {
					$ns = $matches[1];
				} else {
					$ns = 'default';
				}
				$operation['tags'] = [ strtoupper($ns) ];
				if (isset($operation['requestBody']['content']['application/x-www-form-urlencoded'])) {
					$schemaRef = $operation['requestBody']['content']['application/x-www-form-urlencoded']['schema'];
					$currentContent = $operation['requestBody']['content'];
					$operation['requestBody']['content'] = array_merge(
						['application/json' => ['schema' => $schemaRef]], 
						$currentContent
					);
				}
			}
		}
		$schema['tags'] = [];
	}

	return $schema;
}

$schema = buildOpenApiSchema();
wp_send_json( $schema );
