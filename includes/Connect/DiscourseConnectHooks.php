<?php

namespace MediaWiki\Extension\Discourse\Connect;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\ExtensionConfig;
use MediaWiki\Hook\PrefsEmailAuditHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\RenameUser\Hook\RenameUserCompleteHook;
use MediaWiki\User\Hook\ConfirmEmailCompleteHook;
use MediaWiki\User\Hook\UserGroupsChangedHook;
use MediaWiki\User\Hook\InvalidateEmailCompleteHook;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;

class DiscourseConnectHooks implements
	RenameUserCompleteHook,
	UserGroupsChangedHook,
	ConfirmEmailCompleteHook,
	InvalidateEmailCompleteHook,
	PrefsEmailAuditHook
{
	private LoggerInterface $logger;
	public function __construct(
		private readonly UserFactory $userFactory,
		private readonly DiscourseConnectPayloadGenerator $payloadGenerator,
		private readonly DiscourseAPIService $api,
		private readonly ExtensionConfig $config,
	) {
		$this->logger = LoggerFactory::getInstance( ExtensionConfig::LOG_CHANNEL );
	}

	private function syncDiscourseSso( UserIdentity $userIdentity ): void {
		$user = $this->userFactory->newFromUserIdentity( $userIdentity );
		$payload = $this->payloadGenerator->getPayload( $user );
		$ssoPayload = base64_encode( http_build_query( $payload ) );
		$secret = $this->config->getConnectSecret();
		try {
			$this->api->makeRequest( '/admin/users/sync_sso', 'POST', [
				'form_params' => [
					'sso' => $ssoPayload,
					'sig' => hash_hmac( 'sha256', $ssoPayload, $secret ),
				],
			] );
		} catch ( ClientException|GuzzleException $ex ) {
			$this->logger->error( 'Error while syncing Discourse SSO data', [
				'username' => $user->getName(),
				'exception' => $ex,
			] );
		}
	}

	/** @inheritDoc */
	public function onRenameUserComplete( int $uid, string $old, string $new ): void {
		if ( !$this->config->isConnectEnabled() ) {
			return;
		}
		$user = $this->userFactory->newFromId( $uid );
		$this->syncDiscourseSso( $user );
	}

	/** @inheritDoc */
	public function onUserGroupsChanged( $user, $added, $removed, $performer, $reason, $oldUGMs, $newUGMs ) {
		if ( !$this->config->isConnectEnabled() ) {
			return;
		}
		$this->syncDiscourseSso( $user );
	}

	/** @inheritDoc */
	public function onConfirmEmailComplete( $user ) {
		if ( !$this->config->isConnectEnabled() ) {
			return;
		}
		$this->syncDiscourseSso( $user );
	}

	/** @inheritDoc */
	public function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {
		if ( !$this->config->isConnectEnabled() ) {
			return;
		}
		$this->syncDiscourseSso( $user );
	}

	/** @inheritDoc */
	public function onInvalidateEmailComplete( $user ) {
		if ( !$this->config->isConnectEnabled() ) {
			return;
		}
		$this->syncDiscourseSso( $user );
	}
}
