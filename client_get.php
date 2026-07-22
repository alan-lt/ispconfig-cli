#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['id'])) {
	failResult('--id=<int> not present');
}

try {
	initISPConfig();

	$client_id = $arrArg['id'];
	$result = getClient($client_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
