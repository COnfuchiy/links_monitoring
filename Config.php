<?php


class Config
{
    public const DOMAIN = '';

    public const FILESYSTEM_PATH = '';

    public const FILESYSTEM_PATH = '';

    public const NEWS_FILES_PATH = '/images/news';

    public const NEWS_URL = '/news-vpi';

    public const DATA_PATH = '';

    public const RESULT_PATH = '';

    public const FILE_TYPE_DATA = 'data';

    public const FILE_TYPE_RESULT = 'result';

    public const SITEMAP_FILENAME = 'sitemap.json';

    public const ALL_PAGES_FILENAME = 'all_pages.json';

    public const NEWS_LINKS_FILENAME = 'news_urls.json';

    public const NEWS_LINKS_DATA_FILENAME = 'news_urls_data.json';

    public const LINKS_DATA_FILENAME = 'links_data.json';

    public const GLOBAL_LINKS_FILENAME = 'global_links.json';

    public const PAGES_DATA_FILENAME = 'pages_data.json';

    public const DIRECTORIES_FILENAME = 'directories.json';

    public const DIRECTORIES_DATA_FILENAME = 'directories_data.json';

    public const FILES_DATA_FILENAME = 'files.json';

    public const RESULT_FILE = 'result.json';

    public const EXISTING_NEWS_FILENAME = 'existing_news.json';

    public const UNAVAILABLE_NEWS_FILENAME = 'unavailable_news.json';

    public const ANALYZED_LINKS_FILENAME = 'analyzed_links.json';

    public const LINKS_WITHOUT_FILES_FILENAME = 'links_without_files.json';

    public const FILES_WITHOUT_LINKS_FILENAME = 'files_without_links.json';

    public const EXTERNAL_FILES_LINKS_FILENAME = 'external_files_links.json';

    public const ERROR_LINKS_FILENAME = 'error_links.json';

    public const FILETYPE_LINK = 0;

    public const FILETYPE_DOCUMENT = 1;

    public const FILETYPE_IMG = 2;

    public const FILETYPE_CSS = 3;

    public const FILETYPE_JS = 4;

    public const FILES_EXTENSIONS = [
        self::FILETYPE_DOCUMENT => [
            'rar',
            'zip',
            'pdf',
            'doc',
            'docx',
            'xls',
            'ppt',
            'pptx',
            'rtf',
            'xlsx',
            'eps'
        ],
        self::FILETYPE_IMG => [
            'jpeg',
            'jpg',
            'webp',
            'png',
            'svg',
            'gif',
            'ico',
            'jfif',
        ],
        self::FILETYPE_CSS => [
            'css',
            'woff2',
            'woff',
            'ttf',
            'eot',
        ],
        self::FILETYPE_JS => [
            'js',
        ],
    ];

    public const DIRECTORIES_TO_SCAN = [
        '/files',
        '/images',
        '/admin-assets/images/',
        '/afisha-assets/images/',
        '/css',
        '/js',
    ];

    public const EXCEPT_DIRECTORIES = [
        'news'
    ];
}