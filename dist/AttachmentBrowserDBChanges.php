<?php

global $smcFunc;

if (!isset($smcFunc['db_create_table']))
	db_extend('packages');

$create_tables = array(
	'attachment_tags' => array(
		'columns' => array(
		    array(
			   'name' => 'attach_tag',
				'type' => 'varchar',
				'size' => 25,
				'not_null' => true,
			),
			array(
				'name' => 'attach_cat',
				'type' => 'varchar',
				'size' => 25,
				'default' => '',
				'not_null' => true,
			),
		    array(
				'name' => 'aliases',
				'type' => 'varchar',
				'size' => 50,
				'default' => '',
				'not_null' => true,
			),
		),
		'indexes' => array(
			array(
				'type' => 'primary',
				'columns' => array('attach_tag'),
			),
		),
	),
);

$add_columns = array(
	'attachments' => array(
		array(
			'name' => 'tags',
			'type' => 'varchar',
			'size' => 255,
			'default' => null,
			'not_null' => false,
		),
	),
);

$add_indexes = array(
	'attachments' => array(
		array(
			'name' => 'idx_filename',
			'type' => 'index',
			'columns' => array('filename'),
		),
		array(
			'name' => 'idx_fileext',
			'type' => 'index',
			'columns' => array('fileext'),
		),
		array(
			'name' => 'idx_size',
			'type' => 'index',
			'columns' => array('size'),
		),
		array(
			'name' => 'idx_downloads',
			'type' => 'index',
			'columns' => array('downloads'),
		),
		array(
			'name' => 'idx_tags',
			'type' => 'index',
			'columns' => array('tags'),
		),
	),
);

foreach ($create_tables AS $table_name => $data)
	$smcFunc['db_create_table']('{db_prefix}' . $table_name, $data['columns'], $data['indexes']);

foreach ($add_columns AS $table_name => $cols)
	foreach ($cols AS $data)
		$smcFunc['db_add_column']('{db_prefix}' . $table_name, $data);

foreach ($add_indexes AS $table_name => $indexes)
	foreach ($indexes AS $data)
		$smcFunc['db_add_index']('{db_prefix}' . $table_name, $data);
