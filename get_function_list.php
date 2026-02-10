#!/usr/bin/php
<?php

/*
Usage:
  ./get_function_list.php --cat   # Categorized output (default)
  ./get_function_list.php         # Simple list without categories
*/

require 'soap_functions.php';

try {
	initISPConfig();

	$categorize = isset($arrArg['cat']);

	$result = getFunctionList($categorize);

	echo $result;

	closeISPConfig();

} catch (Exception $e) {
	die('Error: ' . $e->getMessage());
}
