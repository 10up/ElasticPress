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

## Troubleshooting

### WSL

#### `Error: Could not connect to Elasticsearch`

Run `./bin/wp-env-cli tests-wordpress "wp --allow-root config set EP_HOST http://host.docker.internal:8890/"`

#### `Error while loading shared libraries: ...`

Run `sudo apt update && sudo apt install libatk1.0-0 libatk-bridge2.0-0 libcups2 libgtk-3-0 libgbm-dev libasound2 xvfb`

#### `Command was killed with SIGILL (Invalid machine instruction)`

Make sure you have `xvfb` installed
