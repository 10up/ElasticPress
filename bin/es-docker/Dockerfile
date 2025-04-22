ARG ES_VERSION=8.16.1
FROM docker.elastic.co/elasticsearch/elasticsearch:${ES_VERSION}

RUN if [ -d plugins/ingest-attachment ]; then true ; else ./bin/elasticsearch-plugin install ingest-attachment -b; fi
