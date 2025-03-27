<?php

namespace MediaWiki\Extension\Discourse\API;

use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;

class DiscourseAPIService {
	private string|false $baseUrl;
	private string $internalUrl;
	private string|false $apiKey;
	private string $apiUsername;
	private string|null $unixSocket;
	private HttpRequestFactory $httpRequestFactory;

	public function __construct(
		Config $config,
		HttpRequestFactory $httpRequestFactory,
	) {
		$this->baseUrl = $config->get( 'DiscourseBaseUrl' );
		$this->internalUrl = $config->get( 'DiscourseBaseUrlInternal', $this->baseUrl );
		$this->apiKey = $config->get( 'DiscourseApiKey' );
		$this->apiUsername = $config->get( 'DiscourseApiUsername' );
		$this->unixSocket = $config->get( 'DiscourseUnixSocket' );
		$this->httpRequestFactory = $httpRequestFactory;
	}

	public function getBaseUrl(): string|false {
		return $this->baseUrl;
	}

	public function throwIfConfigInvalid(): void {
		if ( $this->baseUrl === false ) {
			throw new LogicException( '$wgDiscourseBaseUrl must be set' );
		}
		if ( $this->apiKey === false ) {
			throw new LogicException( '$wgDiscourseApiKey must be set' );
		}
	}

	public function makeRequest(
		string $url,
		string $method = 'GET',
		array $options = [],
	) {
		$requestOptions = array_merge_recursive( [
			'headers' => [
				'Api-Key' => $this->apiKey,
				'Api-Username' => $this->apiUsername,
			],
		], $options );
		if ( $this->unixSocket !== null ) {
			$requestOptions['curl'] = [
				CURLOPT_UNIX_SOCKET_PATH => $this->unixSocket
			];
		}
		$url = "{$this->internalUrl}$url";
		$client = $this->httpRequestFactory->createGuzzleClient();
		$response = $client->request( $method, $url, $requestOptions );
		return json_decode( $response->getBody()->getContents(), true );
	}

	public function sanitizePageTitle($title) {
		$titleText = $title->getText();
		// Always skip sub-pages
		if (strpos($titleText, '/') !== false) {
			return null;
		}
		
		$cleanTitle = $title->getPrefixedText();
		
		// To lower case
		$cleanTitle = strtolower($title);
		// Replace spaces with underscores
		$cleanTitle = str_replace(' ', '_', $cleanTitle);
		// Remove special characters
		$cleanTitle = preg_replace('/[^a-z0-9_-]/', '', $cleanTitle);
		
		// If there's nothing but special characters, return null
		if ($cleanTitle === "") {
			return null;
		}
		
		return substr($cleanTitle, 0, 50);
	}
}
