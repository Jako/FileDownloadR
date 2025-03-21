<?php
/**
 * FileDownloadR FormSave Plugin
 *
 * @package filedownloadr
 * @subpackage plugin
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

$className = 'TreehillStudio\FileDownloadR\Plugins\FormSaveEvents\\' . $modx->event->name;

$corePath = $modx->getOption('filedownloadr.core_path', null, $modx->getOption('core_path') . 'components/filedownloadr/');
/** @var FileDownloadR $filedownloadr */
$filedownloadr = $modx->getService('filedownloadr', FileDownloadR::class, $corePath . 'model/filedownloadr/', [
    'core_path' => $corePath
]);

if ($filedownloadr) {
    if (class_exists($className)) {
        $handler = new $className($modx, $scriptProperties);
        if (get_class($handler) == $className) {
            $handler->run();
        } else {
            $modx->log(xPDO::LOG_LEVEL_ERROR, $className . ' could not be initialized!', '', 'FileDownloadR Plugin');
        }
    } else {
        $modx->log(xPDO::LOG_LEVEL_ERROR, $className . ' was not found!', '', 'FileDownloadR Plugin');
    }
}

return;
