<?php

require_once './Config.php';

/**
 * Class FileSystemDocumentParser
 */
class FileSystemDocumentParser
{

    public $allFiles = [];

    public $allDirectories = [];

    public static function scanNewsDirectories(): array
    {
        $newsDirs = scandir(Config::FILESYSTEM_PATH.Config::NEWS_FILES_PATH, 0);
        $outputDirs = [];
        foreach ($newsDirs as $dir) {
            if ($dir !== '.' && $dir !== '..') {
                $outputDirs[] = Config::DOMAIN . Config::NEWS_URL . '/' . $dir;
            }
        }
        return $outputDirs;
    }

    public function scanDirectories(): void
    {
        foreach (Config::DIRECTORIES_TO_SCAN as $dir) {
            $this->scanDirectory($dir);
        }
    }

    public function scanDirectory(string $dirname): void
    {
        if (file_exists(Config::FILESYSTEM_PATH . $dirname) && is_dir(Config::FILESYSTEM_PATH . $dirname)) {
            $dirFiles = scandir(Config::FILESYSTEM_PATH . $dirname, 0);
            $directories = [];
            foreach ($dirFiles as $file) {
                if (is_dir(Config::FILESYSTEM_PATH . $dirname . '/' . $file)) {
                    if ($file !== '.' && $file !== '..' && array_search($file, Config::EXCEPT_DIRECTORIES) === false) {
                        $directories[] = $file;
                    }
                }
            }
            foreach ($directories as $dir) {
                $this->scanDirectory($dirname . '/' . $dir);
            }
            $this->allDirectories[] = mb_convert_encoding($dirname, 'UTF-8', 'UTF-8');
        }
    }

    public function parseAllFiles()
    {
        foreach ($this->allDirectories as $dir) {
            $this->readDirectoryFiles($dir);
        }
    }

    public static function readDirectoryFiles(string $dirname): array
    {
        $files = [
            'files_count'=> 0,
            'files' => []
        ];

        if (file_exists(Config::FILESYSTEM_PATH . $dirname) && is_dir(Config::FILESYSTEM_PATH . $dirname)) {
            $dirFiles = scandir(Config::FILESYSTEM_PATH . $dirname, 0);
            foreach ($dirFiles as $file) {
                if (is_file(Config::FILESYSTEM_PATH . $dirname . '/' . $file)) {
                    if (($filetype = self::getFileType($file)) === 0) {
                        continue;
                    }
                    $files['files_count']++;
                    $fullFilename =Config::FILESYSTEM_PATH . $dirname . '/' . $file;
                    $fileSizeInBytes = filesize($fullFilename) ?? 0;
                    $fileSizeAsString = $fileSizeInBytes < 1048576 ?
                        number_format($fileSizeInBytes / 1024, 2) . ' KB' :
                        number_format($fileSizeInBytes / 1048576, 2) . ' MB';
                    $createTime = filectime($fullFilename) ?? 0;

                    if (!isset($files[$filetype])) {
                        $files[$filetype] = [];
                    }
                    $filesHref = mb_convert_encoding(Config::DOMAIN . $dirname . '/' . $file,'UTF-8', 'UTF-8');
                    $files['files'][$filetype][$filesHref] =
                        [
                            'path' =>  mb_convert_encoding($fullFilename, 'UTF-8', 'UTF-8'),
                            'sizeAsString' => $fileSizeAsString,
                            'href' =>  $filesHref,
                            'createDate' => date_format(date_create("@$createTime"), 'c'),
                        ];
                }
            }
        }
        return $files;
    }

    public static function getFileType(string $filename): int
    {
        foreach (Config::FILES_EXTENSIONS as $type => $arrayExt) {
            $regexTypes = join('|', $arrayExt);
            if (preg_match("/\.($regexTypes)/", $filename)) {
                return $type;
            }
        }
        return Config::FILETYPE_LINK;
    }
}