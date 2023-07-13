<?php
/**
 * File Get Processor
 *
 * @package filedownloadr
 * @subpackage processors
 */

namespace TreehillStudio\FileDownloadR\Processors;

/**
 * Class Processor
 */
abstract class FileGetProcessor extends Processor
{
    public function process()
    {
        $this->filedownloadr->initConfig([
            'countDownloads' => true
        ]);
        $downloadFile = $this->filedownloadr->downloadFile($this->getProperty('link'));
        $output = array('success' => !!$downloadFile);

        return json_encode($output);
    }
}
