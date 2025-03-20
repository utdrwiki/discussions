<?php

namespace MediaWiki\Extension\Discourse\Profile;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Http\HttpRequestFactory;
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
	private HttpRequestFactory $httpRequestFactory;
	private WANObjectCache $cache;
	private LoggerInterface $logger;

	public function __construct(
		UserFactory $userFactory,
		UserGroupManager $userGroupManager,
		HttpRequestFactory $httpRequestFactory,
		WANObjectCache $cache,
		LoggerInterface $logger,
	) {
		$this->userFactory = $userFactory;
		$this->userGroupManager = $userGroupManager;
		$this->httpRequestFactory = $httpRequestFactory;
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

	private function validateConfiguration( Config $config ): void {
		if ( $config->get( 'DiscourseBaseUrl' ) === false ) {
			throw new LogicException( '$wgDiscourseBaseUrl must be set' );
		}
		if ( $config->get( 'DiscourseApiKey' ) === false ) {
			throw new LogicException( '$wgDiscourseApiKey must be set' );
		}
	}

	private function getProfileData( User $user, Config $config ): ?array {
		$username = $user->getName();
		$baseUrl = $config->get( 'DiscourseBaseUrl' );
		$requestUrl = $config->get( 'DiscourseBaseUrlInternal', $baseUrl );
		$defaultAvatarColor = $config->get( 'DiscourseDefaultAvatarColor' );

		$requestOptions = [
			'headers' => [
				'Api-Key' => $config->get( 'DiscourseApiKey' ),
				'Api-Username' => $config->get( 'DiscourseApiUsername' ),
			],
		];
		$unixSocket = $config->get( 'DiscourseUnixSocket' );
		if ( $unixSocket !== null ) {
			$requestOptions['curl'] = [
				CURLOPT_UNIX_SOCKET_PATH => $unixSocket
			];
		}
		$url = "$requestUrl/users/by-external/{$user->getId()}.json";

		$client = $this->httpRequestFactory->createGuzzleClient();
		try {
			$response = $client->get( $url, $requestOptions );
			$data = json_decode( $response->getBody()->getContents(), true );
			return [
				'avatar' => str_replace( '{size}', '144', $data['user']['avatar_template'] ),
				'bio' => $data['user']['bio_cooked'] ?? '',
				'name' => $data['user']['name'] ?? '',
				'posts' => $data['user']['post_count'],
				'postsUrl' => "$baseUrl/u/$username/activity",
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
			$this->cache->makeKey( 'DiscourseProfile', $user->getId() ),
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

	private function makeTabs( User $user, array $profileData, OutputPage $out ): string {
		$links = [
			'user' => $user->getUserPage()->getFullURL(),
			'contributions' => SpecialPage::getTitleFor( 'Contributions', $user->getName() )->getFullURL(),
		];
		if ( $profileData['postsUrl'] !== null ) {
			$links['posts'] = $profileData['postsUrl'];
		}
		return $this->makeLinkList( $links, 'discourse-profile-tabs', $out );
	}

	private function makeProfile( User $user, array $profileData, OutputPage $out ): string {
		$header = $this->makeProfileHeader( $user, $profileData, $out );
		$avatar = $this->makeAvatar( $user, $profileData, $out );
		$stats = $this->makeStats( $user, $profileData, $out );
		$bio = $this->makeBio( $profileData );
		$tabs = $this->makeTabs( $user, $profileData, $out );
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile'
		], "$header$avatar$stats$bio$tabs" );
	}

	private function makeNoProfileError( OutputPage $out ): string {
		return Html::errorBox( $out->msg( 'discourse-profile-error' ) );
	}

	public function render( string $username, RequestContext $context ): void {
		$user = $this->userFactory->newFromName( $username );
		if ( !$user->isNamed() ) {
			return;
		}
		$out = $context->getOutput();
		$config = $context->getConfig();
		$this->validateConfiguration( $config );

		$profileData = $this->getProfileDataCached( $user, $config );
		if ( $profileData === null ) {
			$out->addHTML( $this->makeNoProfileError( $out ) );
			return;
		}

		$out->addHTML( $this->makeProfile( $user, $profileData, $out ) );
		$out->addModules( 'ext.discourse.profile.scripts' );
		$out->addModuleStyles( 'ext.discourse.profile.styles' );
	}
}
