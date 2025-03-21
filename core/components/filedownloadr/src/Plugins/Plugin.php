<?php
/**
 * Abstract plugin
 *
 * @package filedownloadr
 * @subpackage plugin
 */

namespace TreehillStudio\FileDownloadR\Plugins;

use modX;
use TreehillStudio\FileDownloadR\FileDownloadR;

/**
 * Class Plugin
 */
abstract class Plugin
{
    /** @var modX $modx */
    protected $modx;
    /** @var FileDownloadR $filedownloadr */
    protected $filedownloadr;
    /** @var array $scriptProperties */
    protected $scriptProperties;

    /**
     * Plugin constructor.
     *
     * @param $modx
     * @param $scriptProperties
     */
    public function __construct($modx, &$scriptProperties)
    {
        $this->scriptProperties = &$scriptProperties;
        $this->modx =& $modx;
        $corePath = $this->modx->getOption('filedownloadr.core_path', null, $this->modx->getOption('core_path') . 'components/filedownloadr/');
        $this->filedownloadr = $this->modx->getService('filedownloadr', FileDownloadR::class, $corePath . 'model/filedownloadr/', [
            'core_path' => $corePath
        ]);
    }

    /**
     * Run the plugin event.
     */
    public function run()
    {
        $init = $this->init();
        if ($init !== true) {
            return;
        }

        $this->process();
    }

    /**
     * Initialize the plugin event.
     *
     * @return bool
     */
    public function init()
    {
        return true;
    }

    /**
     * Process the plugin event code.
     *
     * @return mixed
     */
    abstract public function process();
}
