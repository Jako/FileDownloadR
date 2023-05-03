<?php
/**
 * FileDownloadR connector
 *
 * @package filedownloadr
 * @subpackage connector
 *
 * @var modX $modx
 */

$validActions = array(
    'web/file/get'
);
if (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], $validActions)) {
    @session_cache_limiter('public');
    define('MODX_REQP', false);
}

require_once dirname(__FILE__, 4) . '/config.core.php';
require_once MODX_CORE_PATH . 'config/' . MODX_CONFIG_KEY . '.inc.php';
require_once MODX_CONNECTORS_PATH . 'index.php';

$corePath = $modx->getOption('filedownloadr.core_path', null, $modx->getOption('core_path') . 'components/filedownloadr/');
/** @var FileDownloadR $filedownloadr */
$filedownloadr = $modx->getService('filedownloadr', FileDownloadR::class, $corePath . 'model/filedownloadr/', [
    'core_path' => $corePath
]);

// Handle request
$modx->request->handleRequest([
    'processors_path' => $filedownloadr->getOption('processorsPath'),
    'location' => ''
]);
