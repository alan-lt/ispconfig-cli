# ISPConfig CLI Tools Documentation

Command-line interface tools for managing ISPConfig via SOAP API.

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

All commands return JSON-formatted output with the following structure:
- `success`: Boolean indicating if the operation succeeded
- `data` or specific result fields: The actual response data
- `error`: Error message (only present if success is false)

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
./sites_web_domain_add.php --domain=<domain.tld>
```

**Example:**
```bash
./sites_web_domain_add.php --domain=example.com
```

**Output:**
```json
{
  "success": true,
  "domain_id": 42,
  "domain": "example.com"
}
```

**Required Parameters:**
- `--domain`: Domain name (e.g., example.com)

**Default Configuration:**
- PHP-FPM enabled
- HTTPS port: 443, HTTP port: 80
- Subdomain: www
- Daily backups, 2 copies
- Traffic/disk quota: unlimited (-1)

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

### sites_web_domain_get_all.php

Retrieves all web domains for the current system user.

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

## Database Management

### sites_database_add.php

Creates a new MySQL database in ISPConfig.

**Usage:**
```bash
./sites_database_add.php --database_name=<name> --database_user_id=<id> --domain_id=<id>
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
./sites_database_user_add.php --user=<username> --password=<password>
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

## Common Workflows

### Creating a Complete Website with Database

```bash
# 1. Add the web domain
./sites_web_domain_add.php --domain=newsite.com
# Output: {"success":true,"domain_id":42,"domain":"newsite.com"}
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
- **CLI scripts**: Individual command-line tools that use the function library

### Default Configuration Values

From `soap_env.php`:
- `$client_id = 3`: Default client ID
- `$server_id = 1`: Default server ID
- `$sys_userid = 4`: Default system user ID
- `$sys_groupid = 4`: Default system group ID

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
