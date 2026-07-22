#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getAllClients();

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}

?>
