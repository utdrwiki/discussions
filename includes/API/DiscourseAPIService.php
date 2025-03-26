<?php

namespace MediaWiki\Extension\Discourse\API;

class DiscourseAPIService {
    public function sanitizePageTitle($title) {
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
