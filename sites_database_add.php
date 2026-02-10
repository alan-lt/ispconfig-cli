#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['domain_id'])) {
	die('--domain_id=<int> not present' . "\n");
}
if (!isset($arrArg['database_name'])) {
	die('--database_name=<str> not present' . "\n");
}
if (!isset($arrArg['database_user_id'])) {
	die('--database_user_id=<int> not present' . "\n");
}

try {
	initISPConfig();

	// Set the function parameters
	$config = array(
		'client_id'         => $client_id,
		'server_id'         => $server_id,
		'type'              => 'mysql',
		'parent_domain_id'  => $arrArg['domain_id'],
		'database_name'     => $arrArg['database_name'],
		'database_user_id'  => $arrArg['database_user_id'],
		'backup_interval'   => 'daily',
		'backup_copies'     => 2,
		'active'            => 'y'
/*
		'database_ro_user_id' => '0',
		'database_charset'    => 'UTF8',
		'remote_access'       => 'y',
		'remote_ips'          => '',
		'backup_format_web'   => 'default',
		'backup_format_db'    => 'gzip',
*/
	);

	$result = addDatabase($config);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
