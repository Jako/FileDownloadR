<?php
/**
 * @package filedownloadr
 */
$xpdo_meta_map['fdPaths']= array (
  'package' => 'filedownloadr',
  'version' => '1.1',
  'table' => 'fd_paths',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'InnoDB',
  ),
  'fields' => 
  array (
    'ctx' => 'web',
    'media_source_id' => 0,
    'filename' => NULL,
    'extended' => NULL,
    'hash' => NULL,
  ),
  'fieldMeta' => 
  array (
    'ctx' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => true,
      'default' => 'web',
    ),
    'media_source_id' => 
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'attributes' => 'unsigned',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
    ),
    'filename' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => true,
    ),
    'extended' => 
    array (
      'dbtype' => 'mediumtext',
      'phptype' => 'json',
      'null' => true,
    ),
    'hash' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => true,
    ),
  ),
  'composites' => 
  array (
    'Downloads' => 
    array (
      'class' => 'fdDownloads',
      'local' => 'id',
      'foreign' => 'path_id',
      'cardinality' => 'many',
      'owner' => 'local',
    ),
  ),
);
