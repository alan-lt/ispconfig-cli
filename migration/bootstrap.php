#!/usr/bin/php
<?php

/**
 * ISPConfig Migration Bootstrap
 *
 * Bulk-creates web domains, database users, and databases from a JSON config file.
 * Runs in 3 sequential steps, waiting for the ISPConfig job queue between each.
 *
 * If a resource already exists (creation fails), it falls back to searching
 * for the existing resource by name so subsequent steps can still reference it.
 *
 * Expected config.json format:
 * [
 *   {
 *     "website_name": "example.com",
 *     "mysql_user":   "c1example",
 *     "mysql_pass":   "password123",
 *     "mysql_base":   "c1example_db"
 *   },
 *   ...
 * ]
 */

require '../soap_functions.php';

$soap_client     = null;
$soap_session_id = null;


try {
    echo "\nISPConfig Migration Bootstrap\n\n";


    // ──────────────────────────────────────────
    // Initialize: connect, load config, fetch existing resources
    // ──────────────────────────────────────────

    echo "Initializing SOAP connection - ";
    initISPConfig();
    echo "OK\n";

    echo "Loading migration configuration - ";
    $json_string    = file_get_contents("./config.json");
    $migration_list = json_decode($json_string, true);
    if (!is_array($migration_list)) {
        die("ERR (Invalid JSON data)\n");
    }
    echo "OK (" . count($migration_list) . " items)\n";

    // Pre-fetch all existing resources so we can fall back to them if creation fails
    echo "Fetching existing resources - ";
    $existing_domains   = json_decode(getAllWebDomains($sys_userid, $sys_groupid), true);
    $existing_db_users  = json_decode(getAllDatabaseUsers(), true);
    $existing_databases = json_decode(getAllDatabasesForClient($client_id), true);
    echo "OK\n";

    // Working copy of migration items — gets enriched with created IDs as we go
    $completed_items = array();

    $stats = [
        'domains'   => ['ok' => 0, 'err' => 0],
        'users'     => ['ok' => 0, 'err' => 0],
        'databases' => ['ok' => 0, 'err' => 0],
    ];


    // ──────────────────────────────────────────
    // Step 1/3: Create Web Domains
    // ──────────────────────────────────────────

    echo "\nStep 1/3: Creating Web Domains\n";

    foreach ($migration_list as $index => $item) {
        $completed_items[$index] = $item;
        $website_name = $item['website_name'];

        echo "  Create domain $website_name - ";

        $result = json_decode(addWebDomain(array(
            'domain'                => $website_name,
            'client_id'             => $client_id,
            'directive_snippets_id' => 1,
        )), true);

        if ($result['success']) {
            echo "OK (ID: {$result['domain_id']})\n";
            $completed_items[$index]['website_id'] = $result['domain_id'];
            $stats['domains']['ok']++;
        } else {
            echo "ERR ({$result['error']})\n";
            $stats['domains']['err']++;

            // Fallback: try to find domain that already exists
            echo "  Search existing domain $website_name - ";
            $found = false;
            foreach ($existing_domains['domains'] as $domain) {
                if ($domain['domain'] === $website_name) {
                    $completed_items[$index]['website_id'] = $domain['domain_id'];
                    echo "OK (ID: {$domain['domain_id']})\n";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "NOT FOUND\n";
            }
        }

        sleep(1);
    }

    $total = $stats['domains']['ok'] + $stats['domains']['err'];
    echo "  Summary: {$stats['domains']['ok']}/$total successful\n";

    echo "Waiting for job queue -\n";
    waitWithProgressShort();
    sleep(2);


    // ──────────────────────────────────────────
    // Step 2/3: Create Database Users
    // ──────────────────────────────────────────

    echo "\nStep 2/3: Creating Database Users\n";

    foreach ($completed_items as $index => $item) {
        $db_user = $item['mysql_user'];

        echo "  Create user $db_user - ";

        $result = json_decode(addDatabaseUser(array(
            'database_user'     => $db_user,
            'database_password' => $item['mysql_pass'],
            'client_id'         => $client_id,
            'server_id'         => $server_id,
        )), true);

        if ($result['success']) {
            echo "OK (ID: {$result['database_user_id']})\n";
            $completed_items[$index]['database_user_id'] = $result['database_user_id'];
            $stats['users']['ok']++;
        } else {
            echo "ERR ({$result['error']})\n";
            $stats['users']['err']++;

            // Fallback: try to find user that already exists
            echo "  Search existing user $db_user - ";
            $found = false;
            foreach ($existing_db_users['users'] as $user) {
                if ($user['database_user'] === $db_user) {
                    $completed_items[$index]['database_user_id'] = $user['database_user_id'];
                    echo "OK (ID: {$user['database_user_id']})\n";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "NOT FOUND\n";
            }
        }

        sleep(1);
    }

    $total = $stats['users']['ok'] + $stats['users']['err'];
    echo "  Summary: {$stats['users']['ok']}/$total successful\n";

    echo "Waiting for job queue -\n";
    waitWithProgressShort();
    sleep(2);


    // ──────────────────────────────────────────
    // Step 3/3: Create Databases (attached to domain + user)
    // ──────────────────────────────────────────

    echo "\nStep 3/3: Creating Databases\n";

    foreach ($completed_items as $index => $item) {
        $db_name = $item['mysql_base'];

        echo "  Create database $db_name - ";

        $result = json_decode(addDatabase(array(
            'database_name'    => $db_name,
            'database_user_id' => $item['database_user_id'],
            'parent_domain_id' => $item['website_id'],
            'client_id'        => $client_id,
            'server_id'        => $server_id,
        )), true);

        if ($result['success']) {
            echo "OK (ID: {$result['database_id']})\n";
            $completed_items[$index]['database_id'] = $result['database_id'];
            $stats['databases']['ok']++;
        } else {
            echo "ERR ({$result['error']})\n";
            $stats['databases']['err']++;

            // Fallback: try to find database that already exists
            echo "  Search existing database $db_name - ";
            $found = false;
            foreach ($existing_databases['databases'] as $database) {
                if ($database['database_name'] === $db_name) {
                    $completed_items[$index]['database_id'] = $database['database_id'];
                    echo "OK (ID: {$database['database_id']})\n";
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                echo "NOT FOUND\n";
            }
        }

        sleep(1);
    }

    $total = $stats['databases']['ok'] + $stats['databases']['err'];
    echo "  Summary: {$stats['databases']['ok']}/$total successful\n";

    echo "Waiting for job queue -\n";
    waitWithProgressShort();
    sleep(1);


    // ──────────────────────────────────────────
    // Final Summary
    // ──────────────────────────────────────────

    $total_domains   = $stats['domains']['ok']   + $stats['domains']['err'];
    $total_users     = $stats['users']['ok']     + $stats['users']['err'];
    $total_databases = $stats['databases']['ok'] + $stats['databases']['err'];

    echo "\nMigration Complete\n";
    echo "  Domains:   {$stats['domains']['ok']}/{$total_domains} successful\n";
    echo "  Users:     {$stats['users']['ok']}/{$total_users} successful\n";
    echo "  Databases: {$stats['databases']['ok']}/{$total_databases} successful\n";

    echo "\nClosing SOAP connection - ";
    closeISPConfig();
    echo "OK\n";

    echo "\nDone\n\n";

} catch (Exception $e) {
    echo "ERR\n\n";
    echo "Fatal Error: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n\n";
    exit(1);
}