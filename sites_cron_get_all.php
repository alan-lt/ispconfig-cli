#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getAllCrons();

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}

?>
