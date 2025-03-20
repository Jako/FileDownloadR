<?php
/**
 * FileDownloadR After File Download Email Event
 *
 * @package filedownloadr
 * @subpackage plugin
 */

namespace TreehillStudio\FileDownloadR\Plugins\EmailEvents;

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
        $emailProps = $this->filedownloadr->getOption('email_props');
        $this->modx->runSnippet('FormIt', array_merge([
            'hooks' => 'email',
            'successMessage' => 'success',
        ], $emailProps ?? []));
        if ($this->modx->getPlaceholder('fi.successMessage') !== 'success') {
            $errMsg = 'Unabled to send email.';
            $this->modx->setPlaceholder($this->filedownloadr->getOption('prefix') . 'error_message', $errMsg);
            $this->modx->log(xPDO::LOG_LEVEL_ERROR, __LINE__ . ': ' . $errMsg, '', 'FileDownloadPlugin Email');
            $this->modx->event->output($errMsg);
        }
    }
}
