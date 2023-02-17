<?php

declare(strict_types=1);

require_once 'RequestComponent.php';
require_once 'FileSystemDocumentParser.php';
require_once 'TagsParser.php';

/**
 * Class HtmlDocumentParser
 */
class HtmlDocumentParser
{

    public function __construct()
    {
    }

    public static function getMetaData(string $rawHtml): array
    {
        $metaTags = [];
        if (preg_match('/<title>(.+)<\/title>/u', $rawHtml, $outputArray)) {
            $metaTags['title'] = mb_convert_encoding($outputArray[1], 'UTF-8', 'UTF-8');
        }
        if (preg_match('/<meta name="description" content="(.+)"\s?\/?>/u', $rawHtml, $outputArray)) {
            $metaTags['description'] = mb_convert_encoding($outputArray[1], 'UTF-8', 'UTF-8');
        }
        if (preg_match('/<h1>(.+)<\/h1>/u', $rawHtml, $outputArray)) {
            $metaTags['h1'] = mb_convert_encoding($outputArray[1], 'UTF-8', 'UTF-8');
        }
        return $metaTags;
    }

    public function getAllPageLinks(string $htmlPage, array &$uniqLinks): array
    {
        $allLinksArray = $this->getAllViewLinks($htmlPage);
        $allLinksArray = array_merge($allLinksArray, $this->getAllImagesLinks($htmlPage));
        $allLinksArray = array_merge($allLinksArray, $this->getAllCSSLinks($htmlPage));
        $allLinksArray = array_merge($allLinksArray, $this->getAllJsLinks($htmlPage));
        $outputArray = [
            'count'=>0,
            'countUnique'=>0,
        ];
        foreach ($allLinksArray as $link) {
            $outputArray['count']++;
            $link['href'] = mb_convert_encoding($this->simplificationUrl($link['href']),'UTF-8', 'UTF-8');
            $link['type'] = FileSystemDocumentParser::getFileType($link['href']);

            if (!isset($outputArray[$link['type']])) {
                $outputArray[$link['type']] = [];
            }
            if (isset($uniqLinks[$link['type']]) && isset($uniqLinks[$link['type']][$link['href']])) {
                $uniqLinks[$link['type']][$link['href']]++;
                continue;
            }
            $outputArray['countUnique']++;
            $outputArray[$link['type']][$link['href']] = $link;
        }
        return $this->analyseLinks($outputArray);
    }

    public function getAllViewLinks(string $htmlPage): array
    {
        $links = TagsParser::extractTags($htmlPage, 'a');
        $output = [];
        foreach ($links as $link) {
            $text = self::getRawHtmlLinkText($link['full_tag']);
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if (strpos($text, 'span') !== false) {
                $outputName = '';
                foreach (TagsParser::extractTags($link['full_tag'], 'span') as $item) {
                    $spanText = self::getRawHtmlLinkText($item['full_tag']);
                    $outputName .= $spanText . ' ';
                }
                $text = $outputName;
            }

            if (isset($link['attributes']['href']) && !isset($output[$link['attributes']['href']])) {
                $output[$link['attributes']['href']] = [
                    'html' => $link['full_tag'],
                    'href' => $link['attributes']['href'] ?? '',
                ];
            }
        }
        return $output;
    }

    private static function getRawHtmlLinkText(string $htmlLink): string
    {
        if (strpos($htmlLink, '<br>') !== false) {
            $htmlLink = str_replace('<br>', ' ', $htmlLink);
        }
        $rawText = preg_replace('/<[^<>]+>/', '', $htmlLink);
        $rawText = str_replace('&nbsp;',' ',$rawText);
        return trim($rawText, " \t\n\r\0\x0B\xC2\xA0");
    }

    private function getAllImagesLinks(string $htmlPage): array
    {
        $images = TagsParser::extractTags($htmlPage, 'img');
        $output = [];
        foreach ($images as $img) {
            $rawHtml = $img['full_tag'];
            $imgSrc = $img['attributes']['src'];
            $altText = $img['attributes']['alt'] ?? '';
            if (!isset($output[$imgSrc])) {
                $output[$imgSrc] = [
                    'html' => $rawHtml,
                    'href' => $imgSrc,
                ];
            }
        }
        return $output;
    }

    private function getAllCSSLinks(string $htmlPage): array
    {
        $cssFiles = TagsParser::extractTags($htmlPage, 'link');
        $output = [];
        foreach ($cssFiles as $file) {
            $rawHtml = $file['full_tag'];
            $cssSrc = $file['attributes']['href'];
            if (!isset($output[$cssSrc])) {
                $output[$cssSrc] = [
                    'html' => $rawHtml,
                    'href' => $cssSrc,
                ];
            }
        }
        return $output;
    }

    private function getAllJsLinks(string $htmlPage): array
    {
        $scripts = TagsParser::extractTags($htmlPage, 'script');
        $output = [];
        foreach ($scripts as $script) {
            $jsSrc = $script['attributes']['src'] ?? '';
            if (!$jsSrc) {
                continue;
            }
            $rawHtml = $script['full_tag'];
            if (!isset($output[$jsSrc])) {
                $output[$jsSrc] = [
                    'html' => $rawHtml,
                    'href' => $jsSrc,
                ];
            }
        }
        return $output;
    }

    private function analyseLinks(array $links): array
    {
        foreach ($links as $type=>&$linksByType){
            if($type==='count' || $type==='countUnique'){
                continue;
            }
            foreach ($linksByType as $href => &$linkData) {
                if (isset($href)) {
                    if (preg_match('/^tel:\+(\d{11})$/', $linkData['href']) ||
                        preg_match('/^mailto:/', $linkData['href'])) {
                        $linkData['status'] = true;
                        $linkData['code'] = 0;
                    } else {
                        $responseData = RequestComponent::request($linkData['href'], true);
                        $linkData['status'] = $responseData['status'];
                        $linkData['code'] = $responseData['code'];
                        if (isset($responseData['url'])) {
                            $linkData['href'] = $responseData['url'];
                        }
//                        $linkData['href'] = $this->simplificationUrl($linkData['href']);
                    }
                }
            }
        }

        return $links;
    }

    private function simplificationUrl(string $url): string
    {
        $urlParts = parse_url($url);
        if(!isset($urlParts['scheme'])){
            $urlParts['scheme'] = 'https';
        }
        if(!isset($urlParts['host'])){
            $urlParts['host'] = parse_url(Config::DOMAIN,PHP_URL_HOST);
        }
        return rtrim("$urlParts[scheme]://$urlParts[host]" . ($urlParts['path'] ?? '/'), '/');
    }

}