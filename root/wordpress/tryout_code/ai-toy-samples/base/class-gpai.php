<?php

/**
 * GPAI: a toy wrapper for the WordPress AI Client to use toy models and obtain embeddings
 * Licensed under the MIT License.
 */

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\GumPress\GumPressProvider;

class GPAI
{
	public static function wp_ai_client_models()
	{
		$res = wp_remote_get(getenv('GP_AI_ENDPOINT') . '/v1/models', [ 'timeout' => 180, 'headers' => [ 'User-Agent' => 'GPAI', 'Accept' => 'application/json' ] ]);
		if (is_wp_error($res)) throw new Exception("Network Error : " . $res->get_error_message());
		return json_decode(wp_remote_retrieve_body($res), true) ?? [];
	}

	public static function wp_ai_client_prompt( $prompt = null )
	{
		$client = wp_ai_client_prompt( $prompt )->using_model( GumPressProvider::model( 'com-model' ) );
		return $client;
	}

	public static function wp_ai_client_embeds( $prompt = null )
	{
		$client = AiClient::defaultRegistry()->getProviderModel( 'gumpress', 'emb-model' );
		$prompt = is_array($prompt) ? $prompt : [ $prompt ];
		return new class($client, $prompt) {
			private $client; private $prompt;
			public function __construct($client, $prompt) {
				$this->client = $client; $this->prompt = $prompt;
			}
			public function generate( $display = false ) {
				$inputs = array_map(function ($text) { return new MessagePart($text); }, $this->prompt);
				$result = $this->client->generate($inputs);
				if ($display) {
					$size = null;
					foreach ($result as $i => $vector) {
						$text = $this->prompt[$i];
						$size = $size ?? count($vector);
						$dump = implode(', ', array_slice($vector, 0, 4)) . ", ...";
						if ($i > 0) echo "\n";
						echo "Text: " . $text . "\n";
						echo "Size: " . $size . "\n";
						echo "Vect: " . $dump . "\n";
					}
				}
				return $result;
			}
		};
	}
}
