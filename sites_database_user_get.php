#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['id'])) {
	die('--id=<int> not present' . "\n");
}

try {
	initISPConfig();

	$database_user_id = $arrArg['id'];
	$result = getDatabaseUser($database_user_id);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
