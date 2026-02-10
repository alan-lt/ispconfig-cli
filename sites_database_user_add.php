#!/usr/bin/php
<?php

/*
./sites_database_user_add.php --user=ExampleUser --password=ExamplePass123
*/

require 'soap_functions.php';

if (!isset($arrArg['user'])) {
	die('--user=<str> not present' . "\n");
}
if (!isset($arrArg['password'])) {
	die('--password=<str> not present' . "\n");
}

try {
	initISPConfig();

	// Set the function parameters
	$config = array(
		'client_id'         => $client_id,
		'server_id'         => $server_id,
		'database_user'     => $arrArg['user'],
		'database_password' => $arrArg['password'],
	);

	$result = addDatabaseUser($config);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
