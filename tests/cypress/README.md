# ElasticPress End to End Tests

ElasticPress e2e tests use [Cypress](https://www.cypress.io/), [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), and an Elasticsearch Docker container.

## Requirements

* docker
* docker-compose
* npm (>= 10)

## Instructions

### Start

* Start the Elasticsearch container: `cd bin/es-docker/ && docker-compose up -d`
* Install all node packages: `npm i`
* Build assets: `npm run build`
* Start WP env and initial setup: `npm run env start && ./bin/setup-cypress-env.sh`
* Open Cypress: `npm run cypress:open`

### Reset

* Destroy the WP env: `npm run env destroy`
* Restart WP env and redo initial setup: `npm run env start && ./bin/setup-cypress-env.sh`
* Open Cypress: `npm run cypress:open`
