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
* Restart WP env and redo initial setup: `npm run env:start && npm run cypress:setup`
* Open Cypress: `npm run cypress:open`

## Troubleshooting

### WSL

#### `Error: Could not connect to Elasticsearch`

Run `./bin/wp-env-cli tests-wordpress "wp --allow-root config set EP_HOST http://host.docker.internal:8890/"`

#### `Error while loading shared libraries: ...`

Run `sudo apt update && sudo apt install libatk1.0-0 libatk-bridge2.0-0 libcups2 libgtk-3-0 libgbm-dev libasound2 xvfb`

#### `Command was killed with SIGILL (Invalid machine instruction)`

Make sure you have `xvfb` installed

#### `Could not parse server address: Unknown address type (examples of valid types are "tcp" and on UNIX "unix")`

```
export LIBGL_ALWAYS_INDIRECT=1
export DISPLAY=:0
```

#### `elasticsearch The requested image's platform (linux/amd64) does not match the detected host platform (linux/arm64/v8) and no specific platform was requested`

This error may appear when running tests on an Apple Silicon device that was restored from a backup of an Intel machine. Run the following in `./bin/es-docker` to ensure the Docker image is for the right platform:

```
docker-compose down
docker-compose up -d --build --force-recreate
```

### Running tests with ElasticPress.io

To run tests locally using an ElasticPress.io endpoint, in place of running `npm run cypress:setup` during setup, run: `./bin/setup-cypress-env.sh --ep-host="https://" --es-shield="username:password" --ep-index-prefix="username"`, with the arguments populated with the details for your ElasticPress.io endpoint.
