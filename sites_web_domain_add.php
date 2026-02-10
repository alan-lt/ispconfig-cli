#!/usr/bin/php
<?php

require 'soap_functions.php';

if (!isset($arrArg['domain'])) {
	die('--domain=domain.tld not present' . "\n");
}

try {
	initISPConfig();

	// Set the function parameters
	$config = array(
      	'client_id'               => $client_id,
		'server_id'               => $server_id,
		'domain'                  => $arrArg['domain'],
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
		'server_php_id'           => 2,

/*
		'parent_domain_id' => 0,
		'cgi' => 'y',
		'ssi' => 'y',
		'suexec' => 'y',
		'errordocs' => 1,
		'is_subdomainwww' => 1,
		'subdomain' => '',
		'php' => 'y',
		'ruby' => 'n',
		'redirect_type' => '',
		'redirect_path' => '',
		'ssl' => 'n',
		'ssl_state' => '',
		'ssl_locality' => '',
		'ssl_organisation' => '',
		'ssl_organisation_unit' => '',
		'ssl_country' => '',
		'ssl_domain' => '',
		'ssl_request' => '',
		'ssl_key' => '',
		'ssl_cert' => '',
		'ssl_bundle' => '',
		'ssl_action' => '',
		'stats_password' => '',
		'stats_type' => 'webalizer',
		'allow_override' => 'All',
		'apache_directives' => '',
		'php_open_basedir' => '/',
		'pm' => 'ondemand',
		'pm_max_requests' => 0,
		'pm_process_idle_timeout' => 10,
		'custom_php_ini' => '',
		'backup_interval' => '',
		'backup_copies' => 1,
		'backup_format_web' => 'default',
		'backup_format_db' => 'gzip',
		'traffic_quota_lock' => 'n',
*/
	);

	$result = addWebDomain($config);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
