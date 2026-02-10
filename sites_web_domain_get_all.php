#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getAllWebDomains($sys_userid, $sys_groupid);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
