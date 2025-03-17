<?php

namespace MediaWiki\Extension\Discourse\SpecialPage;

use BadRequestError;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;

class DiscourseConnect extends UnlistedSpecialPage {
	private PermissionManager $permissionManager;
	private UserGroupManager $userGroupManager;

	public function __construct(
		PermissionManager $permissionManager,
		UserGroupManager $userGroupManager,
	) {
		parent::__construct( 'DiscourseConnect' );
		$this->permissionManager = $permissionManager;
		$this->userGroupManager = $userGroupManager;
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

	private function validatePayload( Config $config ): array {
		$req = $this->getRequest();
		$payload = $req->getRawVal( 'sso' );
		$signature = $req->getRawVal( 'sig' );
		if ( $payload === null || $signature === null ) {
			throw new BadRequestError( 'discourse-connect-bad-request', 'discourse-connect-missing-params' );
		}
		$secret = $config->get( 'DiscourseConnectSecret' );
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

	private function getDiscourseGroups( Config $config, User $user ): array {
		$groupMap = $config->get( 'DiscourseGroupMap' );
		if ( $groupMap === null ) {
			return [];
		}
		$groups = $this->userGroupManager->getUserEffectiveGroups( $user );
		$groupSet = [];
		foreach ( $groups as $group ) {
			if ( !isset( $groupMap[$group] ) ) {
				continue;
			}
			foreach ( $groupMap[$group] as $group ) {
				$groupSet[$group] = true;
			}
		}
		return array_keys( $groupSet );
	}

	private function getLoginPayload( Config $config, User $user, array $payload ): array {
		$isAdmin = $this->permissionManager->userHasRight( $user, 'discourse-admin' );
		$isModerator = $this->permissionManager->userHasRight( $user, 'discourse-moderator' );
		$groups = $this->getDiscourseGroups( $config, $user );
		return [
			'nonce' => $payload['nonce'],
			'email' => $user->getEmail(),
			'external_id' => $user->getId(),
			'username' => $user->getName(),
			'admin' => $isAdmin ? 'true' : 'false',
			'moderator' => $isModerator ? 'true' : 'false',
			'groups' => implode( ',', $groups ),
			'suppress_welcome_message' => $config->get( 'DiscourseSuppressWelcomeMessage' ) ? 'true' : 'false',
		];
	}

	private function getLoginUrlFromPayload( Config $config, string $returnUrl, array $payload ): string {
		$encodedPayload = base64_encode( http_build_query( $payload ) );
		$signature = hash_hmac( 'sha256', $encodedPayload, $config->get( 'DiscourseConnectSecret' ) );
		return $returnUrl . '?' . http_build_query( [
			'sso' => $encodedPayload,
			'sig' => $signature,
		] );
	}

	/** @inheritDoc */
	public function execute( $subpage ): void {
		parent::execute( $subpage );
		$config = $this->getConfig();
		$user = $this->getUser();
		$this->validateUser( $user );
		$payload = $this->validatePayload( $config );
		$loginPayload = $this->getLoginPayload( $config, $user, $payload );
		$returnUrl = $payload['return_sso_url'];
		$loginUrl = $this->getLoginUrlFromPayload( $config, $returnUrl, $loginPayload );
		$this->getOutput()->redirect( $loginUrl );
	}
}
