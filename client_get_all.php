#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getAllClients();

	echo $result . "\n";

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage() . "\n");
}

?>
