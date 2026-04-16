<?php

/*
	This is a component of GumPress, loaded by gumpress-mu.php must-use plugin with include_once.
	It implements a basic WordPress AI Provider using the AiClient library, allowing for text generation
	and embedding generation connecting to local llama-cpp server instance.
	If removed or renamed, ai-toy-samples will cease to function.
	Edit with caution; incorrect modifications may also compromise general WordPress stability.

	GumPress - MIT License
*/

declare(strict_types=1);

namespace WordPress\GumPress;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\Embedding;
use WordPress\AiClient\Results\DTO\EmbeddingsResult;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;

add_action('init', __NAMESPACE__ . '\\gumpress_init_provider', 5);

function gumpress_init_provider()
{
	if (!class_exists(AiClient::class)) {
		return;
	}
	
	$registry = AiClient::defaultRegistry();

	if ($registry->hasProvider(GumPressProvider::class)) {
		return;
	}
	
	$registry->registerProvider(GumPressProvider::class);
}

define( 'GUMPRESS_API_KEY', 'GumPressProvider' );	// Fake see comment in createProviderMetadata()

class GumPressProvider extends AbstractApiProvider
{
	protected static function baseUrl(): string
	{
		return getenv('GP_AI_ENDPOINT');
	}

	protected static function createModel(ModelMetadata $modelMetadata, ProviderMetadata $providerMetadata): ModelInterface
	{
		foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
			if ($capability->isTextGeneration()) {
				return new GumPressTextGenerationModel($modelMetadata, $providerMetadata);
			}
			if ($capability->isEmbeddingGeneration()) {
				return new GumPressEmbeddingModel($modelMetadata, $providerMetadata);
			}
		}

		throw new RuntimeException('Unsupported Model Capabilities');
	}

	protected static function createProviderMetadata(): ProviderMetadata 
	{
		$id						 = 'gumpress';
		$name						 = 'GumPress';
		$type						 = ProviderTypeEnum::server();
		$credentialsUrl		 = null;
		$authenticationMethod = RequestAuthenticationMethod::apiKey();		// Fake to make it appear in the Connectors settings page, see also define( 'GUMPRESS_API_KEY', 'GumPressProvider' )
		$description			 = 'Text generation with GumPress.';
		$logoPath				 = null;

		return new ProviderMetadata($id, $name, $type, $credentialsUrl, $authenticationMethod, $description, $logoPath);
	}

	protected static function createProviderAvailability(): ProviderAvailabilityInterface
	{
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
	{
		return new GumPressModelMetadataDirectory();
	}
}

class GumPressModelMetadataDirectory implements ModelMetadataDirectoryInterface
{
	public function hasModelMetadata(string $modelId): bool
	{
		return in_array($modelId, ['com-model', 'emb-model'], true);
	}

	public function getModelMetadata(string $modelId): ModelMetadata
	{
		return match ($modelId) {
			'com-model' => $this->buildTextModelMetadata(),
			'emb-model' => $this->buildEmbeddingModelMetadata(),
			default => throw new Exception("Model $modelId not found")
		};
	}

	public function listModelMetadata(): array
	{
		return [
			$this->buildTextModelMetadata(),
			$this->buildEmbeddingModelMetadata()
		];
	}

	private function buildTextModelMetadata(): ModelMetadata
	{
		return new ModelMetadata(
			'com-model',
			'Completion Model',
			[
				CapabilityEnum::textGeneration()
			],
			[
				new SupportedOption(OptionEnum::systemInstruction()),
				new SupportedOption(OptionEnum::temperature()),
				new SupportedOption(OptionEnum::maxTokens()),
				new SupportedOption(OptionEnum::topP()),
				new SupportedOption(OptionEnum::frequencyPenalty()),
				new SupportedOption(OptionEnum::candidateCount()),
				new SupportedOption(OptionEnum::stopSequences()),
				new SupportedOption(OptionEnum::customOptions()),
				new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
				new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]])
			]
		);
	}

	private function buildEmbeddingModelMetadata(): ModelMetadata
	{
		return new ModelMetadata(
			'emb-model',
			'Embedding Model',
			[
				CapabilityEnum::embeddingGeneration() 
			],
			[
				new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]])
			]
		);
	}
}

class GumPressTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface
{
	final public function generateTextResult(array $prompt): GenerativeAiResult
	{
		$httpTransporter = $this->getHttpTransporter();
		$params			  = $this->prepareGenerateTextParams($prompt);

		$request = new Request(
			HttpMethodEnum::POST(),
			GumPressProvider::url('v1/chat/completions'), 
			['Content-Type' => 'application/json'],
			$params,
			$this->getRequestOptions()
		);

		$response = $httpTransporter->send($request);
		ResponseUtil::throwIfNotSuccessful($response);
		return $this->parseResponseToGenerativeAiResult($response);
	}

	protected function prepareGenerateTextParams(array $prompt): array
	{
		$config = $this->getConfig();

		$messages = [];

		$systemInstruction = $config->getSystemInstruction();
		if ($systemInstruction) {
			$messages[] = [ "role" => "system", "content" => $systemInstruction ];
		}

		foreach ($prompt as $m) {
			$messages[] = [
				'role'    => $m->getRole()->equals(MessageRoleEnum::model()) ? 'assistant' : 'user',
				'content' => $m->getParts()[0]->getText()
			];
		}

		$params = [
			'model'    => $this->metadata()->getId(),
			'messages' => $messages
		];

		// llama.cpp preset -> temp
		$temperature = $config->getTemperature();
		if ($temperature !== null) {
			$params['temperature'] = $temperature;
		}

		// llama.cpp preset -> n-predict
		$maxTokens = $config->getMaxTokens();
		if ($maxTokens !== null) {
			$params['max_tokens'] = $maxTokens;
		}

		// llama.cpp preset -> top-p
		$topP = $config->getTopP();
		if ($topP !== null) {
			$params['top_p'] = $topP;
		}

		// llama.cpp preset -> repeat-penalty
		$frequencyPenalty = $config->getFrequencyPenalty();
		if ($frequencyPenalty !== null) {
			$params['frequency_penalty'] = $frequencyPenalty;
		}

		/*
			'n_ctx' => 2048 // llama.cpp preset -> ctx-size
		*/

		// llama.cpp preset -> n
		$candidateCount = $config->getCandidateCount();
		if ($candidateCount !== null) {
			$params['n'] = $candidateCount;
		}

		// llama.cpp preset -> stop
		$stopSequences = $config->getStopSequences();
		if (is_array($stopSequences)) {
			$params['stop'] = $stopSequences;
		}

		$customOptions = $config->getCustomOptions();
		foreach ($customOptions as $key => $value) {
			if (isset($params[$key])) {
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key
					)
				);
			}
			$params[$key] = $value;
		}

		return $params;
	}

	protected function parseResponseToGenerativeAiResult(Response $response): GenerativeAiResult
	{
		$data = $response->getData();

		$content = $data['choices'][0]['message']['content'];

		$messagePart = new MessagePart($content);

		$candidate = new Candidate(
			new Message(MessageRoleEnum::model(), [ $messagePart ]),
			FinishReasonEnum::stop()
		);

		return new GenerativeAiResult(
			$data['id'] ?? uniqid(),
			[ $candidate ],
			new TokenUsage(
				$data['usage']['prompt_tokens'	 ] ?? 0,
				$data['usage']['completion_tokens'] ?? 0,
				$data['usage']['total_tokens'		 ] ?? 0
			),
			$this->providerMetadata(),
			$this->metadata()
		);
	}
}

class GumPressEmbeddingModel extends AbstractApiBasedModel
{
	public function generate(array $input): array
	{
		$httpTransporter = $this->getHttpTransporter();

		$params = [
			'model' => $this->metadata()->getId(),
			'input' => array_map(fn($i) => $i->getText(), $input)
		];

		$config = $this->getConfig();

		foreach ($config->getCustomOptions() as $key => $value) {
			$params[$key] = $value;
		}

		$request = new Request(
			HttpMethodEnum::POST(),
			GumPressProvider::url('v1/embeddings'),
			['Content-Type' => 'application/json'],
			$params,
			$this->getRequestOptions()
		);

		$response = $httpTransporter->send($request);
		ResponseUtil::throwIfNotSuccessful($response);

		return $this->parseResponse($response);
	}

	private function parseResponse(Response $response): array
	{
		$result = $response->getData();
		return (isset($result['data'][0]['embedding'])) ? array_column($result['data'], 'embedding') : [];
	}
}