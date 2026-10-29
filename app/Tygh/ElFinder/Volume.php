<?php
/***************************************************************************
*                                                                          *
*   © 2012 ООО "Эком Системы"                                              *
*                                                                          *
* Это коммерческое программное обеспечение. Только пользователи, которые   *
* приобрели действующую лицензию и согласились с условиями лицензионного   *
* соглашения, могут устанавливать и использовать эту программу.            *
*                                                                          *
****************************************************************************
* ПОЖАЛУЙСТА, ВНИМАТЕЛЬНО ПРОЧТИТЕ ПОЛНЫЙ ТЕКСТ ЛИЦЕНЗИОННОГО СОГЛАШЕНИЯ   *
* В ФАЙЛЕ "copyright.txt", ПРЕДОСТАВЛЕННОМ ВМЕСТЕ С ЭТИМ ДИСТРИБУТИВОМ.    *
***************************************************************************/

namespace Tygh\ElFinder;

use Exception;

/**
 * Local file system volume driver for the built-in file manager.
 *
 * phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
 */
class Volume extends \elFinderVolumeLocalFileSystem
{
    /**
     * Pattern for names of files and directories that can be created, renamed or uploaded.
     * A leading dot, path separators and NUL bytes are not allowed,
     * so a name from a client can never point outside of the directory it is created in.
     */
    const ACCEPTED_NAME_PATTERN = '/^[^.\/\\\\\x00][^\/\\\\\x00]*$/';

    /**
     * Additional extensions/mimetypes for mimeDetect == 'internal' 
     *
     * @var array
     */
    protected static $mimetypes = [
        'htaccess' => 'application/x-extension-htaccess',
    ];

    /**
     * Applies the strict accepted-name pattern on top of the parent driver defaults.
     */
    public function __construct()
    {
        parent::__construct();

        $this->options['acceptedName'] = self::ACCEPTED_NAME_PATTERN;
    }

    /**
     * Joins a directory path and a file name, keeping the result inside the directory.
     *
     * Names reach this method not only from validated client input but also from decoded hashes and internal calls,
     * so a name that contains a parent directory reference is reduced to its last real segment as the last line of defense.
     *
     * @param string $dir  Directory path
     * @param string $name File or directory name
     *
     * @return string
     */
    protected function _joinPath($dir, $name)
    {
        $segments = preg_split('/[\/\\\\]/', $name);

        if (in_array('..', $segments, true)) {
            $segments = array_diff($segments, ['..', '.', '']);
            $name = (string) end($segments);
        }

        return parent::_joinPath($dir, $name);
    }

    /**
     * Return file mimetype
     *
     * @param  string  $path  File path
     * 
     * @return string
     */
    protected function mimetype($path, $name = '', $size = null, $mime = null)
    {
        $type = parent::mimetype($path, $name);
        return $type == 'unknown' ? self::mimetypeInternalDetect($path) : $type;
    }

    protected function getArchivers($use_cache = true)
    {
        $arcs = array(
            'create'  => array(),
            'extract' => array()
        );

        $obj = $this; // php 5.3 compatibility

        if (class_exists('ZipArchive')) {
            $arcs['extract']['application/zip'] = [
                'cmd'  => 'phpfunction',
                'argc' => function ($archive, $path) use ($obj) {
                    $zip = new \ZipArchive();
                    if ($zip->open($archive)) {
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $stat = $zip->statIndex($i);
                            if (empty($stat['size'])) { // directory
                                continue;
                            }
                            $filename = $stat['name'];

                            if (strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
                                throw new Exception(__('file_editor_archive_contains_incorrect_paths'));
                            }

                            $newfile = $obj->tyghDecodeFilename($filename);

                            $obj->tyghMkdir(dirname($path . '/' . $newfile));

                            copy('zip://' . $archive . '#' . $filename, $path . '/' . $newfile);
                        }
                        $zip->close();

                        return true;
                    }

                    return false;
                },
                'ext'  => 'zip',
            ];

            $arcs['create']['application/zip'] = [
                'cmd'  => 'phpfunction',
                'argc' => function ($base_path, $files, $archive) use ($obj) {
                    $zip = new \ZipArchive();
                    if ($zip->open($base_path . DIRECTORY_SEPARATOR . $archive, \ZipArchive::CREATE) === true) {
                        foreach ($files as $file) {
                            $path = $base_path . DIRECTORY_SEPARATOR . $file;
                            if (is_file($path)) {
                                $zip->addFile($path, $obj->tyghEncodeFilename($file));
                            } elseif (is_dir($path)) {

                                foreach ($obj->tyghGetFiles($path) as $_file) {
                                    $zip->addFile(
                                        $path . DIRECTORY_SEPARATOR . $_file,
                                        $obj->tyghEncodeFilename($_file)
                                    );
                                }
                            }
                        }
                        $zip->close();

                        return true;
                    }

                    return false;
                },
                'ext'  => 'zip',
            ];
        }

        if (class_exists('PharData')) {
            $arcs['extract']['application/x-gzip'] = [
                'cmd'  => 'phpfunction',
                'argc' => function ($archive, $path) {
                    $phar = new \PharData($archive);
                    $phar->extractTo($path, null, true);
                },
                'ext'  => 'tgz',
            ];
        }

        return $arcs;
    }

    public function tyghGetFiles($path, $base_path = '')
    {
        $files = array();
        if ($dh = opendir($path)) {
            while (false !== ($file = readdir($dh))) {
                if ($file == '.' || $file == '..') {
                    continue;
                }

                $file_path = $path . '/' . $file;
                if (is_dir($file_path)) {
                    $files += $this->tyghGetFiles($file_path, (!empty($base_path) ? $base_path . '/' : '') . $file);
                } else {
                    $files[] = (!empty($base_path) ? $base_path . '/' : '') . $file;
                }
            }
            closedir($dh);
        }

        return $files;
    }

    public function tyghIsUTF8($str)
    {
        $c = 0; $b = 0;
        $bits = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($str[$i]);
            if ($c > 128) {
                if (($c >= 254)) {
                    return false;
                } elseif ($c >= 252) {
                    $bits = 6;
                } elseif ($c >= 248) {
                    $bits = 5;
                } elseif ($c >= 240) {
                    $bits = 4;
                } elseif ($c >= 224) {
                    $bits = 3;
                } elseif ($c >= 192) {
                    $bits = 2;
                } else {
                    return false;
                }

                if (($i + $bits) > $len) {
                    return false;
                }

                while ($bits > 1) {
                    $i++;
                    $b = ord($str[$i]);
                    if ($b < 128 || $b > 191) {
                        return false;
                    }
                    $bits--;
                }
            }
        }

        return true;
    }

    public function tyghMkdir($dir, $perms = 0777)
    {
        if (!is_dir($dir)) {
            $old = umask(0);
            $res = mkdir($dir, $perms, true);
            umask($old);

            return $res;
        }
    }

    /**
     * Encodes non-UTF-8 filenames for storing in ZIP archive
     *
     * @param  string $filename Name of a file to be archived
     * @return string Encoded filename
     */
    public function tyghEncodeFilename($filename)
    {
        if ($this->tyghIsUTF8($filename) && function_exists('iconv')) {
            return iconv('utf-8', 'cp866', $filename);
        }

        return $filename;
    }

    /**
     * Decodes non-UTF-8 filenames when extracting ZIP archive
     *
     * @param  string $filename Name of a file to be extracted
     * @return string Decoded filename
     */
    public function tyghDecodeFilename($filename)
    {
        if (!$this->tyghIsUTF8($filename) && function_exists('iconv')) {
            return iconv('cp866', 'utf-8', $filename);
        }

        return $filename;
    }

    /**
     * Provides list of mime types of files that can't be created, renamed or uploaded.
     *
     * @return string[] Denied mime types
     */
    public function tyghGetDeniedMimeTypes()
    {
        return array_combine(
            $this->uploadDeny,
            $this->uploadDeny
        );
    }
    
    /**
     * Return Extention/MIME Table (elFinderVolumeDriver::$mimetypes and Volume::$mimetypes)
     * 
     * @return array
     */
    public function getMimeTable() 
    {
        return parent::getMimeTable() + self::$mimetypes;
    }
}
