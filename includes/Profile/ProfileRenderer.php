<?php

namespace MediaWiki\Extension\Discourse\Profile;

use LogicException;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;
use Wikimedia\ObjectCache\WANObjectCache;

class ProfileRenderer {
	private User $user;
	private array $groups;
	private RequestContext $context;
	private HttpRequestFactory $httpRequestFactory;
	private WANObjectCache $cache;

	public function __construct( string $username, RequestContext $context ) {
		$services = MediaWikiServices::getInstance();
		$userFactory = $services->getUserFactory();
		$userGroupManager = $services->getUserGroupManager();
		$this->user = $userFactory->newFromName( $username );
		$this->context = $context;
		$this->groups = $userGroupManager->getUserGroups( $this->user );
		$this->httpRequestFactory = $services->getHttpRequestFactory();
		$this->cache = $services->getMainWANObjectCache();
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

	private function getProfileData( Config $config ): ?array {
		$username = $this->user->getName();
		$baseUrl = $config->get( 'DiscourseBaseUrl' );
		$apiKey = $config->get( 'DiscourseApiKey' );
		$apiUsername = $config->get( 'DiscourseApiUsername' );
		$defaultAvatarColor = $config->get( 'DiscourseDefaultAvatarColor' );
		$req = $this->httpRequestFactory->create( "$baseUrl/users/by-external/{$this->user->getId()}.json" );
		$req->setHeader( 'Api-Key', $apiKey );
		$req->setHeader( 'Api-Username', $apiUsername );
		$status = $req->execute();
		if ( $req->getStatus() === 404 ) {
			return [
				'avatar' => "$baseUrl/letter_avatar_proxy/v4/letter/{$username[0]}/$defaultAvatarColor/144.png",
				'bio' => '',
				'name' => '',
				'posts' => 0,
				'postsUrl' => null,
				'website' => '',
			];
		} else if ( !$status->isOK() ) {
			return null;
		}
		$data = json_decode( $req->getContent(), true );
		return [
			'avatar' => str_replace( '{size}', '144', $data['user']['avatar_template'] ),
			'bio' => $data['user']['bio_cooked'] ?? '',
			'name' => $data['user']['name'] ?? '',
			'posts' => $data['user']['post_count'],
			'postsUrl' => "$baseUrl/u/$username/activity",
			'website' => $data['user']['website'] ?? '',
		];
	}

	private function getProfileDataCached( Config $config ): ?array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'DiscourseProfile', $this->user->getId() ),
			$this->cache::TTL_HOUR,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $config ) {
				$profileData = $this->getProfileData( $config );
				if ( $profileData === null ) {
					$ttl = $this->cache::TTL_MINUTE;
				}
				return $profileData;
			}
		);
	}

	private function makeProfileHeader( array $profileData, OutputPage $out ): string {
		$profileTitle = Html::element( 'h1', [
			'class' => 'discourse-profile-username'
		], $this->user->getName() );
		if ( $profileData['name'] !== '' ) {
			$profileTitle .= Html::element( 'span', [
				'class' => 'discourse-profile-name'
			], $out->msg( 'discourse-profile-name', $profileData['name'] )->text() );
		}
		$tags = [];
		foreach ( $this->groups as $group ) {
			$tags[] = Html::element( 'span', [
				'class' => "discourse-profile-group discourse-profile-group-$group"
			], $out->msg( "group-$group-member" )->text() );
		}
		if ( $this->user->getBlock() !== null ) {
			$tags[] = Html::element( 'span', [
				'class' => 'discourse-profile-group discourse-profile-blocked'
			], $out->msg( 'discourse-profile-blocked' )->text() );
		}
		$tagsString = implode( $tags );
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile-header'
		], "$profileTitle$tagsString" );
	}

	private function makeAvatar( array $profileData, OutputPage $out ): string {
		return Html::element( 'img', [
            'alt' => $out->msg( 'discourse-profile-avatar-alt', $this->user->getName() )->text(),
			'class' => 'discourse-profile-avatar',
			'src' => $profileData['avatar']
		] );
	}

	private function makeStats( array $profileData, OutputPage $out ): string {
		$stats = [
			Html::element( 'li', [
				'class' => 'discourse-profile-edits'
			], $out->msg( 'discourse-profile-edits', $this->user->getEditCount() )->text() ),
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

	private function makeTabs( array $profileData, OutputPage $out ): string {
		$links = [
			'user' => $this->user->getUserPage()->getFullURL(),
			'contributions' => SpecialPage::getTitleFor( 'Contributions', $this->user->getName() )->getFullURL(),
		];
		if ( $profileData['postsUrl'] !== null ) {
			$links['posts'] = $profileData['postsUrl'];
		}
		return $this->makeLinkList( $links, 'discourse-profile-tabs', $out );
	}

	private function makeProfile( array $profileData, OutputPage $out ): string {
		$header = $this->makeProfileHeader( $profileData, $out );
		$avatar = $this->makeAvatar( $profileData, $out );
		$stats = $this->makeStats( $profileData, $out );
		$bio = $this->makeBio( $profileData );
		$tabs = $this->makeTabs( $profileData, $out );
		return Html::rawElement( 'div', [
			'class' => 'discourse-profile'
		], "$header$avatar$stats$bio$tabs" );
	}

	private function makeNoProfileError( OutputPage $out ): string {
		return Html::element( 'div', [
			'class' => 'error'
		], $out->msg( 'discourse-profile-error' )->text() );
	}

	public function render(): void {
		$out = $this->context->getOutput();
		$config = $this->context->getConfig();
		$this->validateConfiguration( $config );

		$profileData = $this->getProfileDataCached( $config );
		if ( $profileData === null ) {
			$out->addHTML( $this->makeNoProfileError( $out ) );
			return;
		}

		$out->addHTML( $this->makeProfile( $profileData, $out ) );
		$out->addModules( 'ext.discourse.profile.scripts' );
		$out->addModuleStyles( 'ext.discourse.profile.styles' );
	}
}
