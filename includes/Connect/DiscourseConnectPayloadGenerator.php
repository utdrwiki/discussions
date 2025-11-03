<?php

namespace MediaWiki\Extension\Discourse\Connect;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Discourse\ExtensionConfig;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManager;

class DiscourseConnectPayloadGenerator {
	public const SERVICE_NAME = 'DiscourseConnectPayloadGenerator';
	public function __construct(
		private readonly PermissionManager $permissionManager,
		private readonly UserGroupManager $userGroupManager,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly ExtensionConfig $config,
	) {}

	private function getDiscourseGroups( User $user ): array {
		$groupMap = $this->config->getGroupMap();
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

	public function getPayload( User $user ): array {
		$isAdmin = $this->permissionManager->userHasRight( $user, 'discourse-admin' );
		$isModerator = $this->permissionManager->userHasRight( $user, 'discourse-moderator' );
		$email = $user->isEmailConfirmed() ?
			$user->getEmail() :
			"{$user->getId()}@mediawiki.invalid";
		$groups = $this->getDiscourseGroups( $user );
		$isLowercase = $this->userOptionsLookup->getBoolOption( $user, 'discourse-lowercase-username' );
		$context = RequestContext::getMain();
		return [
			'email' => $email,
			'external_id' => $user->getId(),
			'username' =>  $isLowercase ?
				$context->getLanguage()->lcfirst( $user->getName() ) :
				$user->getName(),
			'admin' => $isAdmin ? 'true' : 'false',
			'moderator' => $isModerator ? 'true' : 'false',
			'groups' => implode( ',', $groups ),
			'suppress_welcome_message' => $this->config->isWelcomeMessageSuppressed() ? 'true' : 'false',
		];
	}
}
