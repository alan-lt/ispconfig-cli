<?php

require 'soap_env.php';




/**
 * Parse command-line arguments from long-form flags
 *
 * @param array $argv Array of command-line arguments
 * @return array Associative array of parsed arguments
 */
function parseArgs($argv) {
    $result = [];

    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $arg = substr($arg, 2);

            if (strpos($arg, '=') !== false) {
                list($key, $val) = explode('=', $arg, 2);
                $result[$key] = $val;
            } else {
                $result[$arg] = true;
            }
        }
    }

    return $result;
}




/**
 * Starts a new remote session
 *
 * @return array Array with 'client' (SoapClient) and 'session_id' (string)
 */
function initISPConfig() {
    global $soap_location, $soap_uri, $username, $password, $context, $soap_client_trace;
    global $soap_client, $soap_session_id;

    try {
        $soap_client = new SoapClient(null, array(
            'location'       => $soap_location,
            'uri'            => $soap_uri,
            'trace'          => $soap_client_trace,
            'exceptions'     => 1,
            'stream_context' => $context,
        ));

        $soap_session_id = $soap_client->login($username, $password);

        if (!$soap_session_id) {
            throw new Exception('Login failed');
        }

        return array(
            'client'     => $soap_client,
            'session_id' => $soap_session_id
        );

    } catch (SoapFault $e) {
        throw new Exception('SOAP Error: ' . $e->getMessage());
    }
}




/**
 * Cancels a remote session
 */
function closeISPConfig() {
    global $soap_client, $soap_session_id;

    if ($soap_client && $soap_session_id) {
        try {
            $soap_client->logout($soap_session_id);
        } catch (Exception $e) {
        }
        $soap_session_id = null;
    }
}




/**
 * Retrieves information about a client
 *
 * @param int $client_id Client ID
 * @return string JSON response
 */
function getClient($client_id) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $client_data = $soap_client->client_get($soap_session_id, $client_id);

        if (!$client_data) {
            return json_encode(array(
                'success' => false,
                'error' => 'Client not found'
            ));
        }

        return json_encode(array(
            'success' => true,
            'data' => $client_data
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Retrieves information about all clients
 *
 * @param bool $detailed Whether to fetch full client details (default: true) or just IDs (false)
 * @return string JSON response
 */
function getAllClients($detailed = true) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $client_ids = $soap_client->client_get_all($soap_session_id);

        if ($detailed) {
            $clients = array();
            foreach ($client_ids as $client_id) {
                $client_data = $soap_client->client_get($soap_session_id, $client_id);
                if ($client_data) {
                    $clients[] = $client_data;
                }
            }
        } else {
            $clients = $client_ids;
        }

        return json_encode(array(
            'success' => true,
            'count' => count($clients),
            'clients' => $clients
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage()
        ));
    }
}




/**
 * Expands the simplified SSL config, shared by add and update: setting
 * ssl_letsencrypt = 'y' turns on the whole SSL setup (ssl, ssl_domain and the
 * https redirect), so the caller only has to enable Let's Encrypt.
 *
 * @param array  $config Domain configuration / update fields
 * @param string $domain Fallback domain for ssl_domain (used on update, where
 *                       the domain name is not part of the update fields)
 * @return array Config with the SSL fields expanded
 */
function applySslConfig($config, $domain = '') {
    if (isset($config['ssl_letsencrypt']) && $config['ssl_letsencrypt'] === 'y') {
        $config['ssl'] = 'y';
        if (empty($config['ssl_domain'])) {
            $config['ssl_domain'] = (isset($config['domain']) && $config['domain'] !== '') ? $config['domain'] : $domain;
        }
        $config['rewrite_to_https'] = 'y';
    }
    return $config;
}




/**
 * Resolves a PHP version name (e.g. "PHP 8.2") to its server_php_id on a given
 * server. IDs differ per server, so a version is selected by name and looked up
 * live here. Relies on the interface library (bootstrapped in soap_env.php).
 *
 * @param string $php_version PHP version name as shown in ISPConfig (e.g. "PHP 8.2")
 * @param int    $server_id   Server the domain lives on
 * @return int|null server_php_id, or null if no such version on that server
 */
function resolveServerPhpId($php_version, $server_id) {
    global $app;

    $rec = $app->db->queryOneRecord(
        "SELECT server_php_id FROM server_php WHERE name = ? AND server_id = ?",
        $php_version, intval($server_id)
    );

    return $rec ? intval($rec['server_php_id']) : null;
}




/**
 * Adds a new web domain
 *
 * @param array $config Domain configuration
 * @return string JSON response
 */
function addWebDomain($config) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error'   => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $client_id = isset($config['client_id']) ? $config['client_id'] : 1;
        unset($config['client_id']);

        // directive_snippets_id cannot be set via SOAP; apply it after create through
        // the interface library (bootstrapped globally in soap_env.php).
        $snippet_id = array_key_exists('directive_snippets_id', $config) ? $config['directive_snippets_id'] : null;
        unset($config['directive_snippets_id']);

        $defaults = array(
            'server_id'               => 1,
            'domain'                  => '',
            'subdomain'               => 'www',
            'rewrite_to_https'        => 'n',
            'hd_quota'                => -1,
            'traffic_quota'           => -1,
            'traffic_quota_lock'      => 'n',
            'allow_override'          => 'All',
            'pm_process_idle_timeout' => 10,
            'pm_max_requests'         => 0,
            'pm'                      => 'ondemand',
            'http_port'               => 80,
            'https_port'              => 443,
            'type'                    => 'vhost',
            'ip_address'              => '*',
            'vhost_type'              => 'name',
            'active'                  => 'y',
            'php'                     => 'php-fpm',
            'php_fpm_use_socket'      => 'y',
            'suexec'                  => 'y',
            'backup_interval'         => 'daily',
            'backup_copies'           => 2,
            'backup_format_web'       => 'default',
            'backup_format_db'        => 'gzip',
            'backup_excludes'         => 'private,tmp,web,log',
            'log_retention'           => 10,
            'server_php_id'           => 0,
        );

        $params = applySslConfig(array_merge($defaults, $config));

        // php_version (e.g. "PHP 8.2") selects the PHP version by name; resolve it to
        // this server's server_php_id (ids differ per server).
        if (isset($params['php_version']) && $params['php_version'] !== '') {
            $php_id = resolveServerPhpId($params['php_version'], $params['server_id']);
            if ($php_id === null) {
                return json_encode(array(
                    'success' => false,
                    'error'   => "Unknown php_version '" . $params['php_version'] . "' on server_id " . $params['server_id']
                ));
            }
            $params['server_php_id'] = $php_id;
        }
        unset($params['php_version']);

        $domain_id = $soap_client->sites_web_domain_add(
            $soap_session_id,
            $client_id,
            $params,
            false
        );

        $response = array(
            'success'   => true,
            'domain_id' => $domain_id,
            'domain'    => $params['domain'],
        );

        if ($snippet_id !== null) {
            $response['directive_snippet'] = setDirectiveSnippet($domain_id, $snippet_id, null);
            if (empty($response['directive_snippet']['success'])) {
                $response['success'] = false;
            }
        }

        return json_encode($response);

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'domain'  => $params['domain'],
            'error'   => $e->getMessage(),
            'trace'   => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Get the number of pending jobs from jobqueue
 *
 * @param int $server_id Server ID (0 for all servers, default: 1)
 * @return string JSON response
 */
function getJobQueueCount($server_id = 1) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $count = $soap_client->monitor_jobqueue_count($soap_session_id, $server_id);

        return json_encode(array(
            'success' => true,
            'jobqueue_count' => intval($count),
            'server_id' => $server_id
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Groups SOAP function names by category (the part before the first "_").
 * Pure helper for getFunctionList.
 *
 * @param array $functions Sorted list of function names
 * @return array ['categories' => [cat => names[]], 'category_counts' => [cat => n]]
 */
function categorizeFunctions($functions) {
    $categories = array();
    foreach ($functions as $func) {
        $parts = explode('_', $func, 2);
        $category = $parts[0];

        if (empty($category) || !isset($parts[1])) {
            continue;
        }

        if (!isset($categories[$category])) {
            $categories[$category] = array();
        }
        $categories[$category][] = $func;
    }

    ksort($categories);

    $category_counts = array();
    foreach ($categories as $cat => $funcs) {
        $category_counts[$cat] = count($funcs);
    }

    return array('categories' => $categories, 'category_counts' => $category_counts);
}




/**
 * Get list of available SOAP functions
 *
 * @param bool $categorize Whether to group functions by category (default: true)
 * @return string JSON response
 */
function getFunctionList($categorize = true) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $functions = $soap_client->get_function_list($soap_session_id);

        $functions = array_filter($functions, function($func) {
            return strpos($func, '__') !== 0;
        });

        sort($functions);

        if ($categorize) {
            $grouped = categorizeFunctions($functions);

            return json_encode(array(
                'success' => true,
                'total_count' => count($functions),
                'category_counts' => $grouped['category_counts'],
                'categories' => $grouped['categories']
            ), JSON_PRETTY_PRINT);
        } else {
            return json_encode(array(
                'success' => true,
                'count' => count($functions),
                'functions' => $functions
            ), JSON_PRETTY_PRINT);
        }

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Wait for empty job queue with connection error handling
 *
 * @param int $server_id Server ID (0 for all servers)
 * @param int $sleep_seconds Seconds between checks (default: 2)
 * @param int $timeout_seconds Maximum wait time (default: 300)
 * @param int $stable_iterations Consecutive zero counts required (default: 3)
 * @param int $connection_wait Connection error wait time in seconds (default: 10)
 * @return array Result with success status
 */
function waitForEmptyJobQueue_v2($server_id = 0, $sleep_seconds = 2, $timeout_seconds = 300, $stable_iterations = 3, $connection_wait = 10) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        );
    }

    $start_time = time();
    $iterations = 0;
    $last_count = null;
    $zero_count_streak = 0;

    while (true) {
        $iterations++;
        $elapsed = time() - $start_time;

        if ($elapsed >= $timeout_seconds) {
            echo "\n✗ Timeout reached!\n";
            return array(
                'success' => false,
                'error' => 'Timeout',
                'timeout_seconds' => $timeout_seconds,
                'iterations' => $iterations,
                'elapsed_time' => $elapsed
            );
        }

        try {
            $count = $soap_client->monitor_jobqueue_count($soap_session_id, $server_id);
            $count = intval($count);

            if ($count !== $last_count) {
                echo sprintf(
                    "[%03ds] Jobs: %3d %s\n",
                    $elapsed,
                    $count,
                    $count > 0 ? str_repeat("█", min($count, 50)) : "✓"
                );
                $last_count = $count;
            }

            if ($count === 0) {
                $zero_count_streak++;

                if ($zero_count_streak >= $stable_iterations) {
                    echo "\n✓ Job queue is empty!\n";
                    return array(
                        'success' => true,
                        'iterations' => $iterations,
                        'elapsed_time' => $elapsed,
                        'stable_checks' => $zero_count_streak
                    );
                } else {
                    echo sprintf(
                        "[%03ds] Jobs: 0 (confirming... %d/%d)\n",
                        $elapsed,
                        $zero_count_streak,
                        $stable_iterations
                    );
                }
            } else {
                $zero_count_streak = 0;
            }

            sleep($sleep_seconds);

        } catch (SoapFault $e) {
            $zero_count_streak = 0;

            echo sprintf(
                "[%03ds] API unavailable, waiting %ds...\n",
                $elapsed,
                $connection_wait
            );

            sleep($connection_wait);
        }
    }
}




/**
 * Wait for empty job queue with improved reliability
 *
 * @param int $server_id Server ID (0 for all servers)
 * @param int $sleep_seconds Seconds between checks (default: 2)
 * @param int $timeout_seconds Maximum wait time (default: 300)
 * @param int $stable_iterations Number of consecutive zero counts required (default: 3)
 * @return array Result with success status
 */
function waitForEmptyJobQueue($server_id = 0, $sleep_seconds = 2, $timeout_seconds = 300, $stable_iterations = 3) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        );
    }

    $start_time = time();
    $iterations = 0;
    $last_count = null;
    $zero_count_streak = 0;

    try {
        while (true) {
            $iterations++;

            try {
                $count = $soap_client->monitor_jobqueue_count($soap_session_id, $server_id);

                if (!is_numeric($count)) {
                    throw new Exception('Invalid job count returned: ' . var_export($count, true));
                }

                $count = intval($count);

            } catch (SoapFault $e) {
                return array(
                    'success' => false,
                    'error' => 'SOAP Error: ' . $e->getMessage(),
                    'iterations' => $iterations,
                    'elapsed_time' => time() - $start_time
                );
            }

            $elapsed = time() - $start_time;

            if ($count !== $last_count) {
                echo sprintf(
                    "[%03ds] Jobs: %3d %s\n",
                    $elapsed,
                    $count,
                    $count > 0 ? str_repeat("█", min($count, 50)) : "✓"
                );
                $last_count = $count;
            }

            if ($count === 0) {
                $zero_count_streak++;

                if ($zero_count_streak >= $stable_iterations) {
                    echo "\n✓ Job queue is stable at zero for {$stable_iterations} iterations!\n";
                    return array(
                        'success' => true,
                        'iterations' => $iterations,
                        'elapsed_time' => $elapsed,
                        'stable_checks' => $zero_count_streak
                    );
                } else {
                    echo sprintf(
                        "[%03ds] Jobs: 0 (confirming... %d/%d)\n",
                        $elapsed,
                        $zero_count_streak,
                        $stable_iterations
                    );
                }
            } else {
                $zero_count_streak = 0;
            }

            if ($elapsed >= $timeout_seconds) {
                echo "\n✗ Timeout reached!\n";
                return array(
                    'success' => false,
                    'error' => 'Timeout',
                    'timeout_seconds' => $timeout_seconds,
                    'iterations' => $iterations,
                    'elapsed_time' => $elapsed,
                    'final_count' => $count
                );
            }

            sleep($sleep_seconds);
        }

    } catch (Exception $e) {
        return array(
            'success' => false,
            'error' => 'Unexpected error: ' . $e->getMessage(),
            'iterations' => $iterations,
            'elapsed_time' => time() - $start_time
        );
    }
}




/**
 * Wait for empty queue with progress display
 *
 * @deprecated Use waitForEmptyJobQueue() instead
 */
function waitWithProgress($server_id = 0, $sleep_seconds = 2, $timeout_seconds = 300) {
    global $soap_client, $soap_session_id;

    $start_time = time();
    $iterations = 0;
    $last_count = null;

    echo "Monitoring job queue:\n";

    try {
        while (true) {
            $iterations++;
            $count = $soap_client->monitor_jobqueue_count($soap_session_id, $server_id);
            $elapsed = time() - $start_time;

            if ($count !== $last_count) {
                echo sprintf(
                    "[%03ds] Jobs: %3d %s\n",
                    $elapsed,
                    $count,
                    $count > 0 ? str_repeat("█", min($count, 50)) : "✓"
                );
                $last_count = $count;
            }

            if ($count === 0) {
                echo "\n✓ Job queue is empty!\n";
                return array(
                    'success'      => true,
                    'iterations'   => $iterations,
                    'elapsed_time' => $elapsed
                );
            }

            if ($elapsed >= $timeout_seconds) {
                echo "\n✗ Timeout reached!\n";
                return array(
                    'success'     => false,
                    'error'       => 'Timeout',
                    'final_count' => $count
                );
            }

            sleep($sleep_seconds);
        }

    } catch (SoapFault $e) {
        return array(
            'success' => false,
            'error'   => $e->getMessage()
        );
    }
}




/**
 * Wait for empty queue with progress display (short wrapper)
 */
function waitWithProgressShort()
{
    global $soap_client, $soap_session_id;

    echo "\n";
    $result = waitForEmptyJobQueue_v2(0, 2, 300, 2, 10);
    if ($result['success']) {
        echo "Completed in " . $result['elapsed_time'] . " seconds\n";
    }
}




/**
 * Adds a new database user
 *
 * @param array $config Database user configuration
 * @return string JSON response
 */
function addDatabaseUser($config) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error'   => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        if (empty($config['database_user'])) {
            throw new Exception('Database user is required');
        }
        if (empty($config['database_password'])) {
            throw new Exception('Database password is required');
        }

        $client_id = isset($config['client_id']) ? $config['client_id'] : 1;
        unset($config['client_id']);

        $defaults = array(
            'server_id'         => 1,
            'database_user'     => '',
            'database_password' => ''
        );

        $params = array_merge($defaults, $config);

        $database_user_id = $soap_client->sites_database_user_add(
            $soap_session_id,
            $client_id,
            $params
        );

        return json_encode(array(
            'success'          => true,
            'database_user_id' => $database_user_id,
            'database_user'    => $params['database_user'],
            'server_id'        => $params['server_id']
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success'       => false,
            'database_user' => isset($params['database_user']) ? $params['database_user'] : '',
            'error'         => $e->getMessage(),
            'trace'         => $soap_client->__getLastResponse()
        ));
    } catch (Exception $e) {
        return json_encode(array(
            'success'       => false,
            'database_user' => isset($params['database_user']) ? $params['database_user'] : '',
            'error'         => $e->getMessage()
        ));
    }
}




/**
 * Adds a new database
 *
 * @param array $config Database configuration
 * @return string JSON response
 */
function addDatabase($config) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        if (empty($config['database_name'])) {
            throw new Exception('Database name is required');
        }
        if (empty($config['database_user_id'])) {
            throw new Exception('Database user ID is required');
        }
        if (empty($config['parent_domain_id'])) {
            throw new Exception('Database website ID is required');
        }

        $client_id = isset($config['client_id']) ? $config['client_id'] : 1;
        unset($config['client_id']);

        $defaults = array(
            'server_id'            => 1,
            'type'                 => 'mysql',
            'parent_domain_id'     => 0,
            'database_name'        => '',
            'database_user_id'     => 0,
            'database_ro_user_id'  => 0,
            'database_charset'     => '',
            'database_quota'       => '-1',
            'remote_access'        => 'n',
            'remote_ips'           => '',
            'backup_interval'      => 'daily',
            'backup_copies'        => 2,
            'active'               => 'y'
        );

        $params = array_merge($defaults, $config);

        $database_id = $soap_client->sites_database_add(
            $soap_session_id,
            $client_id,
            $params
        );

        return json_encode(array(
            'success'          => true,
            'database_id'      => $database_id,
            'database_name'    => $params['database_name'],
            'database_user_id' => $params['database_user_id']
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success'       => false,
            'database_name' => isset($params['database_name']) ? $params['database_name'] : '',
            'error'         => $e->getMessage(),
            'trace'         => $soap_client->__getLastResponse()
        ));
    } catch (Exception $e) {
        return json_encode(array(
            'success'       => false,
            'database_name' => isset($params['database_name']) ? $params['database_name'] : '',
            'error'         => $e->getMessage()
        ));
    }
}




/**
 * Retrieves information about a database
 *
 * @param mixed $database_id Database ID (integer) or '-1' for all databases
 * @return string JSON response
 */
function getDatabase($database_id) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $databases = $soap_client->sites_database_get($soap_session_id, $database_id);

        if ($database_id === '-1' || $database_id === -1) {
            if (!is_array($databases)) {
                $databases = array();
            }

            $result = array();
            foreach ($databases as $database) {
                $result[$database['database_id']] = $database;
            }

            return json_encode(array(
                'success' => true,
                'count' => count($result),
                'databases' => $result
            ));
        } else {
            if (!$databases) {
                return json_encode(array(
                    'success' => false,
                    'error' => 'Database not found'
                ));
            }

            return json_encode(array(
                'success' => true,
                'data' => $databases
            ));
        }

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Retrieves information about all databases
 *
 * @return string JSON response
 */
function getAllDatabases() {
    return getDatabase('-1');
}




/**
 * Finds fields that differ between the original and modified records but were not
 * part of the requested updates. Pure helper for the update functions, which
 * refuse to write when the API would change fields the caller did not ask for.
 *
 * @param array $original Original record
 * @param array $modified Record after applying the updates
 * @param array $updates  Requested update fields (keys are the intended changes)
 * @return array field => ['original' => mixed, 'modified' => mixed]
 */
function detectUnexpectedChanges($original, $modified, $updates) {
    $unexpected = array();
    foreach ($modified as $key => $value) {
        if (isset($updates[$key])) {
            continue;
        }
        if (array_key_exists($key, $original) && strval($original[$key]) !== strval($value)) {
            $unexpected[$key] = array(
                'original' => $original[$key],
                'modified' => $value
            );
        }
    }
    return $unexpected;
}




/**
 * Updates a database record
 *
 * @param int $database_id Database ID
 * @param array $updates Associative array of fields to update
 * @param int $client_id Client ID (default: 1)
 * @return string JSON response
 */
function updateDatabase($database_id, $updates, $client_id = 0) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $database_record = $soap_client->sites_database_get($soap_session_id, $database_id);

        if (!$database_record) {
            return json_encode(array(
                'success' => false,
                'error' => 'Database not found'
            ));
        }

        $original_record = $database_record;

        foreach ($updates as $key => $value) {
            $database_record[$key] = $value;
        }

        $unexpected_changes = detectUnexpectedChanges($original_record, $database_record, $updates);

        if (!empty($unexpected_changes)) {
            return json_encode(array(
                'success' => false,
                'error' => 'Unexpected fields were modified outside of --data',
                'unexpected_changes' => $unexpected_changes
            ));
        }

        $affected_rows = $soap_client->sites_database_update($soap_session_id, $client_id, $database_id, $database_record);

        return json_encode(array(
            'success' => true,
            'affected_rows' => $affected_rows,
            'database_id' => $database_id
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'database_id' => $database_id,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Updates a web domain record
 *
 * @param int $domain_id Domain ID
 * @param array $updates Associative array of fields to update
 * @param int $client_id Client ID (default: 0)
 * @return string JSON response
 */
function updateWebDomain($domain_id, $updates, $client_id = 0) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        // directive_snippets_id cannot be set via SOAP; apply it after the update
        // through the interface library (bootstrapped globally in soap_env.php).
        $snippet_id = array_key_exists('directive_snippets_id', $updates) ? $updates['directive_snippets_id'] : null;
        unset($updates['directive_snippets_id']);

        $response = array('success' => true, 'domain_id' => $domain_id);

        // Only touch SOAP when there are real fields to change (a snippet-only edit skips it)
        if (!empty($updates)) {
            $domain_record = $soap_client->sites_web_domain_get($soap_session_id, $domain_id);

            if (!$domain_record) {
                return json_encode(array(
                    'success' => false,
                    'error' => 'Domain not found'
                ));
            }

            // php_version (e.g. "PHP 8.2") selects the PHP version by name; resolve it to
            // this server's server_php_id (ids differ per server).
            if (isset($updates['php_version']) && $updates['php_version'] !== '') {
                $php_id = resolveServerPhpId($updates['php_version'], $domain_record['server_id']);
                if ($php_id === null) {
                    return json_encode(array(
                        'success' => false,
                        'error'   => "Unknown php_version '" . $updates['php_version'] . "' on server_id " . $domain_record['server_id']
                    ));
                }
                $updates['server_php_id'] = $php_id;
            }
            unset($updates['php_version']);

            $updates = applySslConfig($updates, isset($domain_record['domain']) ? $domain_record['domain'] : '');

            $original_record = $domain_record;

            foreach ($updates as $key => $value) {
                $domain_record[$key] = $value;
            }

            $unexpected_changes = detectUnexpectedChanges($original_record, $domain_record, $updates);

            if (!empty($unexpected_changes)) {
                return json_encode(array(
                    'success' => false,
                    'error' => 'Unexpected fields were modified outside of --data',
                    'unexpected_changes' => $unexpected_changes
                ));
            }

            $response['affected_rows'] = $soap_client->sites_web_domain_update($soap_session_id, $client_id, $domain_id, $domain_record);
        }

        if ($snippet_id !== null) {
            $response['directive_snippet'] = setDirectiveSnippet($domain_id, $snippet_id, null);
            if (empty($response['directive_snippet']['success'])) {
                $response['success'] = false;
            }
        }

        return json_encode($response);

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'domain_id' => $domain_id,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Retrieves information about all web domains
 *
 * @return string JSON response
 */
function getAllWebDomains() {
    return getWebDomain('-1');
}




/**
 * Retrieves information about a web domain
 *
 * @param mixed $domain_id Domain ID (integer) or '-1' for all domains
 * @return string JSON response
 */
function getWebDomain($domain_id) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        if ($domain_id === '-1' || $domain_id === -1) {
            $domains = $soap_client->sites_web_domain_get($soap_session_id, array());

            if (!is_array($domains)) {
                $domains = array();
            }

            $result = array();
            foreach ($domains as $domain) {
                $result[$domain['domain_id']] = $domain;
            }

            return json_encode(array(
                'success' => true,
                'count' => count($result),
                'domains' => $result
            ));
        } else {
            $domain_data = $soap_client->sites_web_domain_get($soap_session_id, $domain_id);

            if (!$domain_data) {
                return json_encode(array(
                    'success' => false,
                    'error' => 'Domain not found'
                ));
            }

            return json_encode(array(
                'success' => true,
                'data' => $domain_data
            ));
        }

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Deletes a web domain
 *
 * @param int $domain_id Domain ID to delete
 * @return string JSON response
 */
function deleteWebDomain($domain_id)
{
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        if (empty($domain_id)) {
            throw new Exception('Domain ID is required');
        }

        $affected_rows = $soap_client->sites_web_domain_delete($soap_session_id, $domain_id);

        return json_encode(array(
            'success' => true,
            'affected_rows' => $affected_rows,
            'domain_id' => $domain_id
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'domain_id' => $domain_id,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    } catch (Exception $e) {
        return json_encode(array(
            'success' => false,
            'domain_id' => $domain_id,
            'error' => $e->getMessage()
        ));
    }
}




/**
 * Retrieves information about a database user
 *
 * @param mixed $database_user_id Database user ID (integer) or '-1' for all users
 * @return string JSON response
 */
function getDatabaseUser($database_user_id) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $database_users = $soap_client->sites_database_user_get($soap_session_id, $database_user_id);

        if ($database_user_id === '-1' || $database_user_id === -1) {
            if (!is_array($database_users)) {
                $database_users = array();
            }

            $result = array();
            foreach ($database_users as $user) {
                $result[$user['database_user_id']] = $user;
            }

            return json_encode(array(
                'success' => true,
                'count' => count($result),
                'users' => $result
            ));
        } else {
            if (!$database_users) {
                return json_encode(array(
                    'success' => false,
                    'error' => 'Database user not found'
                ));
            }

            return json_encode(array(
                'success' => true,
                'user' => $database_users
            ));
        }

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Retrieves information about all database users
 *
 * @return string JSON response
 */
function getAllDatabaseUsers() {
    return getDatabaseUser('-1');
}




/**
 * Returns information about the databases of the system user
 *
 * @param int $client_id Client ID
 * @return string JSON response
 */
function getAllDatabasesForClient($client_id = 1) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected.'
        ));
    }

    try {
        $databases = $soap_client->sites_database_get_all_by_user($soap_session_id, $client_id);

        return json_encode(array(
            'success' => true,
            'count' => count($databases),
            'databases' => $databases
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage()
        ));
    }
}




/**
 * Retrieves total database size for all databases linked to a specific web domain
 *
 * Fetches databases from API filtered by parent_domain_id.
 * Gets actual size from information_schema via mysql CLI.
 *
 * @param int $domain_id Domain ID (parent_domain_id in database records)
 * @return string JSON response
 */
function getDatabaseSizeByDomain($domain_id) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        // Verify domain exists
        $domain = $soap_client->sites_web_domain_get($soap_session_id, $domain_id);

        if (!$domain) {
            return json_encode(array(
                'success' => false,
                'error' => 'Domain not found'
            ));
        }

        // Get all databases
        $all_databases = $soap_client->sites_database_get($soap_session_id, -1);

        if (!is_array($all_databases)) {
            $all_databases = array();
        }

        $databases = array();
        $total_used_bytes = 0;

        foreach ($all_databases as $db) {
            if (intval($db['parent_domain_id']) !== intval($domain_id)) {
                continue;
            }

            $db_name = $db['database_name'];
            $quota = isset($db['database_quota']) ? intval($db['database_quota']) : -1;

            // Get actual size from information_schema
            $used_bytes = getDatabaseSizeFromMysql($db_name);

            $databases[] = array(
                'database_id'   => $db['database_id'],
                'database_name' => $db_name,
                'quota_bytes'   => $quota == -1 ? 'unlimited' : $quota * 1024 * 1024,
                'used_bytes'    => $used_bytes,
            );

            $total_used_bytes += $used_bytes;
        }

        return json_encode(array(
            'success'          => true,
            'domain_id'        => $domain_id,
            'domain'           => $domain['domain'],
            'count'            => count($databases),
            'total_used_bytes' => $total_used_bytes,
            'databases'        => $databases
        ), JSON_PRETTY_PRINT);

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error'   => $e->getMessage(),
            'trace'   => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Gets database size in bytes from information_schema via mysql CLI
 *
 * @param string $database_name Database name
 * @return int Size in bytes
 */
function getDatabaseSizeFromMysql($database_name) {
    $sql = "SELECT IFNULL(SUM(data_length + index_length), 0) "
         . "FROM information_schema.tables "
         . "WHERE table_schema = '" . addslashes($database_name) . "'";

    $output = shell_exec('mysql -N -e ' . escapeshellarg($sql) . ' 2>/dev/null');

    return parseMysqlSize($output);
}




/**
 * Parses a single-value `mysql -N` size result into an integer byte count.
 * Pure helper for getDatabaseSizeFromMysql.
 *
 * @param string|null $output Raw `mysql -N` output
 * @return int Size in bytes (0 for null / empty / NULL)
 */
function parseMysqlSize($output) {
    if ($output === null || trim((string)$output) === '' || trim((string)$output) === 'NULL') {
        return 0;
    }
    return intval(trim($output));
}




/**
 * Retrieves disk usage for a specific web domain
 *
 * Gets domain info from API, then measures actual disk usage via du
 * on the document_root directory.
 *
 * @param int $domain_id Domain ID
 * @return string JSON response
 */
function getDiskUsageByDomain($domain_id) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $domain = $soap_client->sites_web_domain_get($soap_session_id, $domain_id);

        if (!$domain) {
            return json_encode(array(
                'success' => false,
                'error' => 'Domain not found'
            ));
        }

        $document_root = $domain['document_root'];
        $hd_quota = intval($domain['hd_quota']);

        // Get actual disk usage via du
        $used_bytes = 0;
        $source = 'du';

        if (is_dir($document_root)) {
            $used_bytes = parseDuBytes(shell_exec('du -sb ' . escapeshellarg($document_root) . ' 2>/dev/null'));
        }

        $result = array(
            'success'         => true,
            'domain_id'       => $domain_id,
            'domain'          => $domain['domain'],
            'document_root'   => $document_root,
            'hd_quota_bytes'  => $hd_quota == -1 ? 'unlimited' : $hd_quota * 1024 * 1024,
            'hd_used_bytes'   => $used_bytes,
            'source'          => $source,
        );

        if ($hd_quota > 0 && $used_bytes > 0) {
            $result['hd_used_percent'] = round(($used_bytes / ($hd_quota * 1024 * 1024)) * 100, 1);
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $soap_client->__getLastResponse()
        ));
    }
}




/**
 * Parses `du -sb` output ("<bytes>\t<path>") into an integer byte count.
 * Pure helper for getDiskUsageByDomain.
 *
 * @param string|null $output Raw `du -sb` output
 * @return int Size in bytes (0 if empty / unparseable)
 */
function parseDuBytes($output) {
    if (!$output) {
        return 0;
    }
    return intval(trim(explode("\t", $output)[0]));
}




/**
 * Lists web directive snippets from the ISPConfig database.
 *
 * There is no SOAP API function for directive snippets, so this reads them
 * directly from the local ISPConfig database via the mysql CLI (same approach
 * as the database size functions above).
 *
 * @return string JSON response
 */
function getDirectiveSnippets() {
    $sql = "SELECT directive_snippets_id, name, type, active "
         . "FROM dbispconfig.directive_snippets "
         . "ORDER BY directive_snippets_id";

    $output = shell_exec('mysql -N -e ' . escapeshellarg($sql) . ' 2>/dev/null');

    if ($output === null) {
        return json_encode(array(
            'success' => false,
            'error'   => 'Failed to query directive_snippets'
        ));
    }

    $snippets = parseSnippetRows($output);

    return json_encode(array(
        'success'  => true,
        'count'    => count($snippets),
        'snippets' => $snippets
    ));
}




/**
 * Parses tab-separated `mysql -N` rows of directive snippets into structured
 * records (id, name, type, active). Pure helper for getDirectiveSnippets.
 *
 * @param string $output Raw `mysql -N` output
 * @return array List of snippet records
 */
function parseSnippetRows($output) {
    $snippets = array();
    foreach (explode("\n", trim((string)$output)) as $line) {
        if ($line === '') {
            continue;
        }
        $cols = explode("\t", $line);
        $snippets[] = array(
            'directive_snippets_id' => intval($cols[0]),
            'name'                  => isset($cols[1]) ? $cols[1] : '',
            'type'                  => isset($cols[2]) ? $cols[2] : '',
            'active'                => isset($cols[3]) ? $cols[3] : '',
        );
    }
    return $snippets;
}




/**
 * Assigns a directive snippet (nginx/apache/php template) to a web domain.
 *
 * directive_snippets_id is a UI-plugin field that the SOAP API silently ignores
 * (on both add and update), so this uses ISPConfig's own interface library
 * (datalogUpdate) to update the column and queue the vhost rebuild - exactly what
 * the panel does. A raw SQL UPDATE would change the value but not rebuild the vhost.
 *
 * Relies on the interface library, which soap_env.php bootstraps for every script.
 *
 * Pass either $snippet_id or $snippet_name. Pass $snippet_id = 0 to clear the
 * assignment (domain uses no directive snippet).
 *
 * @param int         $domain_id    Web domain ID
 * @param int|null    $snippet_id   Directive snippet ID (0 clears the assignment)
 * @param string|null $snippet_name Directive snippet name
 * @return array Result array with 'success' plus details, or 'error' on failure
 */
function setDirectiveSnippet($domain_id, $snippet_id = null, $snippet_name = null) {
    global $app;

    if (!isset($app) || !is_object($app)) {
        return array('success' => false, 'error' => 'ISPConfig interface library not bootstrapped');
    }

    $domain_id = intval($domain_id);

    $domain = $app->db->queryOneRecord('SELECT domain_id, server_id FROM web_domain WHERE domain_id = ?', $domain_id);
    if (!$domain) {
        return array('success' => false, 'error' => 'Domain not found');
    }

    // snippet_id = 0 clears the assignment (domain uses no directive snippet)
    if ($snippet_name === null && $snippet_id !== null && intval($snippet_id) === 0) {
        $app->db->datalogUpdate('web_domain', array('directive_snippets_id' => 0), 'domain_id', $domain_id);
        return array(
            'success'               => true,
            'domain_id'             => $domain_id,
            'directive_snippets_id' => 0,
            'name'                  => '',
        );
    }

    // Only snippets that match this server's web type, active and customer-viewable are valid
    $web_config  = $app->getconf->get_server_config($domain['server_id'], 'web');
    $server_type = $web_config['server_type'];

    if ($snippet_id !== null) {
        $snippet = $app->db->queryOneRecord("SELECT directive_snippets_id, name, type FROM directive_snippets WHERE directive_snippets_id = ? AND active = 'y' AND customer_viewable = 'y'", intval($snippet_id));
    } else {
        $snippet = $app->db->queryOneRecord("SELECT directive_snippets_id, name, type FROM directive_snippets WHERE name = ? AND active = 'y' AND customer_viewable = 'y'", $snippet_name);
    }

    if (!$snippet) {
        return array('success' => false, 'error' => 'Snippet not found, inactive or not customer-viewable');
    }
    if ($snippet['type'] !== $server_type) {
        return array('success' => false, 'error' => "Snippet type '" . $snippet['type'] . "' does not match server web type '" . $server_type . "'");
    }

    // Update the column and queue the vhost rebuild through ISPConfig's own datalog layer
    $app->db->datalogUpdate('web_domain', array('directive_snippets_id' => $snippet['directive_snippets_id']), 'domain_id', $domain_id);

    return array(
        'success'               => true,
        'domain_id'             => $domain_id,
        'directive_snippets_id' => intval($snippet['directive_snippets_id']),
        'name'                  => $snippet['name'],
    );
}




/**
 * Extracts the default field values ISPConfig applies to a new record, read live
 * from the module's tform definition. Used by the --help of the --data scripts so
 * the shown defaults stay correct across ISPConfig updates (the definition, not a
 * hard-coded copy, is the source of truth).
 *
 * Relies on the interface library, which soap_env.php bootstraps for every script.
 *
 * @param string $form_key Which form to read; all tform paths are mapped here,
 *                         so callers pass a key (e.g. 'WEB_DOMAIN_TFORM')
 * @return array field => default value
 */
function getFormDefaults($form_key) {
    global $app, $conf;

    // All ISPConfig form definitions used by the --data scripts, in one place.
    $tforms = array(
        'WEB_DOMAIN_TFORM'    => '/usr/local/ispconfig/interface/web/sites/form/web_vhost_domain.tform.php',
        'DATABASE_TFORM'      => '/usr/local/ispconfig/interface/web/sites/form/database.tform.php',
        'DATABASE_USER_TFORM' => '/usr/local/ispconfig/interface/web/sites/form/database_user.tform.php',
    );

    if (!isset($tforms[$form_key]) || !is_file($tforms[$form_key])) {
        return array();
    }

    // The tform file populates $form; it runs in this scope, so keep it local.
    $form = array();
    include $tforms[$form_key];

    return extractFormDefaults($form);
}




/**
 * Extracts field defaults from a loaded ISPConfig $form definition (walks
 * tabs -> fields -> default). Pure helper for getFormDefaults.
 *
 * @param array $form ISPConfig tform definition
 * @return array field => default value
 */
function extractFormDefaults($form) {
    $defaults = array();
    if (isset($form['tabs']) && is_array($form['tabs'])) {
        foreach ($form['tabs'] as $tab) {
            if (empty($tab['fields']) || !is_array($tab['fields'])) {
                continue;
            }
            foreach ($tab['fields'] as $field => $def) {
                if (is_array($def) && array_key_exists('default', $def)) {
                    $defaults[$field] = $def['default'];
                }
            }
        }
    }
    return $defaults;
}




/**
 * Load environment variables from .env file
 *
 * @param string $file Path to .env file
 */
function loadEnv($file) {
    if (!file_exists($file)) {
        throw new Exception('.env file not found: ' . $file);
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            $value = trim($value, '"\'');

            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
