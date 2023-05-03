<?php
/**
 * FileDownload Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

use TreehillStudio\FileDownloadR\Snippets\FileDownloadSnippet;

$corePath = $modx->getOption('filedownloadr.core_path', null, $modx->getOption('core_path') . 'components/filedownloadr/');
/** @var FileDownloadR $filedownloadr */
$filedownloadr = $modx->getService('filedownloadr', 'FileDownloadR', $corePath . 'model/filedownloadr/', [
    'core_path' => $corePath
]);

$snippet = new FileDownloadSnippet($modx, $scriptProperties);
if ($snippet instanceof TreehillStudio\FileDownloadR\Snippets\FileDownloadSnippet) {
    return $snippet->execute();
}
return 'TreehillStudio\FileDownload\Snippets\FileDownloadSnippet class not found';
