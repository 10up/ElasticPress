#!/usr/bin/env bash

echo "Installing WP-CLI in $1"

./bin/wp-env-cli $1 curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
./bin/wp-env-cli $1 chmod +x wp-cli.phar
./bin/wp-env-cli $1 mv wp-cli.phar /usr/local/bin/wp
