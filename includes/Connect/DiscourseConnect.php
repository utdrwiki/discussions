<?php

namespace MediaWiki\Extension\Discourse\Connect;

use MediaWiki\Exception\BadRequestError;
use MediaWiki\Extension\Discourse\ExtensionConfig;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\User\User;

class DiscourseConnect extends UnlistedSpecialPage {
	public function __construct(
		private readonly ExtensionConfig $config,
		private readonly DiscourseConnectPayloadGenerator $payloadGenerator,
	) {
		parent::__construct( 'DiscourseConnect' );
	}

	/** @inheritDoc */
	protected function getLoginSecurityLevel() {
		return false;
	}

	private function validateUser( User $user ): void {
		$this->requireNamedUser( 'discourse-connect-requires-named' );
		if ( $user->getEmail() === '' ) {
			throw new BadRequestError( 'discourse-connect-valid-email', 'discourse-connect-add-email' );
		}
		if ( !$user->isEmailConfirmed() ) {
			throw new BadRequestError( 'discourse-connect-valid-email', 'discourse-connect-confirm-email' );
		}
	}

	private function validatePayload(): array {
		$req = $this->getRequest();
		$payload = $req->getRawVal( 'sso' );
		$signature = $req->getRawVal( 'sig' );
		if ( $payload === null || $signature === null ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-missing-params' );
		}
		$secret = $this->config->getConnectSecret();
		if ( $secret === false ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-missing-secret' );
		}
		$hmac = hash_hmac( 'sha256', $payload, $secret );
		if ( !hash_equals( $hmac, $signature ) ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-invalid-signature' );
		}
		$decodedPayload = base64_decode( $payload, true );
		if ( $decodedPayload === false ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-invalid-payload' );
		}
		$payloadParams = [];
		parse_str( $decodedPayload, $payloadParams );
		if ( !isset( $payloadParams['nonce'], $payloadParams['return_sso_url'] ) ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-missing-payload-params' );
		}
		return $payloadParams;
	}

	private function getLoginUrlFromPayload( string $returnUrl, array $payload ): string {
		$encodedPayload = base64_encode( http_build_query( $payload ) );
		$signature = hash_hmac( 'sha256', $encodedPayload, $this->config->getConnectSecret() );
		return $returnUrl . '?' . http_build_query( [
			'sso' => $encodedPayload,
			'sig' => $signature,
		] );
	}

	/** @inheritDoc */
	public function execute( $subpage ): void {
		parent::execute( $subpage );
		if ( !$this->config->isConnectEnabled() ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-disabled' );
		}
		$user = $this->getUser();
		$this->validateUser( $user );
		$payload = $this->validatePayload();
		$loginPayload = $this->payloadGenerator->getPayload( $user );
		$loginPayload['nonce'] = $payload['nonce'];
		$returnUrl = $payload['return_sso_url'];
		$loginUrl = $this->getLoginUrlFromPayload( $returnUrl, $loginPayload );
		$this->getOutput()->redirect( $loginUrl );
	}
}
