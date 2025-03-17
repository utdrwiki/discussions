<?php

namespace MediaWiki\Extension\Discourse\Profile;

use Article;
use MediaWiki\Title\Title;

class UserProfilePage extends Article {
	private string $username;

	public function __construct( Title $title, string $username ) {
		$this->username = $username;
		parent::__construct( $title );
	}

	public function view(): void {
		$this->renderer = new ProfileRenderer( $this->username, $this->getContext() );
		$this->renderer->render();
		parent::view();
	}
}
