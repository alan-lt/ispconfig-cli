#!/usr/bin/php
<?php

/*
Unit tests for the pure library functions in soap_functions.php.

Feeds known inputs and checks the return value - no ISPConfig connection is made
(ISPCONFIG_SKIP_BOOTSTRAP=1 skips the interface-library bootstrap in soap_env.php).

Run directly, or via tests/run.sh. Exit code is non-zero if any check fails.
*/

putenv('ISPCONFIG_SKIP_BOOTSTRAP=1');
require __DIR__ . '/../soap_functions.php';

$tests = 0;
$failed = 0;

// Assert deep equality (strict) and print a TAP-style line
function check($name, $got, $want) {
	global $tests, $failed;
	$tests++;
	if ($got === $want) {
		echo "ok $tests - $name\n";
	} else {
		$failed++;
		echo "not ok $tests - $name\n";
		echo '  expected: ' . json_encode($want) . "\n";
		echo '  got:      ' . json_encode($got) . "\n";
	}
}


// ── parseArgs ────────────────────────────────────────────────────────────────

check('parseArgs: --key=value',
	parseArgs(array('script.php', '--domain=example.com')),
	array('domain' => 'example.com'));

check('parseArgs: flag without value is true',
	parseArgs(array('script.php', '--help')),
	array('help' => true));

check('parseArgs: value keeps = signs after the first',
	parseArgs(array('script.php', '--data={"a":"b=c"}')),
	array('data' => '{"a":"b=c"}'));

check('parseArgs: ignores positional args',
	parseArgs(array('script.php', 'positional', '--x=1')),
	array('x' => '1'));

check('parseArgs: no args yields empty array',
	parseArgs(array('script.php')),
	array());


// ── applySslConfig ───────────────────────────────────────────────────────────

check('applySslConfig: letsencrypt cascades using config domain',
	applySslConfig(array('ssl_letsencrypt' => 'y', 'domain' => 'ex.com')),
	array('ssl_letsencrypt' => 'y', 'domain' => 'ex.com', 'ssl' => 'y', 'ssl_domain' => 'ex.com', 'rewrite_to_https' => 'y'));

check('applySslConfig: ssl_domain falls back to the $domain argument',
	applySslConfig(array('ssl_letsencrypt' => 'y'), 'fallback.com'),
	array('ssl_letsencrypt' => 'y', 'ssl' => 'y', 'ssl_domain' => 'fallback.com', 'rewrite_to_https' => 'y'));

check('applySslConfig: explicit ssl_domain is preserved',
	applySslConfig(array('ssl_letsencrypt' => 'y', 'ssl_domain' => 'custom.com', 'domain' => 'ex.com')),
	array('ssl_letsencrypt' => 'y', 'ssl_domain' => 'custom.com', 'domain' => 'ex.com', 'ssl' => 'y', 'rewrite_to_https' => 'y'));

check('applySslConfig: no letsencrypt leaves config untouched',
	applySslConfig(array('php' => 'php-fpm')),
	array('php' => 'php-fpm'));


// ── categorizeFunctions ──────────────────────────────────────────────────────

check('categorizeFunctions: groups by prefix and counts, sorted',
	categorizeFunctions(array('client_add', 'client_get', 'dns_a_add', 'mail_user_get')),
	array(
		'categories' => array(
			'client' => array('client_add', 'client_get'),
			'dns'    => array('dns_a_add'),
			'mail'   => array('mail_user_get'),
		),
		'category_counts' => array('client' => 2, 'dns' => 1, 'mail' => 1),
	));

check('categorizeFunctions: skips names without an underscore',
	categorizeFunctions(array('login', 'client_add')),
	array(
		'categories' => array('client' => array('client_add')),
		'category_counts' => array('client' => 1),
	));


// ── detectUnexpectedChanges ──────────────────────────────────────────────────

check('detectUnexpectedChanges: none when only requested fields changed',
	detectUnexpectedChanges(
		array('a' => '1', 'b' => '2', 'c' => '3'),
		array('a' => '9', 'b' => '2', 'c' => '3'),
		array('a' => '9')),
	array());

check('detectUnexpectedChanges: reports a field changed outside the updates',
	detectUnexpectedChanges(
		array('a' => '1', 'b' => '2', 'c' => '3'),
		array('a' => '9', 'b' => '2', 'c' => '99'),
		array('a' => '9')),
	array('c' => array('original' => '3', 'modified' => '99')));

check('detectUnexpectedChanges: compares loosely (1 == "1")',
	detectUnexpectedChanges(array('x' => 1), array('x' => '1'), array()),
	array());


// ── extractFormDefaults ──────────────────────────────────────────────────────

check('extractFormDefaults: collects defaults across tabs, skips fields without one',
	extractFormDefaults(array('tabs' => array(
		'domain' => array('fields' => array(
			'a' => array('datatype' => 'X', 'default' => '1'),
			'b' => array('datatype' => 'Y'),
		)),
		'ssl' => array('fields' => array(
			'c' => array('default' => '3'),
		)),
	))),
	array('a' => '1', 'c' => '3'));

check('extractFormDefaults: empty form yields empty array',
	extractFormDefaults(array()),
	array());


// ── parseSnippetRows ─────────────────────────────────────────────────────────

check('parseSnippetRows: parses tab-separated rows',
	parseSnippetRows("1\tphp cfg\tphp\ty\n2\tnginx cfg\tnginx\ty"),
	array(
		array('directive_snippets_id' => 1, 'name' => 'php cfg', 'type' => 'php', 'active' => 'y'),
		array('directive_snippets_id' => 2, 'name' => 'nginx cfg', 'type' => 'nginx', 'active' => 'y'),
	));

check('parseSnippetRows: empty output yields empty array',
	parseSnippetRows(''),
	array());

check('parseSnippetRows: tolerates missing columns',
	parseSnippetRows("5\tonly name"),
	array(array('directive_snippets_id' => 5, 'name' => 'only name', 'type' => '', 'active' => '')));


// ── parseMysqlSize ───────────────────────────────────────────────────────────

check('parseMysqlSize: null is 0', parseMysqlSize(null), 0);
check('parseMysqlSize: empty is 0', parseMysqlSize(''), 0);
check('parseMysqlSize: literal NULL is 0', parseMysqlSize('NULL'), 0);
check('parseMysqlSize: numeric with newline', parseMysqlSize("12345\n"), 12345);
check('parseMysqlSize: numeric with spaces', parseMysqlSize('  678 '), 678);


// ── parseDuBytes ─────────────────────────────────────────────────────────────

check('parseDuBytes: null is 0', parseDuBytes(null), 0);
check('parseDuBytes: empty is 0', parseDuBytes(''), 0);
check('parseDuBytes: bytes before the tab', parseDuBytes("12345\t/var/www/web1\n"), 12345);


echo "\n1..$tests\n";
if ($failed) {
	echo "# $failed of $tests failed\n";
	exit(1);
}
echo "# all $tests passed\n";
exit(0);
