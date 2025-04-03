<?php

namespace MediaWiki\Extension\Discourse\Profile;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\WANObjectCache;

class ProfileRenderer {
	private UserFactory $userFactory;
	private UserGroupManager $userGroupManager;
	private DiscourseAPIService $api;
	private WANObjectCache $cache;
	private LoggerInterface $logger;

	public function __construct(
		UserFactory $userFactory,
		UserGroupManager $userGroupManager,
		DiscourseAPIService $api,
		WANObjectCache $cache,
		LoggerInterface $logger,
	) {
		$this->userFactory = $userFactory;
		$this->userGroupManager = $userGroupManager;
		$this->api = $api;
		$this->cache = $cache;
		$this->logger = $logger;
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

	private function getProfileData( User $user, Config $config ): ?array {
		$username = $user->getName();
		$baseUrl = $config->get( 'DiscourseBaseUrl' );
		$defaultAvatarColor = $config->get( 'DiscourseDefaultAvatarColor' );
		try {
			$data = $this->api->makeRequest( "/users/by-external/{$user->getId()}.json" );;
			$discourseUsername = urlencode($data['user']['username']);
			return [
				'avatar' => str_replace( '{size}', '144', $data['user']['avatar_template'] ),
				'bio' => $data['user']['bio_cooked'] ?? '',
				'name' => $data['user']['name'] ?? '',
				'posts' => $data['user']['post_count'],
				'postsUrl' => "$baseUrl/u/$discourseUsername/activity",
				'website' => $data['user']['website'] ?? '',
			];
		} catch ( ClientException $ex ) {
			$response = $ex->getResponse();
			if ( $response->getStatusCode() === 404 ) {
				// User still hasn't logged into Discourse.
				return [
					'avatar' => "$baseUrl/letter_avatar_proxy/v4/letter/{$username[0]}/$defaultAvatarColor/144.png",
					'bio' => '',
					'name' => '',
					'posts' => 0,
					'postsUrl' => null,
					'website' => '',
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

	private function getProfileDataCached( User $user, Config $config ): ?array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeGlobalKey( 'DiscourseProfile', $user->getId() ),
			$this->cache::TTL_HOUR,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $user, $config ) {
				$profileData = $this->getProfileData( $user, $config );
				if ( $profileData === null ) {
					$ttl = $this->cache::TTL_MINUTE;
				}
				return $profileData;
			}
		);
	}

	private function makeProfileHeader( User $user, array $profileData, OutputPage $out ): string {
		$profileTitle = Html::element( 'h1', [
			'class' => 'discourse-profile-username'
		], $user->getName() );
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
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile-header'
		], "$profileTitle$tagsString" );
	}

	private function makeAvatar( User $user, array $profileData, OutputPage $out ): string {
		return Html::element( 'img', [
            'alt' => $out->msg( 'discourse-profile-avatar-alt', $user->getName() )->text(),
			'class' => 'discourse-profile-avatar',
			'src' => $profileData['avatar']
		] );
	}

	private function makeStats( User $user, array $profileData, OutputPage $out ): string {
		$stats = [
			Html::element( 'li', [
				'class' => 'discourse-profile-edits'
			], $out->msg( 'discourse-profile-edits', $user->getEditCount() )->text() ),
			Html::element( 'li', [
				'class' => 'discourse-profile-posts'
			], $out->msg( 'discourse-profile-posts', $profileData['posts'] )->text() ),
		];
		return Html::rawElement( 'ul', [
			'class' => 'discourse-profile-stats'
		], implode( $stats ) );
	}

	private function makeBio( array $profileData ): string {
		// SECURITY: we are taking bio_cooked from Discourse, assuming Discourse
		// properly parsed the Markdown for the bio.
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile-bio'
		], $profileData['bio'] );
	}

	private function makeEditButton( array $profileData, OutputPage $out, string $baseUrl ): string {
		$icon = Html::element('span', [
			'class' => 'vector-icon mw-ui-icon-wikimedia-edit mw-ui-icon-wikimedia-wikimedia-edit'
		]);
		$spanText = $out->msg('discourse-profile-edit-button')->text();
		$span = Html::rawElement('span', [
			'class' => 'cdx-button'
		],  "$icon$spanText");
		$link = Html::rawElement('a', [
			'href' => "$baseUrl/my/preferences/profile",
		], $span);

		return Html::rawElement('div', [
			'class' => 'discourse-profile-edit-button'
		], $link);
	}

	private function makeTabs( User $user, array $profileData, OutputPage $out ): string {
		$links = [
			'user' => $user->getUserPage()->getFullURL(),
			'talk' => $user->getTalkPage()->getFullURL(),
			'contributions' => SpecialPage::getTitleFor( 'Contributions', $user->getName() )->getFullURL(),
		];
		if ( $profileData['postsUrl'] !== null ) {
			$links['posts'] = $profileData['postsUrl'];
		}
		return $this->makeLinkList( $links, 'discourse-profile-tabs', $out );
	}

	private function makeProfile( User $user, array $profileData, OutputPage $out, string $baseUrl ): string {
		$header = $this->makeProfileHeader( $user, $profileData, $out );
		$avatar = $this->makeAvatar( $user, $profileData, $out );
		$stats = $this->makeStats( $user, $profileData, $out );
		$bio = $this->makeBio( $profileData );
		$tabs = $this->makeTabs( $user, $profileData, $out );
		$edit = $user->getId() === $out->getUser()->getId() ? $this->makeEditButton( $profileData, $out, $baseUrl ) : "";
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile'
		], "$header$avatar$stats$bio$edit$tabs" );
	}

	private function makeNoProfileError( OutputPage $out ): string {
		return Html::errorBox( $out->msg( 'discourse-profile-error' ) );
	}

	public function render( string $username, RequestContext $context ): void {
		$this->api->throwIfConfigInvalid();
		$user = $this->userFactory->newFromName( $username );
		if ( !$user->isNamed() ) {
			return;
		}
		$out = $context->getOutput();
		$config = $context->getConfig();

		$profileData = $this->getProfileDataCached( $user, $config );
		if ( $profileData === null ) {
			$out->addHTML( $this->makeNoProfileError( $out ) );
			return;
		}

		$baseUrl = $config->get( 'DiscourseBaseUrl' );
		$out->addHTML( $this->makeProfile( $user, $profileData, $out, $baseUrl ) );
		$out->addModules( 'ext.discourse.profile.scripts' );
		$out->addModuleStyles( 'ext.discourse.profile.styles' );
	}
}
