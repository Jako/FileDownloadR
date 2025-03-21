<?php
/**
 * Abstract processor
 *
 * @package filedownloadr
 * @subpackage processors
 */

namespace TreehillStudio\FileDownloadR\Processors;

use modProcessor;
use modX;
use TreehillStudio\FileDownloadR\FileDownloadR;

/**
 * Class Processor
 */
abstract class Processor extends modProcessor
{
    public $languageTopics = ['filedownloadr:default'];

    /** @var FileDownloadR $filedownloadr */
    public $filedownloadr;

    /**
     * {@inheritDoc}
     * @param modX $modx A reference to the modX instance
     * @param array $properties An array of properties
     */
    public function __construct(modX &$modx, array $properties = [])
    {
        parent::__construct($modx, $properties);

        $corePath = $this->modx->getOption('filedownloadr.core_path', null, $this->modx->getOption('core_path') . 'components/filedownloadr/');
        $this->filedownloadr = $this->modx->getService('filedownloadr', FileDownloadR::class, $corePath . 'model/filedownloadr/');
    }

    /**
     * {@inheritDoc}
     * @return bool
     */
    public function checkPermissions()
    {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }

    abstract public function process();

    /**
     * Get a boolean property.
     * @param string $k
     * @param mixed $default
     * @return bool
     */
    public function getBooleanProperty($k, $default = null)
    {
        return ($this->getProperty($k, $default) === 'true' || $this->getProperty($k, $default) === true || $this->getProperty($k, $default) === '1' || $this->getProperty($k, $default) === 1);
    }
}
