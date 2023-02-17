<?php


class SitemapXmlParser
{

    private $sitemapUrl;

    public function __construct(string $url)
    {
        $this->sitemapUrl = $url;
    }

    public function getAllUrls(): array
    {
        libxml_use_internal_errors(true);
        $urls = simplexml_load_file($this->sitemapUrl);
        if($urls!==false){
            $outputUrls = [];
            foreach ($urls as $url){
                if(isset($url->loc)){
                    $outputUrls[] = (string)$url->loc;
                }
            }
            return $outputUrls;
        }
        foreach (libxml_get_errors() as $error) {
            echo $error->message . PHP_EOL;
        }
        libxml_clear_errors();
        return [];
    }
}