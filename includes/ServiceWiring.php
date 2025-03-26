<?php

use MediaWiki\Extension\Discourse\API\DiscourseAPIService;
use MediaWiki\Extension\Discourse\Profile\ProfileRenderer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
    'DiscourseProfileRenderer' => static function ( MediaWikiServices $services ) {
        return new ProfileRenderer(
            $services->getUserFactory(),
            $services->getUserGroupManager(),
            $services->getHttpRequestFactory(),
            $services->getMainWANObjectCache(),
            LoggerFactory::getInstance( 'Discourse' ),
        );
    },
    'DiscourseAPIService' => static function ( MediaWikiServices $services ) {
        $baseUrl = $services->getMainConfig()->get('DiscourseBaseUrl');
        return new DiscourseAPIService($baseUrl);
    }
];
