<?php

namespace MediaWiki\Extension\Discourse\Notify;

use MediaWiki\Extension\Notifications\AttributeManager;
use MediaWiki\Extension\Notifications\Hooks\BeforeCreateEchoEventHook;
use MediaWiki\Extension\Notifications\UserLocator;

class EchoHooks implements BeforeCreateEchoEventHook {
	public function onBeforeCreateEchoEvent(
		array &$notifications,
		array &$notificationCategories,
		array &$notificationIcons
	): void {
		$notificationCategories['discourse'] = [
			'priority' => 7,
			'tooltip' => 'echo-pref-tooltip-discourse',
		];
		$notifications['discourse'] = [
			'category' => 'discourse',
			'group' => 'positive',
			'section' => 'alert',
			'presentation-model' => DiscoursePresentationModel::class,
			'canNotifyAgent' => true,
			AttributeManager::ATTR_LOCATORS => [
				[ [ UserLocator::class, 'locateEventAgent' ] ]
			],
		];
	}
}
