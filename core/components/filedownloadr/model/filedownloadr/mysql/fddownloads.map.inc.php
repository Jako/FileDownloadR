<?php
/**
 * @package filedownloadr
 */
$xpdo_meta_map['fdDownloads']= array (
  'package' => 'filedownloadr',
  'version' => '1.1',
  'table' => 'fd_downloads',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'InnoDB',
  ),
  'fields' => 
  array (
    'path_id' => NULL,
    'ip' => '0.0.0.0',
    'referer' => NULL,
    'country' => '',
    'region' => '',
    'city' => '',
    'zip' => '',
    'geolocation' => NULL,
    'user' => 0,
    'timestamp' => NULL,
  ),
  'fieldMeta' => 
  array (
    'path_id' => 
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'attributes' => 'unsigned',
      'phptype' => 'integer',
      'null' => false,
    ),
    'ip' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '46',
      'phptype' => 'string',
      'null' => false,
      'default' => '0.0.0.0',
    ),
    'referer' => 
    array (
      'dbtype' => 'text',
      'phptype' => 'string',
      'null' => true,
    ),
    'country' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
    'region' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
    'city' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '255',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
    'zip' => 
    array (
      'dbtype' => 'varchar',
      'precision' => '30',
      'phptype' => 'string',
      'null' => true,
      'default' => '',
    ),
    'geolocation' => 
    array (
      'dbtype' => 'text',
      'phptype' => 'string',
      'null' => true,
    ),
    'user' => 
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'phptype' => 'integer',
      'null' => false,
      'default' => 0,
    ),
    'timestamp' => 
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'phptype' => 'integer',
      'null' => true,
    ),
  ),
  'indexes' => 
  array (
    'path_id' => 
    array (
      'alias' => 'path_id',
      'primary' => false,
      'unique' => false,
      'type' => 'BTREE',
      'columns' => 
      array (
        'path_id' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
    'user' => 
    array (
      'alias' => 'user',
      'primary' => false,
      'unique' => false,
      'type' => 'BTREE',
      'columns' => 
      array (
        'user' => 
        array (
          'length' => '',
          'collation' => 'A',
          'null' => false,
        ),
      ),
    ),
  ),
  'aggregates' => 
  array (
    'Path' => 
    array (
      'class' => 'fdPaths',
      'local' => 'path_id',
      'foreign' => 'id',
      'cardinality' => 'one',
      'owner' => 'foreign',
    ),
  ),
);
