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
            'dateFormat' => 'Y-m-d',
            'directLink::bool' => false,
            'fdlid' => '',
            'fileCss' => $this->filedownloadr->getOption('assets_url') . 'css/fd.min.css',
            'fileJs' => '',
            'geoApiKey' => $this->filedownloadr->getOption('ipinfodb_api_key'),
            'getFile' => '',
            'imgLocat' => $this->filedownloadr->getOption('assets_url') . 'img/filetypes/',
            'imgTypes' => 'fdimages',
            'noDownload::bool' => false,
            'prefix' => 'fd.',
            'saltText' => 'FileDownloadR',
            'toArray::bool' => false,
            'tpl' => '@CODE: <a href="[[+link]]">[[+filename]]</a>',
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
        $this->filedownloadr->setConfigs($this->getProperties());

        if (!$this->filedownloadr->isAllowed()) {
            return $this->filedownloadr->parse->getChunk($this->getProperty('tplNotAllowed'), $this->getProperties());
        }
        if ($this->getProperty('fileCss') !== 'disabled') {
            $this->modx->regClientCSS($this->filedownloadr->replacePropPhs($this->getProperty('fileCss')));
        }
        if ($this->getProperty('ajaxMode') && !empty($this->getProperty('ajaxControllerPage'))) {
            if ($this->getProperty('fileJs') !== 'disabled') {
                $this->modx->regClientStartupScript($this->filedownloadr->replacePropPhs($this->getProperty('fileJs')));
            }
        }

        if (!empty($_GET['fdlfile'])) {
            $ref = $_SERVER['HTTP_REFERER'];
            // deal with multiple snippets which have &browseDirectories
            $xRef = @explode('?', $ref);
            $queries = [];
            parse_str($xRef[1], $queries);
            if (!empty($queries['id'])) {
                // non FURL
                $baseRef = $xRef[0] . '?id=' . $queries['id'];
            } else {
                $baseRef = $xRef[0];
            }
            $baseRef = urldecode($baseRef);
            $page = $this->modx->makeUrl($this->modx->resource->get('id'), '', '', 'full');
            // check referrer and the page
            if ($baseRef !== $page) {
                $this->modx->sendUnauthorizedPage();
            }
        }

        if (!$this->getProperty('downloadByOther')) {
            $sanitizedGets = $this->modx->sanitize($_GET);
            if (!empty($sanitizedGets['fdlfile'])) {
                if (!$this->filedownloadr->checkHash($this->modx->context->key, $sanitizedGets['fdlfile'])) {
                    return '';
                }
                if (!$this->filedownloadr->downloadFile($sanitizedGets['fdlfile'])) {
                    return '';
                }
                // simply terminate, because this is a downloading state
                @session_write_close();
                exit();
            }
        }

        $contents = $this->filedownloadr->getContents();
        if (empty($contents)) {
            return '';
        }

        $fileInfos = $contents['file'][0];
        $tmp = [];
        foreach ($fileInfos as $k => $v) {
            $tmp[$this->getProperty('prefix') . $k] = $v;
        }
        // fallback without prefix
        $fileInfos = array_merge($fileInfos, $tmp);

        /**
         * for Output Filter Modifier
         * @link http://rtfm.modx.com/display/revolution20/Custom+Output+Filter+Examples#CustomOutputFilterExamples-CreatingaCustomOutputModifier
         */
        if (!empty($this->getProperty('input'))) {
            $output = $fileInfos[$this->getProperty('options')];
            // avoid 0 (zero) of the download counting.
            if (empty($output) && !is_numeric($output)) {
                $output = $this->filedownloadr->parse->getChunk($this->getProperty('tpl'), $fileInfos);
            }
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
