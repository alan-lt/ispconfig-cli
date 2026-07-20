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
		die('Error: invalid JSON in --data parameter' . "\n");
	}
	$config = array_merge($config, $override);
}

// --help: ISPConfig's live form defaults with the settings above merged on top
if (isset($arrArg['help'])) {
	echo json_encode(array_merge(getFormDefaults('DATABASE_USER_TFORM'), $config), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	exit(0);
}

if (!isset($arrArg['user'])) {
	die('--user=<str> not present' . "\n");
}
if (!isset($arrArg['password'])) {
	die('--password=<str> not present' . "\n");
}

try {
	initISPConfig();

	$result = addDatabaseUser($config);

	echo $result . "\n";

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage() . "\n");
}
