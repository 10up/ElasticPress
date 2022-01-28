#!/bin/bash

# Determine what kind of env we're in

if [ "$(uname | tr '[:upper:]' '[:lower:]')" = "darwin" ]; then
  echo "Running tests on $(uname)"
  ES_HOST="http://host.docker.internal:8890/"
else
  echo "Running tests on $(uname)"
  ES_HOST="http://172.17.0.1:8890/"
fi
# cat ./bin/2022-01-20-16-39.sql | npm run env run tests-cli "wp db import -"

# 172.17.0.1 is the IP Address of host when using Linux
npm run env run tests-cli "wp config set EP_HOST ${ES_HOST}"

npm run env run tests-cli "wp core multisite-convert"
npm run env run tests-cli "wp user create wpsnapshots wpsnapshots@example.test --role=administrator --user_pass=password"
npm run env run tests-cli "wp super-admin add wpsnapshots"

npm run env run tests-cli "wp import /var/www/html/wp-content/uploads/content-example.xml --authors=create"

npm run env run tests-cli "wp plugin deactivate woocommerce"

npm run env run tests-cli "wp plugin activate debug-bar debug-bar-elasticpress wordpress-importer --network"

npm run env run tests-cli "wp plugin activate elasticpress"

npm run env run tests-cli "wp elasticpress index --setup --yes --show-errors"

npm run env run tests-cli "wp option set posts_per_page 5"
npm run env run tests-cli "wp user meta update wpsnapshots edit_post_per_page 5"

# Generate a SQL file that can be imported later to make things faster
SQL_FILENAME=./bin/$(date +'%F-%H-%M').sql
npm --silent run env run tests-cli "wp db export -" > $SQL_FILENAME
