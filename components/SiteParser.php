<?php

declare(strict_types=1);

require_once 'Config.php';
require_once 'FileSystemDocumentParser.php';
require_once 'HtmlDocumentParser.php';
require_once 'SitemapXmlParser.php';


/**
 * Class SiteParser
 */
class SiteParser
{

    /**
     * @param array $arguments
     * @throws Exception
     */
    public static function init(array $arguments)
    {
        $reanalyze = !(array_search('--reanalyze', $arguments) === false);
        foreach ($arguments as $argument) {
            switch ($argument) {
                case 'get-news-urls':
                {
                    self::parseNewsUrls();
                    return;
                }
                case 'analyze-news-urls':
                {
                    $index = array_search($argument, $arguments);
                    if (isset($arguments[$index + 1]) && is_numeric($arguments[$index + 1])) {
                        self::analyzeNewsLinks($arguments[$index + 1], $reanalyze);
                    } else {
                        self::analyzeNewsLinks(5, $reanalyze);
                    }
                    return;
                }
                case 'analyze-sitemap':
                {
                    self::parseSitemapPages();
                    return;
                }
                case 'analyze-links':
                {
                    $index = array_search($argument, $arguments);
                    if (isset($arguments[$index + 1]) && is_numeric($arguments[$index + 1])) {
                        self::analyzeAllSiteLinks((int)$arguments[$index + 1], $reanalyze);
                    } else {
                        self::analyzeAllSiteLinks(1, $reanalyze);
                    }
                    return;
                }
                case 'get-directories-list':
                {
                    self::parseDirectories();
                    return;
                }
                case 'parse-directories':
                {
                    $index = array_search($argument, $arguments);
                    if (isset($arguments[$index + 1]) && is_numeric($arguments[$index + 1])) {
                        self::parseFiles((int)$arguments[$index + 1], $reanalyze);
                    } else {
                        self::parseFiles(1, $reanalyze);
                    }
                    return;
                }
                case 'get-all-site-pages':
                {
                    self::getAllSitePages();
                    return;
                }
                case 'all-analyze':
                {
                    self::allAnalyze();
                    return;
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function parseNewsUrls()
    {
        $newsUrls = FileSystemDocumentParser::scanNewsDirectories();
        self::saveFile(['urls' => $newsUrls], Config::NEWS_LINKS_FILENAME);
    }

    /**
     * @throws Exception
     */
    private static function saveFile(
        array $arrayToFile,
        string $filename,
        int $totalCount = null,
        int $currentPosition = null,
        string $type = Config::FILE_TYPE_DATA
    ) {
        $outputArray = array_merge(
            $arrayToFile,
            [
                'date' => date('Y-m-d H:i:s'),
            ]
        );
        if ($totalCount && $currentPosition) {
            $outputArray = array_merge(
                $outputArray,
                [
                    'total_count' => $totalCount,
                    'current_position' => $currentPosition
                ]
            );
        }
        $data = json_encode(
            $outputArray,
            JSON_UNESCAPED_UNICODE
        );
        $filepath = $type === Config::FILE_TYPE_RESULT ? Config::RESULT_PATH : Config::DATA_PATH;
        if (file_put_contents(
                $filepath . "/$filename",
                $data
            ) === false) {
            throw new Exception("Ошибка при записи файла $filename");
        }
    }

    /**
     * @throws Exception
     */
    public static function analyzeNewsLinks(int $countToAnalyze = 5, bool $reanalyze = false)
    {
        $newsUrls = self::getFile(Config::NEWS_LINKS_FILENAME, ['urls' => 'array']);

        $urlsToAnalyze = $newsUrls['urls'];
        try {
            $newsUrlsData = self::getFile(
                Config::NEWS_LINKS_DATA_FILENAME,
                ['urls_data' => 'array', 'current_position' => 'int', 'total_count' => 'int',]
            );
        } catch (Exception $e) {
            print $e->getMessage();
            $newsUrlsData = ['urls_data' => [], 'total_count' => count($urlsToAnalyze), 'current_position' => 0];
        }
        if ($reanalyze) {
            $newsUrlsData['current_position'] = 0;
        }
        $newsDataCurrentPosition = $newsUrlsData['current_position'];
        $newsDataTotalCount = $newsUrlsData['total_count'];
        if (count($urlsToAnalyze) === $newsDataTotalCount) {
            if ($newsDataCurrentPosition === $newsDataTotalCount) {
                print 'Анализ ссылок уже был проведён. Используйте ключ --reanalyze' . PHP_EOL;
            }

            print 'Анализируются ссылки с ' . ($newsDataCurrentPosition + 1) . ' по ' . min(
                    $newsDataTotalCount,
                    $newsDataCurrentPosition + $countToAnalyze
                ) . '. Всего ' . $newsDataTotalCount . PHP_EOL;
            $urlsToAnalyze = array_slice(
                $urlsToAnalyze,
                $newsDataCurrentPosition,
                min(
                    $newsDataTotalCount - $newsDataCurrentPosition,
                    $countToAnalyze
                )
            );
        } else {
            print 'Не совпадает количество ссылок, анализ будет проведен заново' . PHP_EOL;
            $urlsToAnalyze = array_slice(
                $urlsToAnalyze,
                0,
                min(
                    $newsDataTotalCount - $newsDataCurrentPosition,
                    $countToAnalyze
                )
            );
        }
        $outputData = $newsUrlsData['urls_data'];


        foreach ($urlsToAnalyze as $url) {
            $urlData = RequestComponent::request($url);

            $metaTags = HtmlDocumentParser::getMetaData($urlData['data']);

            $outputData[$url] = [
                'status' => $urlData['status'],
                'code' => $urlData['code'],
                'title' => $metaTags['title'] ?? '',
                'description' => $metaTags['description'] ?? '',
                'h1' => $metaTags['h1'] ?? '',
            ];
        }


        self::saveFile(
            ['urls_data' => $outputData],
            Config::NEWS_LINKS_DATA_FILENAME,
            count($newsUrls['urls']),
            $newsDataCurrentPosition + count($urlsToAnalyze)
        );
    }

    /**
     * @throws Exception
     */
    public static function getFile(string $filename, array $requiredPropertiesSchema = [], string $type = Config::FILE_TYPE_DATA): array
    {
        $filepath = $type === Config::FILE_TYPE_RESULT ? Config::RESULT_PATH : Config::DATA_PATH;
        if (file_exists($filepath . "/$filename")) {
            try {
                $fileData = json_decode(
                    file_get_contents($filepath . "/$filename"),
                    true
                );
            } catch (Exception $e) {
                throw new Exception("Некорректный формат файла $filename");
            }
            foreach ($requiredPropertiesSchema as $property => $type) {
                if (!isset($fileData[$property])) {
                    throw new Exception("Некорректный формат файла $filename");
                }
                switch ($type) {
                    case 'array':
                    {
                        if (!is_array($fileData[$property])) {
                            throw new Exception(
                                "Файл $filename: Свойство $property должно быть типа $type, а не " . gettype(
                                    $fileData[$property]
                                )
                            );
                        }
                        if (!count($fileData[$property])) {
                            throw new Exception(
                                "Файл $filename: Массив $property должен содержать как минимум один элемент"
                            );
                        }
                        break;
                    }
                    case 'int':
                    {
                        if (!is_int($fileData[$property])) {
                            throw new Exception(
                                "Файл $filename: Свойство $property должно быть типа $type, а не " . gettype(
                                    $fileData[$property]
                                )
                            );
                        }
                        break;
                    }
                    case 'string':
                    {
                        if (!is_string($fileData[$property])) {
                            throw new Exception(
                                "Файл $filename: Свойство $property должно быть типа $type, а не " . gettype(
                                    $fileData[$property]
                                )
                            );
                        }
                        break;
                    }
                    default:
                    {
                    }
                }
            }

            return $fileData;
        }
        throw new Exception("Отсутствует файл $filename");
    }

    /**
     * @throws Exception
     */
    public static function parseSitemapPages()
    {
        $sitemapParser = new SitemapXmlParser(Config::DOMAIN . '/sitemap.xml');
        $urls = $sitemapParser->getAllUrls();

        self::saveFile(
            ['urls' => $sitemapParser->getAllUrls()],
            Config::SITEMAP_FILENAME
        );
    }

    public static function analyzeAllSiteLinks(int $countToAnalyze = 5, bool $reanalyze = false): void
    {
        // get page to analyze from sitemap data file
        try {
            $pages = self::getFile(Config::ALL_PAGES_FILENAME, ['urls' => 'array']);
        } catch (Exception $e) {
            $pages = self::getFile(Config::SITEMAP_FILENAME, ['urls' => 'array']);
        }


        // get existing data files or set empty data array
        try {
            $globalLinks = self::getFile(
                Config::GLOBAL_LINKS_FILENAME,
                ['links' => 'array']
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            $globalLinks = ['links' => []];
        }
        try {
            $pagesData = self::getFile(
                Config::PAGES_DATA_FILENAME,
                ['pages' => 'array', 'current_position' => 'int', 'total_count' => 'int']
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            $pagesData = ['pages' => [], 'current_position' => 0, 'total_count' => count($pages['urls'])];
        }


        // check reanalyze
        if ($reanalyze) {
            $pagesData['current_position'] = 0;
        }

        $pagesCurrentPosition = (int)$pagesData['current_position'];
        $pagesTotalCount = (int)$pagesData['total_count'];
        $pagesToAnalyze = $pages['urls'];


        // check if not updates
        if (count($pagesToAnalyze) === $pagesTotalCount) {
            // check if nothing to analyze
            if ($pagesCurrentPosition === $pagesTotalCount) {
                print 'Анализ страниц уже был проведён. Используйте ключ --reanalyze' . PHP_EOL;
                die();
            }

            print 'Анализируются страницы с ' . ($pagesCurrentPosition + 1) . ' по ' . min(
                    $pagesTotalCount,
                    $pagesCurrentPosition + $countToAnalyze
                ) . '. Всего ' . $pagesTotalCount . PHP_EOL;

            $pagesToAnalyze = array_slice(
                $pagesToAnalyze,
                $pagesCurrentPosition,
                min(
                    $pagesTotalCount - $pagesCurrentPosition,
                    $countToAnalyze
                )
            );
        } elseif ($pagesData['pages'] !== []) {
            print 'Не совпадает количество ссылок, анализ будет проведен заново' . PHP_EOL;
            $pagesToAnalyze = array_slice(
                $pagesToAnalyze,
                0,
                min(
                    $countToAnalyze - $pagesCurrentPosition,
                    $countToAnalyze
                )
            );
        }

        $outputLinksData = [
            Config::FILETYPE_LINK => [],
            Config::FILETYPE_DOCUMENT => [],
            Config::FILETYPE_IMG => [],
            Config::FILETYPE_CSS => [],
            Config::FILETYPE_JS => [],
        ];

        // setup html parser
        $pageParsingComponent = new HtmlDocumentParser();

        // analyze pages
        foreach ($pagesToAnalyze as $page) {
            // get requested data
            $urlData = RequestComponent::request($page);

            // if not error
            if ($urlData['data']) {
                $pagesLinks = $pageParsingComponent->getAllPageLinks($urlData['data'], $globalLinks['links']);
                print (array_search(
                            $page,
                            $pages['urls']
                        ) + 1) . ' ' . ($urlData['url'] ?? $page) . ' - было найдено:' . PHP_EOL;
                print (isset($pagesLinks[Config::FILETYPE_LINK]) ? count(
                        $pagesLinks[Config::FILETYPE_LINK]
                    ) : 0) . ' ссылок на ресурсы' . PHP_EOL;
                print (isset($pagesLinks[Config::FILETYPE_DOCUMENT]) ? count(
                        $pagesLinks[Config::FILETYPE_DOCUMENT]
                    ) : 0) . ' ссылок на файлы' . PHP_EOL;
                print (isset($pagesLinks[Config::FILETYPE_IMG]) ? count(
                        $pagesLinks[Config::FILETYPE_IMG]
                    ) : 0) . ' изображений' . PHP_EOL;
                print (isset($pagesLinks[Config::FILETYPE_CSS]) ? count(
                        $pagesLinks[Config::FILETYPE_CSS]
                    ) : 0) . ' css файлов' . PHP_EOL;
                print (isset($pagesLinks[Config::FILETYPE_JS]) ? count(
                        $pagesLinks[Config::FILETYPE_JS]
                    ) : 0) . ' js файлов' . PHP_EOL;
                $metaTags = $pageParsingComponent->getMetaData($urlData['data']);
            } else {
                $pagesLinks = [];
            }

            $pagesData['pages'][$page] = [
                'status' => $urlData['status'],
                'code' => $urlData['code'],
                'title' => $metaTags['title'] ?? '',
                'description' => $metaTags['description'] ?? '',
                'h1' => $metaTags['h1'] ?? '',
                'totalLinksCount' => $pagesLinks['count'] ?? 0,
                'uniqueLinksCount' => $pagesLinks['countUnique'] ?? 0
            ];

            // add links in outputLinksData and globalLinks
            foreach ($pagesLinks as $linkType => $links) {
                if ($linkType === 'count' || $linkType === 'countUnique') {
                    continue;
                }
                if (!isset($outputLinksData[$linkType])) {
                    $outputLinksData[$linkType] = [];
                }
                if (!isset($globalLinks['links'][$linkType])) {
                    $globalLinks['links'][$linkType] = [];
                }
                $outputLinksData[$linkType] = array_merge($outputLinksData[$linkType], $links);

                $uniqLinks = [];
                foreach (array_keys($links) as $link) {
                    $uniqLinks[$link] = 1;
                }
                $globalLinks['links'][$linkType] = array_merge($globalLinks['links'][$linkType], $uniqLinks);
            }
        }

        // save globalLinks
        self::saveFile(
            ['links' => $globalLinks['links']],
            Config::GLOBAL_LINKS_FILENAME
        );

        // save pagesData
        self::saveFile(
            ['pages' => $pagesData['pages']],
            Config::PAGES_DATA_FILENAME,
            $pagesTotalCount,
            $pagesCurrentPosition + $countToAnalyze
        );

        // unset to save memory
        unset($pagesData, $globalLinks);

        // get links data files or set empty data array
        try {
            $linksData = self::getFile(
                Config::LINKS_DATA_FILENAME,
                ['links' => 'array']
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            $linksData = ['links' => []];
        }

        // add links to links data file
        foreach ($outputLinksData as $linkType => $links) {
            if (!isset($linksData['links'][$linkType])) {
                $linksData['links'][$linkType] = [];
            }
            $linksData['links'][$linkType] = array_merge($linksData['links'][$linkType], $links);
        }

        // save linksData
        self::saveFile(
            ['links' => $linksData['links']],
            Config::LINKS_DATA_FILENAME,
            $pagesTotalCount,
            $pagesCurrentPosition + $countToAnalyze
        );
    }

    /**
     * @throws Exception
     */
    public static function parseDirectories()
    {
        $filesParser = new FileSystemDocumentParser();
        $filesParser->scanDirectories();
        self::saveFile(
            [
                'directories' => $filesParser->allDirectories
            ],
            Config::DIRECTORIES_FILENAME,
            count($filesParser->allDirectories),
            0
        );
    }

    public static function parseFiles(int $countToAnalyze, bool $reanalyze = false)
    {
        $directories = self::getFile(
            Config::DIRECTORIES_FILENAME,
            [
                'directories' => 'array'
            ]
        );

        try {
            $directoriesData = self::getFile(
                Config::DIRECTORIES_DATA_FILENAME,
                ['directories' => 'array', 'current_position' => 'int', 'total_count' => 'int']
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            $directoriesData = [
                'directories' => [],
                'current_position' => 0,
                'total_count' => count($directories['directories'])
            ];
        }

        $directoriesCurrentPosition = (int)$directoriesData['current_position'];
        $directoriesTotalCount = (int)$directoriesData['total_count'];
        $directoriesToAnalyze = $directories['directories'];

        // check if not updates
        if (count($directoriesToAnalyze) === $directoriesTotalCount) {
            // check if nothing to analyze
            if ($directoriesCurrentPosition === $directoriesTotalCount) {
                print 'Анализ директорий уже был проведён. Используйте ключ --reanalyze' . PHP_EOL;
                die();
            }

            print 'Анализируются директории с ' . ($directoriesCurrentPosition + 1) . ' по ' . min(
                    $directoriesTotalCount,
                    $directoriesCurrentPosition + $countToAnalyze
                ) . '. Всего ' . $directoriesTotalCount . PHP_EOL;

            $directoriesToAnalyze = array_slice(
                $directoriesToAnalyze,
                $directoriesCurrentPosition,
                min(
                    $directoriesTotalCount - $directoriesCurrentPosition,
                    $countToAnalyze
                )
            );
        } elseif ($directories['directories'] !== []) {
            print 'Не совпадает количество директорий, анализ будет проведен заново' . PHP_EOL;
            $directoriesToAnalyze = array_slice(
                $directoriesToAnalyze,
                0,
                min(
                    $countToAnalyze - $directoriesCurrentPosition,
                    $countToAnalyze
                )
            );
        }

        $outputFilesData = [
            Config::FILETYPE_DOCUMENT => [],
            Config::FILETYPE_IMG => [],
            Config::FILETYPE_CSS => [],
            Config::FILETYPE_JS => [],
        ];

        foreach ($directoriesToAnalyze as $directory) {
            $filesData = FileSystemDocumentParser::readDirectoryFiles($directory);
            $directoriesData['directories'][$directory] = [
                'files_count' => $filesData['files_count']
            ];
            foreach ($filesData['files'] as $type => $files) {
                if (isset($outputFilesData[$type])) {
                    $outputFilesData[$type] = array_merge($outputFilesData[$type], $files);
                }
            }
        }

        // save directory data
        self::saveFile(
            $directoriesData,
            Config::DIRECTORIES_DATA_FILENAME,
            $directoriesTotalCount,
            $directoriesCurrentPosition + $countToAnalyze
        );

        // unset to save memory
        unset($directoriesData);

        // get files data files or set empty data array
        try {
            if ($reanalyze) {
                throw new Exception('Использован ключ reanalyze. Анализ будет проведён заново');
            }
            $filesData = self::getFile(
                Config::FILES_DATA_FILENAME,
                ['files' => 'array']
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            $filesData = ['links' => []];
        }

        // add files in data file
        foreach ($outputFilesData as $fileType => $files) {
            if (!isset($filesData['files'][$fileType])) {
                $filesData['files'][$fileType] = [];
            }
            $filesData['files'][$fileType] = array_merge($filesData['files'][$fileType], $files);
        }

        // save linksData
        self::saveFile(
            ['files' => $filesData['files']],
            Config::FILES_DATA_FILENAME
        );
    }

    /**
     * @throws Exception
     */
    public static function getAllSitePages()
    {
        $linksData = self::getFile(
            Config::LINKS_DATA_FILENAME,
            ['links' => 'array']
        );
        $linksData = $linksData['links'];

        $sitemapPages = self::getFile(
            Config::SITEMAP_FILENAME,
            ['urls' => 'array']
        );
        $sitemapPages = $sitemapPages['urls'];

        foreach ($linksData as $type => $links) {
            foreach ($links as $link) {
                if ($type === Config::FILETYPE_LINK &&
                    strpos($link['href'], Config::DOMAIN) !== false &&
                    array_search($link['href'], $sitemapPages) === false &&
                    strpos($link['href'], '/news-vpi/') === false &&
                    $link['status'] && $link['code'] === 200) {
                    $sitemapPages[] = $link['href'];
                }
            }
        }
        self::saveFile(
            [
                'urls' => $sitemapPages
            ],
            Config::ALL_PAGES_FILENAME
        );
    }

    public static function allAnalyze()
    {
        // get news files
        try {
            $newsUrls = self::getFile(
                Config::NEWS_LINKS_FILENAME,
                ['urls' => 'array']
            );
            $newsUrlsData = self::getFile(
                Config::NEWS_LINKS_DATA_FILENAME,
                ['urls_data' => 'array', 'current_position' => 'int', 'total_count' => 'int',]
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            die();
        }

        $existingNews = [];

        $unavailableNews = [];

        // division of news by accessibility
        foreach ($newsUrls['urls'] as $url) {
            if (isset($newsUrlsData['urls_data'][$url])) {
                $outputArray = array_merge($newsUrlsData['urls_data'][$url], ['href' => $url]);
                if ($newsUrlsData['urls_data'][$url]['status']) {
                    $existingNews[] = $outputArray;
                } else {
                    $unavailableNews[] = $outputArray;
                }
            } else {
                $unavailableNews[] = [
                    'href' => $url,
                    'status' => false,
                ];
            }
        }

        // free memory
        unset($newsUrls, $newsUrlsData);

        $totalLinksNumber = 0;

        $uniqueLinksNumber = 0;

        // get links numbers
        try {
            $pagesData = self::getFile(
                Config::PAGES_DATA_FILENAME,
                ['pages' => 'array', 'current_position' => 'int', 'total_count' => 'int']
            );
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            die();
        }

        // calc total links count
        foreach ($pagesData['pages'] as $page) {
            if (isset($page['totalLinksCount'])) {
                $totalLinksNumber += $page['totalLinksCount'];
            }
            if (isset($page['uniqueLinksCount'])) {
                $uniqueLinksNumber += $page['uniqueLinksCount'];
            }
        }

        // free memory
        unset($pagesData);

        // get all links files
        try {
            $globalLinks = self::getFile(
                Config::GLOBAL_LINKS_FILENAME,
                ['links' => 'array']
            );
            $globalLinks = $globalLinks['links'];

            $linksData = self::getFile(
                Config::LINKS_DATA_FILENAME,
                ['links' => 'array']
            );
            $linksData = $linksData['links'];

            $files = self::getFile(
                Config::FILES_DATA_FILENAME,
                ['files' => 'array']
            );
            $files = $files['files'];

            $sitemapPages = self::getFile(
                Config::SITEMAP_FILENAME,
                ['urls' => 'array']
            );
            $sitemapPages = $sitemapPages['urls'];
        } catch (Exception $e) {
            print $e->getMessage() . PHP_EOL;
            die();
        }

        $unaccountedLinks = [];

        $analyzedLinks = [];

        $linksWithoutFiles = [];

        $filesWithoutLinks = [];

        $externalFilesLinks = [];

        $errorLinks = [];

        // division of link by file existence and availability link
        foreach ($linksData as $type => $links) {
            foreach ($links as $link) {
                if (strpos($link['href'], '/web/') !== false) {
                    $link['href'] = str_replace('web/', '', $link['href']);
                }

                $fullLinkData = [
                    'href' => $link['href'],
                    'status' => $link['status'],
                    'code' => $link['code'],
                ];

                if ($type !== Config::FILETYPE_LINK) {
                    if (isset($files[$type]) && isset($files[$type][$link['href']])) {
                        $fullLinkData['file'] = $files[$type][$link['href']]['path'];
                        $fullLinkData['size'] = $files[$type][$link['href']]['sizeAsString'];
                        $fullLinkData['createFileDate'] = $files[$type][$link['href']]['createDate'];

                        // add file ref number
                        if (!isset($files[$type][$link['href']]['numLinks'])) {
                            $files[$type][$link['href']]['numLinks'] = 0;
                        }
                        $files[$type][$link['href']]['numLinks']++;

                        if (isset($globalLinks[$type]) && isset($globalLinks[$type][$link['href']])) {
                            $fullLinkData['matchCount'] = $globalLinks[$type][$link['href']];
                        }
                        $analyzedLinks[$type][] = $fullLinkData;
                    } elseif (strpos($link['href'], Config::DOMAIN) === false) {
                        $externalFilesLinks[$type][] = $fullLinkData;
                    } else {
                        $linksWithoutFiles[$type][] = $fullLinkData;
                    }
                } else {
                    if (strpos($link['href'], Config::DOMAIN) !== false &&
                        array_search($link['href'], $sitemapPages) === false && strpos(
                            $link['href'],
                            '/news-vpi/'
                        ) === false) {
                        $unaccountedLinks[] = $fullLinkData;
                    }
                    if ($link['status']) {
                        $analyzedLinks[$type][] = $fullLinkData;
                    } else {
                        $errorLinks[$type][] = $fullLinkData;
                    }
                }
            }
        }

        // get all files without links
        foreach ($files as $type => $filesByType) {
            foreach ($filesByType as $file) {
                if (!isset($file['numLinks']) || isset($file['numLinks']) && $file['numLinks'] === 0) {
                    $filesWithoutLinks[$type][] = $file;
                }
            }
        }

        self::saveFile(
            [
                'labels' => [
                    'sitemapPagesCount' => 'Количество страниц из sitemap',
                    'unaccountedLinksCount' => 'Количество страниц не из sitemap',
                    'totalLinksCount' => 'Общее количество ссылок',
                    'totalUniqueLinksCount' => 'Общее количество уникальных ссылок',
                    'existingNewsCount' => 'Количество существующих новостей',
                    'unavailableNewsCount' => 'Количество новостей без ссылок',
                ],
                'data' => [
                    'sitemapPagesCount' => count($sitemapPages['urls']),
                    'unaccountedLinksCount' => count($unaccountedLinks),
                    'totalLinksCount' => $totalLinksNumber,
                    'totalUniqueLinksCount' => $uniqueLinksNumber,
                    'existingNewsCount' => count($existingNews),
                    'unavailableNewsCount' => count($unavailableNews),
                ]
            ],
            Config::RESULT_FILE
        );

        self::saveFile(
            [
                'urls' => $unavailableNews
            ],
            Config::UNAVAILABLE_NEWS_FILENAME,
            null,
            null,
            Config::FILE_TYPE_RESULT
        );
        self::saveFile(
            [
                'links' => $linksWithoutFiles
            ],
            Config::LINKS_WITHOUT_FILES_FILENAME,
            null,
            null,
            Config::FILE_TYPE_RESULT
        );
        self::saveFile(
            [
                'files' => $filesWithoutLinks
            ],
            Config::FILES_WITHOUT_LINKS_FILENAME,
            null,
            null,
            Config::FILE_TYPE_RESULT
        );
    }
}