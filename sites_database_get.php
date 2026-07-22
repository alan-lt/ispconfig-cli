#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['id'])) {
	failResult('--id=<int> not present');
}

try {
	initISPConfig();

	$database_id = $arrArg['id'];
	$result = getDatabase($database_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
