<?php
/**
 * FileDownloadR
 *
 * Copyright 2011-2022 by Rico Goldsky <goldsky@virtudraft.com>
 * Copyright 2023 by Thomas Jakobi <office@treehillstudio.com>
 *
 * @package filedownloadr
 * @subpackage classfile
 */

namespace TreehillStudio\FileDownloadR;

use IPInfoDB\Api;
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
    public $version = '3.0.1';

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
     * To hold error message
     * @var array $_error
     */
    private $_error = [];

    /**
     * To hold output message
     * @var array $_output
     */
    private $_output = [];

    /**
     * To hold placeholder array, flatten array with prefixable
     * @var array $_placeholders
     */
    private $_placeholders = [];

    /**
     * To hold counting
     * @var array $_count
     */
    private $_count = [];

    /**
     * To hold image type
     * @var array $imgTypes
     */
    private $imgTypes = [];

    /**
     * @var null|modMediaSource $mediaSource
     */
    public $mediaSource;

    /**
     * Directory Separator
     * @var string
     */
    public $ds;

    /**
     * FileDownloadR constructor
     *
     * @param modX $modx A reference to the modX instance.
     * @param array $options An array of options. Optional.
     */
    public function __construct(modX &$modx, $options = [])
    {
        $this->modx =& $modx;
        $this->namespace = $this->getOption('namespace', $options, $this->namespace);

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
            'rows' => '',
            'dirRows' => '',
            'fileRows' => ''
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
            'encoding' => 'utf-8', // @TODO is this used?
            'exclude_scan' => '.,..,Thumbs.db,.htaccess,.htpasswd,.ftpquota,.DS_Store',
        ]);

        $this->parse = new Parse($modx);

        $this->imgTypes = $this->imgTypeProp();
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

        if (empty($this->mediaSource)) {
            $this->ds = DIRECTORY_SEPARATOR;
        } else {
            $this->ds = '/';
        }

        $this->options['getDir'] = !empty($this->getOption('getDir')) ? $this->checkPath($this->getOption('getDir')) : '';
        $this->options['origDir'] = !empty($this->getOption('origDir')) ? $this->trimArray(@explode(',', $this->getOption('origDir'))) : '';
        $this->options['getFile'] = !empty($this->getOption('getFile')) ? $this->checkPath($this->getOption('getFile')) : '';
        $this->options = $this->replacePropPhs($this->options);
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
     * Get Boolean Option
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
     * Get JSON Option
     *
     * @param string $key
     * @param array $options
     * @param mixed $default
     * @return array
     */
    public function getJsonOption($key, $options = [], $default = null)
    {
        $value = json_decode($this->modx->getOption($key, $options, $default ?? ''), true);
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
     * Set a local configuration option.
     *
     * @param array $options The options to be set.
     * @param bool $merge Merge the new options with the existing options.
     */
    public function setOptions(array $options = [], $merge = true)
    {
        $this->options = ($merge) ? array_merge($this->options, $options) : $options;
    }

    /**
     * Set a local configuration option.
     *
     * @param string $key The option key to be set.
     * @param mixed $value The value.
     */
    public function setOption(string $key, $value)
    {
        $this->options[$key] = $value;
    }

    /**
     * Set class configuration exclusively for multiple snippet calls
     * @param array $config snippet's parameters
     */
    public function setConfigs($config = [])
    {
        // Clear previous output for subsequent snippet calls
        $this->_output = [
            'rows' => '',
            'dirRows' => '',
            'fileRows' => ''
        ];

        $config = $this->replacePropPhs($config);

        $config['getDir'] = !empty($config['getDir']) ? $this->checkPath($config['getDir']) : '';
        $config['origDir'] = !empty($config['origDir']) ? $this->trimArray(@explode(',', $config['origDir'])) : '';
        $config['getFile'] = !empty($config['getFile']) ? $this->checkPath($config['getFile']) : '';

        $this->options = array_merge($this->options, $config);
    }

    /**
     * Define individual config for the class
     * @param string $key array's key
     * @param string $val array's value
     */
    public function setConfig($key, $val)
    {
        $this->options[$key] = $val;
    }

    public function getConfig($key)
    {
        return $this->options[$key];
    }

    public function getConfigs()
    {
        return $this->options;
    }

    /**
     * Set string error for boolean returned methods
     * @param string $msg
     */
    public function setError($msg)
    {
        $this->_error[] = $msg;
    }

    /**
     * Get string error for boolean returned methods
     * @param string $delimiter delimiter of the imploded output (default: "\n")
     * @return string output
     */
    public function getError($delimiter = "\n")
    {
        if ($delimiter === '\n') {
            $delimiter = "\n";
        }
        return @implode($delimiter, $this->_error);
    }

    /**
     * Set string output for boolean returned methods
     * @param string $msg
     */
    public function setOutput($msg)
    {
        $this->_output[] = $msg;
    }

    /**
     * Get string output for boolean returned methods
     * @param string $delimiter delimiter of the imploded output (default: "\n")
     * @return string output
     */
    public function getOutput($delimiter = "\n")
    {
        if ($delimiter === '\n') {
            $delimiter = "\n";
        }
        return @implode($delimiter, $this->_output);
    }

    /**
     * Set internal placeholder
     * @param string $key key
     * @param string $value value
     * @param string $prefix add prefix if it's required
     */
    public function setPlaceholder($key, $value, $prefix = '')
    {
        $prefix = !empty($prefix) ? $prefix : $this->getOption('phsPrefix', [], '');
        $this->_placeholders[$prefix . $key] = $this->trimString($value);
    }

    /**
     * Set internal placeholders
     * @param array $placeholders placeholders in an associative array
     * @param string $prefix add prefix if it's required
     * @param boolean $merge define whether the output will be merge to global properties or not
     * @param string $delimiter define placeholder's delimiter
     * @return mixed boolean|array of placeholders
     */
    public function setPlaceholders($placeholders, $prefix = '', $merge = true, $delimiter = '.')
    {
        if (empty($placeholders)) {
            return false;
        }
        $prefix = !empty($prefix) ? $prefix : $this->getOption('phsPrefix', [], '');
        $placeholders = $this->trimArray($placeholders);
        $placeholders = $this->implodePhs($placeholders, rtrim($prefix, $delimiter));
        // enclosed private scope
        if ($merge) {
            $this->_placeholders = array_merge($this->_placeholders, $placeholders);
        }
        // return only for this scope
        return $placeholders;
    }

    /**
     * Get internal placeholders in an associative array
     * @return array
     */
    public function getPlaceholders()
    {
        return $this->_placeholders;
    }

    /**
     * Get an internal placeholder
     * @param string $key key
     * @return string value
     */
    public function getPlaceholder($key)
    {
        return $this->_placeholders[$key];
    }

    /**
     * Merge multi dimensional associative arrays with separator
     * @param array $array raw associative array
     * @param string $keyName parent key of this array
     * @param string $separator separator between the merged keys
     * @param array $holder to hold temporary array results
     * @return array one level array
     */
    public function implodePhs(array $array, $keyName = null, $separator = '.', array $holder = [])
    {
        $phs = !empty($holder) ? $holder : [];
        foreach ($array as $k => $v) {
            $key = !empty($keyName) ? $keyName . $separator . $k : $k;
            if (is_array($v)) {
                $phs = $this->implodePhs($v, $key, $separator, $phs);
            } else {
                $phs[$key] = $v;
            }
        }
        return $phs;
    }

    /**
     * Trim string value
     * @param string $string source text
     * @param string $charlist defined characters to be trimmed
     * @return string trimmed text
     * @link http://php.net/manual/en/function.trim.php
     */
    public function trimString($string, $charlist = null)
    {
        if (empty($string) && !is_numeric($string)) {
            return '';
        }
        $string = htmlentities($string);
        // blame TinyMCE!
        $string = preg_replace('/(&Acirc;|&nbsp;)+/i', '', $string);
        $string = trim($string, $charlist);
        $string = trim(preg_replace('/\s+^(\r|\n|\r\n)/', ' ', $string));
        $string = html_entity_decode($string);
        return $string;
    }

    /**
     * Trim array values
     * @param array $input array contents
     * @param string $charlist [default: null] defined characters to be trimmed
     * @return array trimmed array
     * @link http://php.net/manual/en/function.trim.php
     */
    public function trimArray($input, $charlist = null)
    {
        if (is_array($input)) {
            $output = array_map([$this, 'trimArray'], $input);
        } else {
            $output = $this->trimString($input, $charlist);
        }

        return $output;
    }

    /**
     * Replace the property's placeholders
     * @param string|array $subject Property
     * @return string|array The replaced results
     */
    public function replacePropPhs($subject)
    {
        $pattern = [
            '/\{core_path\}/',
            '/\{base_path\}/',
            '/\{assets_url\}/',
            '/\{filemanager_path\}/',
            '/\[\[\+\+core_path\]\]/',
            '/\[\[\+\+base_path\]\]/'
        ];
        $replacement = [
            $this->modx->getOption('core_path'),
            $this->modx->getOption('base_path'),
            $this->modx->getOption('assets_url'),
            $this->modx->getOption('filemanager_path'),
            $this->modx->getOption('core_path'),
            $this->modx->getOption('base_path')
        ];
        if (is_array($subject)) {
            $parsedString = [];
            foreach ($subject as $k => $s) {
                if (is_array($s)) {
                    $s = $this->replacePropPhs($s);
                }
                $parsedString[$k] = preg_replace($pattern, $replacement, $s);
            }
            return $parsedString;
        } else {
            return preg_replace($pattern, $replacement, $subject);
        }
    }

    /**
     * Get the clean path array and clean up some duplicate slashes
     * @param string $paths multiple paths with comma separated
     * @return array Dir paths in an array
     */
    private function checkPath($paths)
    {
        $forbiddenFolders = [
            realpath(MODX_CORE_PATH),
            realpath(MODX_PROCESSORS_PATH),
            realpath(MODX_CONNECTORS_PATH),
            realpath(MODX_MANAGER_PATH),
            realpath(MODX_BASE_PATH)
        ];
        $cleanPaths = [];
        if (!empty($paths)) {
            $xPath = @explode(',', $paths);
            foreach ($xPath as $path) {
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
                if (in_array($fullPath, $forbiddenFolders)) {
                    continue;
                }
                $cleanPaths[$path] = $fullPath;
            }
        }

        return $cleanPaths;
    }

    /**
     * Retrieve the content of the given path
     * @return array All contents in an array
     */
    public function getContents()
    {
        $this->modx->invokeEvent('OnFileDownloadLoad');

        $dirContents = [];
        if (!empty($this->getOption('getDir'))) {
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
        $mergedContents = $this->sortOrder($mergedContents);

        return $mergedContents;
    }

    /**
     * Existed description from the chunk of the &chkDesc parameter
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

        $linesX = @explode('||', $chunkContent);
        array_walk($linesX, function ($val) {
            return trim($val);
        });
        foreach ($linesX as $k => $v) {
            if (empty($v)) {
                unset($linesX[$k]);
                continue;
            }
            $descX = @explode('|', $v);
            array_walk($descX, function ($val) {
                return trim($val);
            });

            $phsReplaced = $this->replacePropPhs($descX[0]);
            $realPath = realpath($phsReplaced);

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
     * Get dynamic file's basepath
     * @param string $filename file's name
     * @return string
     */
    public function getBasePath($filename)
    {
        if (!empty($this->mediaSource)) {
            if (method_exists($this->mediaSource, 'getBasePath')) {
                return $this->mediaSource->getBasePath($filename);
            } elseif (method_exists($this->mediaSource, 'getBaseUrl')) {
                return $this->mediaSource->getBaseUrl();
            }
        }
        return false;
    }

    /**
     * Check the called file contents with the registered database.
     * If it's not listed, auto save
     * @param array $file Realpath filename / dirname
     * @param boolean $autoCreate Auto create database if it doesn't exist
     * @return bool|array
     */
    private function checkDb(array $file = [], $autoCreate = true)
    {
        if (empty($file)) {
            return false;
        }

        if (empty($this->mediaSource)) {
            $realPath = realpath($file['filename']);
            if (empty($realPath)) {
                return false;
            }
        } else {
            $search = $this->getBasePath($file['filename']);
            if (!empty($search)) {
                $file['filename'] = str_replace($search, '', $file['filename']);
            }
        }

        $filename = $file['filename'];

        $fdlPath = $this->modx->getObject('fdPaths', [
            'ctx' => $file['ctx'],
            'media_source_id' => $this->getOption('mediaSourceId'),
            'filename' => $filename,
            'hash' => $this->setHashedParam($file['ctx'], $file['filename'])
        ]);
        if (!$fdlPath) {
            if (!$autoCreate) {
                return false;
            }
            $fdlPath = $this->modx->newObject('fdPaths');
            $fdlPath->fromArray([
                'ctx' => $file['ctx'],
                'media_source_id' => $this->getOption('mediaSourceId'),
                'filename' => $filename,
                'count' => 0,
                'hash' => $this->setHashedParam($file['ctx'], $file['filename'])
            ]);
            if ($fdlPath->save() === false) {
                $msg = $this->modx->lexicon('filedownloadr.err_save_counter');
                $this->setError($msg);
                $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                return false;
            }
        }
        $checked = $fdlPath->toArray();
        $checked['count'] = (int)$this->modx->getCount('fdDownloads', ['path_id' => $fdlPath->getPrimaryKey()]);

        return $checked;
    }

    /**
     * Check any duplication output
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
     * Count the numbers retrieved objects (dirs/files)
     * @param string $subject the specified subject
     * @return int     number of the subject
     */
    public function countContents($subject)
    {
        if ($subject === 'dirs') {
            return $this->_count['dirs'];
        } elseif ($subject === 'files') {
            return $this->_count['files'];
        } else {
            return intval(0);
        }
    }

    /**
     * Retrieve the content of the given directory path
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
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, '&getDir parameter expects a correct dir path. <b>"' . $rootPath . '"</b> is given.', '', 'FileDownloadR', __FILE__, __LINE__);
                    return [];
                }
            }

            $result = $this->modx->invokeEvent('OnFileDownloadBeforeDirOpen', [
                'dirPath' => $rootPath,
            ]);
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
                $scanDir = scandir($rootPath);

                // Add root path to the Download DB
                $cdb = [];
                $cdb['ctx'] = $this->modx->context->key;
                $cdb['filename'] = $rootPath . $this->ds;
                $cdb['hash'] = $this->getHashedParam($cdb['ctx'], $cdb['filename']);
                $this->checkDb($cdb);

                $excludes = $this->getOption('exclude_scan');
                $excludes = array_map('trim', @explode(',', $excludes));
                foreach ($scanDir as $file) {
                    if (in_array($file, $excludes)) {
                        continue;
                    }

                    $rootRealPath = realpath($rootPath);
                    $fullPath = $rootRealPath . $this->ds . $file;
                    $fileType = @filetype($fullPath);

                    if ($fileType == 'file') {
                        $fileInfo = $this->fileInformation($fullPath);
                        if (!$fileInfo) {
                            continue;
                        }
                        $contents[] = $fileInfo;
                    } elseif ($this->getOption('browseDirectories')) {
                        // a directory
                        $cdb = [];
                        $cdb['ctx'] = $this->modx->context->key;
                        $cdb['filename'] = $fullPath . $this->ds;
                        $cdb['hash'] = $this->getHashedParam($cdb['ctx'], $cdb['filename']);

                        $checkedDb = $this->checkDb($cdb);
                        if (!$checkedDb) {
                            continue;
                        }

                        $notation = $this->aliasName($file);
                        $alias = $notation[1];

                        $unixDate = filemtime($fullPath);
                        $date = date($this->getOption('dateFormat'), $unixDate);
                        $link = $this->linkDirOpen($checkedDb['hash'], $checkedDb['ctx']);

                        $imgType = $this->imgType('dir');
                        $dir = [
                            'ctx' => $checkedDb['ctx'],
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
                            'count' => (int)$this->modx->getCount('fdDownloads', ['path_id' => $checkedDb['id']]),
                            'link' => $link['url'], // fallback
                            'url' => $link['url'],
                            'hash' => $checkedDb['hash']
                        ];

                        $contents[] = $dir;
                    }
                }
            } else {
                $scanDir = $this->mediaSource->getContainerList($rootPath);

                $excludes = $this->getOption('exclude_scan');
                $excludes = array_map('trim', @explode(',', $excludes));
                foreach ($scanDir as $file) {
                    if (in_array(($file['text']), $excludes)) {
                        continue;
                    }

                    $fullPath = $file['id'];

                    if ($file['type'] == 'file') {
                        $fileInfo = $this->fileInformation($fullPath);
                        if (!$fileInfo) {
                            continue;
                        }

                        $contents[] = $fileInfo;
                    } elseif ($this->getOption('browseDirectories')) {
                        // a directory
                        $cdb = [];
                        $cdb['ctx'] = $this->modx->context->key;
                        $cdb['filename'] = $fullPath;
                        $cdb['hash'] = $this->getHashedParam($cdb['ctx'], $cdb['filename']);

                        $checkedDb = $this->checkDb($cdb);
                        if (!$checkedDb) {
                            continue;
                        }

                        $notation = $this->aliasName($file['name']);
                        $alias = $notation[1];

                        if (method_exists($this->mediaSource, 'getBasePath')) {
                            $rootRealPath = $this->mediaSource->getBasePath($rootPath) . $rootPath;
                            $unixDate = filemtime(realpath($rootRealPath));
                        } elseif (method_exists($this->mediaSource, 'getObjectUrl')) {
                            $rootRealPath = $this->mediaSource->getObjectUrl($rootPath);
                            $unixDate = filemtime($rootRealPath);
                        } else {
                            $rootRealPath = realpath($rootPath);
                            $unixDate = filemtime($rootRealPath);
                        }

                        $date = date($this->getOption('dateFormat'), $unixDate);
                        $link = $this->linkDirOpen($checkedDb['hash'], $checkedDb['ctx']);

                        $imgType = $this->imgType('dir');
                        $dir = [
                            'ctx' => $checkedDb['ctx'],
                            'fullPath' => $fullPath,
                            'path' => $rootRealPath,
                            'filename' => $this->basename($fullPath),
                            'alias' => $alias,
                            'type' => 'dir',
                            'ext' => '',
                            'size' => '',
                            'sizeText' => '',
                            'unixdate' => $unixDate,
                            'date' => $date,
                            'image' => $this->getOption('imgLocat') . $imgType,
                            'count' => (int)$this->modx->getCount('fdDownloads', ['path_id' => $checkedDb['id']]),
                            'link' => $link['url'], // fallback
                            'url' => $link['url'],
                            'hash' => $checkedDb['hash']
                        ];

                        $contents[] = $dir;
                    }
                }
            }

            $result = $this->modx->invokeEvent('OnFileDownloadAfterDirOpen', [
                'dirPath' => $rootPath,
                'contents' => $contents,
            ]);
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
     * Retrieve the content of the given file path
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
     * Retrieves the required information from a file
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
            $type = @filetype($fileRealPath);
        } else {
            if (method_exists($this->mediaSource, 'getBasePath')) {
                $fileRealPath = $this->mediaSource->getBasePath($path) . $path;
            } elseif (method_exists($this->mediaSource, 'getObjectUrl')) {
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
            $type = @filetype($fileRealPath);
        }

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

        $cdb = [];
        $cdb['ctx'] = $this->modx->context->key;
        $cdb['filename'] = $fileRealPath;
        $cdb['hash'] = $this->getHashedParam($cdb['ctx'], $cdb['filename']);

        $checkedDb = $this->checkDb($cdb);
        if (!$checkedDb) {
            return [];
        }

        if ($this->getOption('directLink')) {
            $link = $this->directLinkFileDownload($checkedDb['filename']);
            if (!$link) {
                return [];
            }
        } else {
            $link = $this->linkFileDownload($checkedDb['filename'], $checkedDb['hash'], $checkedDb['ctx']);
        }
        $linkdelete = $this->linkFileDelete($checkedDb['filename'], $checkedDb['hash'], $checkedDb['ctx']);

        $unixDate = filemtime($fileRealPath);
        $date = date($this->getOption('dateFormat'), $unixDate);
        $info = [
            'ctx' => $checkedDb['ctx'],
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
            'count' => (int)$this->modx->getCount('fdDownloads', ['path_id' => $checkedDb['id']]),
            'link' => $link['url'], // fallback
            'url' => $link['url'],
            'deleteurl' => $linkdelete['url'],
            'hash' => $checkedDb['hash']
        ];

        return $info;
    }

    /**
     * Get the alias/description from the pipe ( "|" ) symbol on the snippet
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
     * Custom basename, because PHP's basename can not read Chinese characters
     * @param string $path full path
     */
    private function basename($path)
    {
        $parts = @explode($this->ds, $path);
        $parts = array_reverse($parts);

        return $parts[0];
    }

    /**
     * Get the right image type to the specified file's extension, or fall back
     * to the default image.
     * @param string $ext
     * @return string
     */
    private function imgType($ext)
    {
        return isset($this->imgTypes[$ext]) ? $this->imgTypes[$ext] : (isset($this->imgTypes['default']) ? $this->imgTypes['default'] : '');
    }

    /**
     * Retrieve the images for the specified file extensions
     * @return array file type's images
     */
    private function imgTypeProp()
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
            $existingArgs = $this->modx->request->getParameters();
            $args = [];
            if (!empty($existingArgs)) {
                unset($existingArgs['id']);
                unset($existingArgs['fdldelete']);
                foreach ($existingArgs as $k => $v) {
                    $args[$k] = $v;
                }
            }
            $args['fdlfile'] = $hash;
            $url = $this->modx->makeUrl($this->modx->resource->get('id'), $ctx, $args);
            $link['url'] = $url;
        }
        $link['hash'] = $hash;
        return $link;
    }

    /**
     * @param string $filePath file's path
     * @param string $hash hash
     * @param string $ctx specifies a context to limit URL generation to.
     * @return array the download link and the javascript's attribute
     */
    private function linkFileDelete($filePath, $hash, $ctx = 'web')
    {
        $link = [];
        if (!$this->isAllowed('deleteGroups')) {
            $link['url'] = '';
        } else {
            $existingArgs = $this->modx->request->getParameters();
            $args = [];
            if (!empty($existingArgs)) {
                unset($existingArgs['id']);
                unset($existingArgs['fdlfile']);
                foreach ($existingArgs as $k => $v) {
                    $args[$k] = $v;
                }
            }
            $args['fdldelete'] = $hash;
            $url = $this->modx->makeUrl($this->modx->resource->get('id'), $ctx, $args);
            $link['url'] = $url;
        }
        $link['hash'] = $hash;
        return $link;
    }

    /**
     * Set the direct link to the file path
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
            // to use this method, the file should always be placed on the web root
            $corePath = str_replace('/', $this->ds, MODX_CORE_PATH);
            if (stristr($filePath, $corePath)) {
                return [];
            }
            // switching from absolute path to url is nuts
            if (empty($this->mediaSource)) {
                $fileUrl = str_ireplace(MODX_BASE_PATH, MODX_SITE_URL, $filePath);
                $fileUrl = str_replace($this->ds, '/', $fileUrl);
                $parseUrl = parse_url($fileUrl);
                $url = ltrim($parseUrl['path'], '/' . MODX_HTTP_HOST);
                $link['url'] = MODX_URL_SCHEME . MODX_HTTP_HOST . '/' . $url;
            } else {
                if (method_exists($this->mediaSource, 'getObjectUrl')) {
                    $link['url'] = $this->mediaSource->getObjectUrl($filePath);
                }
            }
        }
        $link['hash'] = '';
        return $link;
    }

    /**
     * @param string $hash hash
     * @param string $ctx specifies a context to limit URL generation to.
     * @return array the open directory link and the javascript's attribute
     */
    private function linkDirOpen($hash, $ctx = 'web')
    {
        if (!$this->getOption('browseDirectories')) {
            return [];
        }
        $queries = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        $existingArgs = [];
        if (!empty($queries)) {
            $queries = @explode('&', $queries);
            foreach ($queries as $query) {
                $xquery = @explode('=', $query);
                $existingArgs[$xquery[0]] = !empty($xquery[1]) ? $xquery[1] : '';
            }
        }
        $args = [];
        if (!empty($existingArgs)) {
            unset($existingArgs['id']);
            foreach ($existingArgs as $k => $v) {
                $args[] = $k . '=' . $v;
            }
        }
        $args['fdldir'] = $hash;
        if (!empty($this->getOption('fdlid'))) {
            $args['fdlid'] = $this->getOption('fdlid');
        }
        $url = $this->modx->makeUrl($this->modx->resource->get('id'), $ctx, $args);
        $link = [];
        $link['url'] = $url;
        $link['hash'] = $hash;
        return $link;
    }

    /**
     * Set the new value to the getDir property to browse inside the clicked
     * directory
     * @param string $hash the hashed link
     * @param bool $selected to patch multiple snippet call
     * @return bool    true | false
     */
    public function setDirProp($hash, $selected = true)
    {
        if (empty($hash) || !$selected) {
            return false;
        }
        $fdlPath = $this->modx->getObject('fdPaths', ['hash' => $hash]);
        if (!$fdlPath) {
            return false;
        }
        $ctx = $fdlPath->get('ctx');
        if ($this->modx->context->key !== $ctx) {
            return false;
        }
        $path = $fdlPath->get('filename');
        $this->options['getDir'] = [$path];
        $this->options['getFile'] = [];

        return true;
    }

    /**
     * Download action
     * @param string $hash hashed text
     * @return boolean|void file is pulled to the browser
     */
    public function downloadFile($hash)
    {
        if (empty($hash)) {
            return false;
        }
        $fdlPath = $this->modx->getObject('fdPaths', ['hash' => $hash]);
        if (!$fdlPath) {
            return false;
        }
        $ctx = $fdlPath->get('ctx');
        if ($this->modx->context->key !== $ctx) {
            return false;
        }
        $mediaSourceId = $fdlPath->get('media_source_id');
        if (intval($this->getOption('mediaSourceId')) !== $mediaSourceId) {
            return false;
        }
        $filePath = $fdlPath->get('filename');

        $result = $this->modx->invokeEvent('OnFileDownloadBeforeFileDownload', [
            'hash' => $hash,
            'ctx' => $ctx,
            'mediaSourceId' => $mediaSourceId,
            'filePath' => $filePath,
            'count' => $this->modx->getCount('fdDownloads', ['path_id' => $fdlPath->get('id')]),
        ]);
        if (is_array($result)) {
            foreach ($result as $msg) {
                if ($msg === false) {
                    return false;
                }
            }
        }

        $fileExists = false;
        $filename = $this->basename($filePath);
        if (empty($this->mediaSource)) {
            if (file_exists($filePath)) {
                $fileExists = true;
                $realFilePath = $filePath;
            }
        } else {
            if (method_exists($this->mediaSource, 'getBasePath')) {
                $realFilePath = $this->mediaSource->getBasePath($filePath) . $filePath;
                if (file_exists($realFilePath)) {
                    $fileExists = true;
                }
            } elseif (method_exists($this->mediaSource, 'getBaseUrl')) {
                $this->mediaSource->getObjectUrl($filePath);
                $content = @file_get_contents(urlencode($this->mediaSource->getObjectUrl($filePath)));
                if (!empty($content)) {
                    $pathParts = pathinfo($filename);
                    $temp = tempnam(sys_get_temp_dir(), 'fdl_' . time() . '_' . $pathParts['filename'] . '_');
                    $handle = fopen($temp, "r+b");
                    fwrite($handle, $content);
                    fseek($handle, 0);
                    fclose($handle);
                    $realFilePath = $temp;
                    $fileExists = true;
                } else {
                    $msg = 'Unable to get the content from remote server';
                    $this->setError($msg);
                    $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
                }
            } else {
                $fileExists = false;
            }
        }
        if ($fileExists) {
            // required for IE
            if (ini_get('zlib.output_compression')) {
                ini_set('zlib.output_compression', 'Off');
            }

            @set_time_limit(300);
            @ini_set('magic_quotes_runtime', 0);
            ob_end_clean(); //added to fix ZIP file corruption
            ob_start(); //added to fix ZIP file corruption

            header('Pragma: public'); // required
            header('Expires: 0'); // no cache
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: private', false);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($realFilePath)) . ' GMT');
            header('Content-Description: File Transfer');
            header('Content-Type:'); //added to fix ZIP file corruption
            header('Content-Type: "application/force-download"');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . (string)(filesize($realFilePath))); // provide file size
            header('Connection: close');
            sleep(1);

            //Close the session to allow for header() to be sent
            session_write_close();
            ob_flush();
            flush();

            $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
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
                ob_flush();
                flush();
            }
            fclose($handle);
            if (!empty($temp)) {
                @unlink($temp);
            }
            if ($this->getOption('countDownloads')) {
                $this->setDownloadCount($hash);
            }

            $this->modx->invokeEvent('OnFileDownloadAfterFileDownload', [
                'hash' => $hash,
                'ctx' => $ctx,
                'mediaSourceId' => $mediaSourceId,
                'filePath' => $filePath,
                'count' => $this->modx->getCount('fdDownloads', ['path_id' => $fdlPath->get('id')]),
            ]);

            exit;
        }

        return false;
    }

    /**
     * Delete action
     * @param string $hash hashed text
     * @return boolean
     */
    public function deleteFile($hash)
    {
        if (empty($hash)) {
            return false;
        }
        $fdlPath = $this->modx->getObject('fdPaths', ['hash' => $hash]);
        if (!$fdlPath) {
            return false;
        }
        $ctx = $fdlPath->get('ctx');
        if ($this->modx->context->key !== $ctx) {
            return false;
        }
        $mediaSourceId = $fdlPath->get('media_source_id');
        if (intval($this->getOption('mediaSourceId')) !== $mediaSourceId) {
            return false;
        }
        $filePath = $fdlPath->get('filename');
        $fileExists = false;

        if ($this->isAllowed('deleteGroups')) {
            if (empty($this->mediaSource)) {
                if (file_exists($filePath)) {
                    @unlink($filePath);
                    $fileExists = true;
                }
            } else {
                if (method_exists($this->mediaSource, 'getBasePath')) {
                    if (file_exists($this->mediaSource->getBasePath($filePath) . $filePath)) {
                        @unlink($this->mediaSource->getBasePath($filePath) . $filePath);
                        $fileExists = true;
                    }
                } else {
                    if (method_exists($this->mediaSource, 'getBaseUrl')) {
                        // @todo remove the file from the media source
                    }
                }
            }
        }

        return $fileExists;
    }

    /**
     * Add download counter
     * @param string $hash secret hash
     */
    private function setDownloadCount($hash)
    {
        if (!$this->getOption('countDownloads')) {
            return;
        }
        $fdlPath = $this->modx->getObject('fdPaths', ['hash' => $hash]);
        if (!$fdlPath) {
            return;
        }
        // save the new count
        $fdDownload = $this->modx->newObject('fdDownloads');
        $fdDownload->set('path_id', $fdlPath->getPrimaryKey());
        $fdDownload->set('referer', urldecode($_SERVER['HTTP_REFERER']));
        $fdDownload->set('user', $this->modx->user->get('id'));
        $fdDownload->set('timestamp', time());
        if (!empty($this->getOption('useGeolocation')) && !empty($this->getOption('geoApiKey'))) {
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
        }
        if ($fdDownload->save() === false) {
            $msg = $this->modx->lexicon('filedownloadr.err_save_counter');
            $this->setError($msg);
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, $msg, '', 'FileDownloadR', __FILE__, __LINE__);
        }
    }

    /**
     * getIpAddress
     *
     * Returns the users IP Address
     * This data shouldn't be trusted. Faking HTTP headers is trivial.
     *
     * @return string/false - the users IP address or false
     *
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
     * Check whether the file with the specified extension is hidden from the list
     * @param string $ext file's extension
     * @return bool    true | false
     */
    private function isExtHidden($ext)
    {
        if (empty($this->getOption('extHidden'))) {
            return false;
        }
        $extHiddenX = @explode(',', $this->getOption('extHidden'));
        array_walk($extHiddenX, function ($val) {
            return strtolower(trim($val));
        });
        if (!in_array($ext, $extHiddenX)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check whether the file with the specified extension is shown to the list
     * @param string $ext file's extension
     * @return bool    true | false
     */
    private function isExtShown($ext)
    {
        if (empty($this->getOption('extShown'))) {
            return true;
        }
        $extShownX = @explode(',', $this->getOption('extShown'));
        array_walk($extShownX, function ($val) {
            return strtolower(trim($val));
        });
        if (in_array($ext, $extShownX)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check the user's group
     * @param void
     * @return bool    true | false
     */
    public function isAllowed($type = 'userGroups')
    {
        if (empty($this->getOption($type))) {
            return true;
        } else {
            $userGroupsX = @explode(',', $this->options[$type]);
            array_walk($userGroupsX, function ($val) {
                return trim($val);
            });
            $userAccessGroupNames = $this->userAccessGroupNames();

            $intersect = array_uintersect($userGroupsX, $userAccessGroupNames, "strcasecmp");

            if (count($intersect) > 0) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Get logged in usergroup names
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
     * Prettify the file size with thousands unit byte
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
     * Manage the order sorting by all sorting parameters:
     * - sortBy
     * - sortOrder
     * - sortOrderNatural
     * - sortByCaseSensitive
     * - browseDirectories
     * - groupByDirectory
     * @param array $contents unsorted contents
     * @return array sorted contents
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
                $this->_output['rows'] .= $this->tplDirectory($k);

                $sort['path'][$k] = $this->groupByType($path);
            }
        }

        return $sort;
    }

    /**
     * Grouping the contents by filetype
     * @param array $contents contents
     * @return array grouped contents
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
        $dirs = '';
        if (!empty($this->getOption('browseDirectories')) && !empty($sortType['dir'])) {
            $sort['dir'] = $sortType['dir'];
            // template
            $row = 1;
            foreach ($sort['dir'] as $k => $v) {
                $v['class'] = $this->cssDir($row);
                $dirs .= $this->tplDir($v);
                $row++;
            }
        }

        $phs = [];
        $phs[$this->getOption('prefix') . 'classPath'] = (!empty($this->getOption('cssPath'))) ? ' class="' . $this->getOption('cssPath') . '"' : '';
        $phs[$this->getOption('prefix') . 'path'] = $this->breadcrumbs();
        $this->_output['dirRows'] = '';
        if (!empty($this->getOption('tplWrapperDir')) && !empty($dirs)) {
            $phs[$this->getOption('prefix') . 'dirRows'] = $dirs;
            $this->_output['dirRows'] .= $this->parse->getChunk($this->getOption('tplWrapperDir'), $phs);
        } else {
            $this->_output['dirRows'] .= $dirs;
        }

        $files = '';
        if (!empty($sortType['file'])) {
            $sort['file'] = $sortType['file'];
            // template
            $row = 1;
            foreach ($sort['file'] as $k => $v) {
                $v['class'] = $this->cssFile($row, $v['ext']);
                $files .= $this->tplFile($v);
                $row++;
            }
        }
        $this->_output['fileRows'] = '';
        if (!empty($this->getOption('tplWrapperFile')) && !empty($files)) {
            $phs[$this->getOption('prefix') . 'fileRows'] = $files;
            $this->_output['fileRows'] .= $this->parse->getChunk($this->getOption('tplWrapperFile'), $phs);
        } else {
            $this->_output['fileRows'] .= $files;
        }

        $this->_output['rows'] .= $this->_output['dirRows'];
        $this->_output['rows'] .= $this->_output['fileRows'];

        return $sort;
    }

    /**
     * Multi dimensional sorting
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
     * Generate the class names for the directory rows
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
     * Generate the class names for the file rows
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
     * Parsing the directory template
     * @param array $contents properties
     * @return string rendered HTML
     */
    private function tplDir(array $contents)
    {
        if (empty($contents)) {
            return '';
        }
        $phs = [];
        foreach ($contents as $k => $v) {
            $phs[$this->getOption('prefix') . $k] = $v;
        }
        $tpl = $this->parse->getChunk($this->getOption('tplDir'), $phs);

        return $tpl;
    }

    /**
     * Parsing the file template
     * @param array $fileInfo properties
     * @return string rendered HTML
     */
    private function tplFile(array $fileInfo)
    {
        if (empty($fileInfo) || empty($this->getOption('tplFile'))) {
            return '';
        }
        $phs = [];
        foreach ($fileInfo as $k => $v) {
            $phs[$this->getOption('prefix') . $k] = $v;
        }
        $tpl = $this->parse->getChunk($this->getOption('tplFile'), $phs);

        return $tpl;
    }

    /**
     * Path template if &groupByDirectory is enabled
     * @param string $path Path's name
     * @return string rendered HTML
     */
    private function tplDirectory($path)
    {
        if (empty($path) || is_array($path)) {
            return '';
        }
        $phs[$this->getOption('prefix') . 'class'] = (!empty($this->getOption('cssGroupDir'))) ? ' class="' . $this->getOption('cssGroupDir') . '"' : '';
        $groupPath = str_replace($this->ds, $this->getOption('breadcrumbSeparator'), $this->trimPath($path));
        $phs[$this->getOption('prefix') . 'groupDirectory'] = $groupPath;
        $tpl = $this->parse->getChunk($this->getOption('tplGroupDir'), $phs);

        return $tpl;
    }

    /**
     * Wraps templates
     * @return string rendered template
     */
    private function tplWrapper()
    {
        $phs[$this->getOption('prefix') . 'classPath'] = (!empty($this->getOption('cssPath'))) ? ' class="' . $this->getOption('cssPath') . '"' : '';
        $phs[$this->getOption('prefix') . 'path'] = $this->breadcrumbs();
        $rows = !empty($this->_output['rows']) ? $this->_output['rows'] : '';
        $phs[$this->getOption('prefix') . 'rows'] = $rows;
        $phs[$this->getOption('prefix') . 'dirRows'] = $this->_output['dirRows'];
        $phs[$this->getOption('prefix') . 'fileRows'] = $this->_output['fileRows'];
        if (!empty($this->getOption('tplWrapper'))) {
            $tpl = $this->parse->getChunk($this->getOption('tplWrapper'), $phs);
        } else {
            $tpl = $rows;
        }

        return $tpl;
    }

    /**
     * Trim the absolute path to be a relatively safe path
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
                $modxCorePath = realpath(MODX_CORE_PATH) . $this->ds;
                $modxAssetsPath = realpath(MODX_ASSETS_PATH) . $this->ds;
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
     * Get absolute path of the given relative path, based on media source
     * @param string $path relative path
     * @return string absolute path
     */
    private function getAbsolutePath($path)
    {
        $output = '';
        if (empty($this->mediaSource)) {
            $output = realpath($path) . $this->ds;
        } else {
            if (method_exists($this->mediaSource, 'getBasePath')) {
                $output = $this->mediaSource->getBasePath($path) . trim($path, $this->ds) . $this->ds;
            } elseif (method_exists($this->mediaSource, 'getObjectUrl')) {
                $output = $this->mediaSource->getObjectUrl($path) . $this->ds;
            }
        }
        return $output;
    }

    /**
     * Create a breadcrumbs link
     * @param void
     * @return string a breadcrumbs link
     */
    private function breadcrumbs()
    {
        if (empty($this->getOption('browseDirectories'))) {
            return '';
        }
        $dirs = $this->getOption('getDir');
        if (count($dirs) > 1) {
            return '';
        } else {
            $path = $dirs[0];
        }
        $trimmedPath = trim($path);
        $trimmedPath = trim($this->trimPath($trimmedPath), $this->ds);
        $trimmedPath = trim($trimmedPath, $this->getOption('breadcrumbSeparator'));
        $basePath = str_replace($trimmedPath, '', $path);
        if ($basePath === '/' || $basePath == '//') {
            $basePath = '';
        }
        if ($this->ds !== $this->getOption('breadcrumbSeparator')) {
            $pattern = '`[' . preg_quote($this->ds) . preg_quote($this->getOption('breadcrumbSeparator')) . ']+`';
        } else {
            $pattern = '`[' . preg_quote($this->ds) . ']+`';
        }
        $trimmedPathX = preg_split($pattern, $trimmedPath);
        $trailingPath = $basePath;
        $trail = [];
        $trailingLink = [];
        $countTrimmedPathX = count($trimmedPathX);
        foreach ($trimmedPathX as $k => $title) {
            $trailingPath .= $title . $this->ds;
            $absPath = $this->getAbsolutePath($trailingPath);
            if (empty($absPath)) {
                return false;
            }
            $fdlPath = $this->modx->getObject('fdPaths', [
                'ctx' => $this->modx->context->key,
                'media_source_id' => $this->getOption('mediaSourceId'),
                'filename' => $absPath,
            ]);
            if (!$fdlPath) {
                $cdb = [];
                $cdb['ctx'] = $this->modx->context->key;
                $cdb['filename'] = $absPath;

                $checkedDb = $this->checkDb($cdb, false);
                if (!$checkedDb) {
                    continue;
                }
                $fdlPath = $this->modx->getObject('fdPaths', [
                    'ctx' => $this->modx->context->key,
                    'media_source_id' => $this->getOption('mediaSourceId'),
                    'filename' => $absPath
                ]);
            }
            $hash = $fdlPath->get('hash');
            $link = $this->linkDirOpen($hash, $this->modx->context->key);

            if ($k === 0) {
                $pageUrl = $this->modx->makeUrl($this->modx->resource->get('id'));
                $trail[$k] = [
                    $this->getOption('prefix') . 'title' => $this->modx->lexicon('filedownloadr.breadcrumb.home'),
                    $this->getOption('prefix') . 'link' => $pageUrl,
                    $this->getOption('prefix') . 'url' => $pageUrl,
                    $this->getOption('prefix') . 'hash' => '',
                ];
            } else {
                $trail[$k] = [
                    $this->getOption('prefix') . 'title' => $title,
                    $this->getOption('prefix') . 'link' => $link['url'], // fallback
                    $this->getOption('prefix') . 'url' => $link['url'],
                    $this->getOption('prefix') . 'hash' => $hash,
                ];
            }
            if ($k < ($countTrimmedPathX - 1)) {
                $trailingLink[] = $this->parse->getChunk($this->getOption('tplBreadcrumb'), $trail[$k]);
            } else {
                $trailingLink[] = $title;
            }
        }
        $breadcrumb = @implode($this->getOption('breadcrumbSeparator'), $trailingLink);

        return $breadcrumb;
    }

    public function parseTemplate()
    {
        $o = $this->tplWrapper();
        return $o;
    }

    /**
     * Sets the salted parameter to the database
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
     * Gets the salted parameter from the System Settings + stored hashed parameter.
     * @param string $ctx context
     * @param string $filename filename
     * @return string hashed parameter
     */
    private function getHashedParam($ctx, $filename)
    {
        if (!empty($this->mediaSource)) {
            $search = $this->getBasePath($filename);
            if (!empty($search)) {
                $filename = str_replace($search, '', $filename);
            }
        }
        $fdlPath = $this->modx->getObject('fdPaths', [
            'ctx' => $ctx,
            'media_source_id' => $this->getOption('mediaSourceId'),
            'filename' => $filename
        ]);
        if (!$fdlPath) {
            return false;
        }
        return $fdlPath->get('hash');
    }

    /**
     * Check whether the REQUEST parameter exists in the database.
     * @param string $ctx context
     * @param string $hash hash value
     * @return bool    true | false
     */
    public function checkHash($ctx, $hash)
    {
        $fdlPath = $this->modx->getObject('fdPaths', [
            'ctx' => $ctx,
            'hash' => $hash
        ]);
        if (!$fdlPath) {
            return false;
        }
        return true;
    }
}
