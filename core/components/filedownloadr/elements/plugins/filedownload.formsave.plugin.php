<?php
/**
 * FileDownload FormSave Plugin
 *
 * @package filedownloadr
 * @subpackage plugin
 *
 * @var modX $modx
 */

$corePath = $modx->getOption('filedownloadr.core_path', null, $modx->getOption('core_path') . 'components/filedownloadr/');
/** @var FileDownloadR $filedownloadr */
$filedownloadr = $modx->getService('filedownloadr', 'FileDownloadR', $corePath . 'model/filedownloadr/', [
    'core_path' => $corePath
]);

switch ($modx->event->name) {
    case 'OnFileDownloadLoad':
        // check the dependencies
        $formIt = $modx->getObject('modSnippet', ['name' => 'FormIt']);
        $formSave = $modx->getObject('modSnippet', ['name' => 'FormSave']);
        if (!$formIt || !$formSave) {
            $errMsg = 'Unable to load FormIt or FormSave';
            $modx->setPlaceholder($filedownloadr->getOption('prefix') . 'error_message', $errMsg);
            $modx->log(xPDO::LOG_LEVEL_ERROR, __LINE__ . ': ' . $errMsg, '', 'FileDownloadPlugin FormSave');
            return false;
        }
        break;
    case 'OnFileDownloadAfterFileDownload':
        $_POST = [
            'ctx' => $modx->event->params['ctx'],
            'filePath' => $modx->event->params['filePath'],
        ];
        $_REQUEST = $_POST;
        $runFormit = $modx->runSnippet('FormIt', [
            'hooks' => 'FormSave',
            'fsFormTopic' => 'downloader',
            'fsFormFields' => 'ctx,filePath',
        ]);
        if ($runFormit === false) {
            $errMsg = 'Unabled to save the downloader into FormSave';
            $modx->setPlaceholder($filedownloadr->getOption('prefix') . 'error_message', $errMsg);
            $modx->log(xPDO::LOG_LEVEL_ERROR, __LINE__ . ': ' . $errMsg, '', 'FileDownloadPlugin FormSave');
            return false;
        }
        break;
    default:
        break;
}

return true;
