#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getAllDatabases();

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}

?>
