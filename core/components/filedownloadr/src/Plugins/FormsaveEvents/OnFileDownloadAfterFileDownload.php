<?php
/**
 * FileDownloadR After File Download Formsave Event
 *
 * @package filedownloadr
 * @subpackage plugin
 */

namespace TreehillStudio\FileDownloadR\Plugins\FormsaveEvents;

use TreehillStudio\FileDownloadR\Plugins\Plugin;
use xPDO;

class OnFileDownloadAfterFileDownload extends Plugin
{
    /**
     * {@inheritDoc}
     * @return void
     */
    public function process()
    {
        $_POST = [
            'ctx' => $this->scriptProperties['ctx'],
            'filePath' => $this->scriptProperties['filePath'],
        ];
        $_REQUEST = $_POST;
        $this->modx->runSnippet('FormIt', [
            'hooks' => 'FormSave',
            'fsFormTopic' => 'downloader',
            'fsFormFields' => 'ctx,filePath',
            'successMessage' => 'success',
        ]);
        if ($this->modx->getPlaceholder('fi.successMessage') !== 'success') {
            $errMsg = 'Unabled to save the downloader into FormSave';
            $this->modx->setPlaceholder($this->filedownloadr->getOption('prefix') . 'error_message', $errMsg);
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, __LINE__ . ': ' . $errMsg, '', 'FileDownloadPlugin FormSave');
            $this->modx->event->output($errMsg);
        }
    }
}
