# ISPConfig CLI Tools Documentation

Command-line interface tools for managing ISPConfig via SOAP API.

> **Disclaimer:** I has extensive experience in system administration and programming. This project is created using vibe-coding with AI assistance as an experiment and to save time. Use at your own risk — always review the code and test in a non-production environment before deploying.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Output Format](#output-format)
- **System & API**
  - [get_function_list.php](#get_function_listphp) — list available SOAP API functions
  - [get_jobqueue_count.php](#get_jobqueue_countphp) — check pending jobs count
- **Client Management**
  - [client_get.php](#client_getphp) — get client by ID
  - [client_get_all.php](#client_get_allphp) — list all clients
- **Web Domain Management**
  - [sites_web_domain_add.php](#sites_web_domain_addphp) — create a domain
  - [sites_web_domain_get.php](#sites_web_domain_getphp) — get domain by name
  - [sites_web_domain_get_all.php](#sites_web_domain_get_allphp) — list all domains
  - [sites_web_domain_edit.php](#sites_web_domain_editphp) — update domain settings
  - [sites_web_domain_delete.php](#sites_web_domain_deletephp) — delete a domain
  - [sites_web_domain_disk_usage.php](#sites_web_domain_disk_usagephp) — get disk usage for a domain
  - [sites_web_domain_database_usage.php](#sites_web_domain_database_usagephp) — get database size for a domain
- **Database Management**
  - [sites_database_add.php](#sites_database_addphp) — create a database
  - [sites_database_get.php](#sites_database_getphp) — get database by ID
  - [sites_database_get_all.php](#sites_database_get_allphp) — list all databases
  - [sites_database_edit.php](#sites_database_editphp) — update database settings
- **Database User Management**
  - [sites_database_user_add.php](#sites_database_user_addphp) — create a database user
  - [sites_database_user_get.php](#sites_database_user_getphp) — get database user by ID
  - [sites_database_user_get_all.php](#sites_database_user_get_allphp) — list all database users
- **Cron Management**
  - [sites_cron_add.php](#sites_cron_addphp) — create a cron job
  - [sites_cron_get.php](#sites_cron_getphp) — get cron job by ID
  - [sites_cron_get_all.php](#sites_cron_get_allphp) — list all cron jobs
- **Web Server Config**
  - [directive_snippets_get_all.php](#directive_snippets_get_allphp) — list all directive snippets (apache/nginx/php templates)
  - [sites_web_domain_directive_snippet_set.php](#sites_web_domain_directive_snippet_setphp) — assign a directive snippet to a web domain
- [Common Workflows](#common-workflows)
- [Technical Details](#technical-details)

## Prerequisites

Before using any of these tools, you must configure your `.env` file with your ISPConfig credentials:

```bash
cp .env.example .env
# Edit .env and add your credentials:
# SOAP_USERNAME=your-username
# SOAP_PASSWORD=your-password
# SOAP_LOCATION=https://your-server:8080/remote/index.php
# SOAP_URI=https://your-server:8080/remote/
```

## Output Format

Every command speaks a single, uniform contract on **stdout**: an **NDJSON event
stream**. Each line is exactly one JSON object carrying a `type` discriminator.
There is never any human-decorated text on stdout (no progress bars, no `✓`/`✗`,
no `Error:` prefixes) — presentation is the consumer's job, so the stream stays
machine-parseable without a "parser of a parser".

### The rule

- **One line = one JSON object.** Read stdout line by line, `json_decode` each
  line, `switch` on `type`.
- **The last line is always `type: "result"`** — the terminal outcome of the
  command. Everything before it (if anything) is progress/diagnostics.
- **Ignore any line that fails to decode** (a stray PHP warning, etc.) — this
  keeps a consumer robust.

### Event types

| `type`     | When                                   | Key fields |
|------------|----------------------------------------|------------|
| `progress` | Emitted repeatedly while waiting on the ISPConfig job queue (provisioning in progress) | `jobs` (pending count), `elapsed` (seconds since the wait started), and during the settle phase `stable` / `stable_target` (confirmation ticks x/y) |
| `notice`   | Transient, non-fatal condition during a wait | `message`, `elapsed`, and context (e.g. `wait` seconds before retry) |
| `result`   | Exactly once, as the final line        | `success` (bool); on success the payload fields (e.g. `database_id`); on failure `error` |

### Example stream

A multi-step process that waits for the job queue to drain between operations
(via `waitForEmptyJobQueue()`) emits `progress`/`notice` while waiting, then the
terminal `result`:

```
{"type":"progress","jobs":3,"elapsed":2}
{"type":"progress","jobs":0,"elapsed":8,"stable":1,"stable_target":3}
{"type":"notice","message":"API unavailable, retrying","wait":10,"elapsed":12}
{"type":"progress","jobs":0,"elapsed":24,"stable":3,"stable_target":3}
{"type":"result","success":true,"database_id":42,"database_name":"c1_shop"}
```

A synchronous command (a `get`) emits only the terminal line:

```
{"type":"result","success":true,"data":[{"database_id":"42","database_name":"c1_shop"}]}
```

> The per-command **Output** examples further down show the *payload* of the
> `result` event for brevity. On the wire each is a single line wrapped as
> `{"type":"result", …}`.

### Consuming the stream

Show `progress`/`notice` to the user live (this is how you convey "what's
happening and how long it's taking"), and act on the final `result`:

```bash
# bash: render progress to stderr, keep the last result line for the caller
result=""
while IFS= read -r line; do
  type=$(jq -r '.type // empty' <<<"$line" 2>/dev/null) || continue
  case "$type" in
    progress) jq -r '"  jobs=\(.jobs) elapsed=\(.elapsed)s"' <<<"$line" >&2 ;;
    notice)   jq -r '"  note: \(.message)"'                  <<<"$line" >&2 ;;
    result)   result="$line" ;;
  esac
done < <(./sites_database_add.php --domain_id=5 --database_name=c1_shop --database_user_id=7)

echo "$result" | jq .          # the final outcome, clean JSON
```

```php
// PHP: same idea, calling the CLI (or the library functions) directly
$last = null;
foreach (explode("\n", trim($output)) as $line) {
    $ev = json_decode($line, true);
    if (!is_array($ev) || !isset($ev['type'])) continue;   // skip noise
    if ($ev['type'] === 'progress') renderProgress($ev);   // your UI
    if ($ev['type'] === 'result')   $last = $ev;           // keep the outcome
}
// $last is the terminal result event
```

When calling the library functions in-process, the same events are produced by
`emitEvent()` / `emitResult()` in `soap_functions.php`.

**Where `progress`/`notice` come from.** A plain command (an `add`, `edit`, `get`,
`delete`) is synchronous: it performs its one API call and emits only the terminal
`result` line — no `progress`. The `progress`/`notice` stream appears only when a
process explicitly waits on the ISPConfig job queue via `waitForEmptyJobQueue()`.

`waitForEmptyJobQueue()` does two things at once:

- **Waits** — it blocks (a barrier) until the job queue has drained and stayed
  empty, so a later step doesn't start before ISPConfig has finished provisioning
  the previous one.
- **Reports** — while blocking it emits `progress`/`notice` events so the consumer
  can render "what's happening and how long it's taking".

Use it when building **complex, multi-step processes** — where one operation must
be fully applied on the server before the next begins. It **returns** its summary
array; the orchestrating script emits the single terminal `result` (via
`emitResult()`). The standalone CLI `add`/`edit` commands here do **not** call it —
check the job queue yourself afterwards with `./get_jobqueue_count.php` if you need
to confirm provisioning finished.

### Exit codes

- `0` — the command produced a `result` line. Check its `success` field for the
  operational outcome (a `get` that found nothing still exits `0` with
  `success:true`).
- `1` — a hard failure before/around producing a normal result: missing/invalid
  arguments or an uncaught exception. These still emit a valid
  `{"type":"result","success":false,"error":"…"}` line (via `failResult()`), so
  the stream is valid NDJSON even on failure.

### `--help`

`--help` also emits a single `result` event whose `defaults` field holds the
effective `--data` JSON — the script's defaults merged over ISPConfig's live form
definition (read live so it stays correct across updates). Pretty-print it with
`jq`:

```bash
./sites_database_add.php --help | jq .defaults
```

Any field shown can be overridden with `--data='<json>'`.

---

## System & API Information

### get_function_list.php

Lists all available SOAP API functions from ISPConfig.

**Usage:**
```bash
# Get categorized list of functions (default)
./get_function_list.php --cat

# Get simple list without categories
./get_function_list.php
```

**Output:**
```json
{
  "success": true,
  "total_count": 150,
  "category_counts": {
    "client": 15,
    "dns": 40,
    "mail": 35,
    "sites": 45,
    "monitor": 10,
    "server": 5
  },
  "categories": {
    "client": ["client_add", "client_get", ...],
    "dns": ["dns_zone_add", "dns_a_add", ...]
  }
}
```

---

### get_jobqueue_count.php

Returns the number of pending jobs in the ISPConfig job queue.

**Usage:**
```bash
./get_jobqueue_count.php
```

**Output:**
```json
{
  "success": true,
  "jobqueue_count": 5,
  "server_id": 1
}
```

**Use Case:** Monitor job queue status to ensure tasks are being processed.

---

## Client Management

### client_get.php

Retrieves detailed information about a specific ISPConfig client.

**Usage:**
```bash
./client_get.php --id=<client_id>
```

**Example:**
```bash
./client_get.php --id=3
```

**Output:**
```json
{
  "success": true,
  "data": {
    "client_id": 3,
    "company_name": "Example Company",
    "contact_name": "John Doe",
    "username": "client123",
    "email": "client@example.com",
    ...
  }
}
```

**Required Parameters:**
- `--id`: Client ID (integer)

---

### client_get_all.php

Retrieves information about all ISPConfig clients.

**Usage:**
```bash
./client_get_all.php
```

**Output:**
```json
{
  "success": true,
  "count": 5,
  "clients": [
    {
      "client_id": 1,
      "company_name": "Company 1",
      ...
    },
    {
      "client_id": 2,
      "company_name": "Company 2",
      ...
    }
  ]
}
```

---

## Web Domain Management

### sites_web_domain_add.php

Creates a new web domain (website) in ISPConfig.

**Usage:**
```bash
./sites_web_domain_add.php --domain=<domain.tld> [--data='<json>']
```

**Examples:**
```bash
# Minimal
./sites_web_domain_add.php --domain=example.com

# With SSL (Let's Encrypt) and a directive snippet, in one command
./sites_web_domain_add.php --domain=example.com \
  --data='{"ssl_letsencrypt":"y","directive_snippets_id":2}'
```

**Output:**
```json
{
  "success": true,
  "domain_id": 42,
  "domain": "example.com",
  "directive_snippet": {
    "success": true,
    "domain_id": 42,
    "directive_snippets_id": 2,
    "name": "example nginx config"
  }
}
```

**Required Parameters:**
- `--domain`: Domain name (e.g., example.com)

**Default Configuration:**
- PHP-FPM enabled
- HTTPS port: 443, HTTP port: 80
- Subdomain: www
- Backups disabled (`backup_interval: none`) — enable via `--data='{"backup_interval":"daily"}'`
- Traffic/disk quota: unlimited (-1)

Run `--help` to see the full effective default `--data` JSON. Any field can be
overridden via `--data` (see [`--help`](#--help)).

---

### sites_web_domain_get.php

Retrieves detailed information about a specific web domain by domain name.

**Usage:**
```bash
./sites_web_domain_get.php --domain_name=<domain.tld>
```

**Example:**
```bash
./sites_web_domain_get.php --domain_name=example.com
```

**Output:**
```json
{
  "success": true,
  "data": {
    "domain_id": 42,
    "domain": "example.com",
    "document_root": "/var/www/clients/client1/web42",
    "active": "y",
    "php": "php-fpm",
    "ssl": "y",
    ...
  }
}
```

**Required Parameters:**
- `--domain_name`: Domain name to look up

---

### sites_web_domain_edit.php

Updates an existing web domain's configuration.

**Usage:**
```bash
./sites_web_domain_edit.php --id=<domain_id> --data='{"field": "value"}'
```

**Examples:**
```bash
# Update plain fields
./sites_web_domain_edit.php --id=42 --data='{"php":"php-fpm","rewrite_to_https":"y"}'

# Enable SSL (Let's Encrypt) — turns on the full SSL setup
./sites_web_domain_edit.php --id=42 --data='{"ssl_letsencrypt":"y"}'

# Assign / remove a directive snippet (0 = remove)
./sites_web_domain_edit.php --id=42 --data='{"directive_snippets_id":2}'
```

**Output:**
```json
{
  "success": true,
  "affected_rows": 1,
  "domain_id": 42
}
```

**Required Parameters:**
- `--id`: Domain ID (integer)
- `--data`: JSON string with fields to update

Run `--help` to see the available fields and their defaults.

---

### sites_web_domain_get_all.php

Retrieves all web domains.

**Usage:**
```bash
./sites_web_domain_get_all.php
```

**Output:**
```json
{
  "success": true,
  "count": 10,
  "domains": [
    {
      "domain_id": 1,
      "domain": "example.com",
      "active": "y",
      ...
    },
    {
      "domain_id": 2,
      "domain": "test.com",
      "active": "y",
      ...
    }
  ]
}
```

---

### sites_web_domain_delete.php

Deletes a web domain from ISPConfig.

**Usage:**
```bash
./sites_web_domain_delete.php --id=<domain_id>
```

**Example:**
```bash
./sites_web_domain_delete.php --id=42
```

**Output:**
```json
{
  "success": true,
  "affected_rows": 1,
  "domain_id": 42
}
```

**Required Parameters:**
- `--id`: Domain ID (integer)

**Warning:** This is a destructive operation. Make sure you have the correct domain ID.

---

### sites_web_domain_disk_usage.php

Retrieves disk usage for a web domain by measuring its document root with `du`.

**Usage:**
```bash
./sites_web_domain_disk_usage.php --domain_name=example.com
./sites_web_domain_disk_usage.php --domain_id=5
```

**Output:**
```json
{
    "success": true,
    "domain_id": 5,
    "domain": "example.com",
    "document_root": "/var/www/clients/client1/web5",
    "hd_quota_mb": "unlimited",
    "hd_used_mb": 640.01,
    "source": "du"
}
```

**Note:** Must be run on the server where the domain is hosted.

---

### sites_web_domain_database_usage.php

Retrieves total database size for all databases linked to a web domain. Gets actual size from `information_schema`.

**Usage:**
```bash
./sites_web_domain_database_usage.php --domain_name=example.com
./sites_web_domain_database_usage.php --domain_id=5
```

**Output:**
```json
{
    "success": true,
    "domain_id": 5,
    "domain": "example.com",
    "count": 1,
    "total_used_mb": 5.45,
    "databases": [
        {
            "database_id": "15",
            "database_name": "c1mydb",
            "quota_mb": "unlimited",
            "used_mb": 5.45
        }
    ]
}
```

**Note:** Must be run on the server where MySQL is hosted (uses `mysql` CLI).

---

## Database Management

### sites_database_add.php

Creates a new MySQL database in ISPConfig.

**Usage:**
```bash
./sites_database_add.php --database_name=<name> --database_user_id=<id> --domain_id=<id> [--data='<json>']
```

**Example:**
```bash
./sites_database_add.php \
  --database_name=mydb \
  --database_user_id=5 \
  --domain_id=42
```

**Output:**
```json
{
  "success": true,
  "database_id": 15,
  "database_name": "mydb",
  "database_user_id": 5
}
```

**Required Parameters:**
- `--database_name`: Name of the database to create
- `--database_user_id`: ID of the database user that will own this database
- `--domain_id`: Website/domain ID this database belongs to (used as parent_domain_id)

**Default Configuration:**
- Database type: MySQL
- Daily backups, 2 copies
- Active: yes
- Quota: unlimited (-1)

---

### sites_database_edit.php

Updates an existing database's configuration.

**Usage:**
```bash
./sites_database_edit.php --id=<database_id> --data='{"field": "value"}'
```

**Example:**
```bash
./sites_database_edit.php --id=15 --data='{"backup_interval":"weekly","backup_copies":3}'
```

**Output:**
```json
{
  "success": true,
  "affected_rows": 1,
  "database_id": 15
}
```

**Required Parameters:**
- `--id`: Database ID (integer)
- `--data`: JSON string with fields to update

---

### sites_database_get.php

Retrieves information about a specific database by ID.

**Usage:**
```bash
./sites_database_get.php --id=<database_id>
```

**Example:**
```bash
./sites_database_get.php --id=15
```

**Output:**
```json
{
  "success": true,
  "data": {
    "database_id": 15,
    "database_name": "c1mydb",
    "database_user_id": 5,
    "database_quota": -1,
    "active": "y",
    ...
  }
}
```

**Required Parameters:**
- `--id`: Database ID (integer)

---

### sites_database_get_all.php

Retrieves information about all databases in the system.

**Usage:**
```bash
./sites_database_get_all.php
```

**Output:**
```json
{
  "success": true,
  "count": 25,
  "databases": {
    "1": {
      "database_id": 1,
      "database_name": "db1",
      ...
    },
    "2": {
      "database_id": 2,
      "database_name": "db2",
      ...
    }
  }
}
```

---

## Database User Management

### sites_database_user_add.php

Creates a new database user in ISPConfig.

**Usage:**
```bash
./sites_database_user_add.php --user=<username> --password=<password> [--data='<json>']
```

**Example:**
```bash
./sites_database_user_add.php --user=ExampleUser --password=ExamplePass123
```

**Output:**
```json
{
  "success": true,
  "database_user_id": 8,
  "database_user": "c1exampleuser",
  "server_id": 1
}
```

**Required Parameters:**
- `--user`: Database username
- `--password`: Database password

**Note:** ISPConfig will prefix the username with the client identifier (e.g., c1_).

---

### sites_database_user_get.php

Retrieves information about a specific database user.

**Usage:**
```bash
./sites_database_user_get.php --id=<user_id>
```

**Example:**
```bash
./sites_database_user_get.php --id=8
```

**Output:**
```json
{
  "success": true,
  "user": {
    "database_user_id": 8,
    "database_user": "c1exampleuser",
    "server_id": 1,
    ...
  }
}
```

**Required Parameters:**
- `--id`: Database user ID (integer)

---

### sites_database_user_get_all.php

Retrieves information about all database users.

**Usage:**
```bash
./sites_database_user_get_all.php
```

**Output:**
```json
{
  "success": true,
  "count": 12,
  "users": {
    "1": {
      "database_user_id": 1,
      "database_user": "c1user1",
      ...
    },
    "2": {
      "database_user_id": 2,
      "database_user": "c1user2",
      ...
    }
  }
}
```

---

## Cron Management

### sites_cron_add.php

Creates a new cron job attached to a web domain.

**Usage:**
```bash
./sites_cron_add.php --domain_id=<id> --command=<str> [--data='<json>']
```

**Example:**
```bash
# Shell command, daily at 02:00 (ISPConfig placeholders are expanded on the server)
./sites_cron_add.php --domain_id=42 \
  --command="cd {DOCROOT_CLIENT}/../private/bin/; {SITE_PHP} cron.php" \
  --data='{"run_min":"0","run_hour":"2"}'

# URL cron (wget-style) every minute
./sites_cron_add.php --domain_id=42 \
  --command="http://example.com/cron" \
  --data='{"type":"url"}'
```

**Output:**
```json
{
  "success": true,
  "cron_id": 7,
  "command": "cd {DOCROOT_CLIENT}/../private/bin/; {SITE_PHP} cron.php"
}
```

**Required Parameters:**
- `--domain_id`: Web domain ID the cron belongs to (used as parent_domain_id)
- `--command`: Command to run (a shell command, or an http(s):// URL for `type=url`)

**The `type` field:** unlike the web UI — which derives it automatically and hides
it — the SOAP API does not, so you set it yourself. It controls how the command runs:

| type       | how it runs                                          | command      |
|------------|------------------------------------------------------|--------------|
| `full`     | shell command as the web user, no chroot (default)   | shell        |
| `chrooted` | shell command inside the site's jailkit chroot       | shell        |
| `url`      | ISPConfig fetches the URL (wget-style)               | http(s):// URL |

Override it via `--data='{"type":"url"}'`.

**Default Configuration:**
- Type: full
- Schedule: every minute (`* * * * *`) — override the `run_min`/`run_hour`/`run_mday`/`run_month`/`run_wday` fields via `--data`
- Active: yes

---

### sites_cron_get.php

Retrieves information about a specific cron job by ID.

**Usage:**
```bash
./sites_cron_get.php --id=<cron_id>
```

**Example:**
```bash
./sites_cron_get.php --id=7
```

**Output:**
```json
{
  "success": true,
  "data": {
    "id": 7,
    "parent_domain_id": 42,
    "type": "full",
    "command": "cd {DOCROOT_CLIENT}/../private/bin/; {SITE_PHP} cron.php",
    "run_min": "0",
    "run_hour": "2",
    "run_mday": "*",
    "run_month": "*",
    "run_wday": "*",
    "active": "y",
    ...
  }
}
```

**Required Parameters:**
- `--id`: Cron ID (integer)

---

### sites_cron_get_all.php

Retrieves information about all cron jobs in the system.

**Usage:**
```bash
./sites_cron_get_all.php
```

**Output:**
```json
{
  "success": true,
  "count": 3,
  "crons": {
    "1": {
      "id": 1,
      "parent_domain_id": 26,
      "type": "full",
      ...
    },
    "2": {
      "id": 2,
      "parent_domain_id": 42,
      "type": "url",
      ...
    }
  }
}
```

---

## Web Server Config

### directive_snippets_get_all.php

Lists all directive snippets (Apache / nginx / PHP config templates) defined in
ISPConfig. Web domains reference a snippet through their `directive_snippets_id`
field. There is no SOAP API function for directive snippets, so this reads them
directly from the local ISPConfig database via the `mysql` CLI.

**Usage:**
```bash
./directive_snippets_get_all.php
```

**Output:**
```json
{
  "success": true,
  "count": 2,
  "snippets": [
    {
      "directive_snippets_id": 1,
      "name": "example php config",
      "type": "php",
      "active": "y"
    },
    {
      "directive_snippets_id": 2,
      "name": "example nginx config",
      "type": "nginx",
      "active": "y"
    }
  ]
}
```

**Note:** Must be run on the server where the ISPConfig database is hosted (uses the `mysql` CLI).

---

### sites_web_domain_directive_snippet_set.php

Assigns a directive snippet (nginx / Apache / PHP template) to a web domain.

`directive_snippets_id` is a UI-plugin field that the SOAP API cannot set (it is
silently ignored on both add and update). This script uses ISPConfig's own
interface database layer (`datalogUpdate`) to update the column and queue the
vhost rebuild — exactly what the control panel does when an admin picks a snippet
and saves the website. The snippet must be active, customer-viewable and match the
server's web type (e.g. an `nginx` snippet on an nginx server).

**Usage:**
```bash
./sites_web_domain_directive_snippet_set.php --domain_id=42 --snippet_id=2
./sites_web_domain_directive_snippet_set.php --domain_id=42 --snippet_name="example nginx config"
```

**Output:**
```json
{
  "success": true,
  "domain_id": 42,
  "directive_snippets_id": 2,
  "name": "example nginx config"
}
```

**Required Parameters:**
- `--domain_id`: Web domain ID
- `--snippet_id` or `--snippet_name`: Which snippet to assign (`--snippet_id=0` removes it)

**Note:** Must be run on the ISPConfig server (uses the local interface library and database).

**Tip:** `sites_web_domain_add.php` and `sites_web_domain_edit.php` can also assign
a snippet inline via `--data='{"directive_snippets_id":<id>}'` (they call the same
logic), so a separate command is usually not needed.

---

## Common Workflows

### Creating a Complete Website with Database

```bash
# 1. Add the web domain (optionally with SSL + a directive snippet in one go)
./sites_web_domain_add.php --domain=newsite.com \
  --data='{"ssl_letsencrypt":"y","directive_snippets_id":2}'
# Output: {"success":true,"domain_id":42,"domain":"newsite.com",...}
# Save the domain_id (42) for later steps

# 2. Create a database user
./sites_database_user_add.php --user=dbuser --password=SecurePass123
# Output: {"success":true,"database_user_id":10,...}
# Save the database_user_id (10) for the next step

# 3. Create the database and attach it to the domain
# This also attaches the database user to the database
./sites_database_add.php \
  --database_name=sitedb \
  --database_user_id=10 \
  --domain_id=42

# 4. Monitor job queue until processing completes
./get_jobqueue_count.php
```

**Note:** Step 3 performs multiple operations:
- Creates the database
- Attaches the database to the web domain (via `--domain_id`)
- Grants the database user access to the database (via `--database_user_id`)

### Viewing All Resources

```bash
# List all clients
./client_get_all.php

# List all web domains
./sites_web_domain_get_all.php

# List all databases
./sites_database_get_all.php

# List all database users
./sites_database_user_get_all.php
```

### Cleanup/Deletion

```bash
# Get domain information first
./sites_web_domain_get.php --domain_name=example.com

# Delete the domain using its ID
./sites_web_domain_delete.php --id=42

# Check job queue
./get_jobqueue_count.php
```

---

## Technical Details

### Architecture

- **soap_env.php**: Environment configuration and variable initialization
- **soap_functions.php**: Core SOAP client library with all API wrapper functions
- **ispconfig_interface.php**: Bootstraps ISPConfig's interface library for the few
  operations the SOAP API cannot do (directive snippets, reading live form defaults
  for `--help`); required only by scripts that need it, and only on the ISPConfig server
- **CLI scripts**: Individual command-line tools that use the function library

### SSL Configuration

The SOAP client is configured to allow self-signed certificates:
```php
'verify_peer' => false,
'verify_peer_name' => false,
'allow_self_signed' => true
```

This is suitable for development/testing but should be reviewed for production use.

---

## Support

For ISPConfig API documentation, see the official ISPConfig Remote API documentation:
- `remoting_client/API-docs/index.html`
- ISPConfig Manual: https://www.ispconfig.org/documentation/

For issues with these CLI tools, check:
1. `.env` file is configured correctly
2. ISPConfig remote API is enabled
3. User has appropriate permissions
4. SOAP PHP extension is installed
