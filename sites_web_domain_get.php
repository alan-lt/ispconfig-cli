#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['domain_name'])) {
	failResult('--domain_name=domain.tld not present');
}

try {
	initISPConfig();

	$domain_name = $arrArg['domain_name'];
	$result = getWebDomain(array('domain' => $domain_name));

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
