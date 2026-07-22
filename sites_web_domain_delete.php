#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['id'])) {
	failResult('--id=<int> not present');
}

try {
	initISPConfig();

	$domain_id = $arrArg['id'];
	$result = deleteWebDomain($domain_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
