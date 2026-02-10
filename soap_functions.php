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
            'server_php_id'           => 2,
            'directive_snippets_id'   => 0,
        );

        $params = array_merge($defaults, $config);

        $domain_id = $soap_client->sites_web_domain_add(
            $soap_session_id,
            $client_id,
            $params,
            false
        );

        return json_encode(array(
            'success'   => true,
            'domain_id' => $domain_id,
            'domain'    => $params['domain'],
        ));

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

            return json_encode(array(
                'success' => true,
                'total_count' => count($functions),
                'category_counts' => $category_counts,
                'categories' => $categories
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
 * Retrieves information about a web domain
 *
 * @param mixed $identifier Domain ID (int) or array with 'domain' key for name lookup
 * @return string JSON response
 */
function getWebDomain($identifier) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $domain_data = $soap_client->sites_web_domain_get($soap_session_id, $identifier);

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
 * Shows sites of a single user
 *
 * @param int $sys_userid System user ID
 * @param int $sys_groupid System group ID
 * @return string JSON response
 */
function getAllWebDomains($sys_userid = 1, $sys_groupid = 1) {
    global $soap_client, $soap_session_id;

    if (!$soap_client || !$soap_session_id) {
        return json_encode(array(
            'success' => false,
            'error' => 'Not connected. Call initISPConfig() first.'
        ));
    }

    try {
        $domains = $soap_client->client_get_sites_by_user($soap_session_id, $sys_userid, $sys_groupid);

        return json_encode(array(
            'success' => true,
            'count' => count($domains),
            'domains' => $domains
        ));

    } catch (SoapFault $e) {
        return json_encode(array(
            'success' => false,
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
