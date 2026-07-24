#!/usr/bin/php
<?php

/*
Usage:
  ./sites_web_domain_edit.php --id=<int> --data='<json>'
  ./sites_web_domain_edit.php --help

Options:
  --id=<int>       (required) web domain id
  --data='<json>'  (required) fields to update, e.g. --data='{"ssl_letsencrypt":"y"}'
*/

require 'soap_functions.php';

// --help: the fields (with ISPConfig's live defaults) that can be passed via --data
if (isset($arrArg['help'])) {
	emitEvent(array('type' => 'result', 'success' => true, 'defaults' => getFormDefaults('WEB_DOMAIN_TFORM')));
	exit(0);
}

if (!isset($arrArg['id'])) {
	failResult('--id=<int> not present');
}

if (!isset($arrArg['data'])) {
	failResult('--data=\'{"field": "value"}\' not present');
}

$updates = json_decode($arrArg['data'], true);
if ($updates === null) {
	failResult('invalid JSON in --data parameter');
}

try {
	initISPConfig();

	$result = updateWebDomain($arrArg['id'], $updates, $client_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
