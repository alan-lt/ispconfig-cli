<?php

$arrArg = parseArgs($argv);

loadEnv(__DIR__ . '/.env');

$username      = getenv('SOAP_USERNAME');
$password      = getenv('SOAP_PASSWORD');
$soap_location = getenv('SOAP_LOCATION');
$soap_uri      = getenv('SOAP_URI');

// Default client/server for the add scripts (override in .env). Default 1.
$client_id = (getenv('CLIENT_ID') !== false && getenv('CLIENT_ID') !== '') ? intval(getenv('CLIENT_ID')) : 1;
$server_id = (getenv('SERVER_ID') !== false && getenv('SERVER_ID') !== '') ? intval(getenv('SERVER_ID')) : 1;

$soap_client_trace = 0;

$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
]);

// Always bootstrap the ISPConfig interface library so functions that need it
// (directive snippets, live form defaults) work without per-script setup. This
// means every script must run on the ISPConfig server itself.
require __DIR__ . '/ispconfig_interface.php';