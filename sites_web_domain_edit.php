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
	echo json_encode(getFormDefaults('WEB_DOMAIN_TFORM'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	exit(0);
}

if (!isset($arrArg['id'])) {
	die('--id=<int> not present' . "\n");
}

if (!isset($arrArg['data'])) {
	die('--data=\'{"field": "value"}\' not present' . "\n");
}

$updates = json_decode($arrArg['data'], true);
if ($updates === null) {
	die('Error: invalid JSON in --data parameter' . "\n");
}

try {
	initISPConfig();

	$result = updateWebDomain($arrArg['id'], $updates, $client_id);

	echo $result . "\n";

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage() . "\n");
}
