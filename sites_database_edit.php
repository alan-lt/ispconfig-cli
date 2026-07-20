#!/usr/bin/php
<?php

/*
Usage:
  ./sites_database_edit.php --id=<int> --data='<json>'
  ./sites_database_edit.php --help

Options:
  --id=<int>       (required) database id
  --data='<json>'  (required) fields to update, e.g. --data='{"active":"n"}'
*/

require 'soap_functions.php';

// --help: the fields (with ISPConfig's live defaults) that can be passed via --data
if (isset($arrArg['help'])) {
	echo json_encode(getFormDefaults('DATABASE_TFORM'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	exit(0);
}

if (!isset($arrArg['id'])) {
	die('--id=<int> not present' . "\n");
}

if (!isset($arrArg['data'])) {
	die('--data=\'{"field": "value"}\' not present' . "\n");
}

$updates = json_decode($arrArg['data'], true);
if ($updates === null) {
	die('Error: invalid JSON in --data parameter' . "\n");
}

try {
	initISPConfig();

	$database_id = $arrArg['id'];
	$result = updateDatabase($database_id, $updates, $client_id);

	echo $result . "\n";

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage() . "\n");
}
