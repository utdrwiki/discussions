<?php

namespace MediaWiki\Extension\Discourse\API;

use MediaWiki\Extension\Discourse\ExtensionConfig;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Title\Title;

class DiscourseAPIService {
	public const SERVICE_NAME = 'DiscourseAPIService';
	public function __construct(
		private readonly ExtensionConfig $config,
		private readonly HttpRequestFactory $httpRequestFactory,
	) {}

	public function makeRequest(
		string $url,
		string $method = 'GET',
		array $options = [],
	) {
		$requestOptions = array_merge_recursive( [
			'headers' => [
				'Api-Key' => $this->config->getApiKey(),
				'Api-Username' => $this->config->getApiUsername(),
			],
		], $options );
		if ( $this->config->getUnixSocket() !== null ) {
			$requestOptions['curl'] = [
				CURLOPT_UNIX_SOCKET_PATH => $this->config->getUnixSocket(),
			];
		}
		$url = "{$this->config->getInternalBaseUrl()}$url";
		$client = $this->httpRequestFactory->createGuzzleClient();
		$response = $client->request( $method, $url, $requestOptions );
		return json_decode( $response->getBody()->getContents(), true );
	}

	public function sanitizePageTitle( Title $title ): string|null {
		$titleText = $title->getText();
		// Always skip sub-pages
		if ( strpos( $titleText, '/' ) !== false ) {
			return null;
		}

		$cleanTitle = $title->getPrefixedText();

		// To lower case
		$cleanTitle = strtolower( $cleanTitle );
		// Replace spaces with underscores
		$cleanTitle = str_replace( ' ', '_', $cleanTitle );
		// Remove special characters
		$cleanTitle = preg_replace( '/[^a-z0-9_-]/', '', $cleanTitle );

		// If there's nothing but special characters, return null
		if ( $cleanTitle === '' ) {
			return null;
		}

		return substr( $cleanTitle, 0, 50 );
	}
}
