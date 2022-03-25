# ElasticPress End to End Tests

ElasticPress e2e tests use [Cypress](https://www.cypress.io/), [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), and an Elasticsearch Docker container.

## Requirements

* docker
* docker-compose
* npm (>= 10)

## Instructions

### Start

* Start the local environment (WP env and Elasticsearch containers): `npm run env:start`
* Install all node packages: `npm i`
* Build assets: `npm run build`
* Initial database setup: `npm run cypress:setup`
* Open Cypress: `npm run cypress:open`

### Reset

* Destroy the WP env: `npm run env destroy`
* Restart WP env and redo initial setup: `npm run env start && npm run cypress:setup`
* Open Cypress: `npm run cypress:open`
