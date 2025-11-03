<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\Connect\DiscourseConnectPayloadGenerator;
use MediaWiki\Extension\Discourse\ExtensionConfig;
use MediaWiki\Extension\Discourse\Profile\ProfileRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	ExtensionConfig::SERVICE_NAME => fn (
		MediaWikiServices $services,
	): ExtensionConfig => new ExtensionConfig(
		new ServiceOptions(
			ExtensionConfig::CONSTRUCTOR_OPTIONS,
			$services->getMainConfig(),
		),
	),
	DiscourseAPIService::SERVICE_NAME => fn (
		MediaWikiServices $services,
	): DiscourseAPIService => new DiscourseAPIService(
		$services->getService( ExtensionConfig::SERVICE_NAME ),
		$services->getHttpRequestFactory(),
	),
	DiscourseConnectPayloadGenerator::SERVICE_NAME => fn (
		MediaWikiServices $services,
	): DiscourseConnectPayloadGenerator => new DiscourseConnectPayloadGenerator(
		$services->getPermissionManager(),
		$services->getUserGroupManager(),
		$services->getUserOptionsLookup(),
		$services->getService( ExtensionConfig::SERVICE_NAME ),
	),
	ProfileRenderer::SERVICE_NAME => fn (
		MediaWikiServices $services,
	): ProfileRenderer => new ProfileRenderer(
		$services->getUserGroupManager(),
		$services->getUserOptionsLookup(),
		$services->getService( DiscourseAPIService::SERVICE_NAME ),
		$services->getService( ExtensionConfig::SERVICE_NAME ),
		$services->getMainWANObjectCache(),
		LoggerFactory::getInstance( ExtensionConfig::LOG_CHANNEL ),
		$services->getRepoGroup()->getLocalRepo(),
	),
];
