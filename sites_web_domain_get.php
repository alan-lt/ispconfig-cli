#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['domain_name'])) {
	die('--domain_name=domain.tld not present' . "\n");
}

try {
	initISPConfig();

	$domain_name = $arrArg['domain_name'];
	$result = getWebDomain(array('domain' => $domain_name));

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
