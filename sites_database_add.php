#!/usr/bin/php
<?php

/*
Usage:
  ./sites_database_add.php --domain_id=<int> --database_name=<str> --database_user_id=<int> [--data='<json>']
  ./sites_database_add.php --help

Options:
  --domain_id=<int>        (required) parent web domain id
  --database_name=<str>    (required) database name
  --database_user_id=<int> (required) database user id
  --data='<json>'          override/extend the default settings below

backup_interval/backup_copies are inherited from the parent web domain
(see sites_backup.php) unless given in --data.
*/

require 'soap_functions.php';

// Set the function parameters (explicit overrides on top of ISPConfig's defaults)
$config = array(
	'client_id'         => $client_id,
	'server_id'         => $server_id,
	'type'              => 'mysql',
	'parent_domain_id'  => isset($arrArg['domain_id']) ? $arrArg['domain_id'] : '',
	'database_name'     => isset($arrArg['database_name']) ? $arrArg['database_name'] : '',
	'database_user_id'  => isset($arrArg['database_user_id']) ? $arrArg['database_user_id'] : '',
	'active'            => 'y',
);

// Optional --data JSON overrides/extends the default settings above
if (isset($arrArg['data'])) {
	$override = json_decode($arrArg['data'], true);
	if ($override === null) {
		failResult('invalid JSON in --data parameter');
	}
	$config = array_merge($config, $override);
}

// --help: ISPConfig's live form defaults with the settings above merged on top
if (isset($arrArg['help'])) {
	emitEvent(array('type' => 'result', 'success' => true, 'usage' => scriptUsage(), 'defaults' => array_merge(getFormDefaults('DATABASE_TFORM'), $config)));
	exit(0);
}

if (!isset($arrArg['domain_id'])) {
	failResult('--domain_id=<int> not present');
}
if (!isset($arrArg['database_name'])) {
	failResult('--database_name=<str> not present');
}
if (!isset($arrArg['database_user_id'])) {
	failResult('--database_user_id=<int> not present');
}

try {
	initISPConfig();

	// Inherit the parent web domain's backup schedule so web and database backups stay in sync
	$domain = json_decode(getWebDomain($arrArg['domain_id']), true);
	if (empty($domain['success'])) {
		failResult('Parent domain ' . $arrArg['domain_id'] . ' not found');
	}
	$config += array(
		'backup_interval' => $domain['data']['backup_interval'],
		'backup_copies'   => $domain['data']['backup_copies'],
	);

	$result = addDatabase($config);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
