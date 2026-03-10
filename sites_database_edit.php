#!/usr/bin/php
<?php

require 'soap_functions.php';

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
