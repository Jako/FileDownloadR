<?php
/**
 * FileDownloadCountSnippet Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

use TreehillStudio\FileDownloadR\Snippets\FileDownloadCountSnippet;

$corePath = $modx->getOption('filedownloadr.core_path', null, $modx->getOption('core_path') . 'components/filedownloadr/');
/** @var FileDownloadR $filedownloadr */
$filedownloadr = $modx->getService('filedownloadr', FileDownloadR::class, $corePath . 'model/filedownloadr/', [
    'core_path' => $corePath
]);

$snippet = new FileDownloadCountSnippet($modx, $scriptProperties);
if ($snippet instanceof TreehillStudio\FileDownloadR\Snippets\FileDownloadCountSnippet) {
    return $snippet->execute();
}
return 'TreehillStudio\FileDownload\Snippets\FileDownloadCountSnippet class not found';
