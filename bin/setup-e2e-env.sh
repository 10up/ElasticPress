#!/bin/bash

# cat ./bin/2022-02-15-12-49.sql | ./bin/wp-env-cli tests-wordpress "wp --allow-root db import -"

ACF_PRO_LICENSE_KEY=""
DISPLAY_HELP=0
EP_HOST=""
EP_CREDENTIALS=""
EP_INDEX_PREFIX=""
WP_VERSION=""
WC_VERSION=""

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
	echo "-h|--help                 Display this help screen"
	exit
fi

if [ -z $WC_VERSION ]; then
	./bin/wp-env-cli tests-wordpress "wp --allow-root plugin install woocommerce --activate"
	# Latest WooCommerce enables HPOS by default. ElasticPress does not
	# integrate the HPOS orders list, so e2e tests that use
	# edit.php?post_type=shop_order and #post-search-input need the posts
	# datastore. Ignore failure on older WooCommerce that lacks this command.
	./bin/wp-env-cli tests-wordpress "wp --allow-root wc hpos disable" || true
else
	./bin/wp-env-cli tests-wordpress "wp --allow-root plugin install woocommerce --activate --version=${WC_VERSION}"
fi

# Set twentytwentyone as the active theme here, as 2025 won't work with WP 6.2
./bin/wp-env-cli tests-wordpress "wp --allow-root theme activate twentytwentyone"

# Fix the debug-bar-elasticpress dependency of ElasticPress
./bin/wp-env-cli tests-wordpress "wp --allow-root plugin install debug-bar-elasticpress"
./bin/wp-env-cli tests-wordpress "sed -i \"s/Requires Plugins:  elasticpress/Requires Plugins:  $PLUGIN_NAME/\" /var/www/html/wp-content/plugins/debug-bar-elasticpress/debug-bar-elasticpress.php"
./bin/wp-env-cli tests-wordpress "wp --allow-root plugin activate debug-bar-elasticpress"

if [ ! -z $WP_VERSION ]; then
	./bin/wp-env-cli tests-wordpress "wp --allow-root core update --version=${WP_VERSION} --force"
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
./bin/wp-env-cli tests-wordpress "wp --allow-root config set EP_HOST ${EP_HOST}"

if [ ! -z $EP_CREDENTIALS ]; then
	./bin/wp-env-cli tests-wordpress "wp --allow-root config set EP_CREDENTIALS ${EP_CREDENTIALS}"
fi

if [ ! -z $EP_INDEX_PREFIX ]; then
	./bin/wp-env-cli tests-wordpress "wp --allow-root config set EP_INDEX_PREFIX ${EP_INDEX_PREFIX}"
fi

if [ ! -z $ACF_PRO_LICENSE_KEY ]; then
	./bin/wp-env-cli tests-wordpress "composer --working-dir=./wp-content config http-basic.connect.advancedcustomfields.com ${ACF_PRO_LICENSE_KEY} https://elasticpress.test"
	MAX_RETRIES=3
	RETRY_COUNT=0
	while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
		if ./bin/wp-env-cli tests-wordpress "composer --working-dir=./wp-content install"; then
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
	./bin/wp-env-cli tests-wordpress "rm wp-content/auth.json"
	./bin/wp-env-cli tests-wordpress "wp --allow-root plugin activate advanced-custom-fields-pro"
	./bin/wp-env-cli tests-wordpress "wp --allow-root config set ACF_PRO_LICENSE ${ACF_PRO_LICENSE_KEY}"
fi

./bin/wp-env-cli tests-wordpress "wp --allow-root core multisite-convert"

SITES_COUNT=$(./bin/wp-env-cli tests-wordpress "wp --allow-root site list --format=count")
echo "SITES_COUNT: $SITES_COUNT"
if [ $SITES_COUNT -eq 1 ]; then
	./bin/wp-env-cli tests-wordpress "wp --allow-root site create --slug=second-site --title='Second Site'"
	./bin/wp-env-cli tests-wordpress "wp --allow-root search-replace localhost/ localhost:8889/ --all-tables"
fi

# Not sure why, wp-env makes it http://localhost:8889/:8889 (not related to the command above)
./bin/wp-env-cli tests-wordpress "wp --allow-root option set home 'http://localhost:8889'"
./bin/wp-env-cli tests-wordpress "wp --allow-root option set siteurl 'http://localhost:8889'"

./bin/wp-env-cli tests-wordpress "wp --allow-root plugin activate wordpress-importer"
./bin/wp-env-cli tests-wordpress "wp --allow-root import /var/www/html/wp-content/uploads/content-example.xml --authors=create"

./bin/wp-env-cli tests-wordpress "wp --allow-root plugin deactivate woocommerce elasticpress-proxy"

./bin/wp-env-cli tests-wordpress "wp --allow-root plugin activate debug-bar debug-bar-elasticpress wordpress-importer --network"

./bin/wp-env-cli tests-wordpress "wp --allow-root plugin activate ${PLUGIN_NAME}"

./bin/wp-env-cli tests-wordpress "wp --allow-root elasticpress sync --setup --yes --show-errors"

./bin/wp-env-cli tests-wordpress "wp --allow-root option set posts_per_page 5"
./bin/wp-env-cli tests-wordpress "wp --allow-root user meta update admin edit_post_per_page 5"
./bin/wp-env-cli tests-wordpress "wp --allow-root user update admin --user_pass=password"

# Generate a SQL file that can be imported later to make things faster
# SQL_FILENAME=./bin/$(date +'%F-%H-%M').sql
# npm --silent run env run tests-cli "wp db export -" > $SQL_FILENAME