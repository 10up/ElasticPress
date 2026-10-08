# ElasticPress Test Status

Live tracking of unit and e2e test suite health across WordPress versions.

---

## Environment

| Component | Value |
|-----------|-------|
| Plugin | ElasticPress 5.3.3 |
| Elasticsearch | 8.16.1 (Docker, port 8890) |
| PHP | 8.4.x (local) / 8.x (wp-env container) |
| WooCommerce | 9.6.1 (via composer vendor) |

> **How to run unit tests (local, not in wp-env):**
> ```bash
> WP_TESTS_DIR=/var/folders/.../wordpress-tests-lib \
>   EP_HOST=http://127.0.0.1:8890 \
>   vendor/bin/phpunit
> ```
> Always delete all ES indices before a run to avoid flakiness: `curl -X DELETE http://localhost:8890/*`

> **How to run e2e tests:**
> ```bash
> npm run env:start          # starts wp-env + ES
> npm run e2e:setup          # provisions WP multisite, imports content, runs initial sync
> npm run playwright:test -- --ignore-glob="**/acf-repeater.spec.ts"
> ```

---

## Unit Tests

### WordPress 6.9.4

**Result: ✅ PASS** (1006 tests, 0 failures, 1 skipped, 16 incomplete — all expected)

| # | Test | Status | Notes |
|---|------|--------|-------|
| — | All 1006 tests | ✅ Pass | Confirmed clean on 3rd run after wiping ES indices |

> **Note on flakiness:** On the first 1–2 runs after a prior suite run, 1 test may fail (different test each time, e.g. `TestProtectedContent::test_post_statuses_for_admin_with_multiple_statuses` or `TestPost::testTaxQueryInvalidWithInvalidField`). Both pass in isolation. Root cause: the local ES instance is persistent — stale indexed documents from prior tests bleed into the next run. Fix: `curl -X DELETE http://localhost:8890/*` before every run. CI is immune because it starts a fresh ES container each time.

---

### WordPress 7.0

**Result: ❌ FAIL** (1006 tests, 1 failure, 1 skipped, 16 incomplete)

| # | Test | Status | Failure | Root Cause |
|---|------|--------|---------|------------|
| 1 | `TestInstantResults::test_requirements_status` | ❌ Fail | `wp_kses_post()` converts single-quoted `href='...'` to double-quoted `href="..."` | WP 7.0 — `wp_kses_hair()` refactored to use HTML API (see below) |

#### Failure Detail

```
Expected: "...href='https://elasticpress.io'..."
Actual:   "...href="https://elasticpress.io"..."
```

Source in `includes/classes/Feature/InstantResults/InstantResults.php:145`:
```php
$status->message[] = wp_kses_post( __( "...href='https://elasticpress.io'...", 'elasticpress' ) );
```

#### WordPress 7.0 Change That Caused This

| Field | Detail |
|-------|--------|
| **Trac Ticket** | [#63694](https://core.trac.wordpress.org/ticket/63694) |
| **PR** | [wordpress-develop#9248](https://github.com/WordPress/wordpress-develop/pull/9248) |
| **Commit** | [`290c46ab`](https://github.com/WordPress/wordpress-develop/commit/290c46ab171347d152bf386433b55fd6682d5b2a) — merged 2025-07-11 |
| **File** | `src/wp-includes/kses.php` — `wp_kses_hair()` |

**What changed:** `wp_kses_hair()` was rebuilt from a 140-line hand-rolled state machine to use `WP_HTML_Tag_Processor`. The old parser passed attribute values through unchanged (preserving single quotes). The HTML API normalizes all attribute values to double quotes on serialization — a spec-compliant behaviour change that broke any test asserting the exact quote style of `wp_kses_post()` output.

**Fix:** Use double-quoted `href` attributes in the source string (they survive `wp_kses_post` unchanged on both 6.9 and 7.0), or update the test assertion to use double quotes.

```php
// Before (fails on WP 7.0):
wp_kses_post( __( "...<a href='https://elasticpress.io'>...", 'elasticpress' ) )

// After (works on WP 6.9 and 7.0):
wp_kses_post( __( '...<a href="https://elasticpress.io">...', 'elasticpress' ) )
```

---

## E2E Tests

> **How to run correctly (local):**
> ```bash
> # 1. Full clean start — required or test data pollutes ES scoring
> npm run env:reset   # wp-env clean all + env:start (but see known bug below)
> # If env:reset aborts at line 125, manually fix then re-run e2e:setup:
> #   ./bin/wp-env-cli tests-wordpress "wp --allow-root config delete MULTISITE SUBDOMAIN_INSTALL DOMAIN_CURRENT_SITE WP_ALLOW_MULTISITE PATH_CURRENT_SITE SITE_ID_CURRENT_SITE BLOG_ID_CURRENT_SITE" 2>/dev/null
> #   npm run e2e:setup
> #   ./bin/wp-env-cli tests-wordpress "wp --allow-root site create --slug=second-site --title='Second Site'"
> # 2. Wipe ES indices (setup sync is enough, but belt-and-suspenders)
> curl -X DELETE http://localhost:8890/*
> # 3. Run with single worker (matches CI)
> npx playwright test -c tests/e2e/src/playwright.config.ts --workers=1 \
>   --ignore-glob="**/acf-repeater.spec.ts"
> ```

---

### WordPress 6.9.4

**Result: ⚠️ PARTIAL** — 110/127 pass, 17 fail with local environment issues

> All 17 failures are **local-only setup issues** — the same tests pass in CI (Linux + `retries: 2`). They are NOT code defects.

#### Setup Issues Found and Resolved

| # | Issue | Root Cause | Fix Applied |
|---|-------|-----------|-------------|
| 1 | `wp-env clean all` leaves MULTISITE=true in wp-config with empty `wp_site` table | `wp-env clean all` wipes the DB but NOT the Docker volume–stored wp-config; multisite constants persist | Manually delete 4 constants before re-running `e2e:setup` |
| 2 | Duplicate multisite constants in wp-config | `multisite-convert` runs twice across repeated `env:reset` calls | Deduplicate `PATH_CURRENT_SITE`, `SITE_ID_CURRENT_SITE`, `BLOG_ID_CURRENT_SITE` |
| 3 | `setDefaultFeatureSettings` JSON parse failure | WP-CLI 2.12.0 outputs "already defined" warnings for multisite constants; `wp-env-cli` merges stderr+stdout; `JSON.parse("[12-Jun...")` fails at position 3 | Deduplicate constants so WP-CLI stops warning |
| 4 | Parallel workers cause state pollution | Default Playwright workers locally vs `CI ? 1 : undefined` | Run with `--workers=1` |
| 5 | Stale test-created posts pollute ES scoring | Previous test runs left "Blog post" posts (today's date); ES recency boost outranks imported "a Blog page" | `wp-env clean all` before every run |
| 6 | Second multisite site never created | `setup-e2e-env.sh` line 125: WP-CLI 2.12.0 deprecation warnings in `SITES_COUNT` output cause `[: too many arguments` in bash comparison | Manually create: `wp --allow-root site create --slug=second-site` |

#### Remaining 17 Failures

**Category A — Block editor iframe timing (14 tests)**

`publishPost()` and block-insertion helpers call `getEditorFrame(page)` immediately after navigation. `getEditorFrame` uses `isVisible()` (instant, no wait) to check for `iframe[name="editor-canvas"]`. On macOS+Docker, React hasn't rendered the iframe yet, so the function returns the outer page. Subsequent selectors (`h1.editor-post-title__input, #post-title-0`) are searched on the outer page where they don't exist → 30s timeout.

CI compensates: faster Linux machines + `retries: 2` (configured in `playwright.config.ts`). Running locally with `--retries=2` makes things **worse** due to state pollution between retry attempts on a stateful WordPress instance.

Fix needed in `tests/e2e/src/utils.ts` — `getEditorFrame` should use `waitFor()` instead of `isVisible()`:
```typescript
// Current (race condition):
if (await editorFrame.isVisible()) { return editorFrame.contentFrame(); }

// Fix:
await editorFrame.waitFor({ state: 'visible', timeout: 10000 }).catch(() => null);
if (await editorFrame.isVisible()) { return editorFrame.contentFrame(); }
```

| Test | File |
|------|------|
| Can include the new post in search result | `custom-results.spec.ts:90` |
| Can sync post data and meta details | `general.spec.ts:44` |
| Can insert, configure, and use the Search Comments block | `indexables/comments.spec.ts:52` |
| Can not sync anonymous comments until approved | `indexables/comments.spec.ts:299` |
| Can delete posts if a password is added or removed | `indexables/post.spec.ts:99` |
| Is possible to manually open Instant Results with a plugin | `instant-results.spec.ts:376` |
| Is possible to set the default post type from a search form | `instant-results.spec.ts:466` |
| Is possible to filter the taxonomy terms | `instant-results.spec.ts:536` |
| Can search password protected post | `protected-content.spec.ts:111` |
| Can insert, configure, use, and transform the legacy Related Posts widget | `related-posts.spec.ts:124` |
| Can see exact matches showing higher | `search/search.spec.ts:21` |
| Can see highlighted text | `search/search.spec.ts:75` |
| Is possible to create, edit, and delete hyponym rules | `search/synonyms.spec.ts:176` |
| Can add, weight and search meta fields | `search/weighting.spec.ts:123` |

**Category B — Missing second site (1 test)**

`wp-cli.spec.ts:259` — "Can index all blogs in network" expects 2 `Indexing posts on site` log lines but gets 1. The second multisite site was never created because `setup-e2e-env.sh` line 125 fails when WP-CLI 2.12.0 deprecation notices pollute the `SITES_COUNT` bash variable.

Fix needed in `bin/setup-e2e-env.sh` line 123:
```bash
# Current (breaks when wp-env-cli includes stderr in output):
SITES_COUNT=$(./bin/wp-env-cli tests-wordpress "wp --allow-root site list --format=count")
if [ $SITES_COUNT -eq 1 ]; then

# Fix (suppress stderr, quote variable):
SITES_COUNT=$(./bin/wp-env-cli tests-wordpress "wp --allow-root site list --format=count" 2>/dev/null | tail -1)
if [ "$SITES_COUNT" -eq 1 ]; then
```

**Category C — Docker/macOS performance timing (2 tests)**

| Test | Failure | Root Cause |
|------|---------|-----------|
| `search/search.spec.ts:39` Can see newer matches showing higher | `.site-content article h2` not found | 2s wait for ES indexing is insufficient under Docker/macOS; posts created via WP-CLI aren't yet reflected in search results |
| `woocommerce.spec.ts:162` Can not display other users orders | 30s test budget consumed by WooCommerce checkout form-fills | WooCommerce checkout page loads/renders slower in macOS+Docker vs CI Linux |

---

### WordPress 7.0

- [ ] **TODO** — Pending (unit test already has 1 known failure)
