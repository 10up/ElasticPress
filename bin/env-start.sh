#!/bin/bash

. ./bin/init-nvm.sh

wp-env start && cd bin/es-docker/ && docker-compose up -d
