#!/usr/bin/php
<?php

require 'soap_functions.php';

try {
	initISPConfig();

	$result = getJobQueueCount($server_id);

	emitResult($result);

	closeISPConfig();

} catch (Exception $e) {
	failResult($e->getMessage());
}
