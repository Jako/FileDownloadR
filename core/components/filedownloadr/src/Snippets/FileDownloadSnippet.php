<?php
/**
 * File Download Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 */

namespace TreehillStudio\FileDownloadR\Snippets;

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
            'countUserDownloads::bool' => false,
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
            'dirSeparator' => "\n",
            'dirsSeparator' => "\n",
            'downloadByOther::bool' => false,
            'extHidden' => '',
            'extShown' => '',
            'fdlid' => '',
            'fileCss' => '{fd_assets_url}css/fd.min.css',
            'fileJs' => '',
            'fileSeparator' => "\n",
            'filesSeparator' => "\n",
            'geoApiKey' => $this->filedownloadr->getOption('ipinfodb_api_key'),
            'getDir' => '',
            'getFile' => '',
            'groupByDirectory::bool' => false,
            'imgLocat' => '{fd_assets_url}img/filetypes/',
            'imgTypes' => 'fdImages',
            'limit::int' => '0',
            'mediaSourceId::int' => 0,
            'noDownload::bool' => false,
            'offset::int' => '0',
            'prefix' => 'fd.',
            'saltText' => 'FileDownloadR',
            'showEmptyDirectory::bool' => false,
            'sortBy' => 'filename',
            'sortByCaseSensitive::bool' => false,
            'sortOrder' => 'asc',
            'sortOrderNatural::bool' => true,
            'toArray::bool' => false,
            'totalVar' => 'fd.total',
            'tplBreadcrumb' => 'fdBreadcrumbTpl',
            'tplDir' => 'fdRowDirTpl',
            'tplFile' => 'fdRowFileTpl',
            'tplGroupDir' => 'fdGroupDirTpl',
            'tplIndex' => 'fdIndexTpl',
            'tplNotAllowed' => 'fdNotAllowedTpl',
            'tplWrapper' => 'fdWrapperTpl',
            'tplWrapperDir' => '',
            'tplWrapperFile' => '',
            'uploadFile::bool' => false,
            'uploadFileTypes::explodeSeparated' => 'image/gif,image/jpeg,image/png',
            'uploadGroups' => '',
            'uploadMaxSize::int' => 2097152,
            'uploadMaxCount::int' => 0,
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

        // Check the referrer if a file is downloaded or deleted
        if (!empty($_GET['fdlfile']) || !empty($_GET['fdldelete'])) {
            $this->filedownloadr->checkReferrer();
        }

        $upload = false;
        if (!$this->getProperty('downloadByOther')) {
            $sanitizedGets = $this->modx->sanitize($_GET);
            $checked = $this->checkFileDownloadId($sanitizedGets['fdlid'] ?? '');
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
            } elseif (!empty($sanitizedGets['fdldelete'])) {
                // Delete file
                $delete = $sanitizedGets['fdldelete'];
                if ($this->filedownloadr->checkHash($this->modx->context->key, $delete)) {
                    $this->filedownloadr->deleteFile($delete);
                }
            }

            if (!empty($sanitizedGets['fdldir'])) {
                $directory = $sanitizedGets['fdldir'];
                if ($this->filedownloadr->checkHash($this->modx->context->key, $directory)) {
                    if ($checked) {
                        if (!$this->filedownloadr->setDirectory($directory)) {
                            return '';
                        }
                    }
                } else {
                    return '';
                }
            }

            if ($this->getProperty('uploadFile') && !empty($_POST) && $checked) {
                $upload = $this->filedownloadr->uploadFile();
            }
        }

        $contents = $this->filedownloadr->getContents();
        if (empty($contents) && !$this->getProperty('showEmptyDirectory')) {
            return '';
        }

        if ($this->getProperty('toArray')) {
            $output = '<pre>' . print_r($contents, true) . '</pre>';
        } else {
            $output = $this->filedownloadr->listContents($upload);
        }

        if (!empty($toPlaceholder)) {
            $this->modx->setPlaceholder($toPlaceholder, $output);
            return '';
        }
        return $output;
    }

    /**
     * Check FileDownloadId for multiple FileDownload snippets on one page
     *
     * @param string $fileDownloadId
     * @return bool
     */
    private function checkFileDownloadId(string $fileDownloadId)
    {
        $checked = true;
        if (!empty($fileDownloadId) &&
            !empty($this->getProperty('fdlid')) &&
            ($fileDownloadId != $this->getProperty('fdlid'))
        ) {
            $checked = false;
        }
        return $checked;
    }
}
