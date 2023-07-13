<?php
/**
 * File Download Link Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 */

namespace TreehillStudio\FileDownloadR\Snippets;

use xPDO;

/**
 * Class FileDownloadSnippet
 */
class FileDownloadLinkSnippet extends Snippet
{
    /**
     * Get default snippet properties.
     *
     * @return array
     */
    public function getDefaultProperties()
    {
        return [
            'ajaxContainerId' => 'file-download',
            'ajaxControllerPage::int' => 0,
            'ajaxMode::bool' => false,
            'chkDesc' => '',
            'countDownloads::bool' => true,
            'countUserDownloads::bool' => false,
            'dateFormat' => 'Y-m-d',
            'directLink::bool' => false,
            'fileCss' => '{fd_assets_url}css/fd.min.css',
            'fileJs' => '',
            'geoApiKey' => $this->filedownloadr->getOption('ipinfodb_api_key'),
            'getFile' => '',
            'imgLocat' => '{fd_assets_url}img/filetypes/',
            'imgTypes' => 'fdImages',
            'mediaSourceId::int' => 0,
            'noDownload::bool' => false,
            'prefix' => 'fd.',
            'saltText' => 'FileDownloadR',
            'toArray::bool' => false,
            'tpl' => 'fdSingleFileTpl',
            'tplNotAllowed' => 'fdNotAllowedTpl',
            'useGeolocation::bool' => $this->filedownloadr->getOption('use_geolocation'),
            'userGroups' => '',
        ];
    }

    /**
     * Execute the snippet and return the result.
     *
     * @return string
     */
    public function execute()
    {
        $this->filedownloadr->initConfig($this->getProperties());

        if (!$this->filedownloadr->isAllowed()) {
            return $this->filedownloadr->parse->getChunk($this->getProperty('tplNotAllowed'), $this->getProperties());
        }
        if ($this->getProperty('fileCss') !== 'disabled') {
            $this->modx->regClientCSS($this->getProperty('fileCss'));
        }
        if ($this->getProperty('ajaxMode') && !empty($this->getProperty('ajaxControllerPage'))) {
            if ($this->getProperty('fileJs') !== 'disabled') {
                $this->modx->regClientStartupScript($this->getProperty('fileJs'));
            }
        }

        // Check the referrer if a file is downloaded
        if (!empty($_GET['fdlfile'])) {
            $this->filedownloadr->checkReferrer();
        }

        if (!$this->getProperty('downloadByOther')) {
            $sanitizedGets = $this->modx->sanitize($_GET);
            if (!empty($sanitizedGets['fdlfile'])) {
                // Download file
                $file = $sanitizedGets['fdlfile'];
                if ($this->filedownloadr->checkHash($this->modx->context->key, $file)) {
                    $this->filedownloadr->downloadFile($file);
                    // Simply terminate, because this is a downloading state
                    @session_write_close();
                    exit();
                }
                return '';
            }
        }

        $contents = $this->filedownloadr->getContents();
        if (empty($contents)) {
            return '';
        }

        $phs = $this->filedownloadr->config;
        $fileInfos = $contents['file'][0];
        foreach ($fileInfos as $k => $v) {
            $phs[$this->getProperty('prefix') . $k] = $v;
        }

        if (!empty($this->getProperty('input'))) {
            // Run as for Output Filter
            $output = !empty($fileInfos[$this->getProperty('options')]) ? $fileInfos[$this->getProperty('options')] : '';
        } elseif ($this->getProperty('toArray')) {
            $output = '<pre>' . print_r($fileInfos, true) . '</pre>';
        } else {
            $output = $this->filedownloadr->parse->getChunk($this->getProperty('tpl'), $fileInfos);
        }

        if (!empty($toPlaceholder)) {
            $this->modx->setPlaceholder($toPlaceholder, $output);
            return '';
        }
        return $output;
    }
}
