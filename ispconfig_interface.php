<?php

/*
Bootstraps the ISPConfig interface library so $app->* calls (db, getconf,
datalogUpdate, ...) work directly against the local ISPConfig installation.

MUST be required at global scope (not from inside a function): config.inc.php and
app.inc.php create $conf and $app in the current scope, and ISPConfig's own code
expects them to be global. Requiring this file at global scope keeps them global.

Only needed by scripts that touch fields the SOAP API cannot set (e.g. directive
snippets). Must run on the ISPConfig server itself.
*/

$_SERVER['DOCUMENT_ROOT'] = '/usr/local/ispconfig/interface/web';
chdir('/usr/local/ispconfig/interface/web');
require_once '/usr/local/ispconfig/interface/lib/config.inc.php';
require_once '/usr/local/ispconfig/interface/lib/app.inc.php';
$app->uses('db,getconf');
