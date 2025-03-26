<?php

namespace MediaWiki\Extension\Discourse\Hooks;

interface TalkPageLinkResolveHook {
	/**
	 * @param array &$linkAttributes
	 * @return void
	 */
	public function onTalkPageLinkResolve(array &$linkAttributes): void;
}
