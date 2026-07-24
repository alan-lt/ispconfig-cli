#!/usr/bin/php
<?php

/*
Usage:
  ./sites_web_domain_disk_usage.php --domain_name=domain.tld
  ./sites_web_domain_disk_usage.php --domain_id=5
*/

require 'soap_functions.php';

if (!isset($arrArg['domain_name']) && !isset($arrArg['domain_id'])) {
	failResult('--domain_name=domain.tld or --domain_id=<int> not present');
}

try {
	initISPConfig();

	if (isset($arrArg['domain_name'])) {
		$domain_data = json_decode(getWebDomain(array('domain' => $arrArg['domain_name'])), true);
		if (!$domain_data['success'] || empty($domain_data['data'])) {
			failResult('Domain not found');
		}
		$domain_id = intval($domain_data['data'][0]['domain_id']);
	} else {
		$domain_id = intval($arrArg['domain_id']);
	}

	$result = getDiskUsageByDomain($domain_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
