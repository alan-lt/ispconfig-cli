#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['id'])) {
	failResult('--id=<int> not present');
}

try {
	initISPConfig();

	$database_user_id = $arrArg['id'];
	$result = getDatabaseUser($database_user_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
