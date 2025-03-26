<?php

namespace MediaWiki\Extension\Discourse\Maintenance;

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageStore;
use MediaWiki\Api\ApiBase;
use Title;
use Maintenance;


$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
    $IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";


class MyQueryScript extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription("A maintenance script for bulk uploading all mainspace article page names as tags to Discourse.");
    }
    
    public function execute() {
        $pageStore = MediaWikiServices::getInstance()->getPageStore();
        $redirectStore = MediaWikiServices::getInstance()->getRedirectStore();
        $titleFactory = MediaWikiServices::getInstance()->getTitleFactory();
        $config = MediaWikiServices::getInstance()->getMainConfig();
        
        $apiKey = $config->get('DiscourseApiKey');
        $baseUrl = $config->get('DiscourseBaseUrl');
        $apiUrl = $config->get( 'DiscourseBaseUrlInternal', $baseUrl );
        $unixSocket = $config->get( 'DiscourseUnixSocket' );
        
        $pages = $pageStore->newSelectQueryBuilder()
        ->whereNamespace(NS_MAIN) // Fetch only mainspace pages
        // ->limit(100)
        ->fetchPageRecords();
        
        $pageTitles = [];
        $redirectTitles = [];
        
        foreach ($pages as $page) {
            $title = $titleFactory->newFromPageIdentity($page);
            $cleanTitleText = $this->sanitizePageTitle($title);
            if ($cleanTitleText === null) {
                $this->output("Warning: Page title \"" . $title->getText() . "\" could not be transformed to a clean tag and is skipped." . "\n");
                continue;
            }
            
            if ($page->isRedirect()) {
                $redirectTarget = $redirectStore->getRedirectTarget($page);
                if (!$redirectTarget || $redirectTarget->getNamespace() !== NS_MAIN) {
                    continue;
                }
                
                $targetTitle = Title::newFromLinkTarget($redirectTarget);
                $cleanTargetTitle = $this->sanitizePageTitle($targetTitle);
                if ($cleanTargetTitle === null) {
                    $this->output("Warning: Redirect title \"" . $targetTitle->getText() . "\" could not be transformed to a clean tag and is skipped." . "\n");
                    continue;
                }
                
                $redirectTitles[] = [$cleanTitleText, $cleanTargetTitle];
                
                if (!in_array($cleanTargetTitle, $pageTitles)) {
                    $pageTitles[] = $cleanTargetTitle;
                }
            } else {
                $pageTitles[] = strtolower($title->getPrefixedText());
            }
        }
        
        $csvFilePath = sys_get_temp_dir() . '/article_title_tags.csv';
        $fp = fopen($csvFilePath, 'w');
        foreach ($pageTitles as $title) {
            fputcsv($fp, [$title, 'Articles']);
        }
        
        fclose($fp);
        
        $this->output("Found " . sizeof($pageTitles) . " tags to upload.\n");
        
        $curl = curl_init();
        
        $postFields = ['file' => curl_file_create($csvFilePath, 'text/csv', 'tags.csv')];
        $headers = [ 'Api-Username: system', 'Api-Key: ' . $apiKey ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $apiUrl . '/tags/upload.json',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ( $unixSocket !== null ) {
            curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, $unixSocket);
        }
        
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            $this->output("Failed to upload tags. CURL Error: " . curl_error($curl) . "\n");
        } else {
            $info = curl_getinfo($curl);
            
            if ($info['http_code'] !== 200) {
                $this->output("Received error " . $info['http_code'] . " from server: " . $response . "\n");
            } else {
                $this->output("Uploaded tags successfully. Response: " . $response . "\n");
            }
        }
        
        curl_close($curl);
        
        $this->output("Found " . sizeof($redirectTitles) . " synonyms to upload.\n");
        
        foreach ($redirectTitles as $redirectTitle) {
            [$redirectPageTitle, $targetPageTitle] = $redirectTitle;
            $curl = curl_init();
            $headers = [ 'Api-Username: system', 'Api-Key: ' . $apiKey ];
            $curlUrl = $apiUrl . '/tag/' . $targetPageTitle . '/synonyms';
            curl_setopt_array($curl, [
                CURLOPT_URL => $curlUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => "synonyms[]=" . $redirectPageTitle,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ( $unixSocket !== null ) {
                curl_setopt($curl, CURLOPT_UNIX_SOCKET_PATH, $unixSocket);
            }
            
            $response = curl_exec($curl);
            if (curl_errno($curl)) {
                $this->output("Failed to upload tags. CURL Error: " . curl_error($curl) . "\n");
            } else {
                $info = curl_getinfo($curl);
                if ($info['http_code'] !== 200) {
                    $this->output("Received error " . $info['http_code'] . " from server: " . $curlUrl . "\n");
                } else {
                    $this->output("Updated synoyms for " . $redirectPageTitle . " -> " . $targetPageTitle . " successfully. Response: " . $response . "\n");
                }
            }
        }
    }
    
    private function sanitizePageTitle($title) {
        $titleText = $title->getText();
        // Always skip sub-pages
        if (strpos($titleText, '/') !== false) {
            return null;
        }
        
        $cleanTitle = $title->getPrefixedText();
        
        // To lower case
        $cleanTitle = strtolower($title);
        // Replace spaces with underscores
        $cleanTitle = str_replace(' ', '_', $cleanTitle);
        // Remove special characters
        $cleanTitle = preg_replace('/[^a-z0-9_-]/', '', $cleanTitle);
        
        // If there's nothing but special characters, return null
        if ($cleanTitle === "") {
            return null;
        }
        
        return $cleanTitle;
    }
}

// Run the script
$maintClass = MyQueryScript::class;
require_once RUN_MAINTENANCE_IF_MAIN;
