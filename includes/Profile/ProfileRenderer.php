<?php

namespace MediaWiki\Extension\Discourse\Profile;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\ExtensionConfig;
use MediaWiki\FileRepo\FileRepo;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;


class ProfileRenderer {
	public const SERVICE_NAME = 'DiscourseProfileRenderer';
	public const CACHE_KEY_PREFIX = 'DiscourseProfile';
	public function __construct(
		private readonly UserGroupManager $userGroupManager,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly DiscourseAPIService $api,
		private readonly ExtensionConfig $config,
		private readonly WANObjectCache $cache,
		private readonly LoggerInterface $logger,
		private readonly FileRepo $localRepo,
	) {
	}

	public static function makeCacheKey( WANObjectCache $cache, int|string $userId ): string {
		return $cache->makeGlobalKey( self::CACHE_KEY_PREFIX, $userId );
	}

	private function makeLinkList( array $links, string $class, OutputPage $output ): string {
		$list = [];
		foreach ( $links as $linkId => $link ) {
			$text = $output->msg( "discourse-profile-link-$linkId" )->text();
			$link = Html::element( 'a', [ 'href' => $link ], $text );
			$list[] = Html::rawElement( 'li', [
				'class' => "discourse-profile-link discourse-profile-$linkId"
			], $link );
		}
		return Html::rawElement( 'ul', [
			'class' => $class
		], implode( $list ) );
	}

	private function getDefaultProfileAvatar( User $user ): string {
		$username = $user->getName();
		$baseUrl = $this->config->getBaseUrl();
		$defaultAvatarColor = $this->config->getDefaultAvatarColor();
		$firstLetter = mb_substr( $username, 0, 1 );

		return "$baseUrl/letter_avatar_proxy/v4/letter/$firstLetter/$defaultAvatarColor/144.png";
	}

	private function getProfileData( User $user ): ?array {
		$username = $user->getName();
		try {
			$data = $this->api->makeRequest( "/users/by-external/{$user->getId()}.json" );;
			$discourseUsername = urlencode($data['user']['username']);
			return [
				'avatar' => str_replace( '{size}', '144', $data['user']['avatar_template'] ),
				'bio' => $data['user']['bio_cooked'] ?? '',
				'name' => $data['user']['name'] ?? '',
				'posts' => $data['user']['post_count'],
				'postsUrl' => "{$this->config->getBaseUrl()}/u/$discourseUsername/activity",
				'website' => $data['user']['website'] ?? '',
				'badges' => $data['badges'] ?? [],
			];
		} catch ( ClientException $ex ) {
			$response = $ex->getResponse();
			if ( $response->getStatusCode() === 404 ) {
				// User still hasn't logged into Discourse.
				return [
					'avatar' => $this->getDefaultProfileAvatar( $user ),
					'bio' => '',
					'name' => '',
					'posts' => 0,
					'postsUrl' => null,
					'website' => '',
					'badges' => [],
				];
			}
			$this->logger->error( 'Client error when retrieving profile data', [
				'username' => $username,
				'exception' => $ex,
				'statusCode' => $response->getStatusCode(),
				'responseBody' => $response->getBody()->getContents(),
			] );
			return null;
		} catch ( GuzzleException $ex ) {
			$this->logger->error( 'Guzzle error when retrieving profile data', [
				'username' => $username,
				'exception' => $ex,
			] );
			return null;
		}
	}

	private function getProfileDataCached( User $user ): ?array {
		return $this->cache->getWithSetCallback(
			self::makeCacheKey( $this->cache, $user->getId() ),
			$this->cache::TTL_HOUR,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $user ) {
				$profileData = $this->getProfileData( $user );
				if ( $profileData === null ) {
					$ttl = $this->cache::TTL_MINUTE;
				}
				return $profileData;
			}
		);
	}

	private function getUnregisteredAvatar( User $user, OutputPage $out ): string {
		$avatarText = $out->msg( 'Profile-unregistered-avatars' )->inContentLanguage()->plain();

		if ( !$avatarText ) {
			return $this->getDefaultProfileAvatar( $user );
		}

		$avatarTextLines = explode( "\n", trim( $avatarText ) );

		$userHash = md5( $user->getName() );
		// 4 bytes seems like plenty
		$avatarIndex = hexdec( substr( $userHash, 0, 8 ) ) % sizeof( $avatarTextLines );
		$avatarTextLine = $avatarTextLines[$avatarIndex];

		$matches = [];
		preg_match( '/^[#*]?\s*(?:\[\[\s*:?\s*File:\s*)?([^\]|]*)/m', $avatarTextLine, $matches );
		$avatarFileName = $matches[1];

		if ( !$avatarFileName ) {
			return $this->getDefaultProfileAvatar( $user );
		}

		$file = $this->localRepo->findFile( $avatarFileName );
		if ( !$file ) {
			return $this->getDefaultProfileAvatar( $user );
		}

		return $file->getFullURL();
	}

	private function makeBadge( array $badgeData ): string {
		$baseUrl = $this->config->getBaseUrl();

		$badgeImg = Html::rawElement( 'img', [
			'class' => 'discourse-profile-badge-img',
			'src' => $badgeData['image_url'],
			'alt' => $badgeData['name'],
			'height' => '25'
		] );

		$badgeId = $badgeData['id'];
		$badgeSlug = $badgeData['slug'];

		$badgeLink = Html::rawElement( 'a', [
			'class' => 'discourse-profile-badge-link',
			'href' => "$baseUrl/badges/$badgeId/$badgeSlug",
			'title' => $badgeData['name'],
		], $badgeImg );

		return Html::rawElement( 'span', [
			'class' => 'discourse-profile-badge',
		], $badgeLink );
	}

	private function makeProfileHeader( User $user, ?array $profileData, OutputPage $out ): string {
		$username = $this->userOptionsLookup->getBoolOption( $user, 'discourse-lowercase-username' ) ?
			$out->getLanguage()->lcfirst( $user->getName() ) :
			$user->getName();
		$profileTitle = Html::element( 'h1', [
			'class' => 'discourse-profile-username'
		], $username );

		if ( $profileData === null ) {
			return Html::rawElement( 'div', [
				'class' => 'discourse-profile-header'
			], $profileTitle );
		}

		if ( $profileData['name'] !== '' ) {
			$profileTitle .= Html::element( 'span', [
				'class' => 'discourse-profile-name'
			], $out->msg( 'discourse-profile-name', $profileData['name'] )->text() );
		}
		$tags = [];
		foreach ( $this->userGroupManager->getUserGroups( $user ) as $group ) {
			$tags[] = Html::element( 'span', [
				'class' => "discourse-profile-group discourse-profile-group-$group"
			], $out->msg( "group-$group-member" )->text() );
		}
		if ( $user->getBlock() !== null ) {
			$tags[] = Html::element( 'span', [
				'class' => 'discourse-profile-group discourse-profile-blocked'
			], $out->msg( 'discourse-profile-blocked' )->text() );
		}
		$tagsString = implode( $tags );

		$badges = [];
		foreach ( $profileData['badges'] as $badge ) {
			if (!$badge['enabled'] || !$badge['image_url']) {
				continue;
			}
			$badges[] = $this->makeBadge( $badge );
		}
		$badgeList = sizeof($badges) > 0 ? Html::rawElement('div', [
			'class' => 'discourse-profile-badges',
		], implode( $badges ) ) : '';

		return Html::rawElement( 'div', [
			'class' => 'discourse-profile-header'
		], "$profileTitle$tagsString$badgeList" );
	}

	private function makeAvatar( User $user, ?array $profileData, OutputPage $out ): string {
		$imgSrc = $profileData ? $profileData['avatar'] : $this->getUnregisteredAvatar( $user, $out );

		return Html::element( 'img', [
			'alt' => $out->msg( 'discourse-profile-avatar-alt', $user->getName() )->text(),
			'class' => 'discourse-profile-avatar' . ( $profileData === '' ? '' : ' discourse-unregistered-profile-avatar' ),
			'src' => $imgSrc
		] );
	}

	private function makeStats( User $user, ?array $profileData, OutputPage $out ): string {
		$stats = [
			Html::element( 'li', [
				'class' => 'discourse-profile-edits'
			], $out->msg( 'discourse-profile-edits', $user->getEditCount() )->text() ),
		];

		if ($profileData !== null) {
			$stats[] = Html::element( 'li', [
				'class' => 'discourse-profile-posts'
			], $out->msg( 'discourse-profile-posts', $profileData['posts'] )->text() );
		}


		return Html::rawElement( 'ul', [
			'class' => 'discourse-profile-stats'
		], implode( $stats ) );
	}

	private function makeBio( ?array $profileData, OutputPage $out ): string {
		if (!$profileData) {
			$icon = Html::element('span', [
				'class' => 'cdx-message__icon',
			] );

			$content = Html::rawElement('div', [
				'class' => 'cdx-message__content',
			], $out->msg('discourse-profile-unregistered-bio') );

			$message = Html::rawElement('div', [
				'class' => 'cdx-message dx-message--block cdx-message--warning',
			], "$icon$content" );

			return Html::rawElement( 'div', [
				'class' => 'discourse-profile-bio'
			], $message );
		}


		// SECURITY: we are taking bio_cooked from Discourse, assuming Discourse
		// properly parsed the Markdown for the bio.
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile-bio'
		], $profileData['bio'] );
	}

	private function makeEditButton( ?array $profileData, OutputPage $out ): string {
		if ( !$profileData ) {
			return "";
		}

		$icon = Html::element('span', [
			'class' => 'vector-icon mw-ui-icon-wikimedia-edit mw-ui-icon-wikimedia-wikimedia-edit'
		]);
		$spanText = $out->msg('discourse-profile-edit-button')->text();
		$span = Html::rawElement('span', [
			'class' => 'cdx-button'
		],  "$icon$spanText" );
		$link = Html::rawElement('a', [
			'href' => "{$this->config->getBaseUrl()}/my/preferences/profile",
		], $span );

		return Html::rawElement('div', [
			'class' => 'discourse-profile-edit-button'
		], $link );
	}

	private function makeTabs( User $user, ?array $profileData, OutputPage $out ): string {
		$links = [
			'user' => $user->getUserPage()->getFullURL(),
			'talk' => $user->getTalkPage()->getFullURL(),
			'contributions' => SpecialPage::getTitleFor( 'Contributions', $user->getName() )->getFullURL(),
		];
		if ( $profileData !== null && $profileData['postsUrl'] !== null ) {
			$links['posts'] = $profileData['postsUrl'];
		}
		return $this->makeLinkList( $links, 'discourse-profile-tabs', $out );
	}

	private function makeProfile( User $user, ?array $profileData, OutputPage $out ): string {
		$header = $this->makeProfileHeader( $user, $profileData, $out );
		$avatar = $this->makeAvatar( $user, $profileData, $out );
		$stats = $this->makeStats( $user, $profileData, $out );
		$bio = $this->makeBio( $profileData, $out );
		$tabs = $this->makeTabs( $user, $profileData, $out );
		$edit = $user->getId() === $out->getUser()->getId() ?
			$this->makeEditButton( $profileData, $out ) :
			'';
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile'
		], "$header$avatar$stats$bio$edit$tabs" );
	}

	private function makeNoProfileError( OutputPage $out ): string {
		return Html::errorBox( $out->msg( 'discourse-profile-error' ) );
	}

	public function render( User $user, RequestContext $context ): void {
		$out = $context->getOutput();
		$profileData = null;

		if ( $user->isNamed() ) {
			$profileData = $this->getProfileDataCached( $user );

			if ( $profileData === null ) {
				$out->addHTML( $this->makeNoProfileError( $out ) );
				return;
			}
		}

		$out->addHTML( $this->makeProfile( $user, $profileData, $out ) );
		$out->addModules( 'ext.discourse.profile.scripts' );
		$out->addModuleStyles( 'ext.discourse.profile.styles' );
	}
}
