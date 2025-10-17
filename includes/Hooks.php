<?php

namespace MediaWiki\Extension\Discourse;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\Profile\ProfileRenderer;
use MediaWiki\Extension\Discourse\Profile\UserProfilePage;
use MediaWiki\Extension\Discourse\Hooks\TalkPageLinkResolveHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook;
use MediaWiki\Page\Hook\ArticleFromTitleHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\User\UserFactory;

class Hooks implements
	ArticleFromTitleHook,
	LoginFormValidErrorMessagesHook,
	SpecialPageBeforeExecuteHook,
	TalkPageLinkResolveHook,
	BeforePageDisplayHook,
	GetPreferencesHook,
	MakeGlobalVariablesScriptHook
{
	public function __construct(
		private readonly UserFactory $userFactory,
		private readonly ProfileRenderer $renderer,
		private readonly DiscourseAPIService $api,
		private readonly ExtensionConfig $config,
	) {
	}

	/** @inheritDoc */
	public function onLoginFormValidErrorMessages( array &$messages ): void {
		if ( $this->config->isConnectEnabled() ) {
			$messages[] = 'discourse-connect-requires-named';
		}
	}

	/** @inheritDoc */
	public function onArticleFromTitle( $title, &$article, $context ) {
		if (
			!$this->config->isProfileEnabled() ||
			!$title->hasSubjectNamespace( NS_USER ) ||
			$title->isSubpage()
		) {
			return;
		}
		$article = new UserProfilePage(
			$title,
			$this->userFactory,
			$this->renderer
		);
	}

	/** @inheritDoc */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if (
			!$this->config->isProfileEnabled() ||
			$special->getName() !== 'Contributions' ||
			$subPage === '' ||
			$subPage === null
		) {
			return;
		}
		$user = $this->userFactory->newFromName( $subPage );
		if ( !$user || $user->isAnon() ) {
			return;
		}
		$this->renderer->render( $user, $special->getContext() );
	}

	/** @inheritDoc */
	public function onTalkPageLinkResolve( array &$linkAttributes ): void {
		if (
			!$this->config->isTalkButtonEnabled() ||
			$linkAttributes['ns'] !== NS_MAIN
		) {
			return;
		}

		$cleanTitle = $this->api->sanitizePageTitle( $linkAttributes['title'] );

		if ( !$cleanTitle ) {
			return;
		}

		$linkAttributes['href'] = "{$this->config->getBaseUrl()}/tag/$cleanTitle";
		unset( $linkAttributes['rel'] );
	}

	/** @inheritDoc */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( $this->hasArticleTalk( $skin ) ) {
			$out->addModules( [ 'ext.discourse.articleTalk.scripts' ] );
			$out->addModuleStyles( [ 'ext.discourse.articleTalk.styles' ] );
		}
	}

	/** @inheritDoc */
	public function onMakeGlobalVariablesScript( &$vars, $out ): void {
		$title = $out->getTitle();

		$vars['DiscourseBaseUrl'] = $this->config->getBaseUrl();
		$vars['DiscoursePageTag'] = $this->api->sanitizePageTitle( $title );
	}

	private function hasArticleTalk( $skin ): bool {
		$title = $skin->getTitle();
		$action = $skin->getRequest()->getRawVal( 'action' ) ?? 'view';

		$sanitizedTitle = $this->api->sanitizePageTitle( $title );

		return $this->config->areRelatedArticlesEnabled() &&
			$title->inNamespace( NS_MAIN ) &&
			$action === 'view' &&
			!$title->isMainPage() &&
			$title->exists() &&
			$sanitizedTitle !== null;
	}

	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ) {
		if ( !$this->config->isConnectEnabled() ) {
			return;
		}
		$context = RequestContext::getMain();
		$preferences['discourse-lowercase-username'] = [
			'type' => 'toggle',
			'label-message' => 'discourse-userpref-lowercase-username',
			'help' => $context->msg(
				'discourse-userpref-lowercase-username-help',
				$user->getName(),
				$context->getLanguage()->lcfirst( $user->getName() ),
			),
			'section' => 'editing/discussion',
		];
	}
}
