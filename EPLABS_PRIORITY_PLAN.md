# ElasticPress ↔ ElasticPress Labs — Feature Priority Plan (EP-main-only)

**Status:** Proposed (awaiting implementation)
**Author:** Zachary Rener
**Context branch:** `feature/eplabs-ai-feature-migration`
**Constraint:** **All changes live in ElasticPress main. ElasticPress Labs is NOT modified.**
**Goal:** When both plugins are active, EP main takes priority for the four migrated AI features. EPLabs keeps its default priority for everything else.

---

## Background

The AI features (Semantic Search, Vector Embeddings, AI Search Summary, and the old Search Algorithm selector) were migrated from EPLabs into EP main. Both plugins now ship these, but they are **not in sync** — EP main has changes not present in EPLabs, and EP main is the source of truth.

### How each plugin registers features

- **EP main** — `elasticpress.php:144` `register_indexable_posts()`, hooked to `plugins_loaded` at **priority 10** (default). Registers `semantic_search`, `vector_embeddings`, `ai_search_summary` (among all core features) at `elasticpress.php:205-214`.
- **EPLabs** — `includes/functions/core.php:139` `maybe_load_features()`, hooked to `plugins_loaded` at **priority 11** (`core.php:27`, callback `ElasticPressLabs\Core\maybe_load_features`). Registers its own `semantic_search`, `vector_embeddings`, `ai_search_summary` (`core.php:193-200`) plus EPLabs-only features including `search_algorithm`.

### The mechanism behind the conflict

`Features::register_feature()` (`includes/classes/Features.php:112`) stores by slug and **overwrites** — last registration wins:

```php
$this->registered_features[ $feature->slug ] = $feature;
```

Because EPLabs runs at priority **11** (after EP's **10**), it overwrites EP main for every shared slug.

### Timing fact that makes an EP-main-only fix possible

`Features::setup_features()` — which calls each feature's `setup()` — runs on **`init` @ priority 0** (`Features.php:39`), *after* all `plugins_loaded` hooks. So whichever instance sits in `registered_features` at the end of `plugins_loaded` is the one that actually gets set up. EP main can therefore run a **late `plugins_loaded` callback (after EPLabs) to reclaim its own slugs**, and the corrected registry is what `setup()` sees on `init`.

---

## Current behavior (WITHOUT these changes)

**EPLabs wins all four AI features.**

| Slug | EP main | EPLabs | Current live class |
|------|:------:|:------:|--------------------|
| `semantic_search`   | ✅ | ✅ | ❌ **EPLabs** (overwrites EP at priority 11) |
| `vector_embeddings` | ✅ | ✅ | ❌ **EPLabs** (overwrites EP at priority 11) |
| `ai_search_summary` | ✅ | ✅ | ❌ **EPLabs** (overwrites EP at priority 11) |
| `search_algorithm`  | ❌ (removed) | ✅ | **EPLabs** (no collision; sole provider) |

Consequences today:
- EP main's migrated AI code **never runs** when both plugins are active — EPLabs shadows it.
- `search_algorithm`: EP main deleted the standalone feature and folded it into **SemanticSearch**. EP's `SemanticSearch::setup()` (`SemanticSearch.php:125`) and EPLabs' `SearchAlgorithm::setup()` (`SearchAlgorithm.php:83`) **both hook `ep_post_search_algorithm`** and both render a settings UI → duplicated/conflicting controls.

---

## Requirements

1. EP main **always** takes priority over EPLabs for the four migrated AI features.
2. Special case: do **not** activate EPLabs' `search_algorithm` when EP's `semantic_search` is present (its functionality moved into EP's Semantic Search).
3. Scope: this is an **exception for the four AI features only**. For any other overlap, EPLabs keeps winning (priority 11 default). The generic "suppress any overlapping EPLabs feature" behavior is intentionally **out of scope**.
4. Retain enterprise-grade, widely-used WordPress practices. **Do not modify EP core `Features`/`Feature` classes** or the EPLabs plugin.

---

## Design decisions (locked)

- **EP takes the last word.** EP registers as usual at `plugins_loaded@10`, then a second callback runs *after* EPLabs and reclaims EP's slugs. Everything EP does not claim keeps EPLabs' priority-11 result.
- **Generalized, config-driven.** The override logic is data-driven via two keyed arrays, so future migrated features are a one-line addition — not new code. Only the four AI features are populated today.
- **Dedicated class.** Logic lives in its own singleton class (matching `Features`/`Indexables`/`SearchAlgorithms` conventions), not inline in `elasticpress.php`.
- **`search_algorithm` suppressed via public filters**, not by mutating the registry array (see Q3 below).
- **Priority derived dynamically** from EPLabs' actual hook priority + 1, with a safe fallback (see Q2 below).
- **Extensibility filters** (following EP's filter-heavy convention): `ep_protected_features` (slug→class reclaim map), `ep_superseded_features` (absorbed-slug→EP-slug map), and `ep_feature_reclaim_priority` (the computed priority — also lets a site harden against a later-loading add-on). All three are applied once in `setup()` (at `plugins_loaded@10`), so the hot `is_active()` path reads plain properties and never re-runs `apply_filters`. Consumers must hook these before `plugins_loaded@10` (mu-plugin or early hook).

---

## Design detail

### Q1 — Generalization + placement

**New class:** `ElasticPress\FeaturePrecedence`
**File:** `includes/classes/FeaturePrecedence.php` (PSR-4-ish autoload, `elasticpress.php:74`)
**Pattern:** `factory()` singleton + `setup()`, matching existing core singletons.

Two config arrays express the two distinct override modes:

```php
/**
 * Features EP main owns and must win the slug registration for.
 * slug => fully-qualified class to re-register if another plugin overwrote it.
 */
protected $protected_features = [
    'semantic_search'   => \ElasticPress\Feature\SemanticSearch\SemanticSearch::class,
    'vector_embeddings' => \ElasticPress\Feature\VectorEmbeddings\VectorEmbeddings::class,
    'ai_search_summary' => \ElasticPress\Feature\AISearchSummary::class,
];

/**
 * Add-on features absorbed into a differently-named EP feature.
 * absorbed slug => EP slug whose presence supersedes it.
 */
protected $superseded_features = [
    'search_algorithm' => 'semantic_search',
];
```

Behavior:

- **Reclaim (protected):** on the late hook, for each protected slug, if the currently registered instance is **not** an instance of EP's class, re-register EP's own instance (overwriting the add-on's). Guarding on `instanceof` avoids pointless re-instantiation when nothing overrode it.
- **Supersede:** register `ep_feature_active` / `ep_feature_is_visible` filters (see Q3) for each superseded slug, gated on the target EP slug being registered.

Adding a future migrated feature = one line in the relevant array. The class contains **no AI-specific logic** — it is a generic "EP main wins these slugs" mechanism, seeded with the four AI features.

**Wiring:** instantiate in `register_indexable_posts()` (already runs at `plugins_loaded@10`), e.g. `FeaturePrecedence::factory()->setup();` after the core `register_feature()` calls. `setup()` reads the EPLabs priority and schedules the reclaim callback.

### Q2 — Dynamic EPLabs priority lookup (+1)

All active plugin files are included **before** `plugins_loaded` fires, so at `plugins_loaded@10` the EPLabs `@11` callback already exists in `$wp_filter`. Look it up and add 1:

```php
protected function get_reclaim_priority(): int {
    global $wp_filter;

    $fallback = 20; // comfortably after add-ons, as a safety net

    if ( empty( $wp_filter['plugins_loaded'] ) ) {
        return $fallback;
    }

    foreach ( $wp_filter['plugins_loaded']->callbacks as $priority => $callbacks ) {
        if ( in_array( self::EP_LABS_HOOK, array_column( $callbacks, 'function' ), true ) ) {
            return (int) $priority + 1;
        }
    }

    return $fallback;
}
```

Then:

```php
add_action( 'plugins_loaded', [ $this, 'reclaim' ], $this->get_reclaim_priority() );
```

Because this runs *inside* `plugins_loaded@10`, scheduling a callback at a not-yet-processed priority (e.g. 12) is picked up correctly by `WP_Hook` in the same dispatch.

**Trade-off:** dynamic +1 runs the reclaim immediately after EPLabs (nice — downstream `plugins_loaded` hooks see the corrected registry sooner) but couples to EPLabs' function-name string. If EPLabs renames that function the lookup misses and we fall back to `20`, which still guarantees correctness (setup happens later, on `init@0`). This hybrid — **dynamic when detectable, `20` otherwise** — satisfies "look it up and +1" while staying robust.

### Q3 — Proper `search_algorithm` suppression via filters (no `unset`)

EP core exposes exactly the hooks needed; no registry mutation required.

```php
// Prevent activation -> setup_features() never calls its setup() -> no duplicate
// ep_post_search_algorithm hook, no conflicting settings applied.
add_filter( 'ep_feature_active', function ( $active, $settings, $feature ) {
    if ( $this->is_superseded( $feature->slug ) ) {
        return false;
    }
    return $active;
}, 10, 3 );

// Hide the now-redundant dashboard card.
add_filter( 'ep_feature_is_visible', function ( $visible, $slug ) {
    if ( $this->is_superseded( $slug ) ) {
        return false;
    }
    return $visible;
}, 10, 2 );
```

where `is_superseded( $slug )` returns true when `$slug` is in `$superseded_features` **and** its target EP slug (`semantic_search`) is registered:

```php
protected function is_superseded( string $slug ): bool {
    if ( ! isset( $this->superseded_features[ $slug ] ) ) {
        return false;
    }
    return (bool) \ElasticPress\Features::factory()
        ->get_registered_feature( $this->superseded_features[ $slug ] );
}
```

**Why this is correct:** `Features::setup_features()` (`Features.php:405-419`) only calls `$feature->setup()` when `$feature->is_active()` is true. Forcing `ep_feature_active` to `false` for `search_algorithm` short-circuits that, so EPLabs' `SearchAlgorithm::setup()` is never invoked and never adds its `ep_post_search_algorithm` filter — regardless of that class's own internal logic. `ep_feature_is_visible=false` removes the duplicate card from the dashboard. The feature object harmlessly remains in `registered_features` (inactive, invisible), so we never touch the public array directly. Presence-based per the locked decision: suppression is tied to EP **registering** `semantic_search`, not to it being active.

---

## Net behavior WITH both plugins active (after changes)

| Feature | Outcome | Mechanism |
|---|---|---|
| `semantic_search`, `vector_embeddings`, `ai_search_summary` | **EP main wins** | Reclaim (re-register EP instance after EPLabs) |
| `search_algorithm` | **Neutralized** (inactive + hidden) | `ep_feature_active`/`ep_feature_is_visible` filters |
| All other EPLabs features | **Unchanged — EPLabs wins** (priority 11) | Not touched |

---

## Deliberately avoided

- No changes to EPLabs (per constraint).
- No changes to EP core `Features`/`Feature` classes — only additive: a new class + hooks registered from `register_indexable_posts()`.
- No direct mutation of the `registered_features` array — suppression uses documented public filters.
- No hardcoded priority — derived from EPLabs' live hook, with a `20` fallback.

## Known trade-offs

- **Last-word race:** correctness depends on running after EPLabs. Mitigated by the dynamic +1 lookup and the `20` fallback. If some third plugin also reclaims these slugs at an even higher priority, it would win — acceptable and expected for these slugs.
- **Function-name coupling** in the dynamic lookup; falls back safely to `20`.
- **Requirement #3 is intentionally narrow:** only the four named AI features are governed. Future overlaps default to EPLabs winning until added to `$protected_features` / `$superseded_features`.
- **Presence-based supersession:** if EP registers `semantic_search` but a user never enables it, EPLabs' `search_algorithm` stays suppressed. Chosen behavior ("feature has moved") over falling back to the legacy selector.

---

## Verification after implementation

- Activate both plugins; confirm `get_registered_feature('semantic_search'|'vector_embeddings'|'ai_search_summary')` each returns an `ElasticPress\Feature\…` instance, **not** `ElasticPressLabs\Feature\…`.
- Confirm only EP's SemanticSearch hooks `ep_post_search_algorithm` (no duplicate EPLabs SearchAlgorithm hook or dashboard card).
- Confirm EPLabs-unique features (BooleanSearchOperators, CoAuthorsPlus, ExternalContent, GeoLocation, MetaKeyPattern, SearchTemplates, Users, WooCommerceSubscriptionSearch) still appear and function.
- With only EP main active (EPLabs off): confirm reclaim is a no-op (`instanceof` guard) and `search_algorithm` filters are inert (no such slug registered).
- Temporarily rename/relocate the EPLabs hook to confirm the priority lookup falls back to `20` and behavior is unchanged.

---

## Key file references

| File | Line | Role |
|------|------|------|
| `elasticpress/elasticpress.php` | 74 | PSR-4-ish autoloader → new `includes/classes/FeaturePrecedence.php` |
| `elasticpress/elasticpress.php` | 144, 205-214, 240 | EP registers features on `plugins_loaded` @ 10; wiring point for `FeaturePrecedence::factory()->setup()` |
| `elasticpress/includes/classes/Features.php` | 39 | `setup_features()` on `init@0` (after `plugins_loaded`) |
| `elasticpress/includes/classes/Features.php` | 112 | `register_feature()` overwrites by slug |
| `elasticpress/includes/classes/Features.php` | 405-419 | `setup_features()` calls `setup()` only when `is_active()` |
| `elasticpress/includes/classes/Feature.php` | 263 | `ep_feature_active` filter (suppression) |
| `elasticpress/includes/classes/Feature.php` | 370 | `ep_feature_is_visible` filter (hide card) |
| `elasticpress/includes/classes/Feature/SemanticSearch/SemanticSearch.php` | 125 | Hooks `ep_post_search_algorithm`; owns `search_algorithm_version` |
| `elasticpress-labs/includes/functions/core.php` | 27, 139, 193-200 | EPLabs registers on `plugins_loaded` @ 11 (read-only reference) |
| `elasticpress-labs/includes/classes/Feature/SearchAlgorithm.php` | 83 | Also hooks `ep_post_search_algorithm` (conflict being neutralized) |
