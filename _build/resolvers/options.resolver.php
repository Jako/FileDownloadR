<?php

/**
 * FileDownload
 *
 * Copyright 2011 by goldsky <goldsky@fastmail.fm>
 *
 * This file is part of FileDownload, a file downloader for MODX Revolution
 *
 * FileDownload is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * FileDownload is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * FileDownload; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * Resolve creating db tables
 *
 * @package filedownload
 * @subpackage build
 */
if ($modx = & $object->xpdo) {
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            break;
        case xPDOTransport::ACTION_UNINSTALL:
            if (!empty($options['fdl_keep_db'])) {
                $modelPath = $modx->getOption('core_path') . 'components/filedownload/models/';
                $modx->addPackage('filedownload', realpath($modelPath) . DIRECTORY_SEPARATOR);
                $manager = $modx->getManager();

                if (!$manager->removeObjectContainer('FDL')) {
                    $modx->log(xPDO::LOG_LEVEL_ERROR, '$modelPath = ' . $modelPath);
                    $modx->log(xPDO::LOG_LEVEL_ERROR, 'realpath($modelPath) . DIRECTORY_SEPARATOR = ' . realpath($modelPath) . DIRECTORY_SEPARATOR);
                    $modx->log(modX::LOG_LEVEL_ERROR, '[FileDownload] table was unable to delete');
                    return false;
                }
                $modx->log(modX::LOG_LEVEL_INFO, '[FileDownload] table was deleted successfully');
            } else {
                $modx->log(xPDO::LOG_LEVEL_ERROR, '[FileDownload] $options[\'fdl_keep_db\'] is empty ');
            }

            break;
    }
}
return true;