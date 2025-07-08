<?php
/**
 * FileDownloadR
 *
 * Copyright 2011-2022 by Rico Goldsky <goldsky@virtudraft.com>
 * Copyright 2023-2025 by Thomas Jakobi <office@treehillstudio.com>
 *
 * @package filedownloadr
 * @subpackage classfile
 */

namespace TreehillStudio\FileDownloadR;

use Exception;
use fdPaths;
use IPInfoDB\Api;
use Mimey\MimeTypes;
use modLexicon;
use modMediaSource;
use modUserGroupMember;
use modX;
use TreehillStudio\FileDownloadR\Helper\Parse;
use xPDO;

/**
 * Class FileDownloadR
 */
class FileDownloadR
{
    /**
     * A reference to the modX instance
     * @var modX $modx
     */
    public $modx;

    /**
     * The namespace
     * @var string $namespace
     */
    public $namespace = 'filedownloadr';

    /**
     * The package name
     * @var string $packageName
     */
    public $packageName = 'FileDownloadR';

    /**
     * The version
     * @var string $version
     */
    public $version = '3.2.1';

    /**
     * The class options
     * @var array $options
     */
    public $options = [];

    /**
     * @var Parse $parse
     */
    public $parse = null;

    /**
     * @var null|modMediaSource $mediaSource
     */
    public $mediaSource;

    /**
     * To hold error message
     * @var array $_error
     */
    private $_error = [];

    /**
     * To hold output message
     * @var array $_output
     */
    private $_output;

    /**
     * To hold counting
     * @var array $_count
     */
    private $_count = [];

    /**
     * To hold image type
     * @var array $imgTypes
     */
    private $imgTypes;

    /**
     * FileDownloadR constructor
     *
     * @param modX $modx A reference to the modX instance.
     * @param array $options An array of options. Optional.
     */
    public function __construct(modX &$modx, $options = [])
    {
        $this->modx =& $modx;

        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/' . $this->namespace . '/');
        $assetsPath = $this->getOption('assets_path', $options, $this->modx->getOption('assets_path', null, MODX_ASSETS_PATH) . 'components/' . $this->namespace . '/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/' . $this->namespace . '/');
        $modxversion = $this->modx->getVersionData();

        // Load some default paths for easier management
        $this->options = array_merge([
            'namespace' => $this->namespace,
            'version' => $this->version,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'vendorPath' => $corePath . 'vendor/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'pagesPath' => $corePath . 'elements/pages/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'pluginsPath' => $corePath . 'elements/plugins/',
            'controllersPath' => $corePath . 'controllers/',
            'processorsPath' => $corePath . 'processors/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'imagesUrl' => $assetsUrl . 'images/',
            'connectorUrl' => $assetsUrl . 'connector.php',
        ], $options);

        $this->_output = [
            'rows' => [],
            'dirRows' => [],
            'fileRows' => []
        ];

        $lexicon = $this->modx->getService('lexicon', modLexicon::class);
        $lexicon->load($this->namespace . ':default');

        $this->packageName = $this->modx->lexicon('filedownloadr');

        $this->modx->addPackage($this->namespace, $this->getOption('modelPath'));

        // Add default options
        $this->options = array_merge($this->options, [
            'debug' => $this->getBooleanOption('debug', [], false),
            'modxversion' => $modxversion['version'],
            'imgLocat' => $assetsUrl . 'img/filetypes/',
            'imgTypes' => 'fdimages',
            'encoding' => 'utf-8',
            'exclude_scan' => $this->getExplodedOption('exclude_scan', [], '.,..,Thumbs.db,.htaccess,.htpasswd,.ftpquota,.DS_Store'),
            'email_props' => $this->getJsonOption('email_props', [], ''),
            'extended_file_fields' => json_decode($this->getBoundOption('extended_file_fields', [], '[]'), true) ?? [],
        ]);

        $this->parse = new Parse($this->modx);

        $this->imgTypes = $this->getImagetypes();
        if (empty($this->imgTypes)) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Could not load image types.', '', 'FileDownloadR', __FILE__, __LINE__);
            return;
        }
        if (!empty($this->getOption('encoding'))) {
            mb_internal_encoding($this->getOption('encoding'));
        }

        if (!empty($this->getOption('mediaSourceId'))) {
            $this->mediaSource = $this->modx->getObject('sources.modMediaSource', ['id' => $this->getOption('mediaSourceId')]);
            if ($this->mediaSource) {
                $this->mediaSource->initialize();
            }
        }

        $this->options['directorySeparator'] = (empty($this->mediaSource)) ? DIRECTORY_SEPARATOR : '/';
        $this->options['getDir'] = !empty($this->getOption('getDir')) ? $this->checkPath($this->getOption('getDir')) : '';
        $this->options['getFile'] = !empty($this->getOption('getFile')) ? $this->checkPath($this->getOption('getFile')) : '';
        $this->options['origDir'] = $this->options['getDir'];
        $this->options['extendedFields'] = !empty($this->options['extended_file_fields']);
        $this->options = $this->replacePathProperties($this->options);
    }

    /**
     * Get a local configuration option or a namespaced system setting by key.
     *
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * namespaced system setting; by default this value is null.
     * @return mixed The option value or the default value specified.
     */
    public function getOption($key, $options = [], $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("$this->namespace.$key", $this->modx->config)) {
                $option = $this->modx->getOption("$this->namespace.$key");
            }
        }
        return $option;
    }

    /**
     * Get Boolean Option.
     *
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return bool
     */
    public function getBooleanOption($key, $options = [], $default = null)
    {
        $option = $this->getOption($key, $options, $default);
        return ($option === 'true' || $option === true || $option === '1' || $option === 1);
    }

    /**
     * Get Exploded Comma Separated Option.
     *
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return array
     */
    public function getExplodedOption($key, $options = [], $default = null)
    {
        $value = $this->getOption($key, $options, $default);
        return (is_string($value) && $value !== '') ? array_map('trim', explode(',', $value)) : [];
    }

    /**
     * Get JSON Option.
     *
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return array
     */
    public function getJsonOption($key, $options = [], $default = null)
    {
        $value = json_decode($this->getOption($key, $options, $default ?? ''), true);
        return (is_array($value)) ? $value : [];
    }

    /**
     * Get Bound Option
     *
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return mixed
     */
    public function getBoundOption($key, $options = [], $default = null)
    {
        $value = trim($this->getOption($key, $options, $default));
        if (strpos($value, '@FILE') === 0) {
            $path = trim(substr($value, strlen('@FILE')));
            // Sanitize to avoid ../ style path traversal
            $path = preg_replace(["/\.*[\/|\\\]/i", "/[\/|\\\]+/i"], ['/', '/'], $path);
            // Include only files inside the MODX base path
            if (strpos($path, MODX_BASE_PATH) === 0 && file_exists($path)) {
                $value = file_get_contents($path);
            }
        } elseif (strpos($value, '@CHUNK') === 0) {
            $name = trim(substr($value, strlen('@CHUNK')));
            $chunk = $this->modx->getObject('modChunk', ['name' => $name]);
            $value = ($chunk) ? $chunk->get('snippet') : '';
        }
        return $value;
    }

    /**
     * Retrieve the images for the specified file extensions.
     *
     * @return array file type's images
     */
    private function getImagetypes()
    {
        if (empty($this->getOption('imgTypes'))) {
            return [];
        }
        $fdImagesChunk = $this->parse->getChunk($this->getOption('imgTypes'));
        $fdImagesChunkX = @explode(',', $fdImagesChunk);
        $imgType = [];
        foreach ($fdImagesChunkX as $v) {
            $typeX = @explode('=', $v);
            $imgType[strtolower(trim($typeX[0]))] = trim($typeX[1]);
        }

        return $imgType;
    }

    /**
     * Get the clean path array and clean up some duplicate slashes.
     *
     * @param string $paths multiple paths with comma separated
     * @return array Dir paths in an array
     */
    private function checkPath($paths)
    {
        $forbiddenDirectories = [
            realpath(MODX_CORE_PATH),
            realpath(MODX_PROCESSORS_PATH),
            realpath(MODX_CONNECTORS_PATH),
            realpath(MODX_MANAGER_PATH),
            realpath(MODX_BASE_PATH)
        ];
        $cleanPaths = [];
        if (!empty($paths)) {
            $paths = explode(',', $paths);
            foreach ($paths as $path) {
                if (empty($path)) {
                    continue;
                }
                $path = $this->trimString($path);
                if (empty($this->mediaSource)) {
                    $fullPath = realpath($path);
                    if (empty($fullPath)) {
                        $fullPath = realpath(MODX_BASE_PATH . $path);
                        if (empty($fullPath)) {
                            continue;
                        }
                    }
                } else {
                    $fullPath = $path;
                }
                if (in_array($fullPath, $forbiddenDirectories)) {
                    continue;
                }
                $cleanPaths[] = $fullPath;
            }
        }

        return $cleanPaths;
    }

    /**
     * Replace path properties.
     *
     * @param string|array $subject Property
     * @return string|array The replaced results
     */
    public function replacePathProperties($subject)
    {
        $replacements = [
            '{core_path}' => $this->modx->getOption('core_path'),
            '{base_path}' => $this->modx->getOption('base_path'),
            '{assets_url}' => $this->modx->getOption('assets_url'),
            '{fd_assets_url}' => $this->getOption('assetsUrl'),
            '{filemanager_path}' => $this->modx->getOption('filemanager_path'),
            '[[++core_path]]' => $this->modx->getOption('core_path'),
            '[[++base_path]]' => $this->modx->getOption('base_path')
        ];
        if (is_array($subject)) {
            return array_map(function ($v) {
                return $this->replacePathProperties($v);
            }, $subject);
        } else {
            if (is_string($subject)) {
                $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
            }
            return $subject;
        }
    }

    /**
     * Trim string value.
     *
     * @param string $string source text
     * @param string $charlist defined characters to be trimmed
     * @return string trimmed text
     * @link http://php.net/manual/en/function.trim.php
     */
    private function trimString($string, $charlist = null)
    {
        if (empty($string) && !is_numeric($string)) {
            return '';
        }
        $string = trim($string, $charlist);
        $string = trim(preg_replace('/(\r|\n|\r\n)/', ' ', $string));
        return preg_replace('/\s+/i', ' ', $string);
    }

    /**
     * Check if the referrer is the current page, otherwise redirect to the unauthorized page.
     */
    public function checkReferrer()
    {
        $ref = $_SERVER['HTTP_REFERER'];
        // deal with multiple snippets which have &browseDirectories
        $xRef = @explode('?', $ref);
        $queries = [];
        parse_str($xRef[1] ?? '', $queries);
        if (!empty($queries['id'])) {
            // non FURL
            $baseRef = $xRef[0] . '?id=' . $queries['id'];
        } else {
            $baseRef = $xRef[0];
        }
        $baseRef = urldecode($baseRef);
        if ($this->modx->resource) {
            $page = $this->modx->makeUrl($this->modx->resource->get('id'), '', '', 'full');
        } else {
            $page = false;
        }
        // check referrer and the page
        if ($baseRef !== $page) {
            $this->modx->sendUnauthorizedPage();
        }
    }

    /**
     * Set class configuration exclusively for multiple snippet calls.
     *
     * @param array $config snippet's parameters
     */
    public function initConfig($config = [])
    {
        if (!empty($config['mediaSourceId'])) {
            $this->mediaSource = $this->modx->getObject('sources.modMediaSource', ['id' => $config['mediaSourceId']]);
            if ($this->mediaSource) {
                $this->mediaSource->initialize();
            }
        }

        // Clear previous output for subsequent snippet calls
        $this->_output = [
            'rows' => [],
            'dirRows' => [],
            'fileRows' => []
        ];
        $this->_error = [];

        $config = $this->replacePathProperties($config);

        $config['getDir'] = (!empty($config['getDir'])) ? $this->checkPath($config['getDir']) : [''];
        $config['getFile'] = (!empty($config['getFile'])) ? $this->checkPath($config['getFile']) : '';
        $config['origDir'] = $config['getDir'];

        if (!empty($config['uploadFile'])) {
            $mimes = new MimeTypes;
            $fileTypes = [];
            $fileExtensions = [];
            if (!empty($config['uploadFileTypes'])) {
                foreach ($config['uploadFileTypes'] as $uploadFileType) {
                    if ($mimes->getExtension($uploadFileType)) {
                        $fileTypes[] = $uploadFileType;
                        $fileExtensions[] = $mimes->getExtension($uploadFileType);
                    } elseif ($mimes->getMimeType($uploadFileType)) {
                        $fileTypes[] = $mimes->getMimeType(ltrim($uploadFileType, '.'));
                        $fileExtensions[] = $uploadFileType;
                    }
                }
            }
            $config['uploadFileTypes'] = $fileTypes;
            $config['uploadFileExtensions'] = $fileExtensions;
        }

        $config['imgTypes'] = (!empty($config['imgTypes'])) ? $config['imgTypes'] : $this->getOption('imgTypes');
        $this->options = array_merge($this->options, $config);

        $this->imgTypes = $this->getImagetypes();
    }

    /**
     * Get the download counts of a path
     *
     * @param $path
     * @return array|string|string[]
     */
    public function getDirCount($path)
    {
        $getDir = $this->getAbsolutePath($path);
        $phs = [];
        if ($getDir) {
            $fdPath = $this->getFdPath([
                'ctx' => $this->modx->context->key,
                'filename' => $getDir,
            ]);
            if ($fdPath) {
                $dirPath = rtrim($fdPath->get('filename'), $this->getOption('directorySeparator'));
                $phs = array_merge($this->options, [
                    'filename' => $this->basename($dirPath),
                ]);
                foreach ($this->getDownloadCounts($phs, $dirPath) as $key => $value) {
                    $phs[$this->getOption('prefix') . $key] = $value;
                }
            }
        }
        return $phs;
    }

    /**
     * Get absolute path of the given relative path, based on media source.
     *
     * @param string $path relative path
     * @return string absolute path
     */
    private function getAbsolutePath($path)
    {
        $output = '';
        if (empty($this->mediaSource)) {
            $output = realpath($path) . $this->getOption('directorySeparator');
        } else {
            if ($this->mediaSource->getBasePath($path)) {
                $output = rtrim($this->mediaSource->getBasePath($path) . $path, $this->getOption('directorySeparator')) . $this->getOption('directorySeparator');
            } elseif ($this->mediaSource->getObjectUrl($path)) {
                $output = rtrim($this->mediaSource->getObjectUrl($path) . $path, $this->getOption('directorySeparator')) . $this->getOption('directorySeparator');
            }
        }
        return $output;
    }

    /**
     * Custom basename, because PHP's basename can not read Chinese characters.
     *
     * @param string $path full path
     */
    private function basename($path)
    {
        $parts = explode($this->getOption('directorySeparator'), $path);
        $parts = array_reverse($parts);

        return $parts[0];
    }

    /**
     * Get Download Counts
     *
     * @param array $array
     * @param string $fullPath
     * @return array
     */
    private function getDownloadCounts(array $array, string $fullPath)
    {
        $pathIds = $this->getPathIds($fullPath, $this->modx->context->key);
        if (!empty($pathIds)) {
            if ($this->getOption('countDownloads')) {
                $c = $this->modx->newQuery('fdDownloads');
                $c->where([
                    'fdDownloads.path_id:IN' => $pathIds
                ]);
                $c->groupby('fdDownloads.path_id');
                $array['count'] = $this->modx->getCount('fdDownloads', $c);
                $d = $this->modx->newQuery('fdPaths');
                $d->leftJoin('fdDownloads', 'Downloads');
                $d->where([
                    'fdPaths.id:IN' => $pathIds,
                    'Downloads.id:IS' => null
                ]);
                $d->groupby('fdPaths.id');
                $array['count_not'] = $this->modx->getCount('fdPaths', $d);
            }
            if ($this->getOption('countUserDownloads') && $this->modx->user) {
                $c = $this->modx->newQuery('fdDownloads');
                $c->where([
                    'fdDownloads.path_id:IN' => $pathIds,
                    'fdDownloads.user' => $this->modx->user->get('id')
                ]);
                $c->groupby('fdDownloads.path_id');
                $array['count_user'] = $this->modx->getCount('fdDownloads', $c);
                $d = $this->modx->newQuery('fdPaths');
                $d->leftJoin('fdDownloads', 'Downloads');
                $d->where([
                    'fdPaths.id:IN' => $pathIds,
                ]);
                $d->having('SUM(CASE WHEN Downloads.user = ' . $this->modx->user->get('id') . ' THEN 1 ELSE 0 END) = 0');
                $d->groupby('fdPaths.id');
                $array['count_user_not'] = $this->modx->getCount('fdPaths', $d);
            }
        }
        return $array;
    }

    /**
     * Get dynamic file's basepath.
     *
     * @param string $filename file's name
     * @return string
     */
    private function getBasePath($filename)
    {
        if (!empty($this->mediaSource)) {
            if ($this->mediaSource->getBasePath($filename)) {
                return $this->mediaSource->getBasePath($filename);
            } elseif ($this->mediaSource->getBaseUrl()) {
                return $this->mediaSource->getBaseUrl();
            }
        }
        return false;
    }

    /**
     * Sets the salted parameter to the database.
     *
     * @param string $ctx context
     * @param string $filename filename
     * @return string hashed parameter
     */
    private function setHashedParam($ctx, $filename)
    {
        $input = $this->getOption('saltText') . $ctx . $this->getOption('mediaSourceId') . $filename;
        return str_rot13(base64_encode(hash('sha512', $input)));
    }

    /**
     * Set string error for boolean returned methods.
     *
     * @param string $msg
     */
    private function setError($msg)
    {
        $this->_error[] = $msg;
    }

    /**
     * Get the path IDs of all files/directories in the directory
     *
     * @param string $fullPath
     * @param string $ctx
     * @return array
     */
    private function getPathIds(string $fullPath, $ctx)
    {
        $c = $this->modx->newQuery('fdPaths');
        $c->where([
            'filename:LIKE' => $fullPath . $this->getOption('directorySeparator') . '%',
            'media_source_id' => $this->getOption('mediaSourceId'),
            'ctx' => $ctx,
        ]);
        $c->where(['filename:NOT LIKE' => '%/']);
        $paths = $this->modx->getIterator('fdPaths', $c);
        $pathIds = [];
        foreach ($paths as $path) {
            $pathIds[] = $path->get('id');
        }
        return $pathIds;
    }

    /**
     * Retrieve the content of the given path.
     *
     * @return array All contents in an array
     */
    public function getContents()
    {
        try {
            $this->modx->invokeEvent('OnFileDownloadLoad');
        } catch (Exception $e) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadLoad: ' . $e->getMessage());
            return [];
        }

        $dirContents = [];
        if (!empty($this->getOption('getDir')) || $this->mediaSource) {
            $dirContents = $this->getDirContents($this->getOption('getDir'));
            if (!$dirContents) {
                $dirContents = [];
            }
        }
        $fileContents = [];
        if (!empty($this->getOption('getFile'))) {
            $fileContents = $this->getFileContents($this->getOption('getFile'));
            if (!$fileContents) {
                $fileContents = [];
            }
        }
        $mergedContents = array_merge($dirContents, $fileContents);
        $mergedContents = $this->checkDuplication($mergedContents);
        $mergedContents = $this->getDescription($mergedContents);
        return $this->sortOrder($mergedContents);
    }

    /**
     * Retrieve the content of the given directory path.
     *
     * @param array $paths The specified root path
     * @return array Dir's contents in an array
     */
    private function getDirContents(array $paths = [])
    {
        if (empty($paths)) {
            return [];
        }

        $contents = [];
        foreach ($paths as $rootPath) {
            if (empty($this->mediaSource)) {
                $rootRealPath = realpath($rootPath);
                if (!is_dir($rootPath) || empty($rootRealPath)) {
                    if ($rootPath) {
                        $this->modx->log(xPDO::LOG_LEVEL_ERROR, '&getDir parameter expects a correct dir path. <b>"' . $rootPath . '"</b> is given.', '', 'FileDownloadR', __FILE__, __LINE__);
                    }
                    return [];
                }
            }

            try {
                $result = $this->modx->invokeEvent('OnFileDownloadBeforeDirOpen', [
                    'dirPath' => $rootPath,
                ]);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadBeforeDirOpen: ' . $e->getMessage());
                return [];
            }
            if (is_array($result)) {
                foreach ($result as $msg) {
                    if ($msg === false) {
                        return [];
                    } elseif ($msg === 'continue') {
                        continue 2;
                    }
                }
            }

            if (empty($this->mediaSource)) {
                $contents = $this->getPathDirContents($rootPath, $contents);
            } else {
                $contents = $this->getMediasourceDirContents($rootPath, $contents);
            }

            try {
                $result = $this->modx->invokeEvent('OnFileDownloadAfterDirOpen', [
                    'dirPath' => $rootPath,
                    'contents' => $contents,
                ]);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadAfterDirOpen: ' . $e->getMessage());
                return [];
            }
            if (is_array($result)) {
                foreach ($result as $msg) {
                    if ($msg === false) {
                        return [];
                    } elseif ($msg === 'continue') {
                        continue 2;
                    }
                }
            }
        }

        return $contents;
    }

    /**
     * Retrieve the content of the given file path.
     *
     * @param array $paths The specified file path
     * @return array File contents in an array
     */
    private function getFileContents(array $paths = [])
    {
        $contents = [];
        foreach ($paths as $fileRow) {
            $fileInfo = $this->fileInformation($fileRow);
            if (!$fileInfo) {
                continue;
            }
            $contents[] = $fileInfo;
        }

        return $contents;
    }

    /**
     * Check any duplication output.
     *
     * @param array $mergedContents merging the &getDir and &getFile result
     * @return array Unique filenames
     */
    private function checkDuplication(array $mergedContents)
    {
        if (empty($mergedContents)) {
            return $mergedContents;
        }

        $this->_count['dirs'] = 0;
        $this->_count['files'] = 0;

        $c = [];
        $d = [];
        foreach ($mergedContents as $content) {
            if (isset($c[$content['fullPath']])) {
                continue;
            }

            $c[$content['fullPath']] = $content;
            $d[] = $content;

            if ($content['type'] === 'dir') {
                $this->_count['dirs']++;
            } else {
                $this->_count['files']++;
            }
        }

        return $d;
    }

    /**
     * Existed description from the chunk of the &chkDesc parameter.
     *
     * @param array $contents
     * @return array
     */
    private function getDescription(array $contents)
    {
        if (empty($contents)) {
            return $contents;
        }

        if (empty($this->getOption('chkDesc'))) {
            foreach ($contents as $key => $file) {
                $contents[$key]['description'] = '';
            }
            return $contents;
        }

        $chunkContent = $this->modx->getChunk($this->getOption('chkDesc'));

        $linesX = (!empty($chunkContent)) ? array_map('trim', explode('||', $chunkContent)) : [];
        foreach ($linesX as $k => $v) {
            if (empty($v)) {
                unset($linesX[$k]);
                continue;
            }
            $descX = array_map('trim', explode('|', $v));
            $realPath = realpath($this->replacePathProperties($descX[0]));

            if (!$realPath) {
                continue;
            }

            $desc[$realPath] = $descX[1];
        }

        foreach ($contents as $key => $file) {
            $contents[$key]['description'] = '';
            if (isset($desc[$file['fullPath']])) {
                $contents[$key]['description'] = $desc[$file['fullPath']];
            }
        }

        return $contents;
    }

    /**
     * Manage the order sorting by all sorting parameters:
     * - sortBy
     * - sortOrder
     * - sortOrderNatural
     * - sortByCaseSensitive
     * - browseDirectories
     * - groupByDirectory
     *
     * @param array $contents unsorted contents
     * @return array sorted contents
     *
     * @TODO Separate output from sorting
     */
    private function sortOrder(array $contents)
    {
        if (empty($contents)) {
            return $contents;
        }
        if (empty($this->getOption('groupByDirectory'))) {
            $sort = $this->groupByType($contents);
        } else {
            $sortPath = [];
            foreach ($contents as $k => $file) {
                if (!$this->getOption('browseDirectories') && $file['type'] === 'dir') {
                    continue;
                }
                $sortPath[$file['path']][$k] = $file;
            }

            $sort = [];
            foreach ($sortPath as $k => $path) {
                // path name for the &groupByDirectory template: tpl-group
                $this->_output['rows'][] = $this->tplDirectory($k);

                $sort['path'][$k] = $this->groupByType($path);
            }
        }

        return $sort;
    }

    /**
     * Get Path Dir Contents
     *
     * @param $rootPath
     * @param array $contents
     * @return array
     */
    private function getPathDirContents($rootPath, array $contents): array
    {
        $scanDir = scandir($rootPath);

        // Add root path to the Download DB if needed
        $this->getFdPath([
            'ctx' => $this->modx->context->key,
            'filename' => $rootPath . $this->getOption('directorySeparator'),
        ]);

        $excludes = $this->getOption('exclude_scan');
        foreach ($scanDir as $file) {
            if (in_array($file, $excludes)) {
                continue;
            }

            $rootRealPath = realpath($rootPath);
            $fullPath = $rootRealPath . $this->getOption('directorySeparator') . $file;
            $fileType = @filetype($fullPath);

            if ($fileType == 'file') {
                // a file
                $fileInfo = $this->fileInformation($fullPath);
                if (!$fileInfo) {
                    continue;
                }
                $contents[] = $fileInfo;
            } elseif ($this->getOption('browseDirectories')) {
                // a directory
                $fdPath = $this->getFdPath([
                    'ctx' => $this->modx->context->key,
                    'filename' => $fullPath . $this->getOption('directorySeparator'),
                ]);
                if (!$fdPath) {
                    continue;
                }

                $notation = $this->aliasName($file);
                $alias = $notation[1];

                $unixDate = filemtime($fullPath);
                $date = date($this->getOption('dateFormat'), $unixDate);
                $link = $this->createLink($fdPath->get('hash'), $fdPath->get('ctx'));

                $imgType = $this->imgType('dir');
                $dir = [
                    'ctx' => $fdPath->get('ctx'),
                    'fullPath' => $fullPath,
                    'path' => $rootRealPath,
                    'filename' => $file,
                    'alias' => $alias,
                    'type' => $fileType,
                    'ext' => '',
                    'size' => '',
                    'sizeText' => '',
                    'unixdate' => $unixDate,
                    'date' => $date,
                    'image' => $this->getOption('imgLocat') . $imgType,
                    'link' => $link['url'], // fallback
                    'url' => $link['url'],
                    'hash' => $fdPath->get('hash')
                ];
                $dir = $this->getDownloadCounts($dir, $fullPath);
                $contents[] = $dir;
            }
        }
        return $contents;
    }

    /**
     * Get Mediasource Dir Contents
     *
     * @param $rootPath
     * @param array $contents
     * @return array
     */
    private function getMediasourceDirContents($rootPath, array $contents)
    {
        $scanDir = $this->mediaSource->getContainerList($rootPath);

        // Add root path to the Download DB if needed
        $this->getFdPath([
            'ctx' => $this->modx->context->key,
            'filename' => rtrim($rootPath, '/') . $this->getOption('directorySeparator'),
        ]);

        $excludes = $this->getOption('exclude_scan');
        foreach ($scanDir as $file) {
            if (in_array(($file['text']), $excludes)) {
                continue;
            }

            $fullPath = $file['path'];
            $relativePath = $file['pathRelative'];

            if ($file['type'] == 'file') {
                $fileInfo = $this->fileInformation($relativePath);
                if (!$fileInfo) {
                    continue;
                }
                $contents[] = $fileInfo;
            } elseif ($this->getOption('browseDirectories')) {
                // a directory
                $fdPath = $this->getFdPath([
                    'ctx' => $this->modx->context->key,
                    'filename' => $relativePath,
                ]);
                if (!$fdPath) {
                    continue;
                }

                $notation = $this->aliasName($file['text']);
                $alias = $notation[1];

                if ($this->mediaSource->getBasePath($rootPath)) {
                    $rootRealPath = $this->mediaSource->getBasePath($rootPath) . $rootPath;
                    $unixDate = filemtime(realpath($rootRealPath));
                } elseif ($this->mediaSource->getObjectUrl($rootPath)) {
                    $rootRealPath = $this->mediaSource->getObjectUrl($rootPath);
                    $unixDate = filemtime($rootRealPath);
                } else {
                    $rootRealPath = realpath($rootPath);
                    $unixDate = filemtime($rootRealPath);
                }

                $date = date($this->getOption('dateFormat'), $unixDate);
                $link = $this->createLink($fdPath->get('hash'), $fdPath->get('ctx'));

                $imgType = $this->imgType('dir');
                $dir = [
                    'ctx' => $fdPath->get('ctx'),
                    'fullPath' => $fullPath,
                    'path' => $rootRealPath,
                    'filename' => $file['text'],
                    'alias' => $alias,
                    'type' => 'dir',
                    'ext' => '',
                    'size' => '',
                    'sizeText' => '',
                    'unixdate' => $unixDate,
                    'date' => $date,
                    'image' => $this->getOption('imgLocat') . $imgType,
                    'link' => $link['url'], // fallback
                    'url' => $link['url'],
                    'hash' => $fdPath->get('hash')
                ];
                $dir = $this->getDownloadCounts($dir, rtrim($fullPath, $this->getOption('directorySeparator')));
                $contents[] = $dir;
            }
        }
        return $contents;
    }

    /**
     * Retrieves the required information from a file.
     *
     * @param string $file absoulte file path or a file with an [| alias]
     * @return array All about the file
     */
    private function fileInformation($file)
    {
        $notation = $this->aliasName($file);
        $path = $notation[0];
        $alias = $notation[1];

        if (empty($this->mediaSource)) {
            $fileRealPath = realpath($path);
            if (!is_file($fileRealPath) || !$fileRealPath) {
                // @todo: lexicon
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, '&getFile parameter expects a correct file path. ' . $path . ' is given.', '', 'FileDownloadR', __FILE__, __LINE__);
                return [];
            }
            $baseName = $this->basename($fileRealPath);
            $size = filesize($fileRealPath);
        } else {
            if ($this->mediaSource->getBasePath($path)) {
                $fileRealPath = $this->mediaSource->getBasePath($path) . $path;
            } elseif ($this->mediaSource->getObjectUrl($path)) {
                $fileRealPath = $this->mediaSource->getObjectUrl($path);
            } else {
                $fileRealPath = realpath($path);
            }
            $baseName = $this->basename($fileRealPath);
            if (method_exists($this->mediaSource, 'getObjectFileSize')) {
                $size = $this->mediaSource->getObjectFileSize($path);
            } else {
                $size = filesize(realpath($fileRealPath));
            }
        }
        $type = @filetype($fileRealPath);

        $xBaseName = explode('.', $baseName);
        $tempExt = end($xBaseName);
        $ext = strtolower($tempExt);
        $imgType = $this->imgType($ext);

        if (!$this->isExtShown($ext)) {
            return [];
        }
        if ($this->isExtHidden($ext)) {
            return [];
        }

        $fdPath = $this->getFdPath([
            'ctx' => $this->modx->context->key,
            'filename' => $fileRealPath,
            'media'
        ]);
        if (!$fdPath) {
            return [];
        }

        if ($this->getOption('directLink')) {
            $link = $this->directLinkFileDownload($fdPath->get('filename'));
            if (!$link) {
                return [];
            }
        } else {
            $link = $this->linkFileDownload($fdPath->get('filename'), $fdPath->get('hash'), $fdPath->get('ctx'));
        }
        $linkdelete = $this->linkFileDelete($fdPath->get('hash'), $fdPath->get('ctx'));

        $unixDate = filemtime($fileRealPath);
        $date = date($this->getOption('dateFormat'), $unixDate);
        $info = [
            'ctx' => $fdPath->get('ctx'),
            'fullPath' => $fileRealPath,
            'path' => dirname($fileRealPath),
            'filename' => $baseName,
            'alias' => $alias,
            'type' => $type,
            'ext' => $ext,
            'size' => $size,
            'sizeText' => $this->fileSizeText($size),
            'unixdate' => $unixDate,
            'date' => $date,
            'image' => $this->getOption('imgLocat') . $imgType,
            'link' => $link['url'], // fallback
            'url' => $link['url'],
            'deleteurl' => $linkdelete['url'],
            'hash' => $fdPath->get('hash'),
            'extended' => $fdPath->get('extended')
        ];
        return $this->getFileCount($info, $fdPath->get('id'));
    }

    /**
     * Grouping the contents by filetype.
     *
     * @param array $contents contents
     * @return array grouped contents
     *
     * @TODO Separate output from grouping
     */
    private function groupByType(array $contents)
    {
        if (empty($contents)) {
            return [];
        }

        $sortType = [];
        foreach ($contents as $k => $file) {
            if (empty($this->getOption('browseDirectories')) && $file['type'] === 'dir') {
                continue;
            }
            $sortType[$file['type']][$k] = $file;
        }
        if (empty($sortType)) {
            return [];
        }

        foreach ($sortType as $k => $file) {
            if (count($file) > 1) {
                $sortType[$k] = $this->sortMultiOrders($file);
            }
        }

        $sort = [];
        $dirs = [];
        if (!empty($this->getOption('browseDirectories')) && !empty($sortType['dir'])) {
            $sort['dir'] = $sortType['dir'];
            // template
            $row = 1;
            foreach ($sort['dir'] as $v) {
                $v['class'] = $this->cssDir($row);
                $dirs[] = $this->tplDir($v);
                $row++;
            }
        }

        $phs = array_merge($this->options, [
            $this->getOption('prefix') . 'classPath' => (!empty($this->getOption('cssPath'))) ? ' class="' . $this->getOption('cssPath') . '"' : '',
            $this->getOption('prefix') . 'path' => $this->breadcrumbs(),
        ]);
        $this->_output['dirRows'] = [];
        if (!empty($this->getOption('tplWrapperDir')) && !empty($dirs)) {
            $phs[$this->getOption('prefix') . 'dirRows'] = implode($this->getOption('dirSeparator'), $dirs);
            $this->_output['dirRows'][] = $this->parse->getChunk($this->getOption('tplWrapperDir'), $phs);
        } else {
            $this->_output['dirRows'][] = implode($this->getOption('dirSeparator'), $dirs);
        }

        $files = [];
        if (!empty($sortType['file'])) {
            $sort['file'] = $sortType['file'];
            // template
            $row = 1;
            foreach ($sort['file'] as $v) {
                $v['class'] = $this->cssFile($row, $v['ext']);
                $files[] = $this->tplFile($v);
                $row++;
            }
            if (count($this->getOption('getDir')) == 1) {
                $this->modx->setPlaceholder($this->getOption('totalVar'), count($files));
                if ($this->getOption('limit')) {
                    $files = array_slice($files, $this->getOption('offset'), $this->getOption('limit'));
                }
            }
        }
        $this->_output['fileRows'] = [];
        if (!empty($this->getOption('tplWrapperFile')) && !empty($files)) {
            $phs[$this->getOption('prefix') . 'fileRows'] = implode($this->getOption('fileSeparator'), $files);
            $this->_output['fileRows'][] = $this->parse->getChunk($this->getOption('tplWrapperFile'), $phs);
        } else {
            $this->_output['fileRows'][] = implode($this->getOption('fileSeparator'), $files);
        }

        $this->_output['rows'][] = implode($this->getOption('dirsSeparator'), $this->_output['dirRows']);
        $this->_output['rows'][] = implode($this->getOption('filesSeparator'), $this->_output['fileRows']);

        return $sort;
    }

    /**
     * Path template if &groupByDirectory is enabled.
     *
     * @param string $path Path's name
     * @return string rendered HTML
     */
    private function tplDirectory($path)
    {
        if (empty($path) || is_array($path)) {
            return '';
        }
        $phs = array_merge($this->options, [
            $this->getOption('prefix') . 'class' => (!empty($this->getOption('cssGroupDir'))) ? ' class="' . $this->getOption('cssGroupDir') . '"' : '',
            $this->getOption('prefix') . 'groupDirectory' => str_replace($this->getOption('directorySeparator'), $this->getOption('breadcrumbSeparator'), $this->trimPath($path)),
        ]);
        return $this->parse->getChunk($this->getOption('tplGroupDir'), $phs);
    }

    /**
     * Get the alias/description from the pipe ( "|" ) symbol on the snippet.
     *
     * @param string $path the full path
     * @return array [0] => the path [1] => the alias name
     */
    private function aliasName($path)
    {
        $xPipes = @explode('|', $path);
        $notation = [];
        $notation[0] = trim($xPipes[0]);
        $notation[1] = !isset($xPipes[1]) ? '' : trim($xPipes[1]);

        return $notation;
    }

    /**
     * Generate a directory link
     *
     * @param string $hash hash
     * @param string $ctx specifies a context to limit URL generation to.
     * @return array the open directory link and the javascript's attribute
     */
    private function createLink($hash, $ctx = 'web')
    {
        if (!$this->getOption('browseDirectories')) {
            return [];
        }
        $args = $this->modx->request->getParameters();
        unset($args['fdldelete'], $args['fdlfile']);
        $args['fdldir'] = $hash;
        if (!empty($this->getOption('fdlid'))) {
            $args['fdlid'] = $this->getOption('fdlid');
        }
        return [
            'url' => $this->modx->makeUrl($this->modx->resource->get('id'), $ctx, $args),
            'hash' => $hash
        ];
    }

    /**
     * Get the right image type to the specified file's extension, or fall back to the default image.
     *
     * @param string $ext
     * @return string
     */
    private function imgType($ext)
    {
        return $this->imgTypes[$ext] ?? ($this->imgTypes['default'] ?? '');
    }

    /**
     * Check whether the file with the specified extension is shown to the list.
     *
     * @param string $ext file's extension
     * @return bool    true | false
     */
    private function isExtShown($ext)
    {
        if (empty($this->getOption('extShown'))) {
            return true;
        }
        $extShownX = explode(',', $this->getOption('extShown'));
        array_walk($extShownX, function (&$val) {
            $val = strtolower(trim($val));
        });
        if (in_array($ext, $extShownX)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check whether the file with the specified extension is hidden from the list.
     *
     * @param string $ext file's extension
     * @return bool    true | false
     */
    private function isExtHidden($ext)
    {
        if (empty($this->getOption('extHidden'))) {
            return false;
        }
        $extHiddenX = explode(',', $this->getOption('extHidden'));
        array_walk($extHiddenX, function (&$val) {
            $val = strtolower(trim($val));
        });
        if (!in_array($ext, $extHiddenX)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Set the direct link to the file path.
     *
     * @param string $filePath absolute file path
     * @return array the download link and the javascript's attribute
     */
    private function directLinkFileDownload($filePath)
    {
        $link = [];
        $link['url'] = '';
        if ($this->getOption('noDownload')) {
            $link['url'] = $filePath;
        } else {
            $corePath = str_replace('/', $this->getOption('directorySeparator'), MODX_CORE_PATH);
            // don't work in the MODX core path
            if (strpos($filePath, $corePath) === 0) {
                return [];
            }
            if (empty($this->mediaSource)) {
                // the file path has to start with the MODX base path
                $basePath = str_replace('/', $this->getOption('directorySeparator'), MODX_BASE_PATH);
                if (strpos($filePath, $basePath) === 0) {
                    $fileUrl = str_replace($this->getOption('directorySeparator'), '/', $filePath);
                    $fileUrl = MODX_BASE_URL . substr($fileUrl, strlen($basePath));
                    $fileUrl = ltrim($fileUrl, '/');
                    $link['url'] = MODX_URL_SCHEME . MODX_HTTP_HOST . '/' . $fileUrl;
                } else {
                    return [];
                }
            } else {
                $link['url'] = $this->mediaSource->getObjectUrl($filePath);
            }
        }
        $link['hash'] = '';
        return $link;
    }

    /**
     * @param string $filePath file's path
     * @param string $hash hash
     * @param string $ctx specifies a context to limit URL generation to.
     * @return array the download link and the javascript's attribute
     */
    private function linkFileDownload($filePath, $hash, $ctx = 'web')
    {
        $link = [];
        if ($this->getOption('noDownload')) {
            $link['url'] = $filePath;
        } else {
            $args = $this->modx->request->getParameters();
            unset($args['fdldelete']);
            $args['fdlfile'] = $hash;
            $url = $this->modx->makeUrl($this->modx->resource->get('id'), $ctx, $args);
            $link['url'] = $url;
        }
        $link['hash'] = $hash;
        return $link;
    }

    /**
     * @param string $hash hash
     * @param string $ctx specifies a context to limit URL generation to.
     * @return array the download link and the javascript's attribute
     */
    private function linkFileDelete($hash, $ctx = 'web')
    {
        $link = [];
        if (!$this->isAllowed('deleteGroups')) {
            $link['url'] = '';
        } else {
            $args = $this->modx->request->getParameters();
            unset($args['fdlfile']);
            $args['fdldelete'] = $hash;
            $url = $this->modx->makeUrl($this->modx->resource->get('id'), $ctx, $args);
            $link['url'] = $url;
        }
        $link['hash'] = $hash;
        return $link;
    }

    /**
     * Prettify the file size with thousands unit byte.
     *
     * @param int $fileSize filesize()
     * @return string the pretty number
     */
    private function fileSizeText($fileSize)
    {
        if ($fileSize === 0) {
            $returnVal = '0 bytes';
        } else {
            if ($fileSize > 1024 * 1024 * 1024) {
                $returnVal = (ceil($fileSize / (1024 * 1024 * 1024) * 100) / 100) . ' GB';
            } else {
                if ($fileSize > 1024 * 1024) {
                    $returnVal = (ceil($fileSize / (1024 * 1024) * 100) / 100) . ' MB';
                } else {
                    if ($fileSize > 1024) {
                        $returnVal = (ceil($fileSize / 1024 * 100) / 100) . ' kB';
                    } else {
                        $returnVal = $fileSize . ' B';
                    }
                }
            }
        }

        return $returnVal;
    }

    /**
     * @param array $eventProperties
     * @param int $pathId
     * @return array
     */
    private function getFileCount($eventProperties, $pathId)
    {
        if ($this->getOption('countDownloads')) {
            $eventProperties['count'] = $this->modx->getCount('fdDownloads', [
                'path_id' => $pathId
            ]);
        }
        if ($this->getOption('countUserDownloads') && $this->modx->user) {
            $eventProperties['count_user'] = $this->modx->getCount('fdDownloads', [
                'path_id' => $pathId,
                'user' => $this->modx->user->get('id')
            ]);
        }
        return $eventProperties;
    }

    /**
     * Multi dimensional sorting.
     *
     * @param array $array content array
     * @return array the sorted array
     * @link modified from http://www.php.net/manual/en/function.sort.php#104464
     */
    private function sortMultiOrders($array)
    {
        if (!is_array($array) || count($array) < 1) {
            return $array;
        }

        $temp = [];
        foreach (array_keys($array) as $key) {
            $temp[$key] = $array[$key][$this->getOption('sortBy')];
        }

        if ($this->getOption('sortOrderNatural') != 1) {
            if (strtolower($this->getOption('sortOrder')) == 'asc') {
                asort($temp);
            } else {
                arsort($temp);
            }
        } else {
            if ($this->getOption('sortByCaseSensitive') != 1) {
                natcasesort($temp);
            } else {
                natsort($temp);
            }
            if (strtolower($this->getOption('sortOrder')) != 'asc') {
                $temp = array_reverse($temp, true);
            }
        }

        $sorted = [];
        foreach (array_keys($temp) as $key) {
            if (is_numeric($key)) {
                $sorted[] = $array[$key];
            } else {
                $sorted[$key] = $array[$key];
            }
        }

        return $sorted;
    }

    /**
     * Generate the class names for the directory rows.
     *
     * @param int $row the row number
     * @return string imploded class names
     */
    private function cssDir($row)
    {
        $totalRow = $this->_count['dirs'];
        $cssName = [];
        if (!empty($this->getOption('cssDir'))) {
            $cssName[] = $this->getOption('cssDir');
        }
        if (!empty($this->getOption('cssAltRow')) && $row % 2 === 1) {
            $cssName[] = $this->getOption('cssAltRow');
        }
        if (!empty($this->getOption('cssFirstDir')) && $row === 1) {
            $cssName[] = $this->getOption('cssFirstDir');
        } elseif (!empty($this->getOption('cssLastDir')) && $row === $totalRow) {
            $cssName[] = $this->getOption('cssLastDir');
        }

        $o = '';
        $cssNames = @implode(' ', $cssName);
        if (!empty($cssNames)) {
            $o = ' class="' . $cssNames . '"';
        }

        return $o;
    }

    /**
     * Parsing the directory template.
     *
     * @param array $contents properties
     * @return string rendered HTML
     */
    private function tplDir(array $contents)
    {
        if (empty($contents)) {
            return '';
        }
        $phs = $this->options;
        foreach ($contents as $k => $v) {
            $phs[$this->getOption('prefix') . $k] = $v;
        }
        return $this->parse->getChunk($this->getOption('tplDir'), $phs);
    }

    /**
     * Create a breadcrumbs link.
     *
     * @return string a breadcrumbs link
     */
    private function breadcrumbs()
    {
        if (empty($this->getOption('browseDirectories'))) {
            return '';
        }
        $dirs = $this->getOption('getDir');
        $origDirs = $this->getOption('origDir');
        if (count($dirs) > 1) {
            return '';
        } else {
            $path = $dirs[0];
            $origPath = $origDirs[0];
        }
        $trimmedPath = trim($path);
        $trimmedPath = trim($this->trimPath($trimmedPath), $this->getOption('directorySeparator'));
        $trimmedPathX = preg_split('~[' . preg_quote($this->getOption('directorySeparator'), '~') . ']+~', $trimmedPath);
        $basePath = str_replace($trimmedPath, '', $path);
        $trailingPath = $basePath;
        $trailingLink = [];
        $countTrimmedPathX = count($trimmedPathX);

        if (!empty($this->modx->request->getParameters(['fdldir']))) {
            $pageUrl = $this->modx->makeUrl($this->modx->resource->get('id'));
            $trailingLink[] = $this->parse->getChunk($this->getOption('tplBreadcrumb'), array_merge($this->options, [
                $this->getOption('prefix') . 'title' => $this->modx->lexicon('filedownloadr.breadcrumb.home'),
                $this->getOption('prefix') . 'link' => $pageUrl,
                $this->getOption('prefix') . 'url' => $pageUrl,
                $this->getOption('prefix') . 'hash' => '',
            ]));
        }

        foreach ($trimmedPathX as $k => $title) {
            $trailingPath .= $title . $this->getOption('directorySeparator');
            $absPath = $this->getAbsolutePath($trailingPath);
            if (empty($absPath)) {
                return false;
            }
            $fdPath = $this->modx->getObject('fdPaths', [
                'ctx' => $this->modx->context->key,
                'media_source_id' => $this->getOption('mediaSourceId'),
                'filename' => $absPath,
            ]);
            if (!$fdPath) {
                $fdPath = $this->getFdPath([
                    'ctx' => $this->modx->context->key,
                    'filename' => $absPath,
                ], false);
                if (!$fdPath) {
                    continue;
                }
                $fdPath = $this->modx->getObject('fdPaths', [
                    'ctx' => $this->modx->context->key,
                    'media_source_id' => $this->getOption('mediaSourceId'),
                    'filename' => $fdPath->get('filename')
                ]);
            }
            $hash = $fdPath->get('hash');
            $link = $this->createLink($hash, $this->modx->context->key);

            if ($trailingPath !== $origPath . $this->getOption('directorySeparator')) {
                if ($k < ($countTrimmedPathX - 1)) {
                    $trailingLink[] = $this->parse->getChunk($this->getOption('tplBreadcrumb'), array_merge($this->options, [
                        $this->getOption('prefix') . 'title' => $title,
                        $this->getOption('prefix') . 'link' => $link['url'], // fallback
                        $this->getOption('prefix') . 'url' => $link['url'],
                        $this->getOption('prefix') . 'hash' => $hash,
                    ]));
                } else {
                    $trailingLink[] = $title;
                }
            }
        }
        return implode($this->getOption('breadcrumbSeparator'), $trailingLink);
    }

    /**
     * Generate the class names for the file rows.
     *
     * @param int $row the row number
     * @param string $ext extension
     * @return string imploded class names
     */
    private function cssFile($row, $ext)
    {
        $totalRow = $this->_count['files'];
        $cssName = [];
        if (!empty($this->getOption('cssFile'))) {
            $cssName[] = $this->getOption('cssFile');
        }
        if (!empty($this->getOption('cssAltRow')) && $row % 2 === 1) {
            if ($this->_count['dirs'] % 2 === 0) {
                $cssName[] = $this->getOption('cssAltRow');
            }
        }
        if (!empty($this->getOption('cssFirstFile')) && $row === 1) {
            $cssName[] = $this->getOption('cssFirstFile');
        } elseif (!empty($this->getOption('cssLastFile')) && $row === $totalRow) {
            $cssName[] = $this->getOption('cssLastFile');
        }
        if (!empty($this->getOption('cssExtension'))) {
            $cssNameExt = '';
            if (!empty($this->getOption('cssExtensionPrefix'))) {
                $cssNameExt .= $this->getOption('cssExtensionPrefix');
            }
            $cssNameExt .= $ext;
            if (!empty($this->getOption('cssExtensionSuffix'))) {
                $cssNameExt .= $this->getOption('cssExtensionSuffix');
            }
            $cssName[] = $cssNameExt;
        }
        $o = '';
        $cssNames = @implode(' ', $cssName);
        if (!empty($cssNames)) {
            $o = ' class="' . $cssNames . '"';
        }
        return $o;
    }

    /**
     * Parsing the file template.
     *
     * @param array $fileInfo properties
     * @return string rendered HTML
     */
    private function tplFile(array $fileInfo)
    {
        if (empty($fileInfo) || empty($this->getOption('tplFile'))) {
            return '';
        }
        $phs = $this->options;
        foreach ($fileInfo as $key => $value) {
            if ($key === 'extended' && is_array($value)) {
                foreach ($value as $k => $v) {
                    $value[$k] = htmlspecialchars($v);
                }
            }
            $phs[$this->getOption('prefix') . $key] = $value;
        }
        return $this->parse->getChunk($this->getOption('tplFile'), $phs);
    }

    /**
     * Trim the absolute path to be a relatively safe path.
     *
     * @param string $path the absolute path
     * @return string trimmed path
     */
    private function trimPath($path)
    {
        $trimmedPath = $path;
        foreach ($this->getOption('origDir') as $dir) {
            $dir = trim($dir, '/') . '/';
            $pattern = '`^(' . preg_quote($dir) . ')`';
            if (preg_match($pattern, $path)) {
                $trimmedPath = preg_replace($pattern, '', $path);
            }
            if (empty($this->mediaSource)) {
                $modxCorePath = realpath(MODX_CORE_PATH) . $this->getOption('directorySeparator');
                $modxAssetsPath = realpath(MODX_ASSETS_PATH) . $this->getOption('directorySeparator');
            } else {
                $modxCorePath = MODX_CORE_PATH;
                $modxAssetsPath = MODX_ASSETS_PATH;
            }
            if (false !== stristr($trimmedPath, $modxCorePath)) {
                $trimmedPath = str_replace($modxCorePath, '', $trimmedPath);
            } elseif (false !== stristr($trimmedPath, $modxAssetsPath)) {
                $trimmedPath = str_replace($modxAssetsPath, '', $trimmedPath);
            }
        }

        return $trimmedPath;
    }

    /**
     * Check the user's group.
     *
     * @param string $type
     * @return bool
     */
    public function isAllowed($type = 'userGroups')
    {
        if (empty($this->getOption($type))) {
            return true;
        } else {
            $userGroups = array_map('trim', explode(',', $this->options[$type]));
            $userAccessGroupNames = $this->userAccessGroupNames();

            $intersect = array_uintersect($userGroups, $userAccessGroupNames, "strcasecmp");

            if (count($intersect) > 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Get logged in usergroup names.
     *
     * @return array access group names
     */
    private function userAccessGroupNames()
    {
        $userAccessGroupNames = [];

        $userId = $this->modx->user->get('id');
        if (empty($userId)) {
            return $userAccessGroupNames;
        }

        $userObj = $this->modx->getObject('modUser', $userId);
        /** @var modUserGroupMember[] $userGroupObj */
        $userGroupObj = $userObj->getMany('UserGroupMembers');
        foreach ($userGroupObj as $uGO) {
            $userGroupNameObj = $this->modx->getObject('modUserGroup', $uGO->get('user_group'));
            $userAccessGroupNames[] = $userGroupNameObj->get('name');
        }

        return $userAccessGroupNames;
    }

    /**
     * Set the getDir property with the hash to browse inside the clicked directory.
     *
     * @param string $hash the hashed link
     * @return bool    true | false
     */
    public function setDirectory($hash)
    {
        $fdPath = $this->getPathByHash($hash);
        if (!$fdPath ||
            $fdPath->get('ctx') !== $this->modx->context->key
        ) {
            return false;
        }
        $this->options['getDir'] = [rtrim($fdPath->get('filename'), $this->getOption('directorySeparator'))];
        $this->options['getFile'] = [];

        return true;
    }

    /**
     * Get the path by the hash from the database.
     *
     * @param string $hash
     * @return false|fdPaths
     */
    private function getPathByHash(string $hash)
    {
        if (empty($hash)) {
            return false;
        }
        /** @var fdPaths $fdPath */
        $fdPath = $this->modx->getObject('fdPaths', [
            'media_source_id' => $this->getOption('mediaSourceId'),
            'hash' => $hash
        ]);
        if (!$fdPath) {
            return false;
        }
        if ($this->modx->context->key !== $fdPath->get('ctx')) {
            return false;
        }
        return $fdPath;
    }

    /**
     * Download action.
     *
     * @param string $hash hashed text
     * @return boolean|void file is pulled to the browser
     */
    public function downloadFile($hash)
    {
        $fdPath = $this->getPathByHash($hash);
        if (!$fdPath ||
            $fdPath->get('media_source_id') !== intval($this->getOption('mediaSourceId'))
        ) {
            return false;
        }
        $filePath = $fdPath->get('filename');

        $eventProperties = [
            'hash' => $hash,
            'fdPath' => $fdPath,
            'ctx' => $fdPath->get('ctx'),
            'mediaSourceId' => $fdPath->get('media_source_id'),
            'filePath' => $filePath,
            'extended' => $fdPath->get('extended') ?? [],
        ];
        $eventProperties = $this->getFileCount($eventProperties, $fdPath->get('id'));
        try {
            $result = $this->modx->invokeEvent('OnFileDownloadBeforeFileDownload', $eventProperties);
        } catch (Exception $e) {
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadBeforeFileDownload: ' . $e->getMessage());
            return false;
        }
        if (is_array($result)) {
            if (in_array(false, $result, true)) {
                return false;
            }
        }

        $fileExists = false;
        $realFilePath = '';
        $filename = $this->basename($filePath);
        if (empty($this->mediaSource)) {
            if (file_exists($filePath)) {
                $fileExists = true;
                $realFilePath = $filePath;
            }
        } else {
            if ($this->mediaSource->getBasePath($filePath)) {
                $realFilePath = $this->mediaSource->getBasePath($filePath) . $filePath;
                if (file_exists($realFilePath)) {
                    $fileExists = true;
                }
            } else {
                $objectContent = $this->mediaSource->getObjectContents($filePath);
                // $objectContent should be an array for remote media sources
                if (is_array($objectContent)) {
                    try {
                        if (!empty($objectContent)) {
                            $temp = tempnam(sys_get_temp_dir(), 'fdl_' . time() . '_' . pathinfo($filename, PATHINFO_FILENAME) . '_');
                            $handle = fopen($temp, "r+b");
                            fwrite($handle, $objectContent['content']);
                            fseek($handle, 0);
                            fclose($handle);
                            $realFilePath = $temp;
                            $fileExists = true;
                        } else {
                            $msg = $this->modx->lexicon('filedownloadr.remote_err_file_empty');
                            $this->setError($msg);
                            $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                        }
                    } catch (Exception $e) {
                        $msg = $this->modx->lexicon('filedownloadr.remote_err_file_not_available');
                        $this->setError($msg);
                        $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                    }
                }
            }
        }
        if ($fileExists) {
            @set_time_limit(300);

            header('Pragma: public'); // required
            header('Expires: 0'); // no cache
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: private', false);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realFilePath)) . ' GMT');
            header('Content-Description: File Transfer');
            header('Content-Type: "' . mime_content_type($realFilePath) . '"');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($realFilePath)); // provide file size
            header('Connection: close');

            // Close the session to allow for header() to be sent
            session_write_close();

            $chunksize = 1024 * 1024; // how many bytes per chunk
            $handle = @fopen($realFilePath, 'rb');
            if ($handle === false) {
                return false;
            }
            while (!feof($handle) && connection_status() == 0) {
                $buffer = @fread($handle, $chunksize);
                if (!$buffer) {
                    die();
                }
                echo $buffer;
                flush();
            }
            fclose($handle);
            if (!empty($temp)) {
                @unlink($temp);
            }
            if ($this->getOption('countDownloads')) {
                $this->setDownloadCount($hash);
            }

            $eventProperties = [
                'hash' => $hash,
                'fdPath' => $fdPath,
                'ctx' => $fdPath->get('ctx'),
                'mediaSourceId' => $fdPath->get('media_source_id'),
                'filePath' => $filePath,
                'extended' => $fdPath->get('extended') ?? [],
            ];
            $eventProperties = $this->getFileCount($eventProperties, $fdPath->get('id'));
            try {
                $this->modx->invokeEvent('OnFileDownloadAfterFileDownload', $eventProperties);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadAfterFileDownload: ' . $e->getMessage());
                exit();
            }

            exit();
        }

        return false;
    }

    /**
     * Add download counter.
     *
     * @param string $hash secret hash
     */
    private function setDownloadCount($hash)
    {
        if (!$this->getOption('countDownloads')) {
            return;
        }
        $fdPath = $this->modx->getObject('fdPaths', [
            'media_source_id' => $this->getOption('mediaSourceId'),
            'hash' => $hash
        ]);
        if (!$fdPath) {
            return;
        }
        // save the new count
        $fdDownload = $this->modx->newObject('fdDownloads');
        $fdDownload->set('path_id', $fdPath->getPrimaryKey());
        $fdDownload->set('referer', $this->getReferrer());
        $fdDownload->set('user', $this->modx->user->get('id'));
        $fdDownload->set('timestamp', time());
        if (!empty($this->getOption('useGeolocation')) && !empty($this->getOption('geoApiKey'))) {
            try {
                $ipinfodb = new Api($this->getOption('geoApiKey'));
                $userIP = $this->getIPAddress();
                if ($userIP) {
                    $location = $ipinfodb->getCity($userIP);
                    if ($location) {
                        $fdDownload->set('ip', $userIP);
                        $fdDownload->set('country', $location['countryCode']);
                        $fdDownload->set('region', $location['regionName']);
                        $fdDownload->set('city', $location['cityName']);
                        $fdDownload->set('zip', $location['zipCode']);
                        $fdDownload->set('geolocation', json_encode([
                            'latitude' => $location['latitude'],
                            'longitude' => $location['longitude'],
                        ]));
                    }
                }
            } catch (Exception $e) {
                $msg = 'Error getting the IP info: ' . $e->getMessage();
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
            }
        }
        if ($fdDownload->save() === false) {
            $msg = $this->modx->lexicon('filedownloadr.counter_err_save');
            $this->setError($msg);
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
        }
    }

    /**
     * Returns the referrer without FileDownloadR properties
     *
     * @return string - the Referrer or empty
     */
    private function getReferrer()
    {
        $referrer = '';
        if (urldecode($_SERVER['HTTP_REFERER'])) {
            $url = parse_url(urldecode($_SERVER['HTTP_REFERER']));
            $referrer = ($url['scheme'] ?? '') . '://' . ($url['host'] ?? '') . ($url['path'] ?? '');
            $query = $url['query'] ?? '';
            $queryValues = [];
            parse_str($query, $queryValues);
            unset($queryValues['fdldir'], $queryValues['fdlfile'], $queryValues['fdldelete']);
            $referrer .= ($queryValues) ? '?' . http_build_query($queryValues) : '';
        }
        return $referrer;
    }

    /**
     * Returns the users IP Address. This data shouldn't be trusted. Faking HTTP headers is trivial.
     *
     * @return string/false - the users IP address or false
     */
    private function getIPAddress()
    {
        if (isset($_SERVER['REMOTE_ADDR']) and $_SERVER['REMOTE_ADDR'] != '') {
            return $_SERVER['REMOTE_ADDR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) and $_SERVER['HTTP_CLIENT_IP'] != '') {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and $_SERVER['HTTP_X_FORWARDED_FOR'] != '') {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return false;
    }

    /**
     * Upload action.
     *
     * @return bool State of the upload
     */
    public function uploadFile()
    {
        $filePaths = $this->getOption('getDir');
        $filePath = array_shift($filePaths);
        $allowedTypes = $this->getOption('uploadFileTypes');

        if ($this->isAllowed('uploadGroups')) {
            // A file has been uploaded and no errors have occurred
            if (!empty($_FILES) && is_array($_FILES['fdupload']) && $_FILES['fdupload']['error'] == UPLOAD_ERR_OK) {
                $type = mime_content_type($_FILES['fdupload']['tmp_name']);
                if (in_array($type, $allowedTypes)) {
                    if (filesize($_FILES['fdupload']['tmp_name']) <= (int)$this->getOption('uploadMaxSize')) {
                        $mimes = new MimeTypes;
                        $fileName = pathinfo($_FILES['fdupload']['name'], PATHINFO_FILENAME) . '.' . $mimes->getExtension($type);
                    } else {
                        $this->setError($this->modx->lexicon('filedownloadr.upload_err_filesize'));
                        return false;
                    }
                } else {
                    $this->setError($this->modx->lexicon('filedownloadr.upload_err_not_allowed'));
                    return false;
                }
            } else {
                $this->setError($this->modx->lexicon('filedownloadr.upload_err_empty'));
                return false;
            }

            $extendedFields = $this->getPostExtendedFields();
            $eventProperties = [
                'mediaSourceId' => $this->mediaSource->get('id'),
                'filePath' => $filePath,
                'fileName' => $fileName,
                'extended' => $extendedFields,
                'resourceId' => ($this->modx->resource) ? $this->modx->resource->get('id') : 0,
            ];
            try {
                $result = $this->modx->invokeEvent('OnFileDownloadBeforeFileUpload', $eventProperties);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadBeforeFileUpload: ' . $e->getMessage());
                return false;
            }
            if (is_array($result)) {
                if (in_array(false, $result, true)) {
                    return false;
                }
            }
            $filePath = $result['filePath'] ?? $filePath;
            $fileName = $result['fileName'] ?? $fileName;
            $extendedFields = $result['extended'] ?? $extendedFields;

            if (empty($this->mediaSource)) {
                if (file_exists($filePath . $this->getOption('directorySeparator') . $fileName)) {
                    $this->setError($this->modx->lexicon('filedownloadr.upload_err_exists'));
                    return false;
                }
                if (!move_uploaded_file($_FILES['fdupload']['tmp_name'], $filePath . $this->getOption('directorySeparator') . $fileName)) {
                    $this->setError($this->modx->lexicon('filedownloadr.upload_err_not_writable'));
                    return false;
                }
            } else {
                $handle = fopen($_FILES['fdupload']['tmp_name'], 'r');
                if ($handle) {
                    $contents = fread($handle, filesize($_FILES['fdupload']['tmp_name']));
                    if ($contents) {
                        if (!$this->mediaSource->createObject($filePath . $this->getOption('directorySeparator'), $fileName, $contents)) {
                            $this->setError($this->modx->lexicon('filedownloadr.upload_err_not_writable'));
                        }
                    } else {
                        $this->setError($this->modx->lexicon('filedownloadr.upload_err_exists'));
                        return false;
                    }
                    fclose($handle);
                } else {
                    $this->setError($this->modx->lexicon('filedownloadr.upload_err_exists'));
                    return false;
                }
            }

            // Get the upload directory database entry
            $fdUploadPath = $this->getFdPath([
                'ctx' => $this->modx->context->key,
                'filename' => $filePath . $this->getOption('directorySeparator'),
            ]);
            if (!$fdUploadPath) {
                return false;
            }

            // Create the uploaded file database entry
            $fdPath = $this->getFdPath([
                'ctx' => $this->modx->context->key,
                'filename' => $filePath . $this->getOption('directorySeparator') . $fileName,
                'extended' => $extendedFields,
            ]);

            $eventProperties = [
                'hash' => $fdPath->get('hash'),
                'fdPath' => $fdPath,
                'filePath' => $filePath,
                'fileName' => $fileName,
                'extended' => $extendedFields,
                'resourceId' => ($this->modx->resource) ? $this->modx->resource->get('id') : 0,
            ];
            try {
                $result = $this->modx->invokeEvent('OnFileDownloadAfterFileUpload', $eventProperties);
            } catch (Exception $e) {
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadAfterFileUpload: ' . $e->getMessage());
                return false;
            }
            if (is_array($result)) {
                if (in_array(false, $result, true)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Delete action.
     *
     * @param string $hash hashed text
     * @return boolean
     */
    public function deleteFile($hash)
    {
        $fdPath = $this->getPathByHash($hash);
        if (!$fdPath ||
            $fdPath->get('ctx') !== $this->modx->context->key ||
            $fdPath->get('media_source_id') !== intval($this->getOption('mediaSourceId'))
        ) {
            return false;
        }
        $filePath = $fdPath->get('filename');
        if ($this->isAllowed('deleteGroups')) {
            if (empty($this->mediaSource)) {
                if (file_exists($filePath)) {
                    try {
                        $eventProperties = [
                            'hash' => $fdPath->get('hash'),
                            'fdPath' => $fdPath,
                            'ctx' => $fdPath->get('ctx'),
                            'mediaSourceId' => $fdPath->get('media_source_id'),
                            'filePath' => $filePath,
                            'extended' => $fdPath->get('extended') ?? [],
                        ];
                        $eventProperties = $this->getFileCount($eventProperties, $fdPath->get('id'));
                        try {
                            $result = $this->modx->invokeEvent('OnFileDownloadBeforeFileDelete', $eventProperties);
                        } catch (Exception $e) {
                            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadBeforeFileDelete: ' . $e->getMessage());
                            return false;
                        }
                        if (is_array($result)) {
                            if (in_array(false, $result, true)) {
                                return false;
                            }
                        }
                        unlink($filePath);
                        if ($fdPath->remove()) {
                            try {
                                $result = $this->modx->invokeEvent('OnFileDownloadAfterFileDelete', $eventProperties);
                            } catch (Exception $e) {
                                $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadAfterFileDelete: ' . $e->getMessage());
                                return false;
                            }
                            if (is_array($result)) {
                                if (in_array(false, $result, true)) {
                                    return false;
                                }
                            }
                            return true;
                        } else {
                            $msg = $this->modx->lexicon('filedownloadr.file_err_delete', [
                                'file' => pathinfo($filePath, PATHINFO_BASENAME)
                            ]);
                            $this->setError($msg);
                            $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                            return false;
                        }
                    } catch (Exception $e) {
                        $msg = $this->modx->lexicon('filedownloadr.file_err_delete', [
                            'file' => pathinfo($filePath, PATHINFO_BASENAME)
                        ]);
                        $this->setError($msg);
                        $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                        return false;
                    }
                } else {
                    $msg = $this->modx->lexicon('filedownloadr.file_err_not_exist', [
                        'file' => pathinfo($filePath, PATHINFO_BASENAME)
                    ]);
                    $this->setError($msg);
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                    return false;
                }
            } else {
                $eventProperties = [
                    'hash' => $fdPath->get('hash'),
                    'fdPath' => $fdPath,
                    'ctx' => $fdPath->get('ctx'),
                    'mediaSourceId' => $fdPath->get('media_source_id'),
                    'filePath' => $filePath,
                    'extended' => $fdPath->get('extended') ?? [],
                ];
                $eventProperties = $this->getFileCount($eventProperties, $fdPath->get('id'));
                try {
                    $result = $this->modx->invokeEvent('OnFileDownloadBeforeFileDelete', $eventProperties);
                } catch (Exception $e) {
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadBeforeFileDelete: ' . $e->getMessage());
                    return false;
                }
                if (is_array($result)) {
                    if (in_array(false, $result, true)) {
                        return false;
                    }
                }
                if ($this->mediaSource->removeObject($filePath)) {
                    if ($fdPath->remove()) {
                        try {
                            $result = $this->modx->invokeEvent('OnFileDownloadAfterFileDelete', $eventProperties);
                        } catch (Exception $e) {
                            $this->modx->log(xPDO::LOG_LEVEL_ERROR, 'Exception in OnFileDownloadAfterFileDelete: ' . $e->getMessage());
                            return false;
                        }
                        if (is_array($result)) {
                            if (in_array(false, $result, true)) {
                                return false;
                            }
                        }
                        return true;
                    } else {
                        $msg = $this->modx->lexicon('filedownloadr.file_err_delete', [
                            'file' => pathinfo($filePath, PATHINFO_BASENAME)
                        ]);
                        $this->setError($msg);
                        $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                    }
                } else {
                    $msg = $this->modx->lexicon('filedownloadr.file_err_delete', [
                        'file' => pathinfo($filePath, PATHINFO_BASENAME)
                    ]);
                    $this->setError($msg);
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * List the contents
     *
     * @param bool $upload
     * @return string
     */
    public function listContents($upload = false)
    {
        $breadcrumbs = $this->breadcrumbs();
        if (!empty($this->getOption('tplWrapper'))) {
            $prefix = $this->getOption('prefix');
            $args = $this->modx->request->getParameters(['fdldir']);
            if ($this->getOption('fdlid')) {
                $args['fdlid'] = $this->getOption('fdlid');
            }
            $options = array_merge($this->options, [
                $prefix . 'error' => $this->getError(),
                $prefix . 'classPath' => (!empty($this->getOption('cssPath'))) ? ' class="' . $this->getOption('cssPath') . '"' : '',
                $prefix . 'path' => $breadcrumbs,
                $prefix . 'uploadUrl' => $this->modx->makeUrl($this->modx->resource->get('id'), $this->modx->context->key, $args),
                $prefix . 'uploadFileExtensions' => $this->getOption('uploadFileExtensions') ? '(*.' . implode(', *.', $this->getOption('uploadFileExtensions')) . ')' : '',
                $prefix . 'uploadFileTypes' => implode(',', $this->getOption('uploadFileTypes')),
                $prefix . 'rows' => !empty($this->_output['rows']) ? implode('', $this->_output['rows']) : '',
                $prefix . 'dirRows' => $this->_output['dirRows'],
                $prefix . 'fileRows' => $this->_output['fileRows'],
                $prefix . 'extendedFields' => $this->getOption('extendedFields') ? '1' : '',
            ]);
            if (!empty($_POST) && !$upload) {
                $options = array_merge($options, $this->getPostExtendedFields('fdextended_'));
            }

            $output = $this->parse->getChunk($this->getOption('tplWrapper'), $options);
        } else {
            $output = !empty($this->_output['rows']) ? implode('', $this->_output['rows']) : '';
        }

        return $output;
    }

    /**
     * Get string error for boolean returned methods.
     *
     * @return string output
     */
    private function getError()
    {
        return implode("\n", $this->_error);
    }

    /**
     * Check whether the hash exists in the database.
     *
     * @param string $ctx context
     * @param string $hash hash value
     * @return bool    true | false
     */
    public function checkHash($ctx, $hash)
    {
        $fdPath = $this->modx->getObject('fdPaths', [
            'ctx' => $ctx,
            'media_source_id' => $this->getOption('mediaSourceId'),
            'hash' => $hash
        ]);
        return (!$fdPath) ? false : true;
    }

    /**
     * @param string $prefix
     * @return array
     */
    private function getPostExtendedFields($prefix = '')
    {
        $result = [];
        foreach ($this->getOption('extended_file_fields') as $extendedFileField) {
            $name = $extendedFileField['name'] ?? '';
            if ($name) {
                $parameter = $this->modx->request->getParameters('fdextended_' . $name, 'POST');
                if (!empty($parameter)) {
                    switch ($extendedFileField['type'] ?? 'string') {
                        case 'bool':
                        case 'boolean':
                            $parameter = (bool)$parameter;
                            break;
                        case 'int':
                        case 'integer':
                            $parameter = (int)$parameter;
                            break;
                        case 'string':
                        default:
                            $parameter = htmlspecialchars($this->modx->stripTags($parameter));

                    }
                    $result[$prefix . $name] = $parameter;
                }
            }
        }
        return $result;
    }

    /**
     * Check the called file contents with the registered database.
     *
     * If it's not listed, auto save
     * @param array $file
     * @param bool $autoCreate
     * @return \fdPaths|null
     */
    private function getFdPath($file, $autoCreate = true)
    {
        if (empty($file)) {
            return null;
        }

        if (empty($this->mediaSource)) {
            $realPath = realpath($file['filename']);
            if (empty($realPath)) {
                return null;
            }
        } else {
            $basePath = $this->getBasePath($file['filename']);
            if (!empty($basePath)) {
                $file['filename'] = str_replace($basePath, '', $file['filename']);
            }
        }

        /** @var fdPaths $fdPath */
        $fdPath = $this->modx->getObject('fdPaths', [
            'ctx' => $file['ctx'],
            'media_source_id' => $this->getOption('mediaSourceId'),
            'filename' => $file['filename']
        ]);
        if (!$fdPath) {
            if (!$autoCreate) {
                return null;
            }
            /** @var fdPaths $fdPath */
            $fdPath = $this->modx->newObject('fdPaths');
            $fdPath->fromArray([
                'ctx' => $file['ctx'],
                'media_source_id' => $this->getOption('mediaSourceId'),
                'filename' => $file['filename'],
                'count' => 0,
                'hash' => $this->setHashedParam($file['ctx'], $file['filename'])
            ]);
            $fdPath = $this->prepareExtendedFields($file['extended'] ?? [], $fdPath);
            if ($fdPath->save() === false) {
                $msg = $this->modx->lexicon('filedownloadr.counter_err_save');
                $this->setError($msg);
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                return null;
            }
        }
        return $fdPath;
    }

    /**
     * Prepare the extended fields
     *
     * @param array $extendedFields
     * @param fdPaths $fdPath
     * @return fdPaths
     */
    private function prepareExtendedFields($extendedFields, $fdPath)
    {
        foreach ($this->getOption('extended_file_fields') as $extendedFileField) {
            $name = $extendedFileField['name'];
            if ($name) {
                if (isset($extendedFields[$name])) {
                    $fdPath->setExtendedField($name, $extendedFields[$name]);
                }
            }
        }
        return $fdPath;
    }
}
