<?php

namespace MediaWiki\Extension\Discourse;

use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\Profile\ProfileRenderer;
use MediaWiki\Extension\Discourse\Profile\UserProfilePage;
use MediaWiki\Extension\Discourse\Hooks\TalkPageLinkResolveHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserNameUtils;

class Hooks implements
	ArticleFromTitleHook,
	LoginFormValidErrorMessagesHook,
	SpecialPageBeforeExecuteHook,
	TalkPageLinkResolveHook,
	BeforePageDisplayHook,
	MakeGlobalVariablesScriptHook
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

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ($this->hasArticleTalk($skin)) {
			$out->addModules( [ 'ext.discourse.articleTalk.scripts' ] );
			$out->addModuleStyles( [ 'ext.discourse.articleTalk.styles' ] );
		}
	}

	/** @inheritDoc */
	public function onMakeGlobalVariablesScript(&$vars, $out): void {
		$title = $out->getTitle();

		$vars["DiscourseBaseUrl"] = $this->discourseAPI->getBaseUrl();
		$vars["DiscoursePageTag"] = $this->discourseAPI->sanitizePageTitle($title);
	}

	private function hasArticleTalk( $skin ): bool {
		$title = $skin->getTitle();
		$action = $skin->getRequest()->getRawVal( 'action' ) ?? 'view';

		$sanitizedTitle = $this->discourseAPI->sanitizePageTitle($title);

		return $title->inNamespace( NS_MAIN ) &&
			$action === 'view' &&
			!$title->isMainPage() &&
			$title->exists() &&
			$sanitizedTitle !== null;
	}
}
