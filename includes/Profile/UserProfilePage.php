<?php

namespace MediaWiki\Extension\Discourse\Profile;

use MediaWiki\Page\Article;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;

class UserProfilePage extends Article {
	public function __construct(
		private readonly Title $title,
		private readonly UserFactory $userFactory,
		private readonly ProfileRenderer $renderer,
	) {
		parent::__construct( $title );
	}

	public function view(): void {
		$user = $this->userFactory->newFromName( $this->title->getText() );
		if ( $user && !$user->isAnon() ) {
			$this->renderer->render( $user, $this->getContext() );
		}
		parent::view();
	}
}
