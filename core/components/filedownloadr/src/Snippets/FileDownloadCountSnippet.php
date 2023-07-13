<?php
/**
 * File Download Count Snippet
 *
 * @package filedownloadr
 * @subpackage snippet
 */

namespace TreehillStudio\FileDownloadR\Snippets;

/**
 * Class FileDownloadSnippet
 */
class FileDownloadCountSnippet extends Snippet
{
    /**
     * Get default snippet properties.
     *
     * @return array
     */
    public function getDefaultProperties()
    {
        return [
            'browseDirectories::' => true,
            'countDownloads::bool' => true,
            'countUserDownloads::bool' => false,
            'fileCss' => '{fd_assets_url}css/fd.min.css',
            'fileJs' => '',
            'getDir' => '',
            'mediaSourceId::int' => 0,
            'prefix' => 'fd.',
            'saltText' => 'FileDownloadR',
            'toArray::bool' => false,
            'tpl' => 'fdSingleDirTpl',
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

        $phs = $this->filedownloadr->getDirCount($this->getProperty('getDir'));

        if ($this->getProperty('toArray')) {
            $output = '<pre>' . print_r($phs, true) . '</pre>';
        } else {
            $output = $this->filedownloadr->parse->getChunk($this->getProperty('tpl'), $phs);
        }

        if (!empty($toPlaceholder)) {
            $this->modx->setPlaceholder($toPlaceholder, $output);
            return '';
        }
        return $output;
    }
}
