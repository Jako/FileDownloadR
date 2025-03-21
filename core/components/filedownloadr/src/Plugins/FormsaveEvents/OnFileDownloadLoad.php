<?php
/**
 * FileDownloadR Load Formsave Event
 *
 * @package filedownloadr
 * @subpackage plugin
 */

namespace TreehillStudio\FileDownloadR\Plugins\FormsaveEvents;

use TreehillStudio\FileDownloadR\Plugins\Plugin;
use xPDO;

class OnFileDownloadLoad extends Plugin
{
    /**
     * {@inheritDoc}
     * @return void
     */
    public function process()
    {
        $formIt = $this->modx->getObject('modSnippet', ['name' => 'FormIt']);
        $formSave = $this->modx->getObject('modSnippet', ['name' => 'FormSave']);
        if (!$formIt || !$formSave) {
            $errMsg = 'Unable to load FormIt or FormSave';
            $this->modx->setPlaceholder($this->filedownloadr->getOption('prefix') . 'error_message', $errMsg);
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, __LINE__ . ': ' . $errMsg, '', 'FileDownloadPlugin FormSave');
            $this->modx->event->output($errMsg);
        }
    }
}
