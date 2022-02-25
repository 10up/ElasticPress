#!/bin/bash

# cat ./bin/2022-02-15-12-49.sql | npm run env run tests-cli "wp db import -"

EP_HOST=""
ES_SHIELD=""
EP_INDEX_PREFIX=""
DISPLAY_HELP=0

for opt in "$@"; do
	case $opt in
    -h=*|--ep-host=*)
      EP_HOST="${opt#*=}"
      ;;
    -s=*|--es-shield=*)
      ES_SHIELD="${opt#*=}"
      ;;
    -u=*|--ep-index-prefix=*)
      EP_INDEX_PREFIX="${opt#*=}"
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
	echo "-h=*, --ep-host=*             The remote Elasticsearch Host URL."
	echo "-s=*, --es-shield=*           The Elasticsearch credentials, used in the ES_SHIELD constant."
	echo "-u=*, --ep-index-prefix=*     The Elasticsearch credentials, used in the EP_INDEX_PREFIX constant."
	echo "-h|--help                     Display this help screen"
	exit
fi

if [ -z $EP_HOST ]; then
	# Determine what kind of env we're in
	if [ "$(uname | tr '[:upper:]' '[:lower:]')" = "darwin" ]; then
		echo "Running tests on $(uname)"
		EP_HOST="http://host.docker.internal:8890/"
	else
		echo "Running tests on $(uname)"
		# 172.17.0.1 is the IP Address of host when using Linux
		EP_HOST="http://172.17.0.1:8890/"
	fi
fi
npm run env run tests-cli "wp config set EP_HOST ${EP_HOST}"

if [ ! -z $ES_SHIELD ]; then
	npm run env run tests-cli "wp config set ES_SHIELD ${ES_SHIELD}"
fi

if [ ! -z $EP_INDEX_PREFIX ]; then
	npm run env run tests-cli "wp config set EP_INDEX_PREFIX ${EP_INDEX_PREFIX}"
fi

npm run env run tests-cli "wp core multisite-convert"

# Not sure why, wp-env makes it http://localhost:8889/:8889
npm run env run tests-cli "option set home 'http://localhost:8889'"
npm run env run tests-cli "option set siteurl 'http://localhost:8889'"

npm run env run tests-cli "wp user create wpsnapshots wpsnapshots@example.test --role=administrator --user_pass=password"
npm run env run tests-cli "wp super-admin add wpsnapshots"

npm run env run tests-cli "wp theme enable twentytwentyone --network --activate"

npm run env run tests-cli "wp import /var/www/html/wp-content/uploads/content-example.xml --authors=create"

npm run env run tests-cli "wp plugin deactivate woocommerce"

npm run env run tests-cli "wp plugin activate debug-bar debug-bar-elasticpress wordpress-importer --network"

npm run env run tests-cli "wp plugin activate ${PLUGIN_NAME}"

npm run env run tests-cli "wp elasticpress index --setup --yes --show-errors"

npm run env run tests-cli "wp option set posts_per_page 5"
npm run env run tests-cli "wp user meta update wpsnapshots edit_post_per_page 5"

# Generate a SQL file that can be imported later to make things faster
SQL_FILENAME=./bin/$(date +'%F-%H-%M').sql
npm --silent run env run tests-cli "wp db export -" > $SQL_FILENAME
