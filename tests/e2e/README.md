# ElasticPress End to End Tests

ElasticPress e2e tests use [Playwright](https://playwright.dev/), [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), and an Elasticsearch Docker container.

This directory serves two purposes. It holds the ElasticPress test suite, and it is published to npm as `elasticpress-playwright-utils`, the shared helpers used by other ElasticPress plugins such as [ElasticPress Labs](https://github.com/10up/ElasticPressLabs). Anything exported from `src/index.ts` is part of that public surface, so treat changes to `utils.ts`, `fixtures.ts`, and `block-editor.ts` as changes to a published package.

## Requirements

* docker
* node >= 20 and npm >= 9

## The environment under test

Tests do not run against a stock WordPress install. `bin/setup-e2e-env.sh` builds a specific environment, and the tests assume all of it:

| Property | Value |
| --- | --- |
| URL | `http://localhost:8889` |
| Install type | Multisite, subdirectory, two sites (`/` and `/second-site`) |
| Credentials | `admin` / `password` |
| Theme | Twenty Twenty-One, because Twenty Twenty-Five does not support WordPress 6.2 |
| Content | From `content-example.xml`: 55 posts, 18 pages, 25 products with variations, 25 WooCommerce orders, a synonym set and a nav menu |
| Always active | ElasticPress, Debug Bar, Debug Bar ElasticPress, WordPress Importer, Classic Widgets |
| Installed but inactive | WooCommerce, ElasticPress Proxy |
| Elasticsearch | Local container on port `8890`, or an ElasticPress.io endpoint |

The Debug Bar output is load-bearing: many tests read `#debug-menu-target-EP_Debug_Bar_ElasticPress` to assert which query ran and what Elasticsearch returned.

## Running the tests

### Quick start

Every command below runs from the plugin root, not from this directory.

```
npm i
npm run build
npx playwright install --with-deps    # first run only
make start-e2e                        # wp-env plus Elasticsearch
make setup-e2e-tests                  # build the database
npm run playwright:test
```

Use `npm run playwright:ui` for the interactive runner. In VS Code or Cursor the [Playwright extension](https://open-vsx.org/extension/ms-playwright/playwright) also works.

### Make targets

The `Makefile` in the plugin root wraps the same setups CI runs. Run `make` with no arguments to list them.

Credentials and machine-specific paths come from a gitignored `.env` in the plugin root. Copy `.env.example` and fill in what you have:

* An ACF Pro license is optional. Without it every target still works, but the `@paidPlugins` tests will fail.
* ElasticPress.io credentials are only needed by the `setup-e2e-tests-epio*` targets, which stop with an explanatory message when they are missing.

```
make start-e2e                 # wp-env plus Elasticsearch
make setup-e2e-tests           # latest WordPress and WooCommerce
make setup-e2e-tests-min       # minimum supported WordPress and WooCommerce
make destroy-e2e               # tear everything down
```

ElasticPress.io uses no local Elasticsearch, so it has its own start target:

```
make start-e2e-epio
make setup-e2e-tests-epio      # or setup-e2e-tests-epio-min
```

Set `ES_VERSION` in `.env` to start a specific Elasticsearch version. CI covers 7.10.1, 8.12.2 and 9.1.5.

### Test groups

Every `test.describe` carries exactly one tag. CI runs `@group1` then `@group2` as sequential steps in the same job (separate HTML reports and `test-results` dirs). `@paidPlugins` stays its own job because it installs ACF Pro.

| Tag | Contents |
| --- | --- |
| `@group1` | Search, weighting, post indexable, instant results, protected content, documents, features interface, general, settings token, status report |
| `@group2` | Facets, autosuggest, WooCommerce, related posts, custom results, dashboard sync, synonyms, comments, terms indexable, WP-CLI, WordPress basics |
| `@paidPlugins` | ACF repeater tests, which need a license to install ACF Pro |

```
npm run playwright:test -- --grep @group1
```

Each Playwright invocation re-runs the setup project, which resets feature settings. That is why group1 and group2 can share one CI environment as two process runs. Running both tags in a single `--grep` would not get that reset.

### Database cache

The first setup for a given WordPress and WooCommerce combination exports the finished database to `bin/.cache`, and later runs import that file instead of repeating the content import and search-replace. Steps that are not database changes still run every time: plugin and core downloads, the `wp-config.php` constants, and the Elasticsearch sync.

Cache files are keyed by the WordPress version, the WooCommerce version, whether ACF Pro is installed, and a hash of `bin/setup-e2e-env.sh` plus the imported content. Editing either file produces a new key, so there is nothing to invalidate by hand.

To rebuild from scratch, pass `--no-db-cache` to `npm run e2e:setup`, or run `make clean-db-cache`.

### Soft and hard resets

* Reset the database and redo setup: `npm run env:reset`
* Rebuild everything: `make destroy-e2e && make start-e2e && make setup-e2e-tests`

Re-running a setup target on a cache hit also resets the database, which is usually the fastest way back to a clean state.

## Layout

| Path | Contents |
| --- | --- |
| `src/specs/` | The tests. `indexables/` and `search/` group related specs. |
| `src/specs/global.setup.ts` | Playwright setup project. Resets feature settings and publishes `EP_INDEX_NAMES`, `EP_IS_EPIO` and `WP_VERSION` as environment variables. |
| `src/fixtures.ts` | The `loggedInPage` fixture and the re-exported `test` and `expect`. |
| `src/utils.ts` | WordPress and ElasticPress helpers. |
| `src/block-editor.ts` | Block editor helpers, including block support assertions. |
| `src/fixtures/` | Files uploaded by tests: PDF, CSV, PPTX, TXT, JSON. |
| `src/wordpress-files/` | Everything mounted into the container by `.wp-env.json`. |
| `src/playwright.config.ts` | Runner configuration. |

`src/wordpress-files/` is worth knowing in detail, since it is how tests shape the WordPress side:

| Path | Purpose |
| --- | --- |
| `test-plugins/` | Single-file plugins, each forcing one condition. Activated and deactivated by individual tests. |
| `test-mu-plugins/` | Always active. Disables the welcome guide and WP.org lookups, and applies the tweaks described below. |
| `test-docs/content-example.xml` | The imported content. |
| `.htaccess` | Multisite rewrite rules, which WordPress does not write itself. |
| `composer.json` | Pulls in ACF Pro when a license is configured. |

`test-mu-plugins/unique-index-name.php` deserves attention. It appends the Docker container id to `ep_index_name`, so every environment writes to its own indices. That is what lets many CI jobs share one ElasticPress.io account without overwriting each other, and it means an index name is only meaningful within a single run. It also registers `wp elasticpress-tests delete-all-indices`, which CI calls to clean up ElasticPress.io afterwards.

## Writing tests

### Start from the fixture

Import `test` and `expect` from `fixtures.js`, not from `@playwright/test`, and take `loggedInPage` rather than logging in yourself:

```ts
import { test, expect } from '../fixtures.js';
import { goToAdminPage, wpCli } from '../utils.js';

test.describe('My feature', { tag: '@group1' }, () => {
	test('does the thing', async ({ loggedInPage }) => {
		await goToAdminPage(loggedInPage, 'admin.php?page=elasticpress');
		await expect(loggedInPage.locator('.wrap')).toContainText('ElasticPress');
	});
});
```

Imports use the `.js` extension even though the sources are TypeScript, because the package is an ES module.

### Helpers worth knowing

| Helper | Use |
| --- | --- |
| `wpCli(command, ignoreFailures?)` | Run WP-CLI in the container. Returns `null` on failure unless failures are ignored. |
| `wpCliEval(php)` | Run arbitrary PHP through `eval-file`. Use for anything WP-CLI has no command for. |
| `goToAdminPage(page, path)` | Navigate to `/wp-admin/<path>` and wait for frames to load. |
| `publishPost(page, data)` | Create a post through the block editor, then wait for Elasticsearch. |
| `maybeEnableFeature(name)` / `maybeDisableFeature(name)` | Toggle an ElasticPress feature without failing if it is already in that state. |
| `updateFeatures(name, values)` | Write feature settings directly. |
| `updateWeighting(values?)` | Set search weighting, or restore the defaults when called with no argument. |
| `refreshIndex(indexable)` | Force an index refresh instead of sleeping. |
| `activatePlugin` / `deactivatePlugin` | Toggle a plugin through the dashboard or WP-CLI, on one site or the network. |
| `isEpIo()` | Branch on whether the run targets ElasticPress.io. |

### Rules that keep the suite green

These are the constraints that cause the most confusing failures when broken.

**The suite is single-worker on purpose.** Every test shares one WordPress install, and tests toggle features, weighting and plugins globally. `workers` is pinned to `1` in `playwright.config.ts` and `fullyParallel` is `false`. Raising either produces failures that look like product bugs but are tests overwriting each other.

**Clean up global state.** If a test activates a test plugin, enables a feature, or changes weighting, it should put it back. `global.setup.ts` applies `defaultFeatures` once per run, not between tests, so anything left behind reaches the next spec.

**Elasticsearch is not immediately consistent.** After creating or editing content, either wait for the index or call `refreshIndex()`. `publishPost` and `createTerm` already wait. `EP_INDEX_TIMEOUT` controls how long sync-related assertions wait, defaulting to 30 seconds.

**The install is multisite.** Plugin activation is per-site unless you pass network mode, and a test that only looks at the main site may miss the second one.

**Support the minimum WordPress version.** Several helpers branch on `process.env.WP_VERSION === '6.2'` because the block editor markup differs. New editor interactions need the same treatment, or they will pass on latest and fail on the minimum matrix leg.

**Prefer asserting on the Debug Bar.** Checking the query response in `#debug-menu-target-EP_Debug_Bar_ElasticPress` proves ElasticPress served the request. Asserting only on rendered results can pass when WordPress silently falls back to MySQL.

### Adding a test plugin

To force a condition WordPress or ElasticPress will not produce on demand, add a single-file plugin to `src/wordpress-files/test-plugins/`, map it in `.wp-env.json`, then activate and deactivate it inside the test with `activatePlugin(page, 'slug', 'wpCli')`.

## How CI runs this

`.github/workflows/playwright.yml` runs a matrix of 2 suites (`@groups` = `@group1` then `@group2` in one job, plus `@paidPlugins`), 4 Elasticsearch backends (7.10.1, 8.12.2, 9.1.5 and EP.io) and 2 core versions (latest and the 6.2 / WooCommerce 6.4.0 minimum), for 16 jobs. Each job builds its own environment, restores the database cache, runs its suite, and afterwards verifies the plugin uninstalls cleanly. Cleanup (stop Elasticsearch, delete EP.io indices, uninstall) runs even when tests fail.

CI sets `CI=true`, which enables two retries. Locally there are no retries, so a flaky test fails immediately.

### Fork pull requests

GitHub does not pass repository secrets to `pull_request` workflows from forks. The EP.io backend and the `@paidPlugins` group need those secrets (`EPIO_HOST`, `EPIO_CREDENTIALS`, `EPIO_INDEX_PREFIX`, `ACF_PRO_LICENSE_KEY`), so those 10 jobs are skipped on fork PRs. Same-repository PRs and pushes to `develop` / `trunk` still run the full matrix.

To get EP.io and ACF coverage before merge, a maintainer reviews the diff (especially `package.json` scripts, `bin/setup-e2e-env.sh`, and `tests/e2e`) and then either:

* Adds the `safe-to-test` label, which starts `.github/workflows/playwright-privileged.yml` against that commit
* Runs **E2e Tests (privileged)** from the Actions tab and passes the pull request number

The label is removed on every new push, so a later commit cannot reuse an earlier approval. Create the `safe-to-test` label in the repository if it does not already exist.

Do not make the EP.io or `@paidPlugins` job names required status checks if fork PRs should remain mergeable: skipped required checks count as success. Treat the privileged run as a review step, not a branch-protection gate.

## Troubleshooting

### Running tests with ElasticPress.io

Use `make start-e2e-epio` followed by `make setup-e2e-tests-epio`, with `EPIO_HOST`, `EPIO_CREDENTIALS` and `EPIO_INDEX_PREFIX` set in `.env`. To bypass the Makefile, call the setup script directly:

```
./bin/setup-e2e-env.sh --ep-host="https://..." --ep-credentials="username:password" --ep-index-prefix="username"
```

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
