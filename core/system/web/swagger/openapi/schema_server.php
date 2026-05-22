<?php

namespace OpenApi;

class SchemaServer 
{
	private string $url;

	public function __construct( $url ) {
		$this->url = $url;
	}

	public function toArray(): array {
		return array(
			'url' => $this->url,
		);
	}
}
