#!/bin/bash

# ──────────────────────────────────────────────────────────────
# Sync website files and database from source server to ISPConfig
#
# Requires: jq, rsync, pv, mysqldump, mysql
# Usage:    Uncomment DOMAIN below, then run: ./sync-db-and-files.sh
# ──────────────────────────────────────────────────────────────

# Uncomment and set domain to migrate (commented out to prevent accidental runs)
#DOMAIN='mydomain.com'

# Source server credentials (change these to match your environment)
MYSQL_HOST='10.0.0.123'
MYSQL_USER='mysql_ro_user'
MYSQL_PASS='mysql_ro_pass'

SSH_HOST='10.0.0.123'
SSH_USER='ssh_ro_user'


# ──────────────────────────────────────────
# Resolve domain info from ISPConfig API and config.json
# ──────────────────────────────────────────

SITE_DOMAIN_USER=$(../sites_web_domain_get.php --domain_name="$DOMAIN" | jq -r .system_user)
SITE_DOMAIN_GROUP=$(../sites_web_domain_get.php --domain_name="$DOMAIN" | jq -r .system_group)
SITE_DOMAIN_WEBROOT=$(../sites_web_domain_get.php --domain_name="$DOMAIN" | jq -r .document_root)

SITE_DOMAIN_WEBROOT_FROM=$(jq -r ".[] | select(.website_name==\"$DOMAIN\") | .website_from" ./config.json)


# ──────────────────────────────────────────
# Step 1: Rsync files from source server
# ──────────────────────────────────────────

echo "------------------------------------"
echo "start $DOMAIN rsync"
echo "------------------------------------"

time rsync -avh --no-perms --delete --partial --info=progress2 --info=name0 \
    "$SSH_USER@$SSH_HOST:/home/*/domains/$SITE_DOMAIN_WEBROOT_FROM/public_html/" \
    "$SITE_DOMAIN_WEBROOT/web/"


# ──────────────────────────────────────────
# Step 2: Fix file ownership and permissions
# ──────────────────────────────────────────

echo "------------------------------------"
echo "chown $SITE_DOMAIN_WEBROOT/web"
echo "------------------------------------"

mkdir -p "$SITE_DOMAIN_WEBROOT/web/download/invoices/mpdf"
chown -R "$SITE_DOMAIN_USER:$SITE_DOMAIN_GROUP" "$SITE_DOMAIN_WEBROOT/web"
find "$SITE_DOMAIN_WEBROOT/web/" -type d -exec chmod 0755 {} +
find "$SITE_DOMAIN_WEBROOT/web/" -type f -exec chmod 0644 {} +


# ──────────────────────────────────────────
# Step 3: Dump source database and import into destination
# ──────────────────────────────────────────

# Read source DB name from the synced config.php
MYSQL_SRC_BASE=$(grep DB_DATABASE "$SITE_DOMAIN_WEBROOT/web/config.php" | awk -F "'" '{print $4}')

# Read destination DB credentials from config.json
MYSQL_DST_BASE=$(jq -r ".[] | select(.website_name==\"$DOMAIN\") | .mysql_base" ./config.json)
MYSQL_DST_USER=$(jq -r ".[] | select(.website_name==\"$DOMAIN\") | .mysql_user" ./config.json)
MYSQL_DST_PASS=$(jq -r ".[] | select(.website_name==\"$DOMAIN\") | .mysql_pass" ./config.json)
MYSQL_DST_HOST='localhost'

echo "------------------------------------"
echo "mysqldump $MYSQL_SRC_BASE to $MYSQL_DST_BASE"
echo "------------------------------------"

time mysqldump -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASS" "$MYSQL_SRC_BASE" \
    | pv \
    | mysql -h"$MYSQL_DST_HOST" -u"$MYSQL_DST_USER" -p"$MYSQL_DST_PASS" "$MYSQL_DST_BASE"


# ──────────────────────────────────────────
# Step 4: Update config.php with new paths and DB credentials
# ──────────────────────────────────────────

echo "------------------------------------"
echo "update config.php and admin/config.php"
echo "------------------------------------"

time sed -i "
  s|\(define('DOMAIN_NAME',[[:space:]]*'\)[^']*\(');\)|\1https://$DOMAIN/\2|
  s|\(define('DIR_ROOT',[[:space:]]*'\)[^']*\(');\)|\1/var/www/$DOMAIN/web/\2|
  s|\(define('DIR_SYS_ROOT',[[:space:]]*'\)[^']*\(');\)|\1/usr/share/php/tvs/v5/\2|
  s|\(define('DB_HOSTNAME',[[:space:]]*'\)[^']*\(');\)|\1$MYSQL_DST_HOST\2|
  s|\(define('DB_USERNAME',[[:space:]]*'\)[^']*\(');\)|\1$MYSQL_DST_USER\2|
  s|\(define('DB_PASSWORD',[[:space:]]*'\)[^']*\(');\)|\1$MYSQL_DST_PASS\2|
  s|\(define('DB_DATABASE',[[:space:]]*'\)[^']*\(');\)|\1$MYSQL_DST_BASE\2|
" "$SITE_DOMAIN_WEBROOT/web/config.php" "$SITE_DOMAIN_WEBROOT/web/admin/config.php"

time sed -i "
  s|file_exists('[^']*/config\.php')|file_exists('/var/www/$DOMAIN/web/config.php')|
  s|require_once('[^']*/config\.php')|require_once('/var/www/$DOMAIN/web/config.php')|
" "$SITE_DOMAIN_WEBROOT/web/cli.php"
