<?php

namespace OpenApi;

class SchemaGenerator
{
	private \WP_REST_Server $restServer;
	private array $siteInfo;

	/**
	 * @param Filters        $hooks
	 * @param array          $siteInfo Array containing admin_email, blogname, blogdescription, home, wp_version options
	 * @param WP_REST_Server $restServer
	 */
	public function __construct(array $siteInfo, \WP_REST_Server $restServer ) {
		$this->restServer = $restServer;
		$this->siteInfo   = $siteInfo;
	}

	private function generateInfo( array $hookArgs ): SchemaInfo {
		$contact = new SchemaContact(
			$this->siteInfo['blogname'],
			$this->siteInfo['home'],
			'' /* $this->siteInfo['admin_email'] */
		);

		$info = new SchemaInfo(
			ucfirst( $this->siteInfo['blogname'] ) . ' API',
			$this->siteInfo['wp_version'],
			$this->siteInfo['blogdescription'],
			$contact
		);

		return $info;
	}

	public function generate( $requestedNamespace ): array {
		$namespaces = $requestedNamespace === 'all' ? $this->restServer->get_namespaces() : array( $requestedNamespace );

		$hookArgs = array(
			'requestedNamespace' => $requestedNamespace,
		);

		$base = array(
			'openapi'    => '3.1.0',
			'info'       => $this->generateInfo( $hookArgs )->toArray(),
			'servers'    => array(
				new SchemaServer( $this->siteInfo['home'] . '/' . rest_get_url_prefix() ),
			),
			'tags'       => array(),
			'components' => array(
				'schemas' => array(),
			),
		);

		$paths = array();

		foreach ( $namespaces as $namespace ) {
			$base['tags'][] = new SchemaTag( $namespace );
			foreach ( $this->restServer->get_routes( $namespace ) as $path => $args ) {
				$options     = $this->restServer->get_route_options( $path );
				$schemaTitle = null;
				if ( isset( $options['schema'] ) ) {
					$schema = call_user_func( $options['schema'] );
					if ( isset( $schema['title'] ) ) {
						$title = Util::normalizeSchemaTitle( $schema['title'] );
						$schemaTitle                                   = $title;
						$base['components']['schemas'][ $schemaTitle ] = $schema;
					}
				}
				$path = new SchemaPath( $path, $schemaTitle );
				$path->generateOperationsFromRouteArgs( $args );
				$paths[ $path->getPath() ] = $path;
			}
		}

		$base['servers'] = array_map(
			function( SchemaServer $server ) {
				return $server->toArray();
			},
			$base['servers']
		);

		$base['paths'] = array_map(
			function( $path ) use ( $hookArgs ) {
				return $path->toArray();
			},
			$paths
		);

		$base['tags'] = array_map(
			function ( SchemaTag $tag ) use ( $hookArgs ) {
				return $tag->toArray();
			},
			$base['tags']
		);

		$base['components'] = $base['components'];

		return $this->processInvalidSchemas( $base );
	}

	protected function processInvalidSchemas( array $base ): array
	{
		// Remove context, readonly, requird from the schema
		foreach ( $base['components']['schemas'] as $key =>$schema ) {
			$keyToRemove = isset($schema['properties']) ? 'properties' : 'items';
			if (isset($schema[$keyToRemove])) {
				$base['components']['schemas'][$key][$keyToRemove] = Util::removeArrayKeysRecursively( $schema[$keyToRemove], array( 'context', 'readonly' ) );
			}
			Util::modifyArrayValueByKeyRecursive($base['components']['schemas'][$key], 'properties', function($properties) {
				if (is_array($properties) && count($properties) === 0) {
					return new \stdClass();
				}
				foreach ($properties as $key => $property) {
					if (isset($property['required'])) {
						unset($properties[$key]['required']);
					}
				}
				return $properties;
			});
			// Update enum
			Util::modifyArrayValueByKeyRecursive($base['components']['schemas'][$key], 'enum', function($enum) {
				if (!is_array($enum)) {
					return $enum;
				}
				if (Util::is_assoc_array($enum)) {
					return array_values($enum);
				}
				return $enum;
			});
		}

		// Update endpoints from WooCommerce.
		$fixList = [
			'install_async_schema',
			'install_and_activate_schema',
			'count_low_in_stock_items'
		];
		foreach ($fixList as $schemaKey) {
			if (isset($base['components']['schemas'][$schemaKey]) && isset($base['components']['schemas'][$schemaKey]['properties']['properties'])) {
				$base['components']['schemas'][$schemaKey]['properties'] = $base['components']['schemas'][$schemaKey]['properties']['properties'];	
				foreach ($base['components']['schemas'][$schemaKey]['properties'] as $key => $property) {
					if (is_string($property)) {
						$base['components']['schemas'][$schemaKey]['properties'][$key] = array('type' => Util::normalzieInvalidType($property));
					}
				}
			}
		}

		return $base;
	}
}
