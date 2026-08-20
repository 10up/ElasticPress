#!/bin/bash

ACF_PRO_LICENSE_KEY=""
DISPLAY_HELP=0
EP_HOST=""
EP_CREDENTIALS=""
EP_INDEX_PREFIX=""
WP_VERSION=""
WC_VERSION=""
USE_DB_CACHE=1

for opt in "$@"; do
	case $opt in
    --acf-pro-license=*)
      ACF_PRO_LICENSE_KEY="${opt#*=}"
      ;;
    -H=*|--ep-host=*)
      EP_HOST="${opt#*=}"
      ;;
    -C=*|--ep-credentials=*)
      EP_CREDENTIALS="${opt#*=}"
      ;;
    -p=*|--ep-index-prefix=*)
      EP_INDEX_PREFIX="${opt#*=}"
      ;;
    -wp=*|--wp-version=*)
      WP_VERSION="${opt#*=}"
      ;;
    -wc=*|--wc-version=*)
      WC_VERSION="${opt#*=}"
      ;;
    --no-db-cache)
      USE_DB_CACHE=0
      ;;
    -h|--help|*)
      DISPLAY_HELP=1
      ;;
	esac
done

PLUGIN_NAME=$(basename "$PWD")

if [ $DISPLAY_HELP -eq 1 ]; then
	echo "This script will setup the environment for the Cypress tests"
	echo "Usage: ${0##*/} [OPTIONS...]"
	echo
	echo "Optional parameters:"
	echo "--acf-pro-license=*       ACF Pro License Key."
	echo "-H=*, --ep-host=*         The remote Elasticsearch Host URL."
	echo "-C=*, --ep-credentials=*       The Elasticsearch credentials, used in the EP_CREDENTIALS constant."
	echo "-p=*, --ep-index-prefix=* The Elasticsearch credentials, used in the EP_INDEX_PREFIX constant."
	echo "-W=*, --wp-version=*      WordPress Core version."
	echo "-w=*, --wc-version=*      WooCommerce version."
	echo "--no-db-cache             Rebuild the database from scratch, ignoring any cached SQL file."
	echo "-h|--help                 Display this help screen"
	exit
fi

CONTENT_FIXTURE="./tests/e2e/src/wordpress-files/test-docs/content-example.xml"

# The cached database is only valid for the inputs that shaped it. The hash of
# this script and the imported content means editing either one produces a new
# cache file rather than silently reusing a stale database.
if command -v sha256sum > /dev/null; then
	CACHE_HASH=$(cat "$0" "$CONTENT_FIXTURE" | sha256sum | cut -c1-12)
else
	CACHE_HASH=$(cat "$0" "$CONTENT_FIXTURE" | shasum -a 256 | cut -c1-12)
fi

if [ -z "$ACF_PRO_LICENSE_KEY" ]; then
	CACHE_ACF="noacf"
else
	CACHE_ACF="acf"
fi

# Elasticsearch is deliberately left out of the cache key. The host, credentials,
# and index prefix are wp-config.php constants rather than database rows, and the
# sync always runs after the import, so one file serves every backend.
DB_CACHE_DIR="./bin/.cache"
DB_CACHE_FILE="${DB_CACHE_DIR}/db-${WP_VERSION:-latest}-${WC_VERSION:-latest}-${CACHE_ACF}-${CACHE_HASH}.sql"
DB_CACHE_FILE_IN_CONTAINER="/var/www/html/wp-content/plugins/${PLUGIN_NAME}/${DB_CACHE_FILE#./}"

# wp-env-cli prints whatever the container wrote, so a PHP notice or a WP-CLI
# error can precede the value. Only a line that is entirely digits is a count,
# and an unparsable result has to read as empty rather than as a number.
wp_count() {
	./bin/wp-env-cli wordpress "wp --allow-root $1" 2>/dev/null | tr -d '\r' | grep -xE '[0-9]+' | tail -1
}

# `wp-env reset` empties the database but leaves the multisite constants behind
# in wp-config.php. WordPress then declares itself a network with no network
# tables, so no command can bootstrap. This has to run before anything else
# touches the install, or the plugin and theme steps below fail too. Dropping
# the constants turns it back into the single site multisite-convert can convert.
reset_stale_multisite_config() {
	if ./bin/wp-env-cli wordpress "wp --allow-root site list --format=count" > /dev/null 2>&1; then
		return 0
	fi

	if ! ./bin/wp-env-cli wordpress "wp --allow-root config has MULTISITE" > /dev/null 2>&1; then
		return 0
	fi

	echo "The multisite constants are set but the network is missing. Clearing them."

	# WP_ALLOW_MULTISITE is cleared too because multisite-convert writes its own
	# copy. Leaving the old one in place defines it twice, and PHP then prints a
	# warning on every request, which corrupts both page output and the WP-CLI
	# responses this script parses.
	for constant in MULTISITE SUBDOMAIN_INSTALL DOMAIN_CURRENT_SITE PATH_CURRENT_SITE SITE_ID_CURRENT_SITE BLOG_ID_CURRENT_SITE WP_ALLOW_MULTISITE; do
		while ./bin/wp-env-cli wordpress "wp --allow-root config has ${constant}" > /dev/null 2>&1; do
			./bin/wp-env-cli wordpress "wp --allow-root config delete ${constant}" > /dev/null 2>&1 || break
		done
	done
}

# wp-config.php has to describe the database it points at. multisite-convert
# declines to touch an install whose network tables already exist ("The network
# already exists"), so it cannot write the constants describing a network it did
# not create -- an imported cache, or a database left over from a previous run.
# Only the constants are written here; the network itself is already there.
ensure_multisite_config() {
	if ./bin/wp-env-cli wordpress "wp --allow-root site list --format=count" > /dev/null 2>&1; then
		return 0
	fi

	echo "Restoring the multisite constants to match the database."
	./bin/wp-env-cli wordpress "wp --allow-root config set MULTISITE true --raw"
	./bin/wp-env-cli wordpress "wp --allow-root config set SUBDOMAIN_INSTALL false --raw"
	./bin/wp-env-cli wordpress "wp --allow-root config set DOMAIN_CURRENT_SITE localhost:8889"
	./bin/wp-env-cli wordpress "wp --allow-root config set PATH_CURRENT_SITE /"
	./bin/wp-env-cli wordpress "wp --allow-root config set SITE_ID_CURRENT_SITE 1 --raw"
	./bin/wp-env-cli wordpress "wp --allow-root config set BLOG_ID_CURRENT_SITE 1 --raw"
}

# The exported file is only worth keeping if it captures a database the suite
# can actually run against: the imported content, the second site the multisite
# tests index, and an active plugin.
verify_database() {
	local sites
	local posts

	sites=$(wp_count "site list --format=count")
	if [ "$sites" != "2" ]; then
		echo "Expected 2 sites in the network, found '${sites:-none}'."
		return 1
	fi

	posts=$(wp_count "post list --post_type=post --post_status=publish --format=count")
	if [ -z "$posts" ] || [ "$posts" -lt 10 ]; then
		echo "Expected the imported posts, found '${posts:-none}'."
		return 1
	fi

	if ! ./bin/wp-env-cli wordpress "wp --allow-root plugin is-active ${PLUGIN_NAME}" > /dev/null 2>&1; then
		echo "${PLUGIN_NAME} is not active."
		return 1
	fi
}

# Every database change belongs in here, so that a cache hit can skip the lot.
# Anything touching the filesystem or wp-config.php has to run either way.
setup_database() {
	SITES_COUNT=$(wp_count "site list --format=count")
	echo "SITES_COUNT: ${SITES_COUNT:-unreadable}"
	if [ -z "$SITES_COUNT" ]; then
		echo "Could not read the site list, so the network is not usable."
		return 1
	fi
	if [ "$SITES_COUNT" -eq 1 ]; then
		./bin/wp-env-cli wordpress "wp --allow-root site create --slug=second-site --title='Second Site'"
		./bin/wp-env-cli wordpress "wp --allow-root search-replace localhost/ localhost:8889/ --all-tables"
	fi

	# Not sure why, wp-env makes it http://localhost:8889/:8889 (not related to the command above)
	./bin/wp-env-cli wordpress "wp --allow-root option set home 'http://localhost:8889'"
	./bin/wp-env-cli wordpress "wp --allow-root option set siteurl 'http://localhost:8889'"

	./bin/wp-env-cli wordpress "wp --allow-root plugin activate wordpress-importer"
	./bin/wp-env-cli wordpress "wp --allow-root import /var/www/html/wp-content/content-example.xml --authors=create"

	./bin/wp-env-cli wordpress "wp --allow-root plugin deactivate woocommerce elasticpress-proxy"

	./bin/wp-env-cli wordpress "wp --allow-root plugin activate debug-bar debug-bar-elasticpress wordpress-importer --network"

	./bin/wp-env-cli wordpress "wp --allow-root plugin activate ${PLUGIN_NAME}"

	./bin/wp-env-cli wordpress "wp --allow-root option set posts_per_page 5"
	./bin/wp-env-cli wordpress "wp --allow-root user meta update admin edit_post_per_page 5"
	./bin/wp-env-cli wordpress "wp --allow-root user update admin --user_pass=password"
}

reset_stale_multisite_config

if [ -z $WC_VERSION ]; then
	./bin/wp-env-cli wordpress "wp --allow-root plugin install woocommerce --activate"
else
	./bin/wp-env-cli wordpress "wp --allow-root plugin install woocommerce --activate --version=${WC_VERSION}"
fi

# Set twentytwentyone as the active theme here, as 2025 won't work with WP 6.2
./bin/wp-env-cli wordpress "wp --allow-root theme activate twentytwentyone"

# Fix the debug-bar-elasticpress dependency of ElasticPress
./bin/wp-env-cli wordpress "wp --allow-root plugin install debug-bar-elasticpress"
./bin/wp-env-cli wordpress "sed -i \"s/Requires Plugins:  elasticpress/Requires Plugins:  $PLUGIN_NAME/\" /var/www/html/wp-content/plugins/debug-bar-elasticpress/debug-bar-elasticpress.php"
./bin/wp-env-cli wordpress "wp --allow-root plugin activate debug-bar-elasticpress"

if [ ! -z $WP_VERSION ]; then
	./bin/wp-env-cli wordpress "wp --allow-root core update --version=${WP_VERSION} --force"
fi

if [ -z $EP_HOST ]; then
	# Determine what kind of env we're in
	if [ "$(uname | tr '[:upper:]' '[:lower:]')" = "darwin" ]; then
		echo "Running tests on $(uname)"
		EP_HOST="http://host.docker.internal:8890/"
	elif grep -qi microsoft /proc/version; then
		echo "Running tests on Windows"
		EP_HOST="http://host.docker.internal:8890/"
	else
		echo "Running tests on $(uname)"
		# 172.17.0.1 is the IP Address of host when using Linux
		EP_HOST="http://172.17.0.1:8890/"
	fi
fi
./bin/wp-env-cli wordpress "wp --allow-root config set EP_HOST ${EP_HOST}"

if [ ! -z $EP_CREDENTIALS ]; then
	./bin/wp-env-cli wordpress "wp --allow-root config set EP_CREDENTIALS ${EP_CREDENTIALS}"
fi

if [ ! -z $EP_INDEX_PREFIX ]; then
	./bin/wp-env-cli wordpress "wp --allow-root config set EP_INDEX_PREFIX ${EP_INDEX_PREFIX}"
fi

if [ ! -z $ACF_PRO_LICENSE_KEY ]; then
	./bin/wp-env-cli wordpress "composer --working-dir=./wp-content config http-basic.connect.advancedcustomfields.com ${ACF_PRO_LICENSE_KEY} https://elasticpress.test"
	MAX_RETRIES=3
	RETRY_COUNT=0
	while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
		if ./bin/wp-env-cli wordpress "composer --working-dir=./wp-content install"; then
			break
		fi
		RETRY_COUNT=$((RETRY_COUNT + 1))
		if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
			echo "Composer install failed, retrying ($RETRY_COUNT/$MAX_RETRIES)..."
			sleep 1
		fi
	done
	if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
		echo "Composer install failed after $MAX_RETRIES attempts"
		exit 1
	fi
	./bin/wp-env-cli wordpress "rm wp-content/auth.json"
	./bin/wp-env-cli wordpress "wp --allow-root plugin activate advanced-custom-fields-pro"
	./bin/wp-env-cli wordpress "wp --allow-root config set ACF_PRO_LICENSE ${ACF_PRO_LICENSE_KEY}"
fi

./bin/wp-env-cli wordpress "wp --allow-root core multisite-convert"
# Covers the case multisite-convert declines: a database that already holds a
# network, whose constants are missing from wp-config.php.
ensure_multisite_config

# WordPress does not write .htaccess on multisite, so the subdirectory rewrite rules are copied in.
./bin/wp-env-cli wordpress "cp /var/www/html/wp-content/plugins/${PLUGIN_NAME}/tests/e2e/src/wordpress-files/.htaccess /var/www/html/.htaccess"

# The cli container is used for the dump because the wordpress one has no mysql
# client. Writing to the mounted plugin directory keeps the file off stdout,
# which wp-env-cli merges with stderr.
if [ $USE_DB_CACHE -eq 1 ] && [ -f "$DB_CACHE_FILE" ]; then
	echo "Importing the database from ${DB_CACHE_FILE}"
	./bin/wp-env-cli cli "wp --allow-root db import ${DB_CACHE_FILE_IN_CONTAINER}"
	ensure_multisite_config

	if ! verify_database; then
		echo "The cached database is not usable. Delete ${DB_CACHE_FILE} and run again."
		exit 1
	fi
else
	if ! setup_database; then
		echo "Setting up the database failed."
		exit 1
	fi

	# A partial setup used to be exported anyway, and every later run imported
	# that broken state as though it were a valid cache.
	if ! verify_database; then
		echo "The database is not in the expected state. Not caching it."
		exit 1
	fi

	# Written to a temporary name first, so an interrupted export cannot leave a
	# truncated file behind that later runs would treat as a usable cache.
	echo "Exporting the database to ${DB_CACHE_FILE}"
	mkdir -p "$DB_CACHE_DIR"
	if ./bin/wp-env-cli cli "wp --allow-root db export ${DB_CACHE_FILE_IN_CONTAINER}.tmp"; then
		mv "${DB_CACHE_FILE}.tmp" "$DB_CACHE_FILE"
	else
		echo "Could not export the database. Continuing without a cache file."
		rm -f "${DB_CACHE_FILE}.tmp"
	fi
fi

# Runs after the database is in place either way, as the index is never cached.
./bin/wp-env-cli wordpress "wp --allow-root elasticpress sync --setup --yes --show-errors"