#!/bin/bash

INSTALL_ES_DOCKER=0
ES_HOST=""
ES_SHIELD=""
ATTEMPTS=1
DISPLAY_HELP=0

for opt in "$@"; do
	case $opt in
    -l|--install-local-es*)
      INSTALL_ES_DOCKER=1
      ;;
    -h=*|--ep-host=*)
      EP_HOST="${opt#*=}"
      ;;
    -s=*|--es-shield=*)
      ES_SHIELD="${opt#*=}"
      ;;
    -u=*|--ep-index-prefix=*)
      EP_INDEX_PREFIX="${opt#*=}"
      ;;
    -t=*|--attempts=*)
      ATTEMPTS="${opt#*=}"
      ;;
    -h|--help|*)
      DISPLAY_HELP=1
      ;;
	esac
done

if [ $DISPLAY_HELP -eq 1 ]; then
	echo "This script will run the WP Acceptance Tests"
	echo "Usage: ${0##*/} [OPTIONS...]"
	echo
	echo "Optional parameters:"
  echo "-l|--install-local-es         Setup a Elasticsearch container locally."
	echo "-h=*, --ep-host=*             The remote Elasticsearch Host URL."
	echo "-s=*, --es-shield=*           The Elasticsearch credentials, used in the ES_SHIELD constant."
	echo "-u=*, --ep-index-prefix=*     The Elasticsearch credentials, used in the EP_INDEX_PREFIX constant."
	echo "-t=*, --attempts=*             Number of times the tests should be executed if it fails."
	echo "-h|--help                     Display this help screen"
	exit
fi

if [ $INSTALL_ES_DOCKER -eq 1 ]
then
  echo "Creating Elasticsearch container"

rm wpa-elasticsearch.yml
cat <<EOT >> wpa-elasticsearch.yml
http.host: 0.0.0.0

http.cors.enabled : true
http.cors.allow-origin : "*"
http.cors.allow-methods : OPTIONS, HEAD, GET, POST, PUT, DELETE
http.cors.allow-headers : X-Requested-With,X-Auth-Token,Content-Type, Content-Length
EOT

  docker run \
    -d \
    -v "$(pwd)/wpa-elasticsearch.yml:/usr/share/elasticsearch/config/elasticsearch.yml:cached" \
    -p 9200:9200 -p 9300:9300 \
    -e "xpack.security.enabled=false" \
    -e "discovery.type=single-node" \
    -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
    docker.elastic.co/elasticsearch/elasticsearch:5.6.16

  EP_HOST="http://host.docker.internal:9200/"
fi

if [ ! -z $EP_HOST ] || [ ! -z $ES_SHIELD] || [ ! -z $EP_INDEX_PREFIX]; then
  echo "Creating custom-ep-credentials.php MU Plugin"

  PLUGIN_PATH='./tests/wpa/test-mu-plugins/custom-ep-credentials.php'
  rm $PLUGIN_PATH
  touch $PLUGIN_PATH
  echo '<?php' >> $PLUGIN_PATH
  echo '/*' >> $PLUGIN_PATH
  echo ' * This plugin is generated automatically. DO NOT MODIFY IT.' >> $PLUGIN_PATH
  echo ' */' >> $PLUGIN_PATH
  echo '' >> $PLUGIN_PATH
  if [ ! -z $EP_HOST ]; then
    echo "define( 'EP_HOST', '$EP_HOST' );" >> $PLUGIN_PATH
  fi
  if [ ! -z $ES_SHIELD ]; then
    echo "define( 'ES_SHIELD', '$ES_SHIELD' );" >> $PLUGIN_PATH
  fi
  if [ ! -z $EP_INDEX_PREFIX ]; then
    echo "define( 'EP_INDEX_PREFIX', '$EP_INDEX_PREFIX' );" >> $PLUGIN_PATH
  fi
fi

for i in $(seq 1 $ATTEMPTS); do

  ./vendor/bin/wpacceptance run

  EXIT_CODE=$?

  if [ $EXIT_CODE -ge 1 ] && [ $i -lt $ATTEMPTS ]; then
    echo
    echo '-------------------------------'
    echo
    echo "         Retrying..."
    echo "         Attempt #$(($i + 1))"
    echo
    echo '-------------------------------'
    echo
    echo
    sleep 3
  else
    break
  fi
done

exit $EXIT_CODE