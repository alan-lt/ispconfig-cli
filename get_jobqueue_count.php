#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getJobQueueCount($server_id);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
