<?php
/**
 * File Download Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 */

namespace TreehillStudio\FileDownloadR\Snippets;

use xPDO;

/**
 * Class FileDownloadSnippet
 */
class FileDownloadSnippet extends Snippet
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
            'breadcrumbSeparator' => ' / ',
            'browseDirectories::bool' => false,
            'chkDesc' => '',
            'countDownloads::bool' => true,
            'cssAltRow' => 'fd-alt',
            'cssDir' => 'fd-dir',
            'cssExtension::bool' => false,
            'cssExtensionPrefix' => 'fd-',
            'cssExtensionSuffix' => '',
            'cssFile' => 'fd-file',
            'cssFirstDir' => 'fd-firstDir',
            'cssFirstFile' => 'fd-firstFile',
            'cssGroupDir' => 'fd-group-dir',
            'cssLastDir' => 'fd-lastDir',
            'cssLastFile' => 'fd-lastFile',
            'cssPath' => 'fd-path',
            'dateFormat' => 'Y-m-d',
            'deleteGroups' => '',
            'directLink::bool' => false,
            'downloadByOther::bool' => false,
            'extHidden' => '',
            'extShown' => '',
            'fdlid' => '',
            'fileCss' => $this->filedownloadr->getOption('assets_url') . 'css/fd.min.css',
            'fileJs' => '',
            'geoApiKey' => $this->filedownloadr->getOption('ipinfodb_api_key'),
            'getDir' => '',
            'getFile' => '',
            'groupByDirectory::bool' => false,
            'imgLocat' => $this->filedownloadr->getOption('assets_url') . 'img/filetypes/',
            'imgTypes' => 'fdImages',
            'mediaSourceId::int' => 0,
            'noDownload::bool' => false,
            'prefix' => 'fd.',
            'saltText' => 'FileDownloadR',
            'showEmptyDirectory::bool' => false,
            'sortBy' => 'filename',
            'sortByCaseSensitive::bool' => false,
            'sortOrder' => 'asc',
            'sortOrderNatural::bool' => true,
            'toArray::bool' => false,
            'tplBreadcrumb' => 'fdBreadcrumbTpl',
            'tplDir' => 'fdRowDirTpl',
            'tplFile' => 'fdRowFileTpl',
            'tplGroupDir' => 'fdGroupDirTpl',
            'tplIndex' => 'fdIndexTpl',
            'tplNotAllowed' => 'fdNotAllowedTpl',
            'tplWrapper' => 'fdWrapperTpl',
            'tplWrapperDir' => '',
            'tplWrapperFile' => '',
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
        $this->filedownloadr->setOption('origDir', $this->filedownloadr->getOption('getDir'));

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

        if (!empty($_GET['fdldir']) || !empty($_GET['fdlfile']) || !empty($_GET['fdldelete'])) {
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
            } elseif (!empty($sanitizedGets['fdldir'])) {
                if (!$this->filedownloadr->checkHash($this->modx->context->key, $sanitizedGets['fdldir'])) {
                    return '';
                }
                if ((!empty($sanitizedGets['fdlid']) && !empty($this->getProperty('fdlid'))) &&
                    ($sanitizedGets['fdlid'] != $this->getProperty('fdlid'))
                ) {
                    $selected = false;
                } else {
                    $selected = true;
                }
                if ($selected) {
                    if (!$this->filedownloadr->setDirProp($sanitizedGets['fdldir'], $selected)) {
                        return '';
                    }
                }
            } elseif (!empty($sanitizedGets['fdldelete'])) {
                if (!$this->filedownloadr->checkHash($this->modx->context->key, $sanitizedGets['fdldelete'])) {
                    return '';
                }
                $this->filedownloadr->deleteFile($sanitizedGets['fdldelete']);
            }
        }

        $contents = $this->filedownloadr->getContents();
        if (empty($contents) && !$this->getProperty('showEmptyDirectory')) {
            return '';
        }

        if ($this->getProperty('toArray')) {
            $output = '<pre>' . print_r($contents, true) . '</pre>';
        } else {
            $output = $this->filedownloadr->parseTemplate();
        }

        if (!empty($toPlaceholder)) {
            $this->modx->setPlaceholder($toPlaceholder, $output);
            return '';
        }
        return $output;
    }
}
