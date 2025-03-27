<?php

namespace MediaWiki\Extension\Discourse;

use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\Profile\ProfileRenderer;
use MediaWiki\Extension\Discourse\Profile\UserProfilePage;
use MediaWiki\Extension\Discourse\Hooks\TalkPageLinkResolveHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserNameUtils;

class Hooks implements
	ArticleFromTitleHook,
	LoginFormValidErrorMessagesHook,
	SpecialPageBeforeExecuteHook,
	TalkPageLinkResolveHook
{
	private UserNameUtils $userNameUtils;
	private ProfileRenderer $renderer;
	private DiscourseAPIService $discourseAPI;

	public function __construct( UserNameUtils $userNameUtils, ProfileRenderer $renderer, DiscourseAPIService $discourseAPI ) {
		$this->userNameUtils = $userNameUtils;
		$this->renderer = $renderer;
		$this->discourseAPI = $discourseAPI;
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ): void {
		$messages[] = 'discourse-connect-requires-named';
	}

	/** @inheritDoc */
	public function onArticleFromTitle( $title, &$article, $context ) {
		if (
			$context->getConfig()->get( 'DiscourseEnableProfile' ) &&
			$title->hasSubjectNamespace( NS_USER ) &&
			!$title->isSubpage() &&
			$this->userNameUtils->isUsable( $title->getText() )
		) {
			$article = new UserProfilePage( $title, $this->renderer );
		}
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if (
			$special->getConfig()->get( 'DiscourseEnableProfile' ) &&
			$special->getName() === 'Contributions' &&
			$subPage !== '' &&
			$subPage !== null &&
			$this->userNameUtils->isUsable( $subPage )
		) {
			$this->renderer->render( $subPage, $special->getContext() );
		}
	}

	/** @inheritDoc */
	public function onTalkPageLinkResolve(array &$linkAttributes): void {
		if ($linkAttributes['ns'] !== NS_MAIN) {
			return;
		}

		$cleanTitle = $this->discourseAPI->sanitizePageTitle( $linkAttributes['title'] );

		if (!$cleanTitle) {
			return;
		}

		$linkAttributes['href'] = $this->discourseAPI->getBaseUrl() . '/tag/' . $cleanTitle;
	}
}
