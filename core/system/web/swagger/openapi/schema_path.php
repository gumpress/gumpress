<?php

namespace OpenApi;

class SchemaPath 
{
	/**
	 * @var string
	 */
	private string $path;

	/**
	 * @var Operation[]
	 */
	private array $operations = array();
	private string $originalPath;
	private array $pathVariables = array();
	private ?string $schemaRef;

	public function __construct(
		string $path,
		string $schemaRef = null
	) {
		$this->originalPath = $path;
		$this->path         = $this->replacePathVariable( $path );
		if ( $schemaRef ) {
			$this->schemaRef = '#/components/schemas/' . $schemaRef;
		}
	}

	public function replacePathVariable( string $path ): string {
		if ( str_contains( $path, '(?P<' ) ) {
			$path = trim( $path, '?' );
			$path = preg_replace_callback(
				'/\(.*?<([^<>]*)>.*?\)(?=\/|$|\+)/',
				function ( $match ) {
					$this->pathVariables[] = $match[1];
					return '{' . $match[1] . '}';
				},
				$path
			);
			// it's possible that the path still have unwanted chars left
			// after the preg replacement. Clean them up.
			// @todo -- find a better regex that works for all
			 $path = str_replace( array( '(', ')', '?' ), '', $path );
		}

		return $path;
	}

	public function getOperations(): array {
		return $this->operations;
	}

	public function generateOperationsFromRouteArgs( $args ): void {
		foreach ( $args as $arg ) {
			$responses = array();
			if ( ! empty( $this->schemaRef ) ) {
				$content  = new SchemaResponseContent(
					'application/json',
					array(
						'$ref' => $this->schemaRef,
					)
				);
				$response = new SchemaResponse( 200, 'OK' );
				$response->addContent( $content );
				$responses[] = $response;

			} else {
				$responses[] = new SchemaResponse( 200, 'OK' );
			}

			foreach ( $arg['methods'] as $method => $value ) {
				$description = $arg['description'] ?? '';
				$method      = strtolower( $method );
				if($method == 'options') continue;
				$op          = new SchemaOperation( $method, $responses, $this->path );
				$op->setDescription( $description );
				$op->generateParametersFromRouteArgs( $method, $arg['args'], $this->pathVariables );
                if ( isset( $arg['security']) && is_array( $arg['security'] ) ) {
                    foreach ( $arg['security'] as $name => $values) {
                        $op->addSecurity( $name, $values );
                    }
                }
				$this->operations[] = $op;
			}
		}

		$this->operations = $this->addCoreEndPoints($this->operations);
	}

	public function getOriginalPath(): string {
		return $this->originalPath;
	}

	public function getPath(): string {
		return $this->path;
	}

	public function toArray(): array {
		$data = array();
		foreach ( $this->operations as $operation ) {
			$data[ $operation->getMethod() ] = $operation->toArray();
		}

		return $data;
	}

	private function addCoreEndPoints(array $operations)
	{
		$WP_CORE_COLLECTION_ENDPOINTS = array(
			'/wp/v2/posts',
			'/wp/v2/pages',
			'/wp/v2/media',
			'/wp/v2/menu-items',
			'/wp/v2/blocks',
			'/wp/v2/templates',
			'/wp/v2/template-parts',
			'/wp/v2/navigation',
			'/wp/v2/font-families',
			'/wp/v2/categories',
			'/wp/v2/tags',
			'/wp/v2/menus',
			'/wp/v2/wp_pattern_category',
			'/wp/v2/users',
			'/wp/v2/comments',
			'/wp/v2/search',
			'/wp/v2/block-types',
			'/wp/v2/themes',
			'/wp/v2/plugins',
			'/wp/v2/sidebars',
			'/wp/v2/widget-types',
			'/wp/v2/widgets',
			'/wp/v2/block-directory/search',
			'/wp/v2/pattern-directory/patterns',
			'/wp/v2/block-patterns/patterns',
			'/wp/v2/block-patterns/categories',
			'/wp/v2/font-collections',
			'/wp/v2/posts/{parent}/revisions',
			'/wp/v2/posts/{id}/autosaves',
			'/wp/v2/pages/{parent}/revisions',
			'/wp/v2/pages/{id}/autosaves',
			'/wp/v2/menu-items/{id}/autosaves',
			'/wp/v2/blocks/{parent}/revisions',
			'/wp/v2/blocks/{id}/autosaves',
			'/wp/v2/templates/{parent}/revisions',
			'/wp/v2/templates/{id}/autosaves',
			'/wp/v2/template-parts/{parent}/revisions',
			'/wp/v2/template-parts/{id}/autosaves',
			'/wp/v2/global-styles/{parent}/revisions',
			'/wp/v2/global-styles/themes/{stylesheet}/variations',
			'/wp/v2/navigation/{parent}/revisions',
			'/wp/v2/navigation/{id}/autosaves',
			'/wp/v2/font-families/{font_family_id}/font-faces',
			'/wp/v2/users/{user_id}/application-passwords',
		);

		foreach ($operations as $operation) {
			$endpoint = $operation->getEndpoint();
			$method  = $operation->getMethod();
			if ($method !== 'get') {
				continue;
			}
			if (!in_array($endpoint, $WP_CORE_COLLECTION_ENDPOINTS, true)) {
				continue;
			}

			$response = $operation->getResponse(200);
			if (!$response) {
				continue;
			}

			$newResponse = new SchemaResponse(
				$response->getCode(),
				$response->getDescription()
			);

			foreach ($response->getContents() as $content) {
				$mediaType = $content->getMediaType();
				$schema    = $content->getSchema();

				$hasValidJsonSchema = (
					$mediaType === 'application/json' &&
					is_array($schema) &&
					isset($schema['$ref'])
				);

				if ($hasValidJsonSchema) {
					$newContent = new SchemaResponseContent(
						'application/json',
						[
							'type'  => 'array',
							'items' => [
								'$ref' => $schema['$ref'],
							],
						]
					);
					$newResponse->addContent($newContent);
				} else {
					$newResponse->addContent($content);
				}
			}

			$operation->addResponse($newResponse);
		}

		return $operations;
	}

}
