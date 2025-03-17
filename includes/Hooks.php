<?php

namespace MediaWiki\Extension\Discourse;

use MediaWiki\Extension\Discourse\Profile\ProfileRenderer;
use MediaWiki\Extension\Discourse\Profile\UserProfilePage;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserNameUtils;

class Hooks implements
	ArticleFromTitleHook,
	LoginFormValidErrorMessagesHook,
	SpecialPageBeforeExecuteHook
{
	private UserNameUtils $userNameUtils;

	public function __construct( UserNameUtils $userNameUtils ) {
		$this->userNameUtils = $userNameUtils;
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
			$article = new UserProfilePage( $title, $title->getText() );
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
			$renderer = new ProfileRenderer( $subPage, $special->getContext() );
			$renderer->render();
		}
	}
}
