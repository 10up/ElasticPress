#!/usr/bin/env bash

INSTALL_ES_DOCKER=$1

if [ $INSTALL_ES_DOCKER ]
then
    echo "Creating Elasticsearch container"
    docker run \
        -d \
        -v '/home/elia/wp-local-docker-sites/ep-wpa-tests-test/config/elasticsearch/elasticsearch.yml:/usr/share/elasticsearch/config/elasticsearch.yml:cached' \
        -p 9200:9200 -p 9300:9300 \
        -e "xpack.security.enabled=false" \
        -e "discovery.type=single-node" \
        -e "ES_JAVA_OPTS=-Xms512m -Xmx512m" \
        docker.elastic.co/elasticsearch/elasticsearch:5.6.16
fi

for i in 1 2 3; do

    ./vendor/bin/wpacceptance run

    EXIT_CODE=$?

    if [ $EXIT_CODE -gt 1 ]; then
        echo "Retrying..."
        sleep 3
    else
        break
    fi
done

exit $EXIT_CODE