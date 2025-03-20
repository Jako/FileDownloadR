<?php
/**
 * FileDownloadLinkSnippet Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 *
 * @var modX $modx
 * @var array $scriptProperties
 */

use TreehillStudio\FileDownloadR\Snippets\FileDownloadLinkSnippet;

$corePath = $modx->getOption('filedownloadr.core_path', null, $modx->getOption('core_path') . 'components/filedownloadr/');
/** @var FileDownloadR $filedownloadr */
$filedownloadr = $modx->getService('filedownloadr', FileDownloadR::class, $corePath . 'model/filedownloadr/', [
    'core_path' => $corePath
]);

$snippet = new FileDownloadLinkSnippet($modx, $scriptProperties);
if ($snippet instanceof TreehillStudio\FileDownloadR\Snippets\FileDownloadLinkSnippet) {
    return $snippet->execute();
}
return 'TreehillStudio\FileDownload\Snippets\FileDownloadLinkSnippet class not found';
