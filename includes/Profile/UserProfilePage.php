<?php

namespace MediaWiki\Extension\Discourse\Profile;

use Article;
use MediaWiki\Title\Title;

class UserProfilePage extends Article {
	private string $username;
	private ProfileRenderer $renderer;

	public function __construct( Title $title, ProfileRenderer $renderer ) {
		$this->username = $title->getText();
		$this->renderer = $renderer;
		parent::__construct( $title );
	}

	public function view(): void {
		$this->renderer->render( $this->username, $this->getContext() );
		parent::view();
	}
}
