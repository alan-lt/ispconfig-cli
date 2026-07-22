#!/usr/bin/php
<?php

/*
Usage:
  ./sites_database_user_add.php --user=<str> --password=<str> [--data='<json>']
  ./sites_database_user_add.php --help

Options:
  --user=<str>      (required) database user name
  --password=<str>  (required) database password
  --data='<json>'   override/extend the default settings below
*/

require 'soap_functions.php';

// Set the function parameters (explicit overrides on top of ISPConfig's defaults)
$config = array(
	'client_id'         => $client_id,
	'server_id'         => $server_id,
	'database_user'     => isset($arrArg['user']) ? $arrArg['user'] : '',
	'database_password' => isset($arrArg['password']) ? $arrArg['password'] : '',
);

// Optional --data JSON overrides/extends the default settings above
if (isset($arrArg['data'])) {
	$override = json_decode($arrArg['data'], true);
	if ($override === null) {
		failResult('invalid JSON in --data parameter');
	}
	$config = array_merge($config, $override);
}

// --help: ISPConfig's live form defaults with the settings above merged on top
if (isset($arrArg['help'])) {
	emitEvent(array('type' => 'result', 'success' => true, 'defaults' => array_merge(getFormDefaults('DATABASE_USER_TFORM'), $config)));
	exit(0);
}

if (!isset($arrArg['user'])) {
	failResult('--user=<str> not present');
}
if (!isset($arrArg['password'])) {
	failResult('--password=<str> not present');
}

try {
	initISPConfig();

	$result = addDatabaseUser($config);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
