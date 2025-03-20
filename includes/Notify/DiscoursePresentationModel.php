<?php

namespace MediaWiki\Extension\Discourse\Notify;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;

class DiscoursePresentationModel extends EchoEventPresentationModel {
	/** @inheritDoc */
	public function getIconType() {
		return 'chat';
	}

	/** @inheritDoc */
	public function getHeaderMessage() {
		return $this->msg(
			"notification-header-discourse-{$this->event->getExtraParam( 'event-type' )}",
			$this->event->getExtraParam( 'user' ),
			$this->event->getExtraParam( 'topic' ),
		);
	}

	/** @inheritDoc */
	public function getPrimaryLink() {
		return [
			'url' => $this->event->getExtraParam( 'url' ),
			'label' => $this->msg( 'discourse-notify-event-label' )->text()
		];
	}

	/** @inheritDoc */
	public function getSecondaryLinks() {
		$user = $this->event->getExtraParam( 'user' );
		$userUrl = $this->event->getExtraParam( 'user-url' );
		$topicUrl = $this->event->getExtraParam( 'topic-url' );
		$links = [];
		if ( !empty( $userUrl ) ) {
			$links[] = [
				'url' => $userUrl,
				'label' => $user,
				'icon' => 'userAvatar',
				'prioritized' => true,
			];
		}
		if ( !empty( $topicUrl ) ) {
			$links[] = [
				'url' => $topicUrl,
				'label' => $this->msg( 'notification-discourse-topic' )->text(),
				'icon' => 'speechBubbles',
				'prioritized' => true,
			];
		}
		return $links;
	}
}
