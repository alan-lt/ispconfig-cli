<?php

$arrArg = parseArgs($argv);

loadEnv(__DIR__ . '/.env');

$username      = getenv('SOAP_USERNAME');
$password      = getenv('SOAP_PASSWORD');
$soap_location = getenv('SOAP_LOCATION');
$soap_uri      = getenv('SOAP_URI');

$soap_client_trace = 0;

// ispconfig current default id's
$client_id = 3;
$server_id = 1;
$sys_userid = 4;
$sys_groupid = 4;

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);