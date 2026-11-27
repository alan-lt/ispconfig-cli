#!/usr/bin/php
<?php

/*
Usage:
  ./sites_backup.php --domain_name=<domain.tld> --interval=<daily|weekly|monthly> --copies=<int>
  ./sites_backup.php --domain_name=<domain.tld> --interval=none
  ./sites_backup.php --help

Sets backups for a web domain and all of its databases at once (all-or-nothing).

Options:
  --domain_name=<domain.tld>  (required) web domain name
  --interval=<interval>       (required) none, daily, weekly or monthly
  --copies=<int>              (required unless --interval=none) backup copies to keep
*/

require 'soap_functions.php';

if (isset($arrArg['help'])) {
	emitEvent(array('type' => 'result', 'success' => true, 'usage' => scriptUsage()));
	exit(0);
}

if (!isset($arrArg['domain_name'])) {
	failResult('--domain_name=domain.tld not present');
}

if (!isset($arrArg['interval'])) {
	failResult('--interval=<none|daily|weekly|monthly> not present');
}

try {
	initISPConfig();

	$copies = isset($arrArg['copies']) ? $arrArg['copies'] : null;
	$result = setBackup($arrArg['domain_name'], $arrArg['interval'], $copies, $client_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
