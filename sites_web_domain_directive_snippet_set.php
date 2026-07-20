#!/usr/bin/php
<?php

/*
Usage:
  ./sites_web_domain_directive_snippet_set.php --domain_id=<int> --snippet_id=<int>
  ./sites_web_domain_directive_snippet_set.php --domain_id=<int> --snippet_name="<name>"

  --snippet_id=0 removes the snippet (domain uses no directive snippet).

Assigns a directive snippet (nginx/apache/php template) to a web domain.

The snippet-setting logic lives in setDirectiveSnippet() in soap_functions.php so
it can be reused (sites_web_domain_add.php, sites_web_domain_edit.php). It needs
the ISPConfig interface library, which soap_env.php bootstraps for every script.
*/

require __DIR__ . '/soap_functions.php';

if (!isset($arrArg['domain_id'])) {
	die('--domain_id=<int> not present' . "\n");
}
if (!isset($arrArg['snippet_id']) && !isset($arrArg['snippet_name'])) {
	die('--snippet_id=<int> or --snippet_name="<name>" not present' . "\n");
}

$result = setDirectiveSnippet(
	$arrArg['domain_id'],
	isset($arrArg['snippet_id']) ? $arrArg['snippet_id'] : null,
	isset($arrArg['snippet_name']) ? $arrArg['snippet_name'] : null
);

echo json_encode($result) . "\n";
exit($result['success'] ? 0 : 1);
