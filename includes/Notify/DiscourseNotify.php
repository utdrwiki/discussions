<?php

namespace MediaWiki\Extension\Discourse\Notify;

use DateTime;
use DateTimeZone;
use MediaWiki\Api\ApiBase;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;

class DiscourseNotify extends ApiBase {
	private LoggerInterface $logger;
	private UserFactory $userFactory;
	private WANObjectCache $cache;
	private ExtensionRegistry $extensionRegistry;

	private const DISCOURSE_NOTIFICATION_MENTIONED = 1;
	private const DISCOURSE_NOTIFICATION_REPLIED = 2;
	private const DISCOURSE_NOTIFICATION_QUOTED = 3;
	private const DISCOURSE_NOTIFICATION_EDITED = 4;
	private const DISCOURSE_NOTIFICATION_LIKED = 5;
	private const DISCOURSE_NOTIFICATION_PRIVATE_MESSAGE = 6;
	private const DISCOURSE_NOTIFICATION_INVITED_TO_PRIVATE_MESSAGE = 7;
	private const DISCOURSE_NOTIFICATION_INVITEE_ACCEPTED = 8;
	private const DISCOURSE_NOTIFICATION_POSTED = 9;
	private const DISCOURSE_NOTIFICATION_MOVED_POST = 10;
	private const DISCOURSE_NOTIFICATION_GROUP_MENTIONED = 15;
	private const DISCOURSE_NOTIFICATION_WATCHING_FIRST_POST = 17;
	private const DISCOURSE_NOTIFICATION_REACTION = 25;

	public function __construct(
		$query,
		$moduleName,
		UserFactory $userFactory,
		WANObjectCache $cache,
		ExtensionRegistry $extensionRegistry,
	) {
		$this->logger = LoggerFactory::getInstance( 'Discourse' );
		$this->userFactory = $userFactory;
		$this->cache = $cache;
		$this->extensionRegistry = $extensionRegistry;
		parent::__construct( $query, $moduleName );
	}

	public function execute() {
		$params = $this->extractRequestParams();
		// TODO: Deduplicate with Special:DiscourseConnect.
		$secret = $this->getConfig()->get( 'DiscourseConnectSecret' );
		if ( $secret === false ) {
			$this->dieWithError( 'discourse-connect-missing-secret' );
		}
		$payload = $params['payload'];
		$signature = $params['signature'];
		$hmac = hash_hmac( 'sha256', $payload, $secret );
		if ( !hash_equals( $hmac, $signature ) ) {
			$this->dieWithError( 'discourse-connect-invalid-signature' );
		}
		$decodedPayload = base64_decode( $payload, true );
		if ( $decodedPayload === false ) {
			$this->dieWithError( 'discourse-connect-invalid-payload' );
		}
		$payloadParams = json_decode( $decodedPayload, true );
		$timestamp = $payloadParams['timestamp'] ?? null;
		if ( $timestamp === null ) {
			$this->dieWithError( 'discourse-connect-invalid-timestamp' );
		}
		$utc = new DateTimeZone( 'UTC' );
		$tsUtc = ( new DateTime( $timestamp, $utc ) )->getTimestamp();
		$nowUtc = ( new DateTime( 'now', $utc ) )->getTimestamp();
		if ( abs( $nowUtc - $tsUtc ) > 5 * 60 ) {
			$this->dieWithError( 'discourse-notify-invalid-timestamp' );
		}
		switch ( $payloadParams['event_type'] ) {
			case 'notification':
				$this->notifyUser( $payloadParams );
				break;
			case 'user_updated':
				$this->purgeUser( $payloadParams );
				break;
			default:
				$this->dieWithError( 'discourse-notify-invalid-type' );
		}
	}

	private function notifyUser( array $args ): void {
		$this->logger->debug( 'Received notification from Discourse', $args );
		if ( !$this->extensionRegistry->isLoaded( 'Echo' ) ) {
			return;
		}
		$eventType = $this->getEventType( $args['notification_type'] );
		if ( $eventType === null ) {
			return;
		}
		$user = $this->userFactory->newFromId( intval( $args['user_id'] ) );
		$baseUrl = $this->getConfig()->get( 'DiscourseBaseUrl' );
		$actorUrl = "$baseUrl/u/{$args['actor_username']}";
		$topicUrl = "$baseUrl/t/-/{$args['topic_id']}";
		$postUrl = $topicUrl;
		if ( !empty( $args['post_number'] ) ) {
			$postUrl .= "/{$args['post_number']}";
		}
		Event::create( [
			'type' => 'discourse',
			'agent' => $user,
			'extra' => [
				'event-type' => $eventType,
				'topic' => $args['topic_title'],
				'topic-url' => $topicUrl,
				'user' => $args['actor_username'],
				'user-url' => $actorUrl,
				'url' => $postUrl,
			],
		] );
	}

	private function getEventType( int $notificationType ): ?string {
		switch ( $notificationType ) {
			case self::DISCOURSE_NOTIFICATION_MENTIONED:
				return 'mentioned';
			case self::DISCOURSE_NOTIFICATION_REPLIED:
				return 'replied';
			case self::DISCOURSE_NOTIFICATION_QUOTED:
				return 'quoted';
			case self::DISCOURSE_NOTIFICATION_EDITED:
				return 'edited';
			case self::DISCOURSE_NOTIFICATION_LIKED:
				return 'liked';
			case self::DISCOURSE_NOTIFICATION_PRIVATE_MESSAGE:
				return 'private-message';
			case self::DISCOURSE_NOTIFICATION_INVITED_TO_PRIVATE_MESSAGE:
				return 'private-message-invite';
			case self::DISCOURSE_NOTIFICATION_INVITEE_ACCEPTED:
				return 'private-message-accept';
			case self::DISCOURSE_NOTIFICATION_POSTED:
				return 'posted';
			case self::DISCOURSE_NOTIFICATION_MOVED_POST:
				return 'moved';
			case self::DISCOURSE_NOTIFICATION_GROUP_MENTIONED:
				return 'mentioned-group';
			case self::DISCOURSE_NOTIFICATION_WATCHING_FIRST_POST:
				return 'posted';
			case self::DISCOURSE_NOTIFICATION_REACTION:
				return 'reacted';
			default:
				return null;
		}
	}

	private function purgeUser( array $args ): void {
		// It's cheaper to clear cache without looking up the user.
		$cacheKey = $this->cache->makeGlobalKey( 'DiscourseProfile', $args['user_id'] );
		$this->cache->delete( $cacheKey );
		$this->logger->debug( "Purged profile of user {$args['user_id']}" );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	protected function getAllowedParams() {
		return [
			'payload' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
			'signature' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true
			],
		];
	}
}
