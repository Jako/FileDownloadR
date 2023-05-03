<?php
/**
 * FileDownload Email Plugin
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
    case 'OnFileDownloadAfterFileDownload':
        $_POST = [
            'ctx' => $modx->event->params['ctx'],
            'filePath' => $modx->event->params['filePath'],
        ];
        $_REQUEST = $_POST;
        $emailProps = $filedownloadr->getOption('emailProps');
        $emailProps = json_decode($emailProps, true);
        $formitProps = array_merge(['hooks' => 'email'], $emailProps ?? []);
        $runFormit = $modx->runSnippet('FormIt', $formitProps);
        if ($runFormit === false) {
            $errMsg = 'Unabled to send email.';
            $modx->setPlaceholder($filedownloadr->getOption('prefix') . 'error_message', $errMsg);
            $modx->log(xPDO::LOG_LEVEL_ERROR, __LINE__ . ': ' . $errMsg, '', 'FileDownloadPlugin Email');
            return false;
        }
        break;
    default:
        break;
}

return true;
