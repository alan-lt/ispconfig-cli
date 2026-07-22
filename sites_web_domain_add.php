#!/usr/bin/php
<?php

/*
Usage:
  ./sites_web_domain_add.php --domain=<domain.tld> [--data='<json>']
  ./sites_web_domain_add.php --help

Options:
  --domain=<domain.tld>   (required) domain to create
  --data='<json>'         override/extend the default settings below, e.g.
                          --data='{"ssl_letsencrypt":"y"}'
*/

require 'soap_functions.php';

// Set the function parameters (explicit overrides on top of ISPConfig's defaults)
$config = array(
	'client_id'               => $client_id,
	'server_id'               => $server_id,
	'domain'                  => isset($arrArg['domain']) ? $arrArg['domain'] : '',
	'subdomain'               => 'www',
	'rewrite_to_https'        => 'n',
	'hd_quota'                => -1,
	'traffic_quota'           => -1,
	'traffic_quota_lock'      => 'n',
	'allow_override'          => 'All',
	'pm_process_idle_timeout' => 10,
	'pm_max_requests'         => 0,
	'pm'                      => 'ondemand',
	'http_port'               => 80,
	'https_port'              => 443,
	'type'                    => 'vhost',
	'ip_address'              => '*',
	'vhost_type'              => 'name',
	'active'                  => 'y',
	'php'                     => 'php-fpm',
	'php_fpm_use_socket'      => 'y',
	'suexec'                  => 'y',
	'backup_interval'         => 'daily',
	'backup_copies'           => 2,
	'backup_format_web'       => 'default',
	'backup_format_db'        => 'gzip',
	'backup_excludes'         => 'private,tmp,web,log',
	'log_retention'           => 10,
);

// Optional --data JSON overrides/extends the default settings above
// (e.g. --data='{"ssl_letsencrypt":"y"}').
if (isset($arrArg['data'])) {
	$override = json_decode($arrArg['data'], true);
	if ($override === null) {
		failResult('invalid JSON in --data parameter');
	}
	$config = array_merge($config, $override);
}

// --help: ISPConfig's live form defaults (extracted so they stay correct across
// updates) with the settings above merged on top.
if (isset($arrArg['help'])) {
	$defaults = getFormDefaults('WEB_DOMAIN_TFORM');
	emitEvent(array('type' => 'result', 'success' => true, 'defaults' => array_merge($defaults, $config)));
	exit(0);
}

if (!isset($arrArg['domain'])) {
	failResult('--domain=domain.tld not present');
}

try {
	initISPConfig();

	$result = addWebDomain($config);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
