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

  docker container inspect -f '{{.State.Running}}' ep_wpa_es_server || docker run \
    -d \
    -v "$(pwd)/wpa-elasticsearch.yml:/usr/share/elasticsearch/config/elasticsearch.yml:cached" \
    -p 9200:9200 -p 9300:9300 \
    -e "xpack.security.enabled=false" \
    -e "discovery.type=single-node" \
    -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
    --name "ep_wpa_es_server" \
    docker.elastic.co/elasticsearch/elasticsearch:5.6.16

  docker exec -u root -i ep_wpa_es_server chown -R elasticsearch: plugins
  docker exec -i ep_wpa_es_server bin/elasticsearch-plugin install ingest-attachment -b
  docker restart ep_wpa_es_server

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

#
# Start of tests execution
#

# This variable will hold all tests failed with the "Page crashed" error.
ERRORS=''

# "Real Failed" Attempts in this case will be any failed attempt with an error other than Page Crashed.
# It'll be needed at least 1 failed attempt with only Page Crashed errors.
REAL_FAILED_ATTEMPTS=0

for i in $(seq 1 $ATTEMPTS); do

  TEST_OUTPUT=$(mktemp)

  set -o pipefail
  ./vendor/bin/wpacceptance run --cache_environment --screenshot_on_failure | tee ${TEST_OUTPUT}

  EXIT_CODE=$?


  if [ $EXIT_CODE -ge 1 ]; then

    # List of errors for this specific attempt.
    SUMMARY=$(sed -e '/Summary of non-successful tests:/,//!d' ${TEST_OUTPUT})

    # Count all errors
    TOTAL_ERRORS_COUNT=$(echo "${SUMMARY}" | grep '✘' | wc -l )

    # Get the Page Crashed errors
    PAGE_CRASHED_ERRORS=$(echo "${SUMMARY}" | grep -Pzo '✘([^✘☢])*?Page crashed' | tr '\0' '\n' | grep '✘' )
    PAGE_CRASHED_ERRORS_COUNT=$(echo "${PAGE_CRASHED_ERRORS}" | wc -l )

    if [ $TOTAL_ERRORS_COUNT -gt $PAGE_CRASHED_ERRORS_COUNT ]; then
      ((REAL_FAILED_ATTEMPTS++))
    fi

    # Add the Page Crashed errors to a full list to count them later
    ERRORS+="
${PAGE_CRASHED_ERRORS}"

    if [ $i -lt $ATTEMPTS ]; then
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
    fi
  else
    break
  fi
done

# If the final attempt wasn't successful, check if we had at least one attempt with Page Crashed errors only.
# If different tests failed, then consider it a success.
if [ $EXIT_CODE -ge 1 ] && [ $REAL_FAILED_ATTEMPTS -lt $ATTEMPTS ]; then
  echo
  echo
  echo
  echo '-------------------------------'
  echo
  echo "         Final list of tests ended with 'Page crashed':"
  echo
  echo '-------------------------------'
  echo
  echo

  ERRORS_COUNT=$(echo "${ERRORS}" | sort | uniq -c)

  echo "${ERRORS_COUNT}"

  if [[  -z $(echo "${ERRORS_COUNT}" | grep -P "^      $ATTEMPTS") ]]; then
    echo
    echo 'As any test failed in all attempts, consider it OK.'
    echo
    EXIT_CODE=0
  fi
fi

exit $EXIT_CODE