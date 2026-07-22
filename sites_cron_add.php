#!/usr/bin/php
<?php

/*
Usage:
  ./sites_cron_add.php --domain_id=<int> --command=<str> [--data='<json>']
  ./sites_cron_add.php --help

Options:
  --domain_id=<int>   (required) parent web domain id
  --command=<str>     (required) command to run
  --data='<json>'     override/extend the default settings below

The schedule defaults to every minute ('*'). Override the run_* fields via --data,
e.g. --data='{"run_min":"0","run_hour":"3"}' for a daily 03:00 job.

Cron 'type' is set by you here (defaults to 'full'):
  full     - shell command run as the web user (default)
  chrooted - shell command run inside the site's jailkit chroot
  url      - ISPConfig fetches an http(s):// URL (command must be a URL)
The web UI derives type automatically from the command; the SOAP API does not,
so set it explicitly, e.g. --data='{"type":"url","command":"https://..."}'.
*/

require 'soap_functions.php';

// Set the function parameters. type defaults to 'full'.
$config = array(
	'client_id'        => $client_id,
	'server_id'        => $server_id,
	'parent_domain_id' => isset($arrArg['domain_id']) ? $arrArg['domain_id'] : '',
	'type'             => 'full',
	'command'          => isset($arrArg['command']) ? $arrArg['command'] : '',
	'run_min'          => '*',
	'run_hour'         => '*',
	'run_mday'         => '*',
	'run_month'        => '*',
	'run_wday'         => '*',
	'active'           => 'y',
);

// Optional --data JSON overrides/extends the default settings above
if (isset($arrArg['data'])) {
	$override = json_decode($arrArg['data'], true);
	if ($override === null) {
		die('Error: invalid JSON in --data parameter' . "\n");
	}
	$config = array_merge($config, $override);
}

// --help: ISPConfig's live form defaults with the settings above merged on top
if (isset($arrArg['help'])) {
	echo json_encode(array_merge(getFormDefaults('CRON_TFORM'), $config), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
	exit(0);
}

if (!isset($arrArg['domain_id'])) {
	die('--domain_id=<int> not present' . "\n");
}
if (!isset($arrArg['command'])) {
	die('--command=<str> not present' . "\n");
}

try {
	initISPConfig();

	$result = addCron($config);

	echo $result . "\n";

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage() . "\n");
}
