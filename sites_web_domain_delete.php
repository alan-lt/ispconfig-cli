#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['id'])) {
	die('--id=<int> not present' . "\n");
}

try {
	initISPConfig();

	$domain_id = $arrArg['id'];
	$result = deleteWebDomain($domain_id);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
