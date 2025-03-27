<?php

namespace MediaWiki\Extension\Discourse\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;


$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
    $IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";


class BulkUploadDiscourseTags extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription( 'A maintenance script for bulk uploading all mainspace page names as tags to Discourse.' );
    }

    public function execute() {
        $services = $this->getServiceContainer();
        $pageStore = $services->getPageStore();
        $redirectStore = $services->getRedirectStore();
        $titleFactory = $services->getTitleFactory();
        $discourseAPI = $services->getService( 'DiscourseAPIService' );

        $mainspacePages = $pageStore->newSelectQueryBuilder()
            ->whereNamespace( NS_MAIN )
            ->fetchPageRecords();

        $pageTitles = [];
        $redirectTitles = [];

        foreach ( $mainspacePages as $page ) {
            $title = $titleFactory->newFromPageIdentity( $page );
            $cleanTitleText = $discourseAPI->sanitizePageTitle( $title );
            if ( $cleanTitleText === null ) {
                $this->output( "Warning: Page title \"{$title->getText()}\" could not be transformed to a clean tag and is skipped.\n" );
                continue;
            }

            if ( $page->isRedirect() ) {
                $redirectTarget = $redirectStore->getRedirectTarget( $page );
                if ( !$redirectTarget || $redirectTarget->getNamespace() !== NS_MAIN ) {
                    continue;
                }

                $targetTitle = Title::newFromLinkTarget( $redirectTarget );
                $cleanTargetTitle = $discourseAPI->sanitizePageTitle( $targetTitle );
                if ( $cleanTargetTitle === null ) {
                    $this->output( "Warning: Redirect title \"{$targetTitle->getText()}\" could not be transformed to a clean tag and is skipped.\n" );
                    continue;
                }

                $redirectTitles[] = [ $cleanTitleText, $cleanTargetTitle ];

                if ( !in_array( $cleanTargetTitle, $pageTitles ) ) {
                    $pageTitles[] = $cleanTargetTitle;
                }
            } else {
                if ( !in_array( $cleanTitleText, $pageTitles ) ) {
                    $pageTitles[] = $cleanTitleText;
                }
            }
        }
        $this->output("Found " . count( $pageTitles ) . " tags to upload.\n");

        $discourseAPI->throwIfConfigInvalid();
        $csv = implode( array_map(  static function( $title ) {
            return "$title,Articles\n";
        }, $pageTitles ) );
        $discourseAPI->makeRequest( '/tags/upload.json', 'POST', [
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $csv,
                    'filename' => 'tags.csv',
                    'headers' => [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ],
                ],
            ]
        ] );

        $this->output("Found " . count( $redirectTitles ) . " synonyms to upload.\n");

        foreach ( $redirectTitles as $redirectTitle ) {
            [ $redirectPageTitle, $targetPageTitle ] = $redirectTitle;
            $discourseAPI->makeRequest( "/tag/$targetPageTitle/synonyms", 'POST', [
                'form_params' => [
                    'synonyms[]' => $redirectPageTitle,
                ],
            ] );
            // Avoid getting ratelimited.
            sleep( 1 );
            $this->output("Updated synoyms for $redirectPageTitle -> $targetPageTitle successfully.\n");
        }
    }
}

$maintClass = BulkUploadDiscourseTags::class;
require_once RUN_MAINTENANCE_IF_MAIN;
