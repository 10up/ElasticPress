#!/bin/bash


echo "y" | ./node_modules/.bin/wp-env destroy
pushd bin/es-docker/
docker-compose down
popd
rm -f bin/*sql
