# ElasticPress End to End Tests

ElasticPress e2e tests use [Playwright](https://playwright.dev/), [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), and an Elasticsearch Docker container.

This package contains the tests used on ElasticPress as well as some utility functions used on other ElasticPress plugins, like [ElasticPress Labs](https://github.com/10up/ElasticPressLabs).

## Requirements

* docker
* npm (>= 10)

## Instructions

### Start

* Start the local environment (WP env and Elasticsearch containers): `npm run env:start`
* Install all node packages: `npm i`
* Build assets: `npm run build`
* Initial database setup: `npm run e2e:setup`, or one of the `make` targets below
* Open Playwright: `npm run playwright:ui`. If you are using VS Code or Cursor, you can also use the [Playwright Test for VSCode](https://open-vsx.org/extension/ms-playwright/playwright) extension.

### Soft Reset

* Clean the database and redo initial setup: `npm run env:reset`
* Open Playwright: `npm run playwright:ui`

### Hard Reset

* Destroy the WP env: `npm run env destroy`
* Restart WP env and redo initial setup: `npm run env:start && npm run e2e:setup`
* Open Playwright: `npm run playwright:ui`

### Make targets

The `Makefile` in the plugin root wraps the setups the e2e workflow runs. Run `make` to list them.

Credentials and machine-specific paths are read from a `.env` file in the plugin root, which is gitignored. Copy `.env.example` to `.env` and fill in what you have:

* An ACF Pro license is optional. Without it every target still runs, but the tests tagged `@paidPlugins` are skipped.
* ElasticPress.io credentials are only needed by the `setup-e2e-tests-epio*` targets, which fail with an explanatory message when they are missing.

Each environment is started, set up, then destroyed:

```
make start-e2e                 # wp-env plus Elasticsearch
make setup-e2e-tests           # latest WordPress and WooCommerce
make setup-e2e-tests-min       # minimum supported WordPress and WooCommerce
make destroy-e2e               # tear everything down
```

ElasticPress.io runs against no local Elasticsearch, so it has its own start target:

```
make start-e2e-epio
make setup-e2e-tests-epio      # or setup-e2e-tests-epio-min
make destroy-e2e
```

The e2e workflow runs each of these against several Elasticsearch versions. Set `ES_VERSION` in `.env` to reproduce a specific one.

## Troubleshooting

### WSL

#### `Error: Could not connect to Elasticsearch`

Run `./bin/wp-env-cli wordpress "wp --allow-root config set EP_HOST http://host.docker.internal:8890/"`

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
docker compose down
docker compose up -d --build --force-recreate
```

### Running tests with ElasticPress.io

To run tests locally using an ElasticPress.io endpoint, in place of running `npm run e2e:setup` during setup, run: `./bin/setup-e2e-env.sh --ep-host="https://" --ep-credentials="username:password" --ep-index-prefix="username"`, with the arguments populated with the details for your ElasticPress.io endpoint.