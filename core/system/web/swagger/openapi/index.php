<?php

namespace OpenApi;

require_once __DIR__ . '/../../../../../root/public_html/wp-load.php';

require_once __DIR__ . '/schema_generator.php';
require_once __DIR__ . '/schema_contact.php';
require_once __DIR__ . '/schema_info.php';
require_once __DIR__ . '/schema_license.php';
require_once __DIR__ . '/schema_operation.php';
require_once __DIR__ . '/schema_parameter.php';
require_once __DIR__ . '/schema_path.php';
require_once __DIR__ . '/schema_response.php';
require_once __DIR__ . '/schema_response_content.php';
require_once __DIR__ . '/schema_server.php';
require_once __DIR__ . '/schema_tag.php';
	
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

	$schemaGenerator = new SchemaGenerator($siteInfo, $restServer );
	$schema = $schemaGenerator->generate( 'all' );
	// Clearing
	if (isset($schema['info'])) {
		$schema['info']['title']   = "";
		$schema['info']['contact'] = new \stdClass();
	}
	// Generate a namespace-to-tag map removing 'openapi' path
	if (isset($schema['paths']) && is_array($schema['paths'])) {
		foreach ($schema['paths'] as $path => &$methods) {
			if (strpos($path, '/gumpress/') === 0 || strpos($path, 'gumpress') !== false) {
				unset($schema['paths'][$path]);
				continue;
			}
			if (strpos($path, 'openapi') !== false) {
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

//---

class Util {
	public static function removeArrayKeysRecursively( array $array, array $keysToRemove ): array {
		foreach ($array as $key => &$value) {
			if (in_array($key, $keysToRemove, true)) {
				unset($array[$key]);
			} elseif (is_array($value)) {
				$value = self::removeArrayKeysRecursively($value, $keysToRemove);
			}
		}

		return $array;
	}

	public static function is_assoc_array(array $array): bool {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	public static function modifyArrayValueByKeyRecursive(array &$array, $key, callable $callback): void {
		foreach ($array as $k => &$v) {
			if ($k === $key) {
				$v = $callback($v);
			}

			if (is_array($v)) {
				self::modifyArrayValueByKeyRecursive($v, $key, $callback);
			}
		}
	}

	/**
	 * In WordPress, some schema formats are not compatible with the OpenAPI specification.
	 * This function converts those special or non-standard types to OpenAPI-compatible values.
	 * These values should not be used as 'type' values according to JSON Schema,
	 * but are sometimes used in WordPress REST API schemas regardless.
	*/
	public static function normalzieInvalidType( $type ) {
		if ( is_array( $type ) ) {
			foreach ( $type as $key => $value ) {
				$type[ $key ] = self::normalzieInvalidType( $value );
			}
			return $type;
		}

		$replacements = array(
			'date' => 'string',
			'date-time' => 'string',
			'email' => 'string',
			'hostname' => 'string',
			'ipv4' => 'string',
			'uri' => 'string',
			'mixed' => 'string',
			'bool' => 'boolean',
		);

		if ( isset( $replacements[ $type ] ) ) {
			return $replacements[ $type ];
		}

		return $type;
	}

	public static function normalizeEnum( $enum ) {
		if ( is_array( $enum ) ) {
			return array_unique( array_values( $enum ) );
		}
		return $enum;
	}

	public static function normalizeSchemaTitle( $title ) {
		// Remove invalid characters for schema titles.
		// Only allow alphanumeric characters and underscores.
		$title = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $title );
		// Ensure the title starts with an alphabetic character or underscore.
		if ( ! preg_match( '/^[a-zA-Z_]/', $title ) ) {
			$title = '_' . $title;
		}


		return strtolower( $title );
	}
}
