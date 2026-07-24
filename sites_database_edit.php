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
	emitEvent(array('type' => 'result', 'success' => true, 'usage' => scriptUsage(), 'defaults' => getFormDefaults('DATABASE_TFORM')));
	exit(0);
}

if (!isset($arrArg['id'])) {
	failResult('--id=<int> not present');
}

if (!isset($arrArg['data'])) {
	failResult('--data=\'{"field": "value"}\' not present');
}

$updates = json_decode($arrArg['data'], true);
if ($updates === null) {
	failResult('invalid JSON in --data parameter');
}

try {
	initISPConfig();

	$database_id = $arrArg['id'];
	$result = updateDatabase($database_id, $updates, $client_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
