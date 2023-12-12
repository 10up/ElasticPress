# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](https://keepachangelog.com/).

## [Unreleased]

<!--
### Added
### Changed
### Deprecated
### Removed
### Fixed
### Security
-->

## [5.0.1] - 2023-12-XX

### Added
* Failed queries in the Index Health page will now be outputted with their error messages. Props [@felipeelia](https://github.com/felipeelia) and [@pvnanini](https://github.com/pvnanini) via [#3776](https://github.com/10up/ElasticPress/pull/3776).

### Fixed
* Queries failing due to a "request body is required" error. Props [@felipeelia](https://github.com/felipeelia) via [#3770](https://github.com/10up/ElasticPress/pull/3770).
* Fatal error when site has a bad cookie. Props [@burhandodhy](https://github.com/burhandodhy) via [#3778](https://github.com/10up/ElasticPress/pull/3778).
* Broken i18n of some strings. Props [@felipeelia](https://github.com/felipeelia) and [@iazema](https://github.com/iazema) via [#3780](https://github.com/10up/ElasticPress/pull/3780).
* PHP Warning on term archive pages when the term was not found. Props [@felipeelia](https://github.com/felipeelia) and [@Igor-Yavych](https://github.com/Igor-Yavych) via [#3777](https://github.com/10up/ElasticPress/pull/3777).
* PHP warning when using block themes. Props [@felipeelia](https://github.com/felipeelia) and [@tropicandid](https://github.com/tropicandid) via [#3781](https://github.com/10up/ElasticPress/pull/3781).
* Several typos. Props [@szepeviktor](https://github.com/szepeviktor) via [#3750](https://github.com/10up/ElasticPress/pull/3750).
* Index cleanup process - offset being zeroed too late. Props [@pknap](https://github.com/pknap) via [#3765](https://github.com/10up/ElasticPress/pull/3765).
* PHP warning in site health page. Props [@turtlepod](https://github.com/turtlepod) via [#3758](https://github.com/10up/ElasticPress/pull/3758).
* ReactDOM.render is no longer supported in React 18. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3686](https://github.com/10up/ElasticPress/pull/3686).
* E2e tests with WordPress 6.4. Props [@felipeelia](https://github.com/felipeelia) via [#3771](https://github.com/10up/ElasticPress/pull/3771).
* PHP Setup in GitHub Actions. Props [@felipeelia](https://github.com/felipeelia) via [#3784](https://github.com/10up/ElasticPress/pull/3784).

## [5.0.0] - 2023-11-01

**ElasticPress 5.0.0 contains some important changes. Make sure to read these highlights before upgrading:**
- This version does not require a full reindex but it is recommended, especially for websites using synonyms containing spaces. See [#3610](https://.github.com/10up/ElasticPress/pull/3610).
- Meta keys are not indexed by default anymore. The new Weighting Dashboard allows admin users to mark meta fields as indexables. The new `ep_prepare_meta_allowed_keys` filter allows to add meta keys programmatically. See [#3068](https://github.com/10up/ElasticPress/pull/3068).
- Features now have their fields declared in JSON. Custom features may need to implement the `set_settings_schema()` method to work. See [#3655](https://github.com/10up/ElasticPress/pull/3655).
- The `Users` feature was moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin and is no longer available in this plugin. If you use this feature, make sure to install and configure EP Labs before upgrading. See [#3670](https://github.com/10up/ElasticPress/pull/3670).
- The `Terms` and `Comments` features are now hidden by default for sites that do not have them active yet. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info. See [#3691](https://github.com/10up/ElasticPress/pull/3691).
- New minimum versions (see [#3668](https://github.com/10up/ElasticPress/pull/3668)) are:
	||Min|Max|
	|---|:---:|:---:|
	|Elasticsearch|5.2|Unset|
	|WordPress|6.0+|latest|
	|PHP|7.4+|latest|

### Added
* New Sync page. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@apurvrdx1](https://github.com/apurvrdx1), [@brandwaffle](https://github.com/brandwaffle), [@anjulahettige](https://github.com/anjulahettige), [@burhandodhy](https://github.com/burhandodhy), and [@MARQAS](https://github.com/MARQAS) via [#3657](https://github.com/10up/ElasticPress/pull/3657) and [#3735](https://github.com/10up/ElasticPress/pull/3735).
* New feature settings screen. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@apurvrdx1](https://github.com/apurvrdx1), [@brandwaffle](https://github.com/brandwaffle), and [@anjulahettige](https://github.com/anjulahettige) via [#3712](https://github.com/10up/ElasticPress/pull/3712).
* New weighting dashboard with support for making meta fields searchable. Props [@JakePT](https://github.com/JakePT), [@mehidi258](https://github.com/mehidi258), and [@felipeelia](https://github.com/felipeelia) via [#3068](https://github.com/10up/ElasticPress/pull/3068).
* New Date Filter Block. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia) via [#3700](https://github.com/10up/ElasticPress/pull/3700).
* Sync history to the Sync page. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@apurvrdx1](https://github.com/apurvrdx1), [@brandwaffle](https://github.com/brandwaffle), and [@anjulahettige](https://github.com/anjulahettige) via [#3664](https://github.com/10up/ElasticPress/pull/3664).
* Final status of syncs (success, with errors, failed, or aborted.) Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3669](https://github.com/10up/ElasticPress/pull/3669).
* REST API endpoint at `elasticpress/v1/features` for updating feature settings. Props [@JakePT](https://github.com/JakePT) via [#3676](https://github.com/10up/ElasticPress/pull/3676).
* New `ElasticsearchErrorInterpreter` class. Props [@felipeelia](https://github.com/felipeelia) via [#3661](https://github.com/10up/ElasticPress/pull/3661).
* New `default_search` analyzer to differentiate what is applied during sync and search time. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS) via [#3610](https://github.com/10up/ElasticPress/pull/3610) and [#3703](https://github.com/10up/ElasticPress/pull/3703).
* The sync page now describes what triggered the current sync, and previous syncs. Props [@JakePT](https://github.com/JakePT) via [#3722](https://github.com/10up/ElasticPress/pull/3722).
* Weighting and Synonyms Dashboards to multisites. Props [@felipeelia](https://github.com/felipeelia) via [#3724](https://github.com/10up/ElasticPress/pull/3724).
* No-cache headers to sync calls. Props [@felipeelia](https://github.com/felipeelia) via [#3731](https://github.com/10up/ElasticPress/pull/3731).

### Changed
* Abstracted Sync page logic into a provider pattern. Props [@JakePT](https://github.com/JakePT) via [#3630](https://github.com/10up/ElasticPress/pull/3630).
* Moved syncing from an `admin-ajax.php` callback to a custom REST API endpoint with support for additional arguments. Props [@JakePT](https://github.com/JakePT) via [#3643](https://github.com/10up/ElasticPress/pull/3643).
* Store previous syncs info, changed option name from `ep_last_index` to `ep_sync_history`. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3647](https://github.com/10up/ElasticPress/pull/3647).
* Features settings declared as JSON. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3655](https://github.com/10up/ElasticPress/pull/3655).
* Tweaked layout and notifications style on the Status Report screen for consistency with the updated Sync page. Props [@JakePT](https://github.com/JakePT) via [#3662](https://github.com/10up/ElasticPress/pull/3662).
* Moved REST API endpoint definitions to controller classes. Props [@JakePT](https://github.com/JakePT) via [#3650](https://github.com/10up/ElasticPress/pull/3650).
* SyncManager array queues are now indexed by the blog ID. Props [@sathyapulse](https://github.com/sathyapulse) and [@felipeelia](https://github.com/felipeelia) via [#3689](https://github.com/10up/ElasticPress/pull/3689).
* Comments and Terms are now hidden by default. Props [@felipeelia](https://github.com/felipeelia) via [#3691](https://github.com/10up/ElasticPress/pull/3691).
* WooCommerce-related hooks are now removed when switching to a site that does not have WC active. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS) via [#3688](https://github.com/10up/ElasticPress/pull/3688).
* Run e2e tests against the minimum supported WordPress version. Props [@felipeelia](https://github.com/felipeelia) via [#3540](https://github.com/10up/ElasticPress/pull/3540).
* Several tweaks in the Features settings API. Props [@JakePT](https://github.com/JakePT) via [#3708](https://github.com/10up/ElasticPress/pull/3708) and [#3709](https://github.com/10up/ElasticPress/pull/3709).
* EP Settings are now reverted if it is not possible to connect to the new ES Server. Props [@felipeelia](https://github.com/felipeelia), [@burhandodhy](https://github.com/burhandodhy), and [@scottbuscemi](https://github.com/scottbuscemi) via [#3684](https://github.com/10up/ElasticPress/pull/3684).
* Node packages updated. Props [@felipeelia](https://github.com/felipeelia) via [#3706](https://github.com/10up/ElasticPress/pull/3706).
* Updated the labels of feature settings and options for consistency and clarity. Props [@JakePT](https://github.com/JakePT) via [#3721](https://github.com/10up/ElasticPress/pull/3721).
* Depending on the requirements, some feature settings are now saved to be applied after a full sync. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3720](https://github.com/10up/ElasticPress/pull/3720).
* Minimum requirements. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#3668](https://github.com/10up/ElasticPress/pull/3668).
* Old features will have their settings displayed based on their default setting values. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3728](https://github.com/10up/ElasticPress/pull/3728).
* Radio and checkbox settings were changed from booleans to strings. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3730](https://github.com/10up/ElasticPress/pull/3730).
* The troubleshooting article link was updated. Props [@felipeelia](https://github.com/felipeelia) and [@anjulahettige](https://github.com/anjulahettige) via [#3748](https://github.com/10up/ElasticPress/pull/3748).

### Deprecated
* The `IndexHelper::get_last_index` method was replaced by `IndexHelper::get_last_sync`. See [#3647](https://github.com/10up/ElasticPress/pull/3647).
* The `FailedQueries::maybe_suggest_solution_for_es` method was replaced by `ElasticsearchErrorInterpreter::maybe_suggest_solution_for_es`. See [#3661](https://github.com/10up/ElasticPress/pull/3661).
* `Weighting::render_settings_section`, `Weighting::handle_save`, `Weighting::redirect`, and `Weighting::save_weighting_configuration` were deprecated in favor of React components. See [#3068](https://github.com/10up/ElasticPress/pull/3068).

### Removed
* Users-related files from the main plugin. Props [@felipeelia](https://github.com/felipeelia) via [#3670](https://github.com/10up/ElasticPress/pull/3670).
* Removed mapping files related to older versions of Elasticsearch. Props [@MARQAS](https://github.com/MARQAS) via [#3704](https://github.com/10up/ElasticPress/pull/3704).

### Fixed
* Docblock for the `ep_facet_renderer_class` filter. Props [@misfist](https://github.com/misfist) via [#3696](https://github.com/10up/ElasticPress/pull/3696).
* Instant Results console warning. Props [@burhandodhy](https://github.com/burhandodhy) via [#3687](https://github.com/10up/ElasticPress/pull/3687).
* Total fields limit message interpretation. Props [@felipeelia](https://github.com/felipeelia) [@JakePT](https://github.com/JakePT) via [#3702](https://github.com/10up/ElasticPress/pull/3702).
* End to end tests intermittent failures. Props [@felipeelia](https://github.com/felipeelia) via [#3572](https://github.com/10up/ElasticPress/pull/3572).
* React warning on Sync page. Props [@burhandodhy](https://github.com/burhandodhy) via [#3718](https://github.com/10up/ElasticPress/pull/3718).
* Content was not showing properly on the tooltop on install page. Props [@burhandodhy](https://github.com/burhandodhy) via [#3725](https://github.com/10up/ElasticPress/pull/3725).
* Redirect to correct sync url after enabling feature that requires a new sync. Props [@burhandodhy](https://github.com/burhandodhy) via [#3726](https://github.com/10up/ElasticPress/pull/3726).
* Post type setting wasn't respected during sync. Props [@burhandodhy](https://github.com/burhandodhy) via [#3727](https://github.com/10up/ElasticPress/pull/3727).
* Fix a JS error appearing when sync requests are intentionally stopped. Props [@burhandodhy](https://github.com/burhandodhy) via [#3736](https://github.com/10up/ElasticPress/pull/3736).
* Features description copy. Props [@felipeelia](https://github.com/felipeelia), [@burhandodhy](https://github.com/burhandodhy), and [@MARQAS](https://github.com/MARQAS) via [#3737](https://github.com/10up/ElasticPress/pull/3737).
* Endpoint URL field is not a URL type field. Props [@burhandodhy](https://github.com/burhandodhy) via [#3733](https://github.com/10up/ElasticPress/pull/3733).
* WooCommerce feature not autoactivating. Props [@felipeelia](https://github.com/felipeelia) via [#3739](https://github.com/10up/ElasticPress/pull/3739).
* Elasticsearch errors interpretation. Props [@felipeelia](https://github.com/felipeelia) via [#3741](https://github.com/10up/ElasticPress/pull/3741).
* Deactivating a feature via WP-CLI also takes into account draft states. Props [@felipeelia](https://github.com/felipeelia) via [#3749](https://github.com/10up/ElasticPress/pull/3749).

### Security

## [4.7.2] - 2023-10-10

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* New `ep_highlight_number_of_fragments` filter. Props [@dgnorrod](https://github.com/dgnorrod) and [@felipeelia](https://github.com/felipeelia) via [#3681](https://github.com/10up/ElasticPress/pull/3681).
* >=PHP 7.0 version check. Props [@bmarshall511](https://github.com/bmarshall511) and [@felipeelia](https://github.com/felipeelia) via [#3641](https://github.com/10up/ElasticPress/pull/3641).
* GitHub action to automatically open a new issue when a new version of WordPress is released. Props [@felipeelia](https://github.com/felipeelia) via [#3666](https://github.com/10up/ElasticPress/pull/3666).

### Removed
* Unnecessary aliases in use statements. Props [@felipeelia](https://github.com/felipeelia) via [#3671](https://github.com/10up/ElasticPress/pull/3671).

### Fixed
* Calls to `ep_woocommerce_default_supported_post_types` were ignored. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS) via [#3679](https://github.com/10up/ElasticPress/pull/3679).
* WooCommerce Orders search field disappearing when Orders Autosuggest receives an unexpected response from ElasticPress.io. Props [@JakePT](https://github.com/JakePT) and [@anjulahettige](https://github.com/anjulahettige) via [#3682](https://github.com/10up/ElasticPress/pull/3682).
* Call composer while building docs. Props [@felipeelia](https://github.com/felipeelia) via [#3625](https://github.com/10up/ElasticPress/pull/3625).
* Make sure `post__not_in` and `post_status` are translated into arrays, not objects. Props [@felipeelia](https://github.com/felipeelia) via [#3652](https://github.com/10up/ElasticPress/pull/3652) and [#3680](https://github.com/10up/ElasticPress/pull/3680).
* Updated phpDoc entries. Props [@renatonascalves](https://github.com/renatonascalves) via [#3635](https://github.com/10up/ElasticPress/pull/3635).
* Docblock for `Utils\get_option` return type. Props [@felipeelia](https://github.com/felipeelia) via [#3653](https://github.com/10up/ElasticPress/pull/3653).
* Docblock for `ep_capability` and `ep_network_capability` filters. Props [@burhandodhy](https://github.com/burhandodhy) via [#3685](https://github.com/10up/ElasticPress/pull/3685).
* PHP warning related to the Autosuggest template generation. Props [@felipeelia](https://github.com/felipeelia) via [#3651](https://github.com/10up/ElasticPress/pull/3651).
* WooCommerce unit tests running multiple times. Props [@felipeelia](https://github.com/felipeelia) via [#3656](https://github.com/10up/ElasticPress/pull/3656).
* Display the meta range facet block in versions prior to WP 6.1. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS) via [#3658](https://github.com/10up/ElasticPress/pull/3658).
* Number of expected arguments for `add_attachment` and `edit_attachment`. Props [@burhandodhy](https://github.com/burhandodhy) via [#3690](https://github.com/10up/ElasticPress/pull/3690).
* Error while running `composer install` on PHP 8. Props [@burhandodhy](https://github.com/burhandodhy) via [#3683](https://github.com/10up/ElasticPress/pull/3683).

### Security
* Bumped `composer/composer` from 2.5.8 to 2.6.4. Props [@dependabot](https://github.com/dependabot) via [#3672](https://github.com/10up/ElasticPress/pull/3672).

## [4.7.1] - 2023-08-31

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* Synonyms and weighting settings added to the status report. Props [@felipeelia](https://github.com/felipeelia) via [#3609](https://github.com/10up/ElasticPress/pull/3609).

### Changed
* Composer packages are namespaced by Strauss. Props [@felipeelia](https://github.com/felipeelia) and [@junaidbhura](https://github.com/junaidbhura) via [#3621](https://github.com/10up/ElasticPress/pull/3621).
* E2e tests now log the formatted query info from Debug Bar ElasticPress. Props [@felipeelia](https://github.com/felipeelia) via [#3613](https://github.com/10up/ElasticPress/pull/3613).

### Fixed
* WooCommerce products sorted by popularity are now always sorted in a descending order. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3619](https://github.com/10up/ElasticPress/pull/3619).
* E2e tests with WordPress 6.3. Props [@felipeelia](https://github.com/felipeelia) via [#3599](https://github.com/10up/ElasticPress/pull/3599).

## [4.7.0] - 2023-08-10

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* Exclude Media Attachments from search results. Props [@burhandodhy](https://github.com/burhandodhy) via [#3539](https://github.com/10up/ElasticPress/pull/3539).
* New `Default to Site Language` option in the language dropdown in ElasticPress' settings page. Props [@felipeelia](https://github.com/felipeelia) via [#3550](https://github.com/10up/ElasticPress/pull/3550).
* Compatibility with block themes for the Facet meta blocks. Props [@felipeelia](https://github.com/felipeelia) via [#3498](https://github.com/10up/ElasticPress/pull/3498).
* Integrate Did You Mean feature in the Instant Results. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT) via [#3564](https://github.com/10up/ElasticPress/pull/3564).
* All blocks now support styling features in themes that support them. Props [@JakePT](https://github.com/JakePT) via [#3403](https://github.com/10up/ElasticPress/pull/3403) and [#3584](https://github.com/10up/ElasticPress/pull/3584).
* Descriptions and keywords have been added to all blocks. Props [@JakePT](https://github.com/JakePT) via [#3403](https://github.com/10up/ElasticPress/pull/3403).
* New `ep_stop` filter, that changes the stop words used according to the language set. Props [@felipeelia](https://github.com/felipeelia) via [#3549](https://github.com/10up/ElasticPress/pull/3549).
* New `get-index-settings` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia) via [#3547](https://github.com/10up/ElasticPress/pull/3547).
* New `ep_facet_tax_special_slug_taxonomies` filter. Props [@oscarssanchez](https://github.com/oscarssanchez) via [#3506](https://github.com/10up/ElasticPress/pull/3506).
* New `--stop-on-error` flag to the `sync` command. Props [@oscarssanchez](https://github.com/oscarssanchez) via [#3500](https://github.com/10up/ElasticPress/pull/3500).
* New `get` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia) via [#3567](https://github.com/10up/ElasticPress/pull/3567).
* Transient utility functions. Props [@felipeelia](https://github.com/felipeelia) via [#3551](https://github.com/10up/ElasticPress/pull/3551).
* Indices' language settings in status reports. Props [@felipeelia](https://github.com/felipeelia) via [#3552](https://github.com/10up/ElasticPress/pull/3552).
* Initial changes to implement a DI Container. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott) via [#3559](https://github.com/10up/ElasticPress/pull/3559).
* New `$only_indexable` parameter to the `Utils\get_sites()` function. Props [@felipeelia](https://github.com/felipeelia) via [#3577](https://github.com/10up/ElasticPress/pull/3577).

### Changed
* WooCommerce feature only integrates with queries that are the main query, a search, or have ep_integrate set as true. Props [@felipeelia](https://github.com/felipeelia) via [#3546](https://github.com/10up/ElasticPress/pull/3546).
* Miscellaneous changes to all blocks, including their category, names, and code structure. Props [@JakePT](https://github.com/JakePT), [@oscarssanchez](https://github.com/oscarssanchez), and [@felipeelia](https://github.com/felipeelia) via [#3403](https://github.com/10up/ElasticPress/pull/3403).
* The Facets feature was renamed to Filters. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia) via [#3403](https://github.com/10up/ElasticPress/pull/3403), [#3581](https://github.com/10up/ElasticPress/pull/3581), and [#3597](https://github.com/10up/ElasticPress/pull/3597).
* The WooCommerce feature was refactored, separating code related to products and orders. Props [@felipeelia](https://github.com/felipeelia) via [#3502](https://github.com/10up/ElasticPress/pull/3502).
* Transients deletion during uninstall. Props [@felipeelia](https://github.com/felipeelia) via [#3548](https://github.com/10up/ElasticPress/pull/3548).
* Bump Elasticsearch version to 7.10.2 for E2E tests. Props [@burhandodhy](https://github.com/burhandodhy) via [#3556](https://github.com/10up/ElasticPress/pull/3556) and [#3561](https://github.com/10up/ElasticPress/pull/3561).
* Refactor `get_settings()` usage inside ElasticPress features. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#3558](https://github.com/10up/ElasticPress/pull/3558).
* In a multisite, if a site is indexable or not is now stored in site meta, instead of a blog option. Props [@felipeelia](https://github.com/felipeelia) via [#3571](https://github.com/10up/ElasticPress/pull/3571).
* Autosuggest authenticated requests are not cached anymore and are only sent during the sync process or when the weighting dashboard is saved. Props [@felipeelia](https://github.com/felipeelia) and [@kovshenin](https://github.com/kovshenin) via [#3566](https://github.com/10up/ElasticPress/pull/3566).
* Use `createRoot` instead of `render` to render elements. Props [@oscarssanchez](https://github.com/oscarssanchez), [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia) via [#3573](https://github.com/10up/ElasticPress/pull/3573) and [#3595](https://github.com/10up/ElasticPress/pull/3595).
* Moved methods to abstract Facet classes. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#3499](https://github.com/10up/ElasticPress/pull/3499).
* Only display available languages in the Settings screen. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3587](https://github.com/10up/ElasticPress/pull/3587).
* WooCommerce feature description. Props [@brandwaffle](https://github.com/brandwaffle), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT) via [#3592](https://github.com/10up/ElasticPress/pull/3592).

### Deprecated
* `Autosuggest::delete_cached_query()` was deprecated without a replacement. See [#3566](https://github.com/10up/ElasticPress/pull/3566).
* `EP_Uninstaller::delete_related_posts_transients()` and `EP_Uninstaller::delete_total_fields_limit_transients()` was merged into `EP_Uninstaller::delete_transients_by_name`. See [#3548](https://github.com/10up/ElasticPress/pull/3548).
* The `ep_woocommerce_default_supported_post_types` filter was split into `ep_woocommerce_orders_supported_post_types` and `ep_woocommerce_products_supported_post_types`. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* The `ep_woocommerce_supported_taxonomies` filter is now `ep_woocommerce_products_supported_taxonomies`. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* All old `WooCommerce\Orders` methods were migrated to the new `WooCommerce\OrdersAutosuggest` class. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* The `Elasticsearch::clear_total_fields_limit_cache()` method was replaced by `Elasticsearch::clear_index_settings_cache()`. See [#3552](https://github.com/10up/ElasticPress/pull/3552).
* Several methods that were previously part of the `WooCommerce\WooCommerce` class were moved to the new `WooCommerce\Product` class. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* Several methods that were specific to Facet types were moved to the new `Block` and `Renderer` abstract classes. See [#3499](https://github.com/10up/ElasticPress/pull/3499).

### Fixed
* Same error message being displayed more than once on the Dashboard sync. Props [@felipeelia](https://github.com/felipeelia), [@MARQAS](https://github.com/MARQAS), [@tott](https://github.com/tott), and [@wildberrylillet](https://github.com/wildberrylillet) via [#3557](https://github.com/10up/ElasticPress/pull/3557).
* Sync media item when attaching or detaching media. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#3562](https://github.com/10up/ElasticPress/pull/3562).
* Display "Loading results" instead of "0 results" on first search using Instant Results. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@JakePT](https://github.com/JakePT) via [#3568](https://github.com/10up/ElasticPress/pull/3568).
* Highlighting returning inaccurate post title when partial/no term match on Instant Results. Props [@oscarssanchez](https://github.com/oscarssanchez), [@JakePT](https://github.com/JakePT), and [@tomi10up](https://github.com/tomi10up) via [#3575](https://github.com/10up/ElasticPress/pull/3575).
* Warning in Orders Autosuggest: `"Creation of dynamic property $search_template is deprecated"`. Props [@burhandodhy](https://github.com/burhandodhy) via [#3591](https://github.com/10up/ElasticPress/pull/3591).
* Warning while using PHP 8.1+: `Deprecated: version_compare(): Passing null to parameter #1 ($version1) of type string is deprecated`. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3593](https://github.com/10up/ElasticPress/pull/3593).
* Warning in the metadata range facet renderer: `Undefined array key "is_preview"`. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3594](https://github.com/10up/ElasticPress/pull/3594).

### Security
* Bumped `word-wrap` from 1.2.3 to 1.2.4. Props [@dependabot](https://github.com/dependabot) via [#3543](https://github.com/10up/ElasticPress/pull/3543).
* Bumped `tough-cookie` from 4.1.2 to 4.1.3 and `@cypress/request` from 2.88.10 to 2.88.12. Props [@dependabot](https://github.com/dependabot) via [#3583](https://github.com/10up/ElasticPress/pull/3583).

## [4.6.1] - 2023-07-05

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* Add doc url for "Did You Mean" feature. Props [@burhandodhy](https://github.com/burhandodhy) via [#3529](https://github.com/10up/ElasticPress/pull/3529).

### Changed
* Use `wp_cache_supports` over `wp_cache_supports_group_flush`. Props [@spacedmonkey](https://github.com/spacedmonkey) via [#3501](https://github.com/10up/ElasticPress/pull/3501).
* Update the `ep_exclude_from_search` post meta only if it is set or has some value. Props [@MARQAS](https://github.com/MARQAS) and [@columbian-chris](https://github.com/columbian-chris) via [#3521](https://github.com/10up/ElasticPress/pull/3521).

### Fixed
* Deprecation notice in `ElasticPress\Feature\WooCommerce\Orders`. Props [@mwidmann](https://github.com/mwidmann) via [#3507](https://github.com/10up/ElasticPress/pull/3507).
* Don't apply a facet filter to the query if the filter value is empty. Props [@burhandodhy](https://github.com/burhandodhy) via [#3524](https://github.com/10up/ElasticPress/pull/3524).
* Syncing a post with empty post meta key. Props [@MARQAS](https://github.com/MARQAS) and [@oscarssanchez](https://github.com/oscarssanchez) via [#3516](https://github.com/10up/ElasticPress/pull/3516).
* Order by clauses with Elasticsearch field formats are not changed anymore. Props [@felipeelia](https://github.com/felipeelia) and [@tlovett1](https://github.com/tlovett1) via [#3512](https://github.com/10up/ElasticPress/pull/3512).
* Failed Query logs are automatically cleared on refreshing the "Status Report" page. Props [@burhandodhy](https://github.com/burhandodhy) via [#3533](https://github.com/10up/ElasticPress/pull/3533).
* Warning message on Health page when Last Sync information is not available. Props [@burhandodhy](https://github.com/burhandodhy) via [#3530](https://github.com/10up/ElasticPress/pull/3530).
* Deprecation notice: json_encode(): Passing null to parameter #2. Props [@burhandodhy](https://github.com/burhandodhy) via [#3532](https://github.com/10up/ElasticPress/pull/3532).
* Documentation of the `ep_facet_search_get_terms_args` filter. Props [@burhandodhy](https://github.com/burhandodhy) via [#3525](https://github.com/10up/ElasticPress/pull/3525).

## [4.6.0] - 2023-06-13

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* 'Did you mean' feature. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), and [@tott](https://github.com/tott) via [#3425](https://github.com/10up/ElasticPress/pull/3425) and [#3492](https://github.com/10up/ElasticPress/pull/3492).
* Facet by Post type. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@burhandodhy](https://github.com/burhandodhy) via [#3473](https://github.com/10up/ElasticPress/pull/3473).
* Two new options to disable weighting results by date in WooCommerce products related queries. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#3462](https://github.com/10up/ElasticPress/pull/3462).
* Sort meta queries by named clauses and sort by different meta types. Props [@felipeelia](https://github.com/felipeelia) and [@selim13](https://github.com/selim13) via [#3469](https://github.com/10up/ElasticPress/pull/3469).
* New `--force` flag in the sync WP-CLI command, to stop any other ongoing syncs. Props [@felipeelia](https://github.com/felipeelia) and [@tomjn](https://github.com/tomjn) via [#3479](https://github.com/10up/ElasticPress/pull/3479).
* Installers added to composer.json, so `installer-paths` works without any additional requirement. Props [@felipeelia](https://github.com/felipeelia) and [@tomjn](https://github.com/tomjn) via [#3478](https://github.com/10up/ElasticPress/pull/3478).
* Links to Patchstack Vulnerability Disclosure Program. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#3465](https://github.com/10up/ElasticPress/pull/3465).
* E2E tests for Password Protected Post. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2864](https://github.com/10up/ElasticPress/pull/2864).

### Changed
* If no index is found, the "failed queries" message will be replaced with a prompt to sync. Props [@felipeelia](https://github.com/felipeelia) via [#3436](https://github.com/10up/ElasticPress/pull/3436) and [#3466](https://github.com/10up/ElasticPress/pull/3466).
* Bumped Cypress version to v12. Props [@felipeelia](https://github.com/felipeelia) via [#3441](https://github.com/10up/ElasticPress/pull/3441).
* Documentation partially moved to Zendesk. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#3493](https://github.com/10up/ElasticPress/pull/3493).

### Fixed
* Fatal error related to the `maybe_process_error_limit` function. Props [@burhandodhy](https://github.com/burhandodhy) via [#3454](https://github.com/10up/ElasticPress/pull/3454).
* Display the error message returned by Elasticsearch if a mapping operation fails. Props [@felipeelia](https://github.com/felipeelia) via [#3464](https://github.com/10up/ElasticPress/pull/3464) and [#3495](https://github.com/10up/ElasticPress/pull/3495).
* Negative `menu_order` values being transformed into positive numbers. Props [@felipeelia](https://github.com/felipeelia) and [@navidabdi](https://github.com/navidabdi) via [#3468](https://github.com/10up/ElasticPress/pull/3468).
* Password protected content being indexed upon save when Protected Content is not active. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2864](https://github.com/10up/ElasticPress/pull/2864).
* Error message when the Elasticsearch server is not available during the put mapping operation. Props [@felipeelia](https://github.com/felipeelia) via [#3483](https://github.com/10up/ElasticPress/pull/3483).

## [4.5.2] - 2023-04-19

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* New `ep_enable_query_integration_during_indexing` filter. Props [@rebeccahum](https://github.com/rebeccahum) via [#3445](https://github.com/10up/ElasticPress/pull/3445).

### Changed
* Automated message sent in GitHub issues after 3 days of inactivity. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#3448](https://github.com/10up/ElasticPress/pull/3448).

### Fixed
* Authenticated requests for autosuggest were not being properly cached while using external object cache. Props [@felipeelia](https://github.com/felipeelia) via [#3438](https://github.com/10up/ElasticPress/pull/3438).

## [4.5.1] - 2023-04-11

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

### Added
* New `ep_instant_results_args_schema` filter for filtering Instant Results arguments schema. Props [@JakePT](https://github.com/JakePT) via [#3389](https://github.com/10up/ElasticPress/pull/3389).
* New `ep.Autosuggest.navigateCallback` JS filter for changing the behavior of a clicked element on Autosuggest. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT) via [#3419](https://github.com/10up/.ElasticPress/pull/3419).
* New `ep.Autosuggest.fetchOptions` JS filter for filtering Elasticsearch fetch configuration of Autosuggest. Props [@tlovett1](https://github.com/,tlovett1), [@MARQAS](https://github.com/MARQAS), and [@felipeelia](https://github.com/felipeelia) via [#3382](https://github.com/10up/ElasticPress/pull/3382).
* Code linting before pushing to the repository. Props [@felipeelia](https://github.com/felipeelia) via [#3411](https://github.com/10up/ElasticPress/pull/3411).
* Unit tests for the Status Reports feature. Props [@burhandodhy](https://github.com/burhandodhy) via [#3395](https://github.com/10up/ElasticPress/pull/3395).

### Changed
* Meta field facets now only filter based on fields selected on blocks. The new `ep_facet_should_check_if_allowed` filter reverts this behavior. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3430](https://github.com/10up/ElasticPress/pull/3430).

### Fixed
* Instant Results crashing when using taxonomies as facets that are attached to both searchable and non-searchable post types. Props [@JakePT](https://github.com/JakePT) via [#3386](https://github.com/10up/ElasticPress/pull/3386).
* Fatal error during plugin uninstall. Props [@felipeelia](https://github.com/felipeelia) via [#3407](https://github.com/10up/ElasticPress/pull/3407).
* Compatibility issue which prevented Instant Results from working in WordPress 6.2. Props [@JakePT](https://github.com/JakePT) via [#3417](https://github.com/10up/ElasticPress/pull/3417).
* Fatal error while syncing on older versions of WordPress. Props [@felipeelia](https://github.com/felipeelia), [@TorlockC](https://github.com/TorlockC) via [#3420](https://github.com/10up/ElasticPress/pull/3420).
* Facets removing taxonomy parameters in the URL when not using pretty permalinks. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#3422](https://github.com/10up/ElasticPress/pull/3422).
* JS errors when creating Facet blocks in WP 6.2. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3429](https://github.com/10up/ElasticPress/pull/3429).
* `ep_index_meta` option blowing up on an indexing process with many errors. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#3399](https://github.com/10up/ElasticPress/pull/3399).
* Typo in `index_output` WP-CLI command help text. Props [@bratvanov](https://github.com/bratvanov) via [#3405](https://github.com/10up/ElasticPress/pull/3405).
* React warning messages for the comments block. Props [@burhandodhy](https://github.com/burhandodhy) via [#3434](https://github.com/10up/ElasticPress/pull/3434).
* Fixed `action_edited_term` to call `kill_sync` in SyncManager for post Indexable. Props [@rebeccahum](https://github.com/rebeccahum) via [#3432](https://github.com/10up/ElasticPress/pull/3432).
* Undefined array key `'index'` during sync. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3435](https://github.com/10up/ElasticPress/pull/3435).
* Meta Range Facet Block e2e tests. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#3426](https://github.com/10up/ElasticPress/pull/3426).
* Users e2e tests using WP 6.2. Props [@felipeelia](https://github.com/felipeelia) via [#3431](https://github.com/10up/ElasticPress/pull/3431).

### Security
* Bumped `webpack` from 5.75.0 to 5.76.3. Props [@dependabot](https://github.com/dependabot) via [#3412](https://github.com/10up/ElasticPress/pull/3412).

## [4.5.0] - 2023-03-09

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

ElasticPress 4.5.0 release highlights:
* Autosuggest for WooCommerce Orders ([#3175](https://github.com/10up/ElasticPress/pull/3175), [#3308](https://github.com/10up/ElasticPress/pull/3308), [#3321](https://github.com/10up/ElasticPress/pull/3321), [#3324](https://github.com/10up/ElasticPress/pull/3324), [#3323](https://github.com/10up/ElasticPress/pull/3323), [#3310](https://github.com/10up/ElasticPress/pull/3310), [#3349](https://github.com/10up/ElasticPress/pull/3349), [#3339](https://github.com/10up/ElasticPress/pull/3339), and [#3363](https://github.com/10up/ElasticPress/pull/3363))
* New Facet by Meta Range block ([#3289](https://github.com/10up/ElasticPress/pull/3289), [#3342](https://github.com/10up/ElasticPress/pull/3342), [#3337](https://github.com/10up/ElasticPress/pull/3337), [#3361](https://github.com/10up/ElasticPress/pull/3361), [#3364](https://github.com/10up/ElasticPress/pull/3364), [#3368](https://github.com/10up/ElasticPress/pull/3368), and [#3365](https://github.com/10up/ElasticPress/pull/3365))
* ElasticPress.io messages system ([#3162](https://github.com/10up/ElasticPress/pull/3162) and [#3376](https://github.com/10up/ElasticPress/pull/3376))
* Indices of disabled features will be deleted during a full sync ([#3261](https://github.com/10up/ElasticPress/pull/3261))
* WooCommerce Queries ([#3259](https://github.com/10up/ElasticPress/pull/3259) and [#3362](https://github.com/10up/ElasticPress/pull/3362))

### Added
- Autosuggest for WooCommerce Orders. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia) via [#3175](https://github.com/10up/ElasticPress/pull/3175), [#3308](https://github.com/10up/ElasticPress/pull/3308), [#3321](https://github.com/10up/ElasticPress/pull/3321), [#3324](https://github.com/10up/ElasticPress/pull/3324), [#3323](https://github.com/10up/ElasticPress/pull/3323), [#3310](https://github.com/10up/ElasticPress/pull/3310), [#3349](https://github.com/10up/ElasticPress/pull/3349), and [#3339](https://github.com/10up/ElasticPress/pull/3339).
- New Facet by Meta Range block (currently in Beta.) Props [@felipeelia](https://github.com/felipeelia) via [#3289](https://github.com/10up/ElasticPress/pull/3289), [#3342](https://github.com/10up/ElasticPress/pull/3342), [#3337](https://github.com/10up/ElasticPress/pull/3337), [#3361](https://github.com/10up/ElasticPress/pull/3361), [#3363](https://github.com/10up/ElasticPress/pull/3363), [#3364](https://github.com/10up/ElasticPress/pull/3364), [#3368](https://github.com/10up/ElasticPress/pull/3368), and [#3365](https://github.com/10up/ElasticPress/pull/3365).
- Option to display term counts in Facets blocks. Props [@felipeelia](https://github.com/felipeelia) via [#3309](https://github.com/10up/ElasticPress/pull/3309).
- New capability for managing ElasticPress. Props [@tlovett1](https://github.com/tlovett1), [@tott](https://github.com/tott), and [@felipeelia](https://github.com/felipeelia) via [#3313](https://github.com/10up/ElasticPress/pull/3313).
- New "Download report" button in the Status Report page. Props [@felipeelia](https://github.com/felipeelia) via [#3319](https://github.com/10up/ElasticPress/pull/3319).
- ElasticPress.io messages system. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#3162](https://github.com/10up/ElasticPress/pull/3162) and [#3376](https://github.com/10up/ElasticPress/pull/3376).
- WP CLI commands `get-search-template`, `put-search-template`, and `delete-search-template`. Props [@oscarssanchez](https://github.com/oscarssanchez) via [#3216](https://github.com/10up/ElasticPress/pull/3216).
- New `--status` parameter to the `get-indices` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia) via [#3261](https://github.com/10up/ElasticPress/pull/3261).
- New `ep_instant_results_per_page` filter for changing the number of results per page in Instant Results. Props [@JakePT](https://github.com/JakePT) via [#3292](https://github.com/10up/ElasticPress/pull/3292).
- Support for `post_parent__in` and `post_parent__not_in`. Props [@MARQAS](https://github.com/MARQAS) via [#3300](https://github.com/10up/ElasticPress/pull/3300).
- New `ep_sync_args` filter. Props [@felipeelia](https://github.com/felipeelia) and [@nickchomey](https://github.com/nickchomey) via [#3317](https://github.com/10up/ElasticPress/pull/3317).
- "Full Sync" (Yes/No) to the Last Sync section in Status Report. Props [@felipeelia](https://github.com/felipeelia) via [#3304](https://github.com/10up/ElasticPress/pull/3304).
- New `ep_user_register_feature` and `ep_feature_is_visible` filters. Props [@felipeelia](https://github.com/felipeelia) via [#3334](https://github.com/10up/ElasticPress/pull/3334).
- Requests now have a new header called `X-ElasticPress-Request-ID` to help with debugging. Props [@felipeelia](https://github.com/felipeelia) via [#3307](https://github.com/10up/ElasticPress/pull/3307).
- Compatibility with `'orderby' => 'none'` in WP_Query. Props [@felipeelia](https://github.com/felipeelia) via [#3318](https://github.com/10up/ElasticPress/pull/3318).
- Unit tests related to the `ep_weighting_configuration_for_search` filter. Props [@felipeelia](https://github.com/felipeelia) via [#3303](https://github.com/10up/ElasticPress/pull/3303).
- New Unit tests for the WooCoomerce feature. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3259](https://github.com/10up/ElasticPress/pull/3259).
- Description for the `--network-wide` flag in WP-CLI commands. Props [@MARQAS](https://github.com/MARQAS) via [#3350](https://github.com/10up/ElasticPress/pull/3350).
- New `is_available()` helper method in the Feature class. Props [@burhandodhy](https://github.com/burhandodhy) via [#3356](https://github.com/10up/ElasticPress/pull/3356).

### Changed
- Indices of disabled features will be deleted during a full sync. Mappings of needed but non-existent indices will be added even during a regular sync. Props [@felipeelia](https://github.com/felipeelia) via [#3261](https://github.com/10up/ElasticPress/pull/3261).
- Reduced number of WooCommerce product queries automatically integrated with ElasticPress. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3259](https://github.com/10up/ElasticPress/pull/3259) and [#3362](https://github.com/10up/ElasticPress/pull/3362).
- The number of results per page in Instant Results now matches the site's posts per page setting. Props [@JakePT](https://github.com/JakePT) via [#3292](https://github.com/10up/ElasticPress/pull/3292).
- Under the hood improvements to the structure of Instant Results. Props [@JakePT](https://github.com/JakePT) via [#3159](https://github.com/10up/ElasticPress/pull/3159) and [#3293](https://github.com/10up/ElasticPress/pull/3293).
- Apply the "Exclude from Search" filter directly on ES Query. Props [@burhandodhy](https://github.com/burhandodhy) via [#3266](https://github.com/10up/ElasticPress/pull/3266).
- Avoid using Elasticsearch if query has an unsupported orderby clause. Props [@burhandodhy](https://github.com/burhandodhy) via [#3273](https://github.com/10up/ElasticPress/pull/3273).
- E2e tests split into 2 groups to be executed in parallel. Props [@iamchughmayank](https://github.com/iamchughmayank), [@burhandodhy](https://github.com/burhandodhy), and [@felipeelia](https://github.com/felipeelia) via [#3283](https://github.com/10up/ElasticPress/pull/3283).
- Filter command flags using `get_flag_value()`. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#3301](https://github.com/10up/ElasticPress/pull/3301).
- Code Standards are now applied to the test suite as well. Props [@felipeelia](https://github.com/felipeelia) via [#3351](https://github.com/10up/ElasticPress/pull/3351).
- Text displayed when a feature that requires a sync is about to be enabled. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#3380](https://github.com/10up/ElasticPress/pull/3380).

### Removed
- Remove legacy filters `woocommerce_layered_nav_query_post_ids`, `woocommerce_unfiltered_product_ids`, and `ep_wp_query_search_cached_posts`. Props [@burhandodhy](https://github.com/burhandodhy) via [#3230](https://github.com/10up/ElasticPress/pull/3230).

### Fixed
- API requests for Instant Results sent on page load before the modal has been opened. Props [@JakePT](https://github.com/JakePT) via [#3159](https://github.com/10up/ElasticPress/pull/3159).
- Prevent search queries for coupons from using Elasticsearch. Props [@burhandodhy](https://github.com/burhandodhy) via [#3222](https://github.com/10up/ElasticPress/pull/3222).
- Thumbnails are not removed from indexed WooCommerce Products when the attachments are deleted. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT) via [#3267](https://github.com/10up/ElasticPress/pull/3267).
- Auto sync posts associated with a child term when the term parent is changed. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#3145](https://github.com/10up/ElasticPress/pull/3145).
- Status Report page firing requests to ES twice. Props [@felipeelia](https://github.com/felipeelia) via [#3265](https://github.com/10up/ElasticPress/pull/3265).
- Sanitization of Meta Queries. Props [@MARQAS](https://github.com/MARQAS) via [#3271](https://github.com/10up/ElasticPress/pull/3271).
- Facets styles not enqueued more than once. Props [@felipeelia](https://github.com/felipeelia) and [@MediaMaquina](https://github.com/MediaMaquina) via [#3306](https://github.com/10up/ElasticPress/pull/3306).
- Duplicate terms listed in Instant Results facets. Props [@felipeelia](https://github.com/felipeelia) via [#3335](https://github.com/10up/ElasticPress/pull/3335).
- Not setting the post context when indexing a post. Props [@tomjn](https://github.com/tomjn) via [#3333](https://github.com/10up/ElasticPress/pull/3333).
- Some utilitary methods in the Command class treated as WP-CLI Commands. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3320](https://github.com/10up/ElasticPress/pull/3320).
- Make the "Failed Queries" notice dismissible. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#3348](https://github.com/10up/ElasticPress/pull/3348).
- Undefined index `'elasticpress'` in the Status Report page. Props [@MARQAS](https://github.com/MARQAS) via [#3374](https://github.com/10up/ElasticPress/pull/3374).
- Undefined array key `'displayCount'` error for facet. Props [@burhandodhy](https://github.com/burhandodhy) via [#3373](https://github.com/10up/ElasticPress/pull/3373).
- Warnings on the feature setup page. Props [@burhandodhy](https://github.com/burhandodhy) via [#3377](https://github.com/10up/ElasticPress/pull/3377).

### Security
- Bumped `http-cache-semantics` from 4.1.0 to 4.1.1. Props [@dependabot](https://github.com/dependabot) via [#3295](https://github.com/10up/ElasticPress/pull/3295).
- Bumped `got` from 9.6.0 to 11.8.5 and `simple-bin-help` from 1.7.7 to 1.8.0. Props [@dependabot](https://github.com/dependabot) via [#3290](https://github.com/10up/ElasticPress/pull/3290).
- Bumped `simple-git` from 3.15.1 to 3.16.0. Props [@dependabot](https://github.com/dependabot) via [#3278](https://github.com/10up/ElasticPress/pull/3278).
- Bumped `json5` from 1.0.1 to 1.0.2. Props [@dependabot](https://github.com/dependabot) via [#3251](https://github.com/10up/ElasticPress/pull/3251).

## [4.4.1] - 2023-01-10

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code.**

This is a bug fix release.

### Added
- Node 18 support. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3224](https://github.com/10up/ElasticPress/pull/3224).
- Unit tests for WP-CLI Commands. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3202](https://github.com/10up/ElasticPress/pull/3202).
- Unit tests for the `HealthCheckElasticsearch` class, Protected Feature, and #3106. Props [@burhandodhy](https://github.com/burhandodhy) via [#3213](https://github.com/10up/ElasticPress/pull/3213),[#3183](https://github.com/10up/ElasticPress/pull/3183), and [#3184](https://github.com/10up/ElasticPress/pull/3184).

### Changed
- Detection of indexable meta fields when visiting the sync and status report pages. Props [@felipeelia](https://github.com/felipeelia), [@paoloburzacca](https://github.com/paoloburzacca), and [@burhandodhy](https://github.com/burhandodhy) via [#3215](https://github.com/10up/ElasticPress/pull/3215) and [#3250](https://github.com/10up/ElasticPress/pull/3250).
- `put-mapping` WP-CLI command returns an error message if mapping failed. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia) via [#3206](https://github.com/10up/ElasticPress/pull/3206).
- Last Sync subsection title in the Status Report page. Props [@MARQAS](https://github.com/MARQAS), [@felipeelia](https://github.com/felipeelia), and [@tomioflagos](https://github.com/tomioflagos) via [#3228](https://github.com/10up/ElasticPress/pull/3228).
- Title for Autosuggest and Instant results features, if connected to an ElasticPress.io account. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), and [@NV607FOX](https://github.com/NV607FOX) via [#3207](https://github.com/10up/ElasticPress/pull/3207).
- "Exclude from search" checkbox text. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), and [@anjulahettige](https://github.com/anjulahettige) via [#3210](https://github.com/10up/ElasticPress/pull/3210).
- Visibility of the `analyze_log` method of the `FailedQueries` class. Props [@MARQAS](https://github.com/MARQAS) via [#3223](https://github.com/10up/ElasticPress/pull/3223).
- Text of the notice under the Documents feature. Props [@MARQAS](https://github.com/MARQAS) and [@NV607FOX](https://github.com/NV607FOX) via [#3212](https://github.com/10up/ElasticPress/pull/3212).
- Usage of `get_index_default_per_page` instead of a direct call to `Utils\get_option`. Props [@burhandodhy](https://github.com/burhandodhy) via [#3163](https://github.com/10up/ElasticPress/pull/3163).

### Removed
- Unnecessary `remove_filters` from the unit tests. Props [@burhandodhy](https://github.com/burhandodhy) via [#3220](https://github.com/10up/ElasticPress/pull/3220).

### Fixed
- Sync is stopped if put mapping throws an error. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia) via [#3206](https://github.com/10up/ElasticPress/pull/3206).
- Layout issue in Instant Results that would occur with small result sets. Props [@JakePT](https://github.com/JakePT) via [#3200](https://github.com/10up/ElasticPress/pull/3200).
- Issue where keyboard focus on a facet option was lost upon selection. Props [@JakePT](https://github.com/JakePT) via [#3209](https://github.com/10up/ElasticPress/pull/3209).
- JS error on Status Report page. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3187](https://github.com/10up/ElasticPress/pull/3187).
- Hooks documentation reference. Props [@burhandodhy](https://github.com/burhandodhy) via [#3244](https://github.com/10up/ElasticPress/pull/3244).
- `'current'` as value for the `'sites'` parameter. Props [@burhandodhy](https://github.com/burhandodhy), [@oscarssanchez](https://github.com/oscarssanchez), and [@anders-naslund](https://github.com/anders-naslund) via [#3243](https://github.com/10up/ElasticPress/pull/3243).
- `Uncaught ArgumentCountError: Too few arguments to function WP_CLI::halt()` message. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT) via [#3242](https://github.com/10up/ElasticPress/pull/3242).
- Queries with `post_parent` set to `0` not working correctly. Props [@JiveDig](https://github.com/JiveDig) via [#3211](https://github.com/10up/ElasticPress/pull/3211).
- Sync command exits without any error message if mapping fails. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3202](https://github.com/10up/ElasticPress/pull/3202).
- Evaluate the WP-CLI `--pretty` flag as real boolean. Props [@oscarssanchez](https://github.com/oscarssanchez) via [#3185](https://github.com/10up/ElasticPress/pull/3185).
- Remove deprecated command from the error message. Props [@burhandodhy](https://github.com/burhandodhy) via [#3194](https://github.com/10up/ElasticPress/pull/3194).
- CLI command `delete-index --network-wide` throws error when EP is not network activated. Props [@burhandodhy](https://github.com/burhandodhy) via [#3172](https://github.com/10up/ElasticPress/pull/3172).
- E2E tests for PHP 8. Props [@burhandodhy](https://github.com/burhandodhy) via [#3188](https://github.com/10up/ElasticPress/pull/3188).
- Feature title issue on the report page and notices. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT) via [#3248](https://github.com/10up/ElasticPress/pull/3248).
- Autosuggest Site Health Info containing incorrect information unrelated to Autosuggest. Props [@JakePT](https://github.com/JakePT) via [#3247](https://github.com/10up/ElasticPress/pull/3247).
- Styling of the Instant Results Facets field. Props [@JakePT](https://github.com/JakePT) via [#3249](https://github.com/10up/ElasticPress/pull/3249).

### Security
- Bumped `simple-git` from 3.6.0 to 3.15.1. Props [@dependabot](https://github.com/dependabot) via [#3190](https://github.com/10up/ElasticPress/pull/3190).

## [4.4.0] - 2022-11-29

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code.**

ElasticPress 4.4.0 release highlights:
* New Status Report page and failed queries logs ([#3130](https://github.com/10up/ElasticPress/pull/3130), [#3148](https://github.com/10up/ElasticPress/pull/3148), and [#3136](https://github.com/10up/ElasticPress/pull/3136))
* Instant Results template customization ([#2959](https://github.com/10up/ElasticPress/pull/2959))
* Facets by Meta available by default. Users should delete the 1-file plugin released with 4.3.0 ([#3071](https://github.com/10up/ElasticPress/pull/3071))
* New option to exclude posts from search ([#3100](https://github.com/10up/ElasticPress/pull/3100))
* Renamed some WP-CLI commands and added deprecation notices for the old versions (see table below)

### Added
- New Status Report page. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), [@tott](https://github.com/tott), and [@brandwaffle](https://github.com/brandwaffle) via [#3130](https://github.com/10up/ElasticPress/pull/3130), [#3148](https://github.com/10up/ElasticPress/pull/3148), and [#3154](https://github.com/10up/ElasticPress/pull/3154).
- New Query Logger to display admin notices about failed queries and the list in the new Status Report page. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@brandwaffle](https://github.com/brandwaffle) via [#3136](https://github.com/10up/ElasticPress/pull/3136) and [#3165](https://github.com/10up/ElasticPress/pull/3165).
- New option to exclude posts from search. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT) via [#3100](https://github.com/10up/ElasticPress/pull/3100), [#3156](https://github.com/10up/ElasticPress/pull/3156), and [#3161](https://github.com/10up/ElasticPress/pull/3161).
- Search Comments block. Replaces the Comments widget in the block editor. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia) via [#2986](https://github.com/10up/ElasticPress/pull/2986).
- [Instant Results] Notice when ElasticPress is network activated warning that Instant Results will not work on all sites without additional steps. Props [@JakePT](https://github.com/JakePT) via [#3149](https://github.com/10up/ElasticPress/pull/3149).
- Extra debugging information in the browser console when syncing fails and more useful error messages with a troubleshooting URL. Props [@JakePT](https://github.com/JakePT) via [#3133](https://github.com/10up/ElasticPress/pull/3133).
- New `elasticpress.InstantResults.Result` JavaScript filter for filtering the component used for Instant Results search results. Props [@JakePT](https://github.com/JakePT) via [#2959](https://github.com/10up/ElasticPress/pull/2959).
- New `window.epInstantResults.openModal()` method for developers to manually open Instant Results. Props [@JakePT](https://github.com/JakePT) via [#2987](https://github.com/10up/ElasticPress/pull/2987).
- Support for `stock_status` filter on the WooCommerce Admin Product List. Props [@felipeelia](https://github.com/felipeelia) and [@jakgsl](https://github.com/jakgsl) via [#3036](https://github.com/10up/ElasticPress/pull/3036).
- Option to toggle the term count in Instant results. Props [@MARQAS](https://github.com/MARQAS) and [@JakePT](https://github.com/JakePT) via [#3007](https://github.com/10up/ElasticPress/pull/3007).
- New `ep_autosuggest_query_args` filter, to change WP Query args of the autosuggest query template. Props [@felipeelia](https://github.com/felipeelia) via [#3038](https://github.com/10up/ElasticPress/pull/3038).
- New `ep_post_filters` filter and refactor of the `Post::format_args` method. Props [@felipeelia](https://github.com/felipeelia) via [#3044](https://github.com/10up/ElasticPress/pull/3044).
- New `get_index_settings()` method to retrieve index settings. Props [@rebeccahum](https://github.com/rebeccahum) via [#3126](https://github.com/10up/ElasticPress/pull/3126).
- New `ep_woocommerce_default_supported_post_types` and `ep_woocommerce_admin_searchable_post_types` filters. Props [@ecaron](https://github.com/ecaron) via [#3029](https://github.com/10up/ElasticPress/pull/3029).
- Add test factories for Post, User and Term. Props [@burhandodhy](https://github.com/burhandodhy) via [#3048](https://github.com/10up/ElasticPress/pull/3048).
- Unit tests to check access to custom results endpoints. Props [@burhandodhy](https://github.com/burhandodhy) via [#3022](https://github.com/10up/ElasticPress/pull/3022).
- New unit tests for the user feature. Props [@burhandodhy](https://github.com/burhandodhy) via [#3061](https://github.com/10up/ElasticPress/pull/3061).

### Changed
- Facets by Meta available by default. Props [@burhandodhy](https://github.com/burhandodhy) via [#3071](https://github.com/10up/ElasticPress/pull/3071).
- If an Elasticsearch index is missing, force a full sync.  Props [@MARQAS](https://github.com/MARQAS), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT) via [#3011](https://github.com/10up/ElasticPress/pull/3011).
- ElasticPress.io clients only need to enter the Subscription ID now. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#2976](https://github.com/10up/ElasticPress/pull/2976).
- `Renderer::order_by_selected` visibility. Props [@burhandodhy](https://github.com/burhandodhy) via [#3009](https://github.com/10up/ElasticPress/pull/3009).
- After editing a term, only sync posts if the term is associated with fewer posts than the Content Items per Index Cycle number. Props [@felipeelia](https://github.com/felipeelia), [@cmcandrew](https://github.com/cmcandrew), [@DenisFlorin](https://github.com/DenisFlorin), and [@burhandodhy](https://github.com/burhandodhy) via [#3106](https://github.com/10up/ElasticPress/pull/3106) and [#3122](https://github.com/10up/ElasticPress/pull/3122).
- The `meta_query` clause when using the `meta_key` parameter. Props [@felipeelia](https://github.com/felipeelia), [@MARQAS](https://github.com/MARQAS), and [@Greygooo](https://github.com/Greygooo) via [#2997](https://github.com/10up/ElasticPress/pull/2997).
- Facets filters are not applied in the WP Query level anymore. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3045](https://github.com/10up/ElasticPress/pull/3045) and  [#3076](https://github.com/10up/ElasticPress/pull/3076).
- To be compatible with WordPress 6.1, when passing `'all'` as the `fields` parameter of `WP_User_Query` only user IDs will be returned. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3109](https://github.com/10up/ElasticPress/pull/3109).
- `update_term_meta_cache` parameter set as false while getting terms for Facets. Props [@mae829](https://github.com/mae829) via [#3060](https://github.com/10up/ElasticPress/pull/3060).
- Small refactor of Indexables' `parse_orderby` to make it easier to read. Props [@felipeelia](https://github.com/felipeelia) via [#3042](https://github.com/10up/ElasticPress/pull/3042).
- Search algorithms descriptions. Props [@felipeelia](https://github.com/felipeelia) via [#3051](https://github.com/10up/ElasticPress/pull/3051).
- Hide taxonomies from facet block whose `show_ui` is set to false. Props [@burhandodhy](https://github.com/burhandodhy) via [#2958](https://github.com/10up/ElasticPress/pull/2958).
- Use `Utils\*_option()` when possible. Props [@rebeccahum](https://github.com/rebeccahum) via [#3078](https://github.com/10up/ElasticPress/pull/3078) and [#3081](https://github.com/10up/ElasticPress/pull/3081).
- Remove unnecessary check from `allow_excerpt_html`. Props [@burhandodhy](https://github.com/burhandodhy) via [#3093](https://github.com/10up/ElasticPress/pull/3093).
- Updated Cypress (version 9 to 10). Props [@felipeelia](https://github.com/felipeelia) via [#3066](https://github.com/10up/ElasticPress/pull/3066).
- Use factory to create comments for tests. Props [@burhandodhy](https://github.com/burhandodhy) via [#3074](https://github.com/10up/ElasticPress/pull/3074).
- Improved e2e tests performance. Props [@felipeelia](https://github.com/felipeelia) via [#3085](https://github.com/10up/ElasticPress/pull/3085).
- GitHub Action used by PHPCS. Props [@felipeelia](https://github.com/felipeelia) via [#3104](https://github.com/10up/ElasticPress/pull/3104).

### Deprecated
- The following WP-CLI commands were deprecated via [#3028](https://github.com/10up/ElasticPress/pull/3028). They will still work but with a warning.

|Old Command|New Command|
|---|---|
|wp elasticpress index|wp elasticpress sync|
|wp elasticpress get-cluster-indexes|wp elasticpress get-cluster-indices|
|wp elasticpress get-indexes|wp elasticpress get-indices|
|wp elasticpress clear-index|wp elasticpress clear-sync|
|wp elasticpress get-indexing-status|wp elasticpress get-ongoing-sync-status|
|wp elasticpress get-last-cli-index|wp elasticpress get-last-cli-sync|
|wp elasticpress stop-indexing|wp elasticpress stop-sync|

Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).

- The `sites` parameter for WP_Query, WP_Term_Query and WP_Comment_Query was deprecated in favor of the new `site__in` and `site__not_in`. Props [@burhandodhy](https://github.com/burhandodhy) via [#2991](https://github.com/10up/ElasticPress/pull/2991).

### Removed
- Compatibility code for WP < 4.6 in the Post Search feature. Props [@burhandodhy](https://github.com/burhandodhy) via [#3121](https://github.com/10up/ElasticPress/pull/3121).
- Legacy hook from unit tests. Props [@burhandodhy](https://github.com/burhandodhy) via [#3050](https://github.com/10up/ElasticPress/pull/3050).
- Time average box in the Index Health page. Props [@felipeelia](https://github.com/felipeelia) and [@alaa-alshamy](https://github.com/alaa-alshamy) via [#3115](https://github.com/10up/ElasticPress/pull/3115).
- [Protected Content] Removed post types to be indexed by default: ep-synonym, ep-pointer, wp_global_styles, wp_navigation, wp_template, and wp_template_part. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3135](https://github.com/10up/ElasticPress/pull/3135) and [#3155](https://github.com/10up/ElasticPress/pull/3155).

### Fixed
- Clicking on the Facet Term redirect to Homepage. Props [@burhandodhy](https://github.com/burhandodhy) via [#3032](https://github.com/10up/ElasticPress/pull/3032).
- Custom results are not highlighted. Props [@burhandodhy](https://github.com/burhandodhy) via [#3010](https://github.com/10up/ElasticPress/pull/3010).
- Error when passing an array of post types to WP_Comment_Query. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@MARQAS](https://github.com/MARQAS) via [#3058](https://github.com/10up/ElasticPress/pull/3058).
- Deprecated filters for search algorithms do not overwrite values set with the newer filters. Props [@felipeelia](https://github.com/felipeelia) and [@marc-tt](https://github.com/marc-tt) via [#3037](https://github.com/10up/ElasticPress/pull/3037).
- No posts returned when an invalid value was passed to the tax_query parameter. Props [@burhandodhy](https://github.com/burhandodhy) via [#3030](https://github.com/10up/ElasticPress/pull/3030).
- Incorrect excerpt when `get_the_excerpt` is called outside the Loop and Excerpt highlighting option is enabled. Props [@burhandodhy](https://github.com/burhandodhy) via [#3114](https://github.com/10up/ElasticPress/pull/3114).
- Facet returns no result for a term having accent characters. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3031](https://github.com/10up/ElasticPress/pull/3031).
- An issue where some characters in taxonomy terms would appear encoded when displayed in Instant Results. Props [@JakePT](https://github.com/JakePT) via [#3113](https://github.com/10up/ElasticPress/pull/3113).
- An issue that caused Autosuggest filter functions to no longer work. Props [@JakePT](https://github.com/JakePT) via [#3110](https://github.com/10up/ElasticPress/pull/3110).
- An issue that prevented clicking Autosuggest suggestions if they had been customized with additional markup. Props [@JakePT](https://github.com/JakePT) via [#3110](https://github.com/10up/ElasticPress/pull/3110).
- WooCommerce custom product sort order. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS) via [#2965](https://github.com/10up/ElasticPress/pull/2965).
- Network alias creation failed warning when one of the sites is deactivated. Props [@burhandodhy](https://github.com/burhandodhy) via [#3139](https://github.com/10up/ElasticPress/pull/3139).
- JS Error on widget screen. Props [@burhandodhy](https://github.com/burhandodhy) via [#3143](https://github.com/10up/ElasticPress/pull/3143).
- PHP Warning when a post has no comments. Props [@felipeelia](https://github.com/felipeelia) and [@JiveDig](https://github.com/JiveDig) via [#3127](https://github.com/10up/ElasticPress/pull/3127).
- `put-mapping --network-wide` throws error when plugin is not activated on network. Props [@burhandodhy](https://github.com/burhandodhy) via [#3041](https://github.com/10up/ElasticPress/pull/3041).
- Internationalization of strings in JavaScript files. Props [@felipeelia](https://github.com/felipeelia) via [#3079](https://github.com/10up/ElasticPress/pull/3079).
- Documentation of the `ep_woocommerce_admin_products_list_search_fields` filter. Props [@felipeelia](https://github.com/felipeelia) via [#3124](https://github.com/10up/ElasticPress/pull/3124).
- Warning if `_source` is not returned in query hit. Props [@pschoffer](https://github.com/pschoffer) via [#2992](https://github.com/10up/ElasticPress/pull/2992).
- Undefined variable `$update` on synonyms page. Props [@burhandodhy](https://github.com/burhandodhy) via [#3102](https://github.com/10up/ElasticPress/pull/3102).
- PHP 8 deprecation warning related to `uasort()` usage. Props [@burhandodhy](https://github.com/burhandodhy) via [#3091](https://github.com/10up/ElasticPress/pull/3091).
- Cypress intermittent tests failures. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#3053](https://github.com/10up/ElasticPress/pull/3053), [#3147](https://github.com/10up/ElasticPress/pull/3147), and [#3158](https://github.com/10up/ElasticPress/pull/3158).
- Fix PHP Unit Tests for PHP 8. Props [@burhandodhy](https://github.com/burhandodhy) via [#3073](https://github.com/10up/ElasticPress/pull/3073).

### Security
- Bumped `loader-utils` from 1.4.0 to 1.4.2. Props [@dependabot](https://github.com/dependabot) via [#3125](https://github.com/10up/ElasticPress/pull/3125) and [#3137](https://github.com/10up/ElasticPress/pull/3137).

## [4.3.1] - 2022-09-27
This release fixes some bugs and also adds some new filters.

### Added
- New `ep_facet_taxonomy_terms` filter to filter the Facet terms. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2989](https://github.com/10up/ElasticPress/pull/2989).
- Added `ep.Autosuggest.itemHTML`, `ep.Autosuggest.listHTML`, `ep.Autosuggest.query`, and `ep.Autosuggest.element` JavaScript hooks to Autosuggest and migrated filter functions to hook callbacks for backwards compatibility. Props [@JakePT](https://github.com/JakePT) via [#2983](https://github.com/10up/ElasticPress/pull/2983).
- E2E tests for the Comments Feature. Props [@burhandodhy](https://github.com/burhandodhy) via [#2955](https://github.com/10up/ElasticPress/pull/2955).
- E2E tests for the Instant Results feature. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#2851](https://github.com/10up/ElasticPress/pull/2851).
- More E2E tests for the WooCommerce Feature. Props [@burhandodhy](https://github.com/burhandodhy) via [#2923](https://github.com/10up/ElasticPress/pull/2923).

### Changed
- REST API endpoints used for managing custom results are no longer publicly accessible. Props [@JakePT](https://github.com/JakePT) and [@PypWalters](https://github.com/PypWalters) via [#3004](https://github.com/10up/ElasticPress/pull/3004).

### Fixed
- WooCommerce data privacy eraser query deleting all orders if EP is enabled for admin and Ajax requests. Props [@sun](https://github.com/sun) and [@bogdanarizancu](https://github.com/bogdanarizancu) via [#2975](https://github.com/10up/ElasticPress/pull/2975).
- Facets removing WooCommerce sorting. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2982](https://github.com/10up/ElasticPress/pull/2982).
- Facets triggering the ElasticPress integration in feed pages. Props [@felipeelia](https://github.com/felipeelia) and [@rafaucau](https://github.com/rafaucau) via [#2980](https://github.com/10up/ElasticPress/pull/2980).
- Taxonomy Facet tree issue when child category is selected. Props [@burhandodhy](https://github.com/burhandodhy) via [#3015](https://github.com/10up/ElasticPress/pull/3015).
- Term search in the admin panel for non-public taxonomies returning nothing. Props [@burhandodhy](https://github.com/burhandodhy) via [#2988](https://github.com/10up/ElasticPress/pull/2988).
- Clicking a Related Posts link while in the editor no longer follows the link. Props [@JakePT](https://github.com/JakePT) via [#2998](https://github.com/10up/ElasticPress/pull/2998).
- Visual alignment of elements in the Settings page. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia) via [#3018](https://github.com/10up/ElasticPress/pull/3018).
- Intermittent tests failures. Props [@burhandodhy](https://github.com/burhandodhy) via [#2984](https://github.com/10up/ElasticPress/pull/2984).

## [4.3.0] - 2022-08-31
ElasticPress 4.3.0 fixes some bugs and introduces some new and exciting changes.

WooCommerce Product Variations SKUs are now a publicly searchable field. Site administrators wanting to allow users to search for their product variations SKUs can enable it in the _Search Fields & Weighting_ Dashboard, under Products. If a user searches for a variation SKU, the parent product will be displayed in the search results.

The last ElasticPress sync information is now available in WordPress's Site Health. If you want to check information like the date of the last full sync, time spent, number of indexed content, or errors go to Tools -> Site Health, open the Info tab and click on _ElasticPress - Last Sync_.

Facets received some further improvements in this version. In addition to some refactoring related to WordPress Block Editor, ElasticPress 4.3.0 ships with an experimental version of a _Facet By Meta_ block. With that, users will be able to filter content based on post meta fields. If you want to try it, download and activate [this plugin](https://raw.githubusercontent.com/10up/ElasticPress/develop/tests/cypress/wordpress-files/test-plugins/elasticpress-facet-by-meta.php). Do you have an idea of an enhancement? Search in our [`facets`](https://github.com/10up/ElasticPress/labels/module%3Afacets) label in GitHub and if it is not there yet, feel free to open a new issue. We would love to hear new ideas!

### Added
- Search products by their variations' SKUs. Props [@burhandodhy](https://github.com/burhandodhy) via [#2854](https://github.com/10up/ElasticPress/pull/2854).
- New block to Facet by Meta fields. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/@tott) via [#2954](https://github.com/10up/ElasticPress/pull/2954) and [#2968](https://github.com/10up/ElasticPress/pull/2968).
- Display last sync info in site health screen. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#2917](https://github.com/10up/ElasticPress/pull/2917) and [#2973](https://github.com/10up/ElasticPress/pull/2973).
- New `epwr_decay_field` filter to set the decay field for date weighting. Props [@MARQAS](https://github.com/MARQAS) and [@HypeAU](https://github.com/HypeAU) via [#2907](https://github.com/10up/ElasticPress/pull/2907).
- Autosuggest: filter the autosuggest ElasticSearch query by defining a `window.epAutosuggestQueryFilter()` function in JavaScript. Props [@johnwatkins0](https://github.com/johnwatkins0) via [#2909](https://github.com/10up/ElasticPress/pull/2909).
- Autosuggest: filter the HTML of all results by defining a `window.epAutosuggestListItemsHTMLFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT) via [#2902](https://github.com/10up/ElasticPress/pull/2902).
- Autosuggest: filter the container element by defining a `window.epAutosuggestElementFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT) via [#2902](https://github.com/10up/ElasticPress/pull/2902).
- Documentation for Autosuggest JavaScript filters. Props [@JakePT](https://github.com/JakePT) and [@brandwaffle](https://github.com/brandwaffle) via [#2902](https://github.com/10up/ElasticPress/pull/2902).
- Documentation for styling Instant Results. Props [@JakePT](https://github.com/JakePT) via [#2949](https://github.com/10up/ElasticPress/pull/2949).
- Use `wp_cache_flush_group()` for autosuggest when available. Props [@tillkruss](https://github.com/tillkruss) via [#2916](https://github.com/10up/ElasticPress/pull/2916).
- The public search API is automatically deactivated when the Instant Results feature is deactivated. Props [@JakePT](https://github.com/JakePT) via [#2821](https://github.com/10up/ElasticPress/pull/2821).
- Support for transforming instances of the legacy Facet and Related Posts widgets into blocks. Props [@JakePT](https://github.com/JakePT) via [#2819](https://github.com/10up/ElasticPress/pull/2819).
- Use `wp_cache_flush_runtime()` when available. Props [@tillkruss](https://github.com/tillkruss), [@felipeelia](https://github.com/felipeelia), and [@tott](https://github.com/@tott) via [#2915](https://github.com/10up/ElasticPress/pull/2915).
- E2E tests for the Custom Results feature. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2871](https://github.com/10up/ElasticPress/pull/2871).
- E2E tests for the Terms feature. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2931](https://github.com/10up/ElasticPress/pull/2931).

### Changed
- Improved performance in `get_term_tree()`. Props [@rebeccahum](https://github.com/rebeccahum) via [#2883](https://github.com/10up/ElasticPress/pull/2883).
- Migrated Related Posts block definitions to `block.json`. Props [@JakePT](https://github.com/JakePT) via [#2898](https://github.com/10up/ElasticPress/pull/2898).
- Total comment count made during sync process to be a proper count call. Props [@felipeelia](https://github.com/felipeelia) and [@bsabalaskey](https://github.com/bsabalaskey) via [#2903](https://github.com/10up/ElasticPress/pull/2903).
- Search algorithms moved to separate classes. Props [@felipeelia](https://github.com/felipeelia) via [#2880](https://github.com/10up/ElasticPress/pull/2880).
- The legacy Facet and Related Posts widgets are now hidden when using the block editor. Props [@JakePT](https://github.com/JakePT) via [#2819](https://github.com/10up/ElasticPress/pull/2819).
- Facets are now divided by types and received their own class. Props [@felipeelia](https://github.com/felipeelia) via [#2919](https://github.com/10up/ElasticPress/pull/2919).
- PHP compatibility check merged to regular lint. Props [@felipeelia](https://github.com/felipeelia) via [#2945](https://github.com/10up/ElasticPress/pull/2945).
- E2e tests to run WP-CLI commands in an existent docker container. Props [@felipeelia](https://github.com/felipeelia) via [#2944](https://github.com/10up/ElasticPress/pull/2944).
- Increased E2e tests coverage for WP-CLI commands. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2926](https://github.com/10up/ElasticPress/pull/2926).

### Deprecated
- The following filters were deprecated via [#2880](https://github.com/10up/ElasticPress/pull/2880). They will still work but add a notice in the error logs.

|Old Filter|New Filter|
|---|---|
|ep_formatted_args_query|ep_post_formatted_args_query|
|ep_match_phrase_boost|ep_post_match_phrase_boost|
|ep_match_boost|ep_post_match_boost|
|ep_fuzziness_arg|ep_post_fuzziness_arg|
|ep_match_fuzziness|ep_post_match_fuzziness|
|ep_match_cross_fields_boost|ep_post_match_cross_fields_boost|

### Fixed
- Error returned by the `recreate-network-alias` CLI command when called on single site. Props [@burhandodhy](https://github.com/burhandodhy) via [#2906](https://github.com/10up/ElasticPress/pull/2906).
- Term objects in `format_hits_as_terms` to use `WP_Term` instead of `stdClass` to match WordPress expectations. Props [@jonathanstegall](https://github.com/jonathanstegall) via [#2913](https://github.com/10up/ElasticPress/pull/2913).
- Post reindex on meta deletion. Props [@pschoffer](https://github.com/pschoffer) via [#2862](https://github.com/10up/ElasticPress/pull/2862).
- Autosaved drafts not showing up in draft post listing when using the Protected Content feature. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2861](https://github.com/10up/ElasticPress/pull/2861).
- Display fatal error messages in the Sync Dashboard. Props [@felipeelia](https://github.com/felipeelia) and [@orasik](https://github.com/orasik) via [#2927](https://github.com/10up/ElasticPress/pull/2927).
- An issue where syncing after skipping setup, instead of deleting and syncing, resulted in an error. Props [@JakePT](https://github.com/JakePT) via [#2858](https://github.com/10up/ElasticPress/pull/2858) and [#2939](https://github.com/10up/ElasticPress/pull/2939).
- Stuck progress bar when no post is found. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2953](https://github.com/10up/ElasticPress/pull/2953).
- Infinite loop during sync if the site has just password protected posts and no other content. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2953](https://github.com/10up/ElasticPress/pull/2953).
- JS error on Custom Results edit page. Props [@burhandodhy](https://github.com/burhandodhy) via [#2935](https://github.com/10up/ElasticPress/pull/2935).
- Horizontal scroll in ElasticPress Quick Setup Screen. Props [@MARQAS](https://github.com/MARQAS) and [@JakePT](https://github.com/JakePT) via [#2937](https://github.com/10up/ElasticPress/pull/2937).
- Allows to replace `post_excerpt` with highlighted results from within AJAX and other integrated contexts. Props [@nickchomey](https://github.com/nickchomey) via [#2941](https://github.com/10up/ElasticPress/pull/2941).
- Empty results for taxonomy terms that have non ASCII characters. Props [@alaa-alshamy](https://github.com/alaa-alshamy) via [#2948](https://github.com/10up/ElasticPress/pull/2948).
- Format of highlight tags quotation mark. Props [@nickchomey](https://github.com/nickchomey) via [#2942](https://github.com/10up/ElasticPress/pull/2942).
- Intermittent error with sticky posts in the tests suite. Props [@felipeelia](https://github.com/felipeelia) via [#2943](https://github.com/10up/ElasticPress/pull/2943).

### Security
- Bumped `terser` from 5.12.0 to 5.14.2. Props [@dependabot](https://github.com/dependabot) via [#2900](https://github.com/10up/ElasticPress/pull/2900).
- Bumped `@wordpress/env` from 4.5.0 to 5.0.0. Props [@felipeelia](https://github.com/felipeelia) via [#2925](https://github.com/10up/ElasticPress/pull/2925).

## [4.2.2] - 2022-07-14
This is a bug fix release.

### Added
- New `ep_enable_do_weighting` filter and re-factor with new function `apply_weighting`. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2857](https://github.com/10up/ElasticPress/pull/2857).
- New `ep_default_analyzer_char_filters` filter. Props [@rebeccahum](https://github.com/rebeccahum) via [#2872](https://github.com/10up/ElasticPress/pull/2872).
- E2E test to prevent saving feature settings during a sync. Props [@burhandodhy](https://github.com/burhandodhy) via [#2863](https://github.com/10up/ElasticPress/pull/2863).
- Full compatibility with Composer v2. Props [@felipeelia](https://github.com/felipeelia) via [#2889](https://github.com/10up/ElasticPress/pull/2889).

### Changed
- `update_index_settings()` now accounts for the index closing action timing out and re-opens index if closed. Props [@rebeccahum](https://github.com/rebeccahum) via [#2843](https://github.com/10up/ElasticPress/pull/2843).

### Fixed
- Wrong post types being displayed on the homepage while having the Facets feature enabled. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2886](https://github.com/10up/ElasticPress/pull/2886).
- Wrong notice about unsupported server software. Props [@felipeelia](https://github.com/felipeelia) via [#2892](https://github.com/10up/ElasticPress/pull/2892).

### Security
- Bumped `moment` from 2.29.2 to 2.29.4. Props [@dependabot](https://github.com/dependabot) via [#2890](https://github.com/10up/ElasticPress/pull/2890).

## [4.2.1] - 2022-06-28
This is a bug fix release.

### Added
- Server type/software detection and warning. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#2835](https://github.com/10up/ElasticPress/pull/2835).
- Coverage of E2E tests for the activate-feature command. Props [@burhandodhy](https://github.com/burhandodhy) via [#2802](https://github.com/10up/ElasticPress/pull/2802).

### Changed
- Sync button `title` attribute. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT) via [#2814](https://github.com/10up/ElasticPress/pull/2814).
- `npm run build:zip` to use `git archive`. Props [@felipeelia](https://github.com/felipeelia) via [#2822](https://github.com/10up/ElasticPress/pull/2822).

### Fixed
- Fatal error related to WP-CLI timers on long-running syncs. Props [@felipeelia](https://github.com/felipeelia) and [@przestrzal](https://github.com/przestrzal) via [#2820](https://github.com/10up/ElasticPress/pull/2820).
- Uncaught TypeError on the Settings Page. Props [@burhandodhy](https://github.com/burhandodhy) via [#2816](https://github.com/10up/ElasticPress/pull/2816).
- Meta values that are not dates converted into date format. Props [@burhandodhy](https://github.com/burhandodhy), [@oscarssanchez](https://github.com/oscarssanchez), [@tott](https://github.com/@tott), and [@felipeelia](https://github.com/felipeelia) via [#2828](https://github.com/10up/ElasticPress/pull/2828).
- An issue where feature settings could be saved during a sync. Props [@JakePT](https://github.com/JakePT) via [#2823](https://github.com/10up/ElasticPress/pull/2823).
- Admin menu bar items are not clickable when instant results popup modal is activated. Props [@MARQAS](https://github.com/MARQAS) and [@JakePT](https://github.com/JakePT) via [#2833](https://github.com/10up/ElasticPress/pull/2833).
- Facet block wrongly available in the post editor. Props [@oscarssanchez](https://github.com/oscarssanchez) via [#2831](https://github.com/10up/ElasticPress/pull/2831).
- Show Facet widgets on taxonomy archives. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia) via [#2837](https://github.com/10up/ElasticPress/pull/2837).
- Meta queries with 'exists' as compare operator and empty meta values handling. Props [@burhandodhy](https://github.com/burhandodhy) via [#2817](https://github.com/10up/ElasticPress/pull/2817).
- Sync interruption message always mentioning ElasticPress.io. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT) via [#2813](https://github.com/10up/ElasticPress/pull/2813).
- An issue where the Related Posts block would display the wrong posts in the preview when added inside a Query Loop block. Props [@JakePT](https://github.com/JakePT) via [#2825](https://github.com/10up/ElasticPress/pull/2825).
- E2e tests for the Facets feature. Props [@felipeelia](https://github.com/felipeelia) via [#2840](https://github.com/10up/ElasticPress/pull/2840).
- Intermittent error on GitHub Actions using the latest node 16 version. Props [@felipeelia](https://github.com/felipeelia) via [#2839](https://github.com/10up/ElasticPress/pull/2839).

## [4.2.0] - 2022-05-26
ElasticPress 4.2.0 fixes some bugs and introduces some new and exciting changes.

The sync functionality had its JavaScript refactored. Timeouts, memory limits, and general errors are now properly handled and do not make the sync get stuck when performed via the WP-CLI `index` command. There is also a new `get-last-sync` WP-CLI command to check the errors and numbers from the last sync.

We've improved the admin search experience for sites using both WooCommerce and Protected Content. Previously, WooCommerce was processing that screen with two different queries, and EP was used only in one of them. Now, it will be only one query, fully handled by ElasticPress. Users wanting to keep the previous behavior can do so by adding `add_filter( 'ep_woocommerce_integrate_admin_products_list', '__return_false' );` to their website's codebase.

Facets are now available through a WordPress block. If you are using the Full Site Editing feature, you can now add ElasticPress Facets to your theme with just a few clicks! This block has been introduced with a simplified user interface to enable compatibility with Full Site Editing and will continue to be iterated and improved in future versions of the plugin.

### Added
- E2e tests for the Facets feature. Props [@felipeelia](https://github.com/felipeelia) via [#2667](https://github.com/10up/ElasticPress/pull/2667).
- `$post_args` and `$post_id` to the `ep_pc_skip_post_content_cleanup` filter. Props [@felipeelia](https://github.com/felipeelia) and [@ecaron](https://github.com/ecaron) via [#2728](https://github.com/10up/ElasticPress/pull/2728).
- New filter `ep_integrate_search_queries`. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2735](https://github.com/10up/ElasticPress/pull/2735).
- New `get-last-sync` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia) via [#2748](https://github.com/10up/ElasticPress/pull/2748).
- Facet block (previously only available as a widget.) Props [@felipeelia](https://github.com/felipeelia) via [#2722](https://github.com/10up/ElasticPress/pull/2722).
- New `_variations_skus` field to WooCommerce products. Props [@felipeelia](https://github.com/felipeelia), [@kallehauge](https://github.com/kallehauge), and [@lukaspawlik](https://github.com/lukaspawlik) via [#2763](https://github.com/10up/ElasticPress/pull/2763).
- Support for ordering Users by `user_registered` and lowercase `id`. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2781](https://github.com/10up/ElasticPress/pull/2781).
- New filter `ep_sync_number_of_errors_stored`. Props [@felipeelia](https://github.com/felipeelia), [@tott](https://github.com/tott) and [@JakePT](https://github.com/JakePT) via [#2789](https://github.com/10up/ElasticPress/pull/2789).

### Changed
- Facets widgets rendered by a separate class. Props [@felipeelia](https://github.com/felipeelia) via [#2712](https://github.com/10up/ElasticPress/pull/2712).
- Deprecated `ElasticPress\Feature\Facets\Widget::get_facet_term_html()` in favor of `ElasticPress\Feature\Facets\Renderer::get_facet_term_html()`. Props [@felipeelia](https://github.com/felipeelia) via [#2712](https://github.com/10up/ElasticPress/pull/2712).
- Log errors and remove indexing status on failed syncs. Props [@felipeelia](https://github.com/felipeelia) via [#2748](https://github.com/10up/ElasticPress/pull/2748).
- Refactored Sync page JavaScript. Props [@JakePT](https://github.com/JakePT) via [#2738](https://github.com/10up/ElasticPress/pull/2738).
- Updated admin scripts to use WordPress's version of React. Props [@JakePT](https://github.com/JakePT) via [#2756](https://github.com/10up/ElasticPress/pull/2756).
- WooCommerce products list in the Dashboard now properly leverages ElasticPress. Props [@felipeelia](https://github.com/felipeelia) via [#2757](https://github.com/10up/ElasticPress/pull/2757).
- Removed Instant Results' dependency on `@wordpress/components` and `@wordpress/date`. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia) via [#2774](https://github.com/10up/ElasticPress/pull/2774).
- (Protected Content) Password-protected posts are only hidden on searches. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@burhandodhy](https://github.com/burhandodhy) via [#2780](https://github.com/10up/ElasticPress/pull/2780).
- The plugin is now available via Composer without any additional steps required. Props [@felipeelia](https://github.com/felipeelia), [@jeffpaul](https://github.com/jeffpaul), and [@johnbillion](https://github.com/johnbillion) via [#2799](https://github.com/10up/ElasticPress/pull/2799).

### Fixed
- WP-CLI parameters documentation. Props [@felipeelia](https://github.com/felipeelia) via [#2711](https://github.com/10up/ElasticPress/pull/2711).
- Full indices removal after e2e tests. Props [@felipeelia](https://github.com/felipeelia) and [@dustinrue](https://github.com/dustinrue) via [#2710](https://github.com/10up/ElasticPress/pull/2710).
- Usage of the `$return` parameter in `Feature\RelatedPosts::find_related()`. Props [@felipeelia](https://github.com/felipeelia) and [@altendorfme](https://github.com/altendorfme) via [#2719](https://github.com/10up/ElasticPress/pull/2719).
- Link to API Functions under the Related Posts feature -> Learn more. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2729](https://github.com/10up/ElasticPress/pull/2729).
- Sync of WooCommerce Orders fields when WooCommerce and Protected Content features are enabled. Props [@felipeelia](https://github.com/felipeelia) and [@ecaron](https://github.com/ecaron) via [#2728](https://github.com/10up/ElasticPress/pull/2728).
- An issue where selecting no features during install would just cause the install page to reload without any feedback. Props [@JakePT](https://github.com/JakePT) and [@tlovett1](https://github.com/tlovett1) via [#2734](https://github.com/10up/ElasticPress/pull/2734).
- An issue where deselecting a feature during install would not stop that feature from being activated. Props [@JakePT](https://github.com/JakePT) via [#2734](https://github.com/10up/ElasticPress/pull/2734).
- Add the missing text domain for the Related Posts block. Props [@burhandodhy](https://github.com/burhandodhy) via [#2751](https://github.com/10up/ElasticPress/pull/2751).
- Console error when hitting enter on search inputs with autosuggest. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), and [@MarijnvSprundel](https://github.com/MarijnvSprundel) via [#2754](https://github.com/10up/ElasticPress/pull/2754).
- An issue where attribute selectors could not be used for the Autosuggest Selector. Props [@JakePT](https://github.com/JakePT) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2753](https://github.com/10up/ElasticPress/pull/2753).
- "Time elapsed" and "Total time elapsed" in WP-CLI index command. Props [@felipeelia](https://github.com/felipeelia) and [@archon810](https://github.com/archon810) via [#2762](https://github.com/10up/ElasticPress/pull/2762).
- Sync process with skipped objects. Props [@juliecampbell](https://github.com/juliecampbell) and [@felipeelia](https://github.com/felipeelia) via [#2761](https://github.com/10up/ElasticPress/pull/2761).
- Typo in the sync page. Props [@JakePT](https://github.com/JakePT) and [@davidegreenwald](https://github.com/davidegreenwald) via [#2767](https://github.com/10up/ElasticPress/pull/2767).
- WP-CLI index command without the `--network-wide` only syncs the main site. Props [@felipeelia](https://github.com/felipeelia) and [@colegeissinger](https://github.com/colegeissinger) via [#2771](https://github.com/10up/ElasticPress/pull/2771).
- WP-CLI commands `get-mapping`, `get-indexes`, `status`, and `stats` only uses all sites' indices name when network activated. Props [@felipeelia](https://github.com/felipeelia) and [@colegeissinger](https://github.com/colegeissinger) via [#2771](https://github.com/10up/ElasticPress/pull/2771).
- A bug where URL search parameters could be cleared when using Instant Results. Props [@JakePT](https://github.com/JakePT) and [@yashumitsu](https://github.com/yashumitsu) via [#2777](https://github.com/10up/ElasticPress/pull/2777).
- Undefined index notice in Facets renderer. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2779](https://github.com/10up/ElasticPress/pull/2779).
- Prevent an unnecessary call when the ES server is not set yet. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#2782](https://github.com/10up/ElasticPress/pull/2782).
- An incompatibility with the way WP 6.0 handles WP_User_Query using fields. Props [@felipeelia](https://github.com/felipeelia) via [#2800](https://github.com/10up/ElasticPress/pull/2800).

### Security
- Bumped `moment` from 2.29.1 to 2.29.2. Props [@dependabot](https://github.com/dependabot) via [#2709](https://github.com/10up/ElasticPress/pull/2709).
- Bumped `@wordpress/env` from 4.4.0 to 4.5.0. Props [@felipeelia](https://github.com/felipeelia) via [#2721](https://github.com/10up/ElasticPress/pull/2721).

## [4.1.0] - 2022-04-05

### Added
- Utility command to create zip packages: `npm run build:zip`. Props [@felipeelia](https://github.com/felipeelia) via [#2669](https://github.com/10up/ElasticPress/pull/2669).
- E2e tests for the Synonyms feature. Props [@felipeelia](https://github.com/felipeelia) via [#2655](https://github.com/10up/ElasticPress/pull/2655).
- `generate_mapping()` to post and comment indexables. Props [@rebeccahum](https://github.com/rebeccahum) via [#2637](https://github.com/10up/ElasticPress/pull/2637).
- `get_related_query()` to the `RelatedPosts` class. Props [@ayebare](https://github.com/ayebare) via [#1653](https://github.com/10up/ElasticPress/pull/1653).
- New `--pretty` flag to the WP-CLI commands that output a JSON. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2653](https://github.com/10up/ElasticPress/pull/2653).
- Support for an array of aggregations in the `aggs` parameter of `WP_Query`. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2682](https://github.com/10up/ElasticPress/pull/2682).

### Changed
- Refactored remaining admin scripts to remove jQuery as a dependency. Props [@JakePT](https://github.com/JakePT) via [#2664](https://github.com/10up/ElasticPress/pull/2664).
- Generate Instant Results' search template as an anonymous user by default. Props [@JakePT](https://github.com/JakePT) via [#2672](https://github.com/10up/ElasticPress/pull/2672).

### Fixed
- PHP warning Trying to access array offset on value of type int in `get_index_names()`. Props [@sun](https://github.com/sun) via [#2580](https://github.com/10up/ElasticPress/pull/2580).
- Searches by WooCommerce Order ID. Props [@felipeelia](https://github.com/felipeelia) via [#2666](https://github.com/10up/ElasticPress/pull/2666).
- Display and error message if syncing failed due to invalid JSON response from the server. Props [@dsawardekar](https://github.com/dsawardekar) via [#2677](https://github.com/10up/ElasticPress/pull/2677).
- Better compatibility with PHP 8.1 by replacing the deprecated `FILTER_SANITIZE_STRING`. Props [@sjinks](https://github.com/sjinks) via [#2529](https://github.com/10up/ElasticPress/pull/2529).
- `get_term_tree()` no longer infinite loops when parent ID is non-existent. Props [@rebeccahum](https://github.com/rebeccahum) via [#2687](https://github.com/10up/ElasticPress/pull/2687).
- User search results include users who do not exist in the current site. Props [@tfrommen](https://github.com/tfrommen) and [@felipeelia](https://github.com/felipeelia) via [#2670](https://github.com/10up/ElasticPress/pull/2670).
- Pagination while syncing Indexables other than Posts. Props [@felipeelia](https://github.com/felipeelia) and [@derringer](https://github.com/derringer) via [#2665](https://github.com/10up/ElasticPress/pull/2665).
- Handle the output of an array of messages in sync processes. Props [@felipeelia](https://github.com/felipeelia) via [#2688](https://github.com/10up/ElasticPress/pull/2688).
- Truthy values for the `'enabled'` field's attribute while using the `ep_weighting_configuration_for_search` filter. Props [@felipeelia](https://github.com/felipeelia) and [@moritzlang](https://github.com/moritzlang) via [#2673](https://github.com/10up/ElasticPress/pull/2673).
- "Learn More" link on the Sync Page. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@brandwaffle](https://github.com/brandwaffle) via [#2699](https://github.com/10up/ElasticPress/pull/2699).
- Icons alignment in the WP Dashboard. Props [@jakemgold](https://github.com/jakemgold), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), and [@tlovett1](https://github.com/tlovett1) via [#2701](https://github.com/10up/ElasticPress/pull/2701).

### Security
- Bumped `node-forge` from 1.2.1 to 1.3.0. Props [@dependabot](https://github.com/dependabot) via [#2678](https://github.com/10up/ElasticPress/pull/2678).
- Bumped` @wordpress/env` from 4.2.2 to 4.4.0, and `minimist` from 1.2.5 to 1.2.6. Props [@felipeelia](https://github.com/felipeelia) via [#2680](https://github.com/10up/ElasticPress/pull/2680).

## [4.0.1] - 2022-03-16
**This is a security release affecting users running ElasticPress 4.0 with both the WooCommerce and Protected Content Features activated. Please update to the latest version of ElasticPress if the WooCommerce and Protected Content features are activated and you're using ElasticPress 4.0.**

### Security
- Orders belonging to all users loaded in the My Account WooCommerce page. Props [@tomburtless](https://github.com/tomburtless) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2658](https://github.com/10up/ElasticPress/pull/2658).

## [4.0.0] - 2022-03-08

**ElasticPress 4.0 contains some important changes. Make sure to read these highlights before upgrading:**
- This version requires a full reindex.
- It introduces a new search algorithm that may change the search results displayed on your site.
- A new feature called "Instant Results" is available. As it requires a full reindex, if you plan to use it, we recommend you enable it first and reindex only once.
- Users upgrading from Beta 1 need to re-save the Instant Results feature settings.
- New minimum versions are:
	||Min|Max|
	|---|:---:|:---:|
	|Elasticsearch|5.2|7.10|
	|WordPress|5.6+|latest|
	|PHP|7.0+|latest|

**Note that ElasticPress 4.0.0 release removes built assets from the `develop` branch, replaced `master` with `trunk`, added a ZIP with the plugin and its built assets in the [GitHub Releases page](https://github.com/10up/ElasticPress/releases), and included a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub Releases depending on whether you require built assets or not. (See changes in [#2622](https://github.com/10up/ElasticPress/pull/2622).)

The Facets widget is not currently available within Full Site Editing mode.

This changelog contains all changes made since 3.6.6 (including Beta 1.)

### Added
- Instant Results. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), and [Pratheep Chandrasekhar](https://www.linkedin.com/in/pratheepch/) via [#2481](https://github.com/10up/ElasticPress/pull/2481) and [#2500](https://github.com/10up/ElasticPress/pull/2500).
- New default search algorithm prioritizing exact matches, matches in the same field, then matches across different fields. Props [@brandwaffle](https://github.com/brandwaffle) and [@felipeelia](https://github.com/felipeelia) via [#2498](https://github.com/10up/ElasticPress/pull/2498).
- Filter `ep_load_search_weighting` to disable search weighting engine. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2522](https://github.com/10up/ElasticPress/pull/2522).
- Post types to facet labels when needed to to differentiate facets with duplicate labels. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia) via [#2541](https://github.com/10up/ElasticPress/pull/2541).
- Support for search form post type fields to Instant Results. Props [@JakePT](https://github.com/JakePT) via [#2510](https://github.com/10up/ElasticPress/pull/2510).
- Alternative way to count total posts on larger DBs during indexing. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2571](https://github.com/10up/ElasticPress/pull/2571).
- Do not count posts in `get_total_objects_for_query_from_db()` if any object limit IDs are passed in. Props [@rebeccahum](https://github.com/rebeccahum) via [#2586](https://github.com/10up/ElasticPress/pull/2586).
- Show WP-CLI progress on the new Sync page. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2564](https://github.com/10up/ElasticPress/pull/2564).
- Display results counts for facet options in Instant Results. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia) via [#2563](https://github.com/10up/ElasticPress/pull/2563).
- ARIA attributes to Facet widget links to improve accessibility. Props [@JakePT](https://github.com/JakePT) via [#2607](https://github.com/10up/ElasticPress/pull/2607).
- Support for shareable URLs to Instant Results. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia) via [#2604](https://github.com/10up/ElasticPress/pull/2604).
- Dynamic bulk requests limits. Instead of sending only one request per document batch, send several adjusting their sizes based on the Elasticsearch response. Props [@felipeelia](https://github.com/felipeelia), [@dinhtungdu](https://github.com/dinhtungdu), [@brandwaffle](https://github.com/brandwaffle), and [@Rahmon](https://github.com/Rahmon) via [#2585](https://github.com/10up/ElasticPress/pull/2585).
- New step in the installation process: users can now select features before the initial sync. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), [Jonathan Netek](https://www.linkedin.com/in/jonathan-netek/), and [@brandwaffle](https://github.com/brandwaffle) via [#2572](https://github.com/10up/ElasticPress/pull/2572).
- Complement to the resync message related to Instant Results. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#2628](https://github.com/10up/ElasticPress/pull/2628).

### Changed
- Sync page and code responsible for indexing. Props [@helen](https://github.com/helen), [@felipeelia](https://github.com/felipeelia), [@Rahmon](https://github.com/Rahmon), [@mckdemps](https://github.com/mckdemps), [@tott](https://github.com/tott), and [Pratheep Chandrasekhar](https://www.linkedin.com/in/pratheepch/) via [#1835](https://github.com/10up/ElasticPress/pull/1835), [#2448](https://github.com/10up/ElasticPress/pull/2448), and [#2501](https://github.com/10up/ElasticPress/pull/2501).
- When Protected Content is enabled, ElasticPress will have a more similar behavior to WordPress core but the post content and meta will not be indexed (the new `ep_pc_skip_post_content_cleanup` can be used to skip that removal.) Props [@rebeccahum](https://github.com/rebeccahum), [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), [@dinhtungdu](https://github.com/dinhtungdu), [@cristianuibar](https://github.com/cristianuibar), and [@allan23](https://github.com/allan23), [@mallorydxw](https://github.com/mallorydxw) via [#2408](https://github.com/10up/ElasticPress/pull/2408) and [#2646](https://github.com/10up/ElasticPress/pull/2646).
- Bump minimum required versions of Elasticsearch from 5.0 to 5.2 and WordPress from 3.7.1 to 5.6. Props [@felipeelia](https://github.com/felipeelia) via [#2475](https://github.com/10up/ElasticPress/pull/2475).
- Bump minimum required PHP version from 5.6 to 7.0. Props [@felipeelia](https://github.com/felipeelia), [@ActuallyConnor](https://github.com/ActuallyConnor), and [@brandwaffle](https://github.com/brandwaffle) via [#2485](https://github.com/10up/ElasticPress/pull/2485) and [#2626](https://github.com/10up/ElasticPress/pull/2626).
- Internationalize start and end datetimes of sync. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2485](https://github.com/10up/ElasticPress/pull/2485) and [#2548](https://github.com/10up/ElasticPress/pull/2548).
- `ep_integrate` argument in WP_Query to accept `0` and `'false'` as valid negative values. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2547](https://github.com/10up/ElasticPress/pull/2547) and [#2573](https://github.com/10up/ElasticPress/pull/2573).
- To comply with modern WooCommerce behavior, ElasticPress no longer changes the `orderby` parameter. Props [@felipeelia](https://github.com/felipeelia) and [@beazuadmin](https://github.com/beazuadmin) via [#2577](https://github.com/10up/ElasticPress/pull/2577).
- Query parameters for facets to start with `ep_filter`, changeable via the new `ep_facet_filter_name` filter. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@slaxxarn](https://github.com/slaxxarn) via [#2560](https://github.com/10up/ElasticPress/pull/2560).
- Output of sync processes using offset to display the number of documents skipped. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), [@cbratschi](https://github.com/cbratschi), and [@brandwaffle](https://github.com/brandwaffle) via [#2591](https://github.com/10up/ElasticPress/pull/2591).
- Switched from WP Acceptance to Cypress for end to end tests. Props [@felipeelia](https://github.com/felipeelia), [@Sidsector9](https://github.com/Sidsector9), and [@dustinrue](https://github.com/dustinrue) via [#2446](https://github.com/10up/ElasticPress/pull/2446) and [#2615](https://github.com/10up/ElasticPress/pull/2615).
- CSS vars usage in the new Sync page. Props [@Rahmon](https://github.com/Rahmon), [@JakePT](https://github.com/JakePT), [@mehidi258](https://github.com/mehidi258), and [@felipeelia](https://github.com/felipeelia) via [#2561](https://github.com/10up/ElasticPress/pull/2561).
- Features screen: improved accessibility and jQuery dependency removal. Props [@JakePT](https://github.com/JakePT) via [#2503](https://github.com/10up/ElasticPress/pull/2503).
- Taxonomy parameters now reflect the WordPress parsed `tax_query` value. Props [@felipeelia](https://github.com/felipeelia) and [@sathyapulse](https://github.com/sathyapulse) via [#2419](https://github.com/10up/ElasticPress/pull/2419).
- Features order in the Features screen. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#2618](https://github.com/10up/ElasticPress/pull/2618).
- WooCommerce's `search` parameter also to be used by ElasticPress queries. Props [@felipeelia](https://github.com/felipeelia), [@dianfishekqi](https://github.com/dianfishekqi), and [@oscarssanchez](https://github.com/oscarssanchez) via [#2620](https://github.com/10up/ElasticPress/pull/2620).
- Posts are now reindexed when a new term is associated with them and also when an associated term is updated or deleted. Props [@nickdaugherty](https://github.com/nickdaugherty), [@felipeelia](https://github.com/felipeelia), [@brandon-m-skinner](https://github.com/brandon-m-skinner), [@mckdemps](https://github.com/mckdemps), [@rebeccahum](https://github.com/rebeccahum) via [#2603](https://github.com/10up/ElasticPress/pull/2603).
- Updated `jsdoc` from 3.6.9 to 3.6.10 and fixed the documentation of the `ep_thumbnail_image_size` filter. Props [@felipeelia](https://github.com/felipeelia) via [#2639](https://github.com/10up/ElasticPress/pull/2639).
- Instant Results: type and initial value of search template and move save to the end of sync. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2645](https://github.com/10up/ElasticPress/pull/2645).

### Removed
- Built assets (minified JavaScript and CSS files) from the repository. Props [@felipeelia](https://github.com/felipeelia) and [@jeffpaul](https://github.com/jeffpaul) via [#2486](https://github.com/10up/ElasticPress/pull/2486).
- Duplicate `case 'description':` from `ElasticPress\Indexable\Term\Term::parse_orderby`. Props [@sjinks](https://github.com/sjinks) via [#2533](https://github.com/10up/ElasticPress/pull/2533).

### Fixed
- CSS issues on Features page. Props [@JakePT](https://github.com/JakePT) via [#2495](https://github.com/10up/ElasticPress/pull/2495).
- AJAX URL on subsites. Props [@Rahmon](https://github.com/Rahmon) via [#2501](https://github.com/10up/ElasticPress/pull/2501).
- PHP Notice while monitoring a WP-CLI sync in the dashboard. Props [@felipeelia](https://github.com/felipeelia) and [@ParhamG](https://github.com/ParhamG) via [#2544](https://github.com/10up/ElasticPress/pull/2544).
- Sync page when WooCommerce's "hide out of stock items" and Instant Results are both enabled. Props [@felipeelia](https://github.com/felipeelia) via [#2566](https://github.com/10up/ElasticPress/pull/2566).
- PHPUnit Tests and WordPress 5.9 compatibility. Props [@felipeelia](https://github.com/felipeelia) via [#2569](https://github.com/10up/ElasticPress/pull/2569).
- WooCommerce Orders Search when searching for an order ID. Props [@felipeelia](https://github.com/felipeelia) via [#2554](https://github.com/10up/ElasticPress/pull/2554).
- Code standards. Props [@felipeelia](https://github.com/felipeelia) via [#2574](https://github.com/10up/ElasticPress/pull/2574) and [#2578](https://github.com/10up/ElasticPress/pull/2578).
- Posts insertion and deletion in the same thread. Props [@felipeelia](https://github.com/felipeelia) and [@tcrsavage](https://github.com/tcrsavage) via [#2575](https://github.com/10up/ElasticPress/pull/2575).
- Invalid values in `tax_query` terms resulting in a query failure. Props [@rinatkhaziev](https://github.com/rinatkhaziev) and [@felipeelia](https://github.com/felipeelia) via [#2576](https://github.com/10up/ElasticPress/pull/2576) and [#2583](https://github.com/10up/ElasticPress/pull/2583).
- New Sync Page to display a message when an indexing is stopped by the WP-CLI `stop-indexing` command. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@brandwaffle](https://github.com/brandwaffle) via [#2549](https://github.com/10up/ElasticPress/pull/2549).
- Nested queries are no longer deleted. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@christianc1](https://github.com/christianc1) via [#2567](https://github.com/10up/ElasticPress/pull/2567).
- Type hints for `epwr_decay` and `epwr_weight` hooks. Props [@edwinsiebel](https://github.com/edwinsiebel) via [#2537](https://github.com/10up/ElasticPress/pull/2537).
- Errors count in the new Sync page. Props [@felipeelia](https://github.com/felipeelia) via [#2590](https://github.com/10up/ElasticPress/pull/2590).
- Multisite could index posts from a disabled indexing site. Props [@oscarssanchez](https://github.com/oscarssanchez), [@chrisvanpatten](https://github.com/chrisvanpatten), [@felipeelia](https://github.com/felipeelia) via [#2621](https://github.com/10up/ElasticPress/pull/2621).
- New sync code and the `upper-limit-object-id` and `lower-limit-object-id` parameters in WP-CLI command. Props [@felipeelia](https://github.com/felipeelia) via [#2634](https://github.com/10up/ElasticPress/pull/2634).
- Sync link on index health page. Props [@JakePT](https://github.com/JakePT) via [#2644](https://github.com/10up/ElasticPress/pull/2644).
- Logic checking if it is a full sync and if search should go or not through ElasticPress. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT) via [#2642](https://github.com/10up/ElasticPress/pull/2642).

### Security
- Use most recent external GitHub Actions versions. Props [@felipeelia](https://github.com/felipeelia) and [@qazaqstan2025](https://github.com/qazaqstan2025) via [#2535](https://github.com/10up/ElasticPress/pull/2535).
- Updated `10up-toolkit` from 1.0.13 to 3.0.1, `jsdoc` from 3.6.7 to 3.6.9, `terser-webpack-plugin` from 5.2.4 to 5.3.0, `@wordpress/env` from 4.1.1 to 4.2.2, and `promise-polyfill` from 8.2.0 to 8.2.1. Props [@felipeelia](https://github.com/felipeelia) via [#2559](https://github.com/10up/ElasticPress/pull/2559), [#2611](https://github.com/10up/ElasticPress/pull/2611), and [#2631](https://github.com/10up/ElasticPress/pull/2631).
- Bumped `follow-redirects` from 1.14.7 to 1.14.9. Props [@dependabot](https://github.com/dependabot) via [#2609](https://github.com/10up/ElasticPress/pull/2609).

## [3.6.6] - 2021-12-20

ElasticPress 4.0 Beta 1 is [now available](https://github.com/10up/ElasticPress/releases/tag/4.0.0-beta.1) for non-production testing.

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub releases depending on whether you require built assets or not.

Supported versions:

||Current (3.6.6)|4.0.0|
|---|:---:|:---:|
|Elasticsearch|5.0 - 7.9|5.2 - 7.10|
|WordPress|3.7.1+|5.6+|
|PHP|5.6+|7.0+|

## Added
- Ensure array query parameters do not contain empty items. Props [@roborourke](https://github.com/roborourke) via [#2462](https://github.com/10up/ElasticPress/pull/2462).
- WP-CLI `request` subcommand. Props [@joehoyle](https://github.com/joehoyle) and [@felipeelia](https://github.com/felipeelia) via [#2484](https://github.com/10up/ElasticPress/pull/2484) and [#2523](https://github.com/10up/ElasticPress/pull/2523).

## Changed
- Enabling features that require a reindex will now ask for confirmation. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@Rahmon](https://github.com/Rahmon), [@columbian-chris](https://github.com/columbian-chris), and [@brandwaffle](https://github.com/brandwaffle) via [#2491](https://github.com/10up/ElasticPress/pull/2491), [#2524](https://github.com/10up/ElasticPress/pull/2524), and [#2536](https://github.com/10up/ElasticPress/pull/2536).

## Fixed
- Broken search pagination on hierarchical post types. Props [@tfrommen](https://github.com/tfrommen) via [#2511](https://github.com/10up/ElasticPress/pull/2511).
- Synonyms erased when syncing via WP-CLI. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2517](https://github.com/10up/ElasticPress/pull/2517).
- Deleting a metadata without passing an object id now updates all associated posts. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@Shrimpstronaut](https://github.com/Shrimpstronaut) via [#2483](https://github.com/10up/ElasticPress/pull/2483) and [#2525](https://github.com/10up/ElasticPress/pull/2525).
- Not indexable sites added to indexes list in WP-CLI commands. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#2531](https://github.com/10up/ElasticPress/pull/2531).

## [4.0.0-beta.1] - 2021-12-08

This version requires a full reindex.

Please note that 4.0 introduces a new search algorithm that may change the order of results displayed on your site. Additionally, this algorithm may be changed again during the beta process before a final 4.0 release algorithm is determined. Your feedback on this new algorithm is welcome via the Github [issues list](https://github.com/10up/ElasticPress/issues).

New minimum versions are:

||Min|Max|
|---|:---:|:---:|
|Elasticsearch|5.2|7.10|
|WordPress|5.6+|latest|
|PHP|7.0+|latest|

**Note that the official ElasticPress 4.0.0 release will replace `master` with `trunk`. Built assets were already removed from the branch and added to the zip file attached to the GitHub release page.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub releases depending on whether you require built assets or not.

### Added
- Instant Results. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), and [Pratheep Chandrasekhar](https://www.linkedin.com/in/pratheepch/) via [#2481](https://github.com/10up/ElasticPress/pull/2481) and [#2500](https://github.com/10up/ElasticPress/pull/2500).
- New default search algorithm prioritizing exact matches, matches in the same field, then matches across different fields. Props [@brandwaffle](https://github.com/brandwaffle) and [@felipeelia](https://github.com/felipeelia) via [#2498](https://github.com/10up/ElasticPress/pull/2498).
- WP-CLI `request` subcommand. Props [@joehoyle](https://github.com/joehoyle) and [@felipeelia](https://github.com/felipeelia) via [#2484](https://github.com/10up/ElasticPress/pull/2484).

### Changed
- Sync Page and code responsible for indexing. Props [@helen](https://github.com/helen), [@felipeelia](https://github.com/felipeelia), [@Rahmon](https://github.com/Rahmon), [@mckdemps](https://github.com/mckdemps), [@tott](https://github.com/tott), and [Pratheep Chandrasekhar](https://www.linkedin.com/in/pratheepch/) via [#1835](https://github.com/10up/ElasticPress/pull/1835), [#2448](https://github.com/10up/ElasticPress/pull/2448), and [#2501](https://github.com/10up/ElasticPress/pull/2501).
- When Protected Content is enabled, WordPress behavior for password protected content is correctly reproduced with ElasticPress enabled. Props [@rebeccahum](https://github.com/rebeccahum), [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia),  [@dinhtungdu](https://github.com/dinhtungdu), and [@cristianuibar](https://github.com/cristianuibar) via [#2408](https://github.com/10up/ElasticPress/pull/2408).
- Enabling features that require a reindex will now ask for confirmation. Props [@JakePT](https://github.com/JakePT), [@columbian-chris](https://github.com/columbian-chris), and [@brandwaffle](https://github.com/brandwaffle) via [#2491](https://github.com/10up/ElasticPress/pull/2491).
- Bump minimum required versions of Elasticsearch from 5.0 to 5.2 and WordPress from 3.7.1 to 5.6. Props [@felipeelia](https://github.com/felipeelia) via [#2475](https://github.com/10up/ElasticPress/pull/2475).
- Bump minimum required PHP version from 5.6 to 7.0. Props [@felipeelia](https://github.com/felipeelia), [@ActuallyConnor](https://github.com/ActuallyConnor), and [@brandwaffle](https://github.com/brandwaffle) via [#2485](https://github.com/10up/ElasticPress/pull/2485).

### Removed
- Built assets (minified JavaScript and CSS files) from the repository. Props [@felipeelia](https://github.com/felipeelia) and [@jeffpaul](https://github.com/jeffpaul) via [#2486](https://github.com/10up/ElasticPress/pull/2486).

### Fixed
- Deleting a metadata without passing an object id now updates all associated posts. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@Shrimpstronaut](https://github.com/Shrimpstronaut) via [#2483](https://github.com/10up/ElasticPress/pull/2483).
- CSS issues on Features page. Props [@JakePT](https://github.com/JakePT) via [#2495](https://github.com/10up/ElasticPress/pull/2495).

## [3.6.5] - 2021-11-30

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub releases depending on whether you require built assets or not.

Supported versions:

||Current (3.6.5)|4.0.0|
|---|:---:|:---:|
|Elasticsearch|5.0 - 7.9|5.2 - 7.10|
|WordPress|3.7.1+|5.6+|
|PHP|5.6+|7.0+|

### Added
- Docs: Link to the support page in README.md. Props [@brandwaffle](https://github.com/brandwaffle) via [#2436](https://github.com/10up/ElasticPress/pull/2436).
- New `ep_weighting_default_enabled_taxonomies` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott) via [#2474](https://github.com/10up/ElasticPress/pull/2474).
- `$blog_id` and `$indexable_slug` parameters to the `ep_keep_index` filter. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#2478](https://github.com/10up/ElasticPress/pull/2478).

### Changed
- Add `$type` parameter to `ep_do_intercept_request` filter. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2443](https://github.com/10up/ElasticPress/pull/2443).
- Cache the detected Posts mapping version, avoiding `get_mapping` calls in all admin requests. Props [@felipeelia](https://github.com/felipeelia) via [#2445](https://github.com/10up/ElasticPress/pull/2445).
- Docs: Required ES and WP versions planned for ElasticPress 4.0.0. Props [@felipeelia](https://github.com/felipeelia) via [#2442](https://github.com/10up/ElasticPress/pull/2442).
- The `admin.min.js` file was split in `notice.min.js` and `weighting.min.js`, being loaded accordingly. Props [@felipeelia](https://github.com/felipeelia) and [@barryceelen](https://github.com/barryceelen) via [#2476](https://github.com/10up/ElasticPress/pull/2476).

### Fixed
- Force fetching `ep_wpcli_sync_interrupted` transient from remote to allow for more reliable remote interruption. Props [@rinatkhaziev](https://github.com/rinatkhaziev) and [@rebeccahum](https://github.com/rebeccahum) via [#2433](https://github.com/10up/ElasticPress/pull/2433).
- Duplicate orderby statement in Users query. Props [@brettshumaker](https://github.com/brettshumaker), [@pschoffer](https://github.com/pschoffer), and [@rebeccahum](https://github.com/rebeccahum) via [#2435](https://github.com/10up/ElasticPress/pull/2435).
- When using offset and default maximum result window value for size, subtract offset from size. Props [@rebeccahum](https://github.com/rebeccahum) via [#2441](https://github.com/10up/ElasticPress/pull/2441).
- Order for Custom Search Results in autosuggest. Props [@felipeelia](https://github.com/felipeelia) and [@johnwatkins0](https://github.com/johnwatkins0) via [#2447](https://github.com/10up/ElasticPress/pull/2447).
- WP-CLI stats and status to output all indices related to ElasticPress. Props [@felipeelia](https://github.com/felipeelia) via [#2479](https://github.com/10up/ElasticPress/pull/2479).
- Tests: Ensure that Posts related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) via [#2401](https://github.com/10up/ElasticPress/pull/2401).
- Tests: PHPUnit and yoast/phpunit-polyfills. Props [@felipeelia](https://github.com/felipeelia) via [#2457](https://github.com/10up/ElasticPress/pull/2457).

### Security
- Bumped `path-parse` from 1.0.6 to 1.0.7. Props [@dependabot](https://github.com/dependabot) via [#2458](https://github.com/10up/ElasticPress/pull/2458).
- Bumped `10up-toolkit` from 1.0.12 to 1.0.13. Props [@felipeelia](https://github.com/felipeelia) via [#2467](https://github.com/10up/ElasticPress/pull/2467).

## [3.6.4] - 2021-10-26

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, ~~will build a stable release version including built assets into a `stable` branch,~~ will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to ~~either `stable` or~~ `trunk` or to GitHub releases depending on whether you require built assets or not.

Supported versions:

||Current (3.6.4)|4.0.0|
|---|:---:|:---:|
|Elasticsearch|5.0 - 7.9|5.2 - 7.10|
|WordPress|3.7.1+|5.6+|
|PHP|5.6+|7.0+|

### Added
- WP-CLI: New `get-mapping` command. Props [@tfrommen](https://github.com/tfrommen), [@felipeelia](https://github.com/felipeelia), and [@Rahmon](https://github.com/Rahmon) via [#2414](https://github.com/10up/ElasticPress/pull/2414).
- New filters: `ep_query_request_args` and `ep_pre_request_args`. Props [@felipeelia](https://github.com/felipeelia) via [#2416](https://github.com/10up/ElasticPress/pull/2416).
- Support for Autosuggest to dynamically inserted search inputs. Props [@JakePT](https://github.com/JakePT), [@rdimascio](https://github.com/rdimascio), [@brandwaffle](https://github.com/brandwaffle), and [@felipeelia](https://github.com/felipeelia) via [#2404](https://github.com/10up/ElasticPress/pull/2404).

### Changed
- Automatically generated WP-CLI docs. Props [@felipeelia](https://github.com/felipeelia) via [#2370](https://github.com/10up/ElasticPress/pull/2370).
- Verification of active features requirement. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@WPprodigy](https://github.com/WPprodigy) via [#2410](https://github.com/10up/ElasticPress/pull/2410).
- `ewp_word_delimiter` base filter: changed from `word_delimiter` to `word_delimiter_graph`. Props [@pschoffer](https://github.com/pschoffer) and [@yolih](https://github.com/yolih) via [#2409](https://github.com/10up/ElasticPress/pull/2409).
- Terms search query in admin will not be fuzzy. Props [@rebeccahum](https://github.com/rebeccahum) via [#2417](https://github.com/10up/ElasticPress/pull/2417).

### Fixed
- Elapsed time beyond 1000 seconds in WP-CLI index command. Props [@felipeelia](https://github.com/felipeelia) and [@dustinrue](https://github.com/dustinrue) via [#2380](https://github.com/10up/ElasticPress/pull/2380).
- Layout of Index Health totals on small displays. Props [@JakePT](https://github.com/JakePT) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2403](https://github.com/10up/ElasticPress/pull/2403).
- Deprecated URL for multiple documents get from ElasticSearch. Props [@pschoffer](https://github.com/pschoffer) via [#2397](https://github.com/10up/ElasticPress/pull/2397).
- Add new lines and edit terms in the Advanced Synonym Editor. Props [@JakePT](https://github.com/JakePT) and [@johnwatkins0](https://github.com/johnwatkins0) via [#2411](https://github.com/10up/ElasticPress/pull/2411).
- Terms: Avoid falling back to MySQL when results are empty. Props [@felipeelia](https://github.com/felipeelia) via [#2420](https://github.com/10up/ElasticPress/pull/2420).
- Terms: Usage of several parameters for searching and ordering. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon) via [#2420](https://github.com/10up/ElasticPress/pull/2420) and [#2421](https://github.com/10up/ElasticPress/pull/2421).
- Attachment indexing on Elasticsearch 7. Props [@Rahmon](https://github.com/Rahmon) via [#2425](https://github.com/10up/ElasticPress/pull/2425).
- Tests: Ensure that Documents related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) via [#2388](https://github.com/10up/ElasticPress/pull/2388).
- Tests: Ensure that WooCommerce related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2389](https://github.com/10up/ElasticPress/pull/2389).
- Tests: Ensure that Comments related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2390](https://github.com/10up/ElasticPress/pull/2390).
- Tests: Ensure that Multisite related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2400](https://github.com/10up/ElasticPress/pull/2400).
- Tests: Ensure that Terms related queries use ElasticPress. Props [@felipeelia](https://github.com/felipeelia) via [#2420](https://github.com/10up/ElasticPress/pull/2420).

## [3.6.3] - 2021-09-29

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

Official PHP support is currently 5.6+. Minimum PHP version for ElasticPress 4.0.0 will be 7.0+.

### Added
- New `ep_facet_widget_term_html` and `ep_facet_widget_term_label` filters to the Facet widget for filtering the HTML and label of individual facet terms. Props [@JakePT](https://github.com/JakePT), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2363](https://github.com/10up/ElasticPress/pull/2363).
- New `ep_set_sort` filter for changing the `sort` clause of the ES query if `orderby` is not set in WP_Query. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia) via [#2343](https://github.com/10up/ElasticPress/pull/2343) and [#2364](https://github.com/10up/ElasticPress/pull/2364).
- WP-CLI documentation for some commands and parameters. Props [@felipeelia](https://github.com/felipeelia) via [#2369](https://github.com/10up/ElasticPress/pull/2369).

### Changed
- In addition to post titles, now autosuggest also partially matches taxonomy terms. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon) via [#2347](https://github.com/10up/ElasticPress/pull/2347).
- Date parsing change to avoid `E_WARNING`s. Props [@pschoffer](https://github.com/pschoffer) via [#2340](https://github.com/10up/ElasticPress/pull/2340).

### Fixed
- Comments created by anonymous users (but approved by default) are now indexed. Props [@tomjn](https://github.com/tomjn) and [@Rahmon](https://github.com/Rahmon) via [#2357](https://github.com/10up/ElasticPress/pull/2357).
- Deleted terms are now properly removed from the Elasticsearch index. Props [@felipeelia](https://github.com/felipeelia) via [#2366](https://github.com/10up/ElasticPress/pull/2366).
- Children of deleted terms are now properly removed from the Elasticsearch index. Props [@pschoffer](https://github.com/pschoffer) via [#2349](https://github.com/10up/ElasticPress/pull/2349).
- Post tag duplicated in the Elasticsearch query. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2341](https://github.com/10up/ElasticPress/pull/2341).
- Infinite loading state of ElasticPress Related Posts block in the Widgets Edit Screen. Props [@felipeelia](https://github.com/felipeelia) via [#2353](https://github.com/10up/ElasticPress/pull/2353).
- Return of `Search::integrate_search_queries()` when `is_integrated_request`. Props [@adiloztaser](https://github.com/adiloztaser) via [#2355](https://github.com/10up/ElasticPress/pull/2355).
- Mapping determination based on existing info. Props [@felipeelia](https://github.com/felipeelia) via [#2345](https://github.com/10up/ElasticPress/pull/2345).
- `WP_Term_Query` and `parent = 0`. Props [@felipeelia](https://github.com/felipeelia) and [@juansanchezfernandes](https://github.com/juansanchezfernandes) via [#2344](https://github.com/10up/ElasticPress/pull/2344).
- WP Acceptance Tests. Props [@felipeelia](https://github.com/felipeelia) via [#2352](https://github.com/10up/ElasticPress/pull/2352).
- Typos in the output of some WP-CLI Commands. Props [@rebeccahum](https://github.com/rebeccahum) via [#2336](https://github.com/10up/ElasticPress/pull/2336).

### Security
- Bumped `10up-toolkit` from 1.0.11 to 1.0.12, `terser-webpack-plugin` from 5.1.4 to 5.2.4, `@wordpress/api-fetch` from 3.21.5 to 3.23.1, and `@wordpress/i18n` from 3.18.0 to 3.20.0. Props [@felipeelia](https://github.com/felipeelia) via [#2372](https://github.com/10up/ElasticPress/pull/2372).

## [3.6.2] - 2021-08-26

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version bumps official PHP support from 5.3+ to 5.6+. Minimum PHP version for ElasticPress 4.0.0 will be 7.0+.

### Added
- GitHub Action to test compatibility with different PHP versions. Props [@felipeelia](https://github.com/felipeelia) via [#2303](https://github.com/10up/ElasticPress/pull/2303).
- Validate mapping currently in index against expected version. Props [@tott](https://github.com/tott), [@tlovett1](https://github.com/tlovett1), [@asharirfan](https://github.com/asharirfan), [@oscarssanchez](https://github.com/oscarssanchez), and [@felipeelia](https://github.com/felipeelia) via [#1472](https://github.com/10up/ElasticPress/pull/1472).
- `ep_default_analyzer_filters` filter to adjust default analyzer filters. Props [@pschoffer](https://github.com/pschoffer) and [@felipeelia](https://github.com/felipeelia) via [#2282](https://github.com/10up/ElasticPress/pull/2282).
- `title` and `aria-labels` attributes to each icon hyperlink in the header toolbar. Props [@claytoncollie](https://github.com/claytoncollie) and [@felipeelia](https://github.com/felipeelia) via [#2304](https://github.com/10up/ElasticPress/pull/2304).
- `Utils\is_integrated_request()` function to centralize checks for admin, AJAX, and REST API requests. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), [@moritzlang](https://github.com/moritzlang), and [@lkraav](https://github.com/lkraav) via [#2267](https://github.com/10up/ElasticPress/pull/2267).

### Changed
- Use `10up-toolkit` to build assets. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@nicholasio](https://github.com/nicholasio) via [#2279](https://github.com/10up/ElasticPress/pull/2279).
- Official PHP supported version bumped to 5.6. Props [@felipeelia](https://github.com/felipeelia) via [#2320](https://github.com/10up/ElasticPress/pull/2320).
- Lint React rules using `10up/eslint-config/react`. Props [@Rahmon](https://github.com/Rahmon) via [#2306](https://github.com/10up/ElasticPress/pull/2306).
- For ES 7.0+ mappings, change `edgeNGram` to `edge_ngram`. Props [@pschoffer](https://github.com/pschoffer) and [@rinatkhaziev](https://github.com/rinatkhaziev) via [#2315](https://github.com/10up/ElasticPress/pull/2315).

### Removed
- Remove duplicate category_name, cat and tag_id from ES query when tax_query set. Props [@rebeccahum](https://github.com/rebeccahum) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2241](https://github.com/10up/ElasticPress/pull/2241).
- Remove unused `path` from `dynamic_templates`. Props [@pschoffer](https://github.com/pschoffer) via [#2315](https://github.com/10up/ElasticPress/pull/2315).

### Fixed
- Remove data from Elasticsearch on a multisite network when a site is archived, deleted or marked as spam. Props [@dustinrue](https://github.com/dustinrue) and [@felipeelia](https://github.com/felipeelia) via [#2284](https://github.com/10up/ElasticPress/pull/2284).
- `stats` and `status` commands in a multisite scenario. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@dustinrue](https://github.com/dustinrue) via [#2290](https://github.com/10up/ElasticPress/pull/2290).
- Multiple words synonyms. Props [@scooterlord](https://github.com/scooterlord), [@jonasstrandqvist](https://github.com/jonasstrandqvist), and [@felipeelia](https://github.com/felipeelia) via [#2287](https://github.com/10up/ElasticPress/pull/2287).
- Category slug used when doing cat Tax Query with ID. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@karols0](https://github.com/karols0) via [#2322](https://github.com/10up/ElasticPress/pull/2322).
- Restore current blog when the_post triggers outside the loop in multisite environment and the whole network is searched if the first result is from another blog. Props [@gonzomir](https://github.com/gonzomir) and [@felipeelia](https://github.com/felipeelia) via [#2283](https://github.com/10up/ElasticPress/pull/2283).
- Prevents a post from being attempted to delete twice. Props [@pauarge](https://github.com/pauarge) via [#2314](https://github.com/10up/ElasticPress/pull/2314).
- Indexing button on Health screen. Props [@Rahmon](https://github.com/Rahmon) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2312](https://github.com/10up/ElasticPress/pull/2312).
- WP Acceptance tests and Page Crashed errors. Props [@felipeelia](https://github.com/felipeelia) and [@jeffpaul](https://github.com/jeffpaul) via [#2281](https://github.com/10up/ElasticPress/pull/2281) and [#2313](https://github.com/10up/ElasticPress/pull/2313).
- Facets: Children of selected terms ordered by count. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2288](https://github.com/10up/ElasticPress/pull/2288).

### Security
- Bumps `path-parse` from 1.0.6 to 1.0.7. Props [@dependabot](https://github.com/dependabot) via [#2302](https://github.com/10up/ElasticPress/pull/2302).

## [3.6.1] - 2021-07-15

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version requires a full reindex. The new `facet` field introduced in `3.6.0` requires a change in the mapping, otherwise, all content sync related to posts will silently fail. If you've upgraded to 3.6.0 and didn't resync your content yet (via Dashboard or with WP-CLI `wp elasticpress index --setup`) make sure to do so.

### Added
* Filter `ep_remote_request_add_ep_user_agent`. Passing `true` to that, the ElasticPress version will be added to the User-Agent header in the request. Props [@felipeelia](https://github.com/felipeelia) via [#2264](https://github.com/10up/ElasticPress/pull/2264)
* Flagged `3.6.0` as version that needs a full reindex. Props [@adiloztaser](https://github.com/adiloztaser) and [@felipeelia](https://github.com/felipeelia) via [#2264](https://github.com/10up/ElasticPress/pull/2264)

### Changed
* Notice when a sync is needed is now an error. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle) via [#2264](https://github.com/10up/ElasticPress/pull/2264)

### Fixed
* Encode the Search Term header before sending it to ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia) via [#2265](https://github.com/10up/ElasticPress/pull/2265)

## [3.6.0] - 2021-07-07
**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version requires a full reindex.

### Breaking Changes
* Autosuggest will now respect the `[name="post_type"]` input in the same form. Before it would bring all post types. Props [@mustafauysal](https://github.com/mustafauysal) and [@JakePT](https://github.com/JakePT) via [#1689](https://github.com/10up/ElasticPress/pull/1689)
* Facets Widget presentation, replacing the `<input type="checkbox">` elements in option links with a custom `.ep-checkbox presentational` div. Props [@MediaMaquina](https://github.com/MediaMaquina), [@amesplant](https://github.com/amesplant), [@JakePT](https://github.com/JakePT), and [@oscarssanchez](https://github.com/oscarssanchez) via [#1886](https://github.com/10up/ElasticPress/pull/1886)
* Confirmation for destructive WP-CLI commands. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@Rahmon](https://github.com/Rahmon) via [#2120](https://github.com/10up/ElasticPress/pull/2120)

### Added
* Comments Indexable. Props [@dkotter](https://github.com/dkotter), [@dinhtungdu](https://github.com/dinhtungdu), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#1531](https://github.com/10up/ElasticPress/pull/1531)
* "ElasticPress - Comments", a search form for comments. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia) via [#2238](https://github.com/10up/ElasticPress/pull/2238)
* Facets: new `ep_facet_allowed_query_args` filter. Props [@mustafauysal](https://github.com/mustafauysal), [@JakePT](https://github.com/JakePT),[@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#1689](https://github.com/10up/ElasticPress/pull/1689)
* Facets: new `ep_facet_use_field` filter. Props [@moraleida](https://github.com/moraleida) via [#2071](https://github.com/10up/ElasticPress/pull/2071)
* GitHub Action to auto-close non-responsive reporter feedback issues after 3 days. Props [@jeffpaul](https://github.com/jeffpaul) via [#2199](https://github.com/10up/ElasticPress/pull/2199)
* Autosuggest: new `ep_autosuggest_default_selectors` filter. Props [@JakePT](https://github.com/JakePT) and [@johnbillion](https://github.com/johnbillion) via [#2181](https://github.com/10up/ElasticPress/pull/2181)
* WP-CLI: Index by ID ranges with `--upper-limit-object-id` and `--lower-limit-object-id`. Props [@WPprodigy](https://github.com/WPprodigy), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia) via [#2171](https://github.com/10up/ElasticPress/pull/2171)
* `Elasticsearch::get_documents()` and `Indexable::multi_get()`. Props [@nickdaugherty](https://github.com/nickdaugherty), [@felipeelia](https://github.com/felipeelia), and [@Rahmon](https://github.com/Rahmon) via [#2210](https://github.com/10up/ElasticPress/pull/2210)
* Custom sorting to features on the Features page. Props [@Rahmon](https://github.com/Rahmon) via [#1987](https://github.com/10up/ElasticPress/pull/1987)
* Terms: add a new `facet` field to hold the entire term object in json format. Props [@moraleida](https://github.com/moraleida) via [#2071](https://github.com/10up/ElasticPress/pull/2071)
* Elasticsearch connection check to Site Health page. Props [@spacedmonkey](https://github.com/spacedmonkey) and [@Rahmon](https://github.com/Rahmon) via [#2084](https://github.com/10up/ElasticPress/pull/2084)
* Support for NOT LIKE operator for meta_query. Props [@Thalvik)](https://github.com/Thalvik) and [@Rahmon](https://github.com/Rahmon) via [#2157](https://github.com/10up/ElasticPress/pull/2157)
* Support for `category__not_in` and `tag__not_in`. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia) via [#2174](https://github.com/10up/ElasticPress/pull/2174)
* Support for `post__name_in`. Props [@jayhill90](https://github.com/jayhill90) and [@oscarssanchez](https://github.com/oscarssanchez) via [#2194](https://github.com/10up/ElasticPress/pull/2194)
* `$indexable_slug` property to `ElasticPress\Indexable\Post\SyncManager`. Props [@edwinsiebel](https://github.com/edwinsiebel) via [#2196](https://github.com/10up/ElasticPress/pull/2196)
* Permission check bypass for indexing / deleting for cron and WP CLI. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@felipeelia](https://github.com/felipeelia) via [#2172](https://github.com/10up/ElasticPress/pull/2172)
* Check if term exists before a capabilities check is done. Props [@msawicki](https://github.com/msawicki) via [#2230](https://github.com/10up/ElasticPress/pull/2230)
* New `ep_show_indexing_option_on_multisite` filter. Props [@johnbillion](https://github.com/johnbillion) and [@Rahmon](https://github.com/Rahmon) via [#2156](https://github.com/10up/ElasticPress/pull/2156)
* Documentation updates related to upcoming changes in 4.0.0. Props [@jeffpaul](https://github.com/jeffpaul) via [#2248](https://github.com/10up/ElasticPress/pull/2248)
* Documentation about how to search using rendered content (shortcodes and reusable blocks). Props [@johnbillion](https://github.com/johnbillion) and [@felipeelia](https://github.com/felipeelia) via [#2127](https://github.com/10up/ElasticPress/pull/2127)
* Autosuggest: filter results HTML by defining a `window.epAutosuggestItemHTMLFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT) via [#2146](https://github.com/10up/ElasticPress/pull/2146)

### Changed
* Autosuggest: JavaScript is not loaded anymore when ElasticPress is indexing. Props [@fagiani](https://github.com/fagiani) and [@felipeelia](https://github.com/felipeelia) via [#2163](https://github.com/10up/ElasticPress/pull/2163)
* `Indexable\Post\Post::prepare_date_terms()` to only call `date_i18n()` once. Props [@WPprodigy](https://github.com/WPprodigy) and [@Rahmon](https://github.com/Rahmon) via [#2214](https://github.com/10up/ElasticPress/pull/2214)

### Removed
* Assets source mappings. Props [@Rahmon](https://github.com/Rahmon) and [@MadalinWR](https://github.com/MadalinWR) via [#2162](https://github.com/10up/ElasticPress/pull/2162)
* References to `posts_by_query` property and `spl_object_hash` calls. Props [@danielbachhuber](https://github.com/danielbachhuber) and [@Rahmon](https://github.com/Rahmon) via [#2158](https://github.com/10up/ElasticPress/pull/2158)

### Fixed
* GitHub issue templates. Props [@jeffpaul](https://github.com/jeffpaul) via [#2145](https://github.com/10up/ElasticPress/pull/2145)
* Facets: error in filters where terms wouldn't match if the user types a space. Props [@felipeelia](https://github.com/felipeelia) via [#2218](https://github.com/10up/ElasticPress/pull/2218)
* Facets: pagination parameters in links are now removed when clicking on filters. Props [@shmaltz](https://github.com/shmaltz), [@oscarssanchez](https://github.com/oscarssanchez), and [@Rahmon](https://github.com/Rahmon) via [#2229](https://github.com/10up/ElasticPress/pull/2229)
* Output of WP-CLI index errors. Props [@notjustcode-sp](https://github.com/notjustcode-sp) and [@felipeelia](https://github.com/felipeelia) via [#2243](https://github.com/10up/ElasticPress/pull/2243)
* `index_name` is transformed in lowercase before the index creation in Elasticsearch. Props [@teoteo](https://github.com/teoteo) and [@felipeelia](https://github.com/felipeelia) via [#2173](https://github.com/10up/ElasticPress/pull/2173)
* Validate that a meta_value is a recognizable date value before storing. Props [@jschultze](https://github.com/jschultze), [@moraleida](https://github.com/moraleida) and [@Rahmon](https://github.com/Rahmon) via [#1703](https://github.com/10up/ElasticPress/pull/1703)
* Array with a MIME type without the subtype in `post_mime_type` argument. Props [@ethanclevenger91](https://github.com/ethanclevenger91) and [@Rahmon](https://github.com/Rahmon) via [#2222](https://github.com/10up/ElasticPress/pull/2222)
* Sort for WP_User_Query. Props [@Rahmon](https://github.com/Rahmon) via [#2226](https://github.com/10up/ElasticPress/pull/2226)
* WP Acceptance Tests. Props [@felipeelia](https://github.com/felipeelia) via [#2184](https://github.com/10up/ElasticPress/pull/2184)
* Styling issue of Autosuggest and search block (WP 5.8). Props [@dinhtungdu](https://github.com/dinhtungdu) via [#2219](https://github.com/10up/ElasticPress/pull/2219)
* `Undefined variable: closed` notice in `Elasticsearch::update_index_settings()`. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@pschoffer](https://github.com/pschoffer) via [#2159](https://github.com/10up/ElasticPress/pull/2159)
* Documentation for WP-CLI `*-feature` commands. Props [@felipeelia](https://github.com/felipeelia) via [#2164](https://github.com/10up/ElasticPress/pull/2164)
* Custom Results: a `current_user_can()` call now receives the post ID instead of the whole object. Props [@Sysix](https://github.com/Sysix) via [#2255](https://github.com/10up/ElasticPress/pull/2255)
* Autosuggest: adjust debounce to avoid sending unnecessary requests to the server. Props [@Rahmon](https://github.com/Rahmon) via [#2257](https://github.com/10up/ElasticPress/pull/2257)

### Security
* Updated browserslist and jsdoc versions. Props [@felipeelia](https://github.com/felipeelia) via [#2246](https://github.com/10up/ElasticPress/pull/2246)
* Updated lodash, hosted-git-info, ssri, rmccue/requests, and y18n versions. Props [@dependabot](https://github.com/dependabot) via [#2203](https://github.com/10up/ElasticPress/pull/2203), [#2204](https://github.com/10up/ElasticPress/pull/2204), [#2179](https://github.com/10up/ElasticPress/pull/2179), [#2188](https://github.com/10up/ElasticPress/pull/2188), and [#2153](https://github.com/10up/ElasticPress/pull/2153)

## [3.5.6] - 2021-03-18
This release fixes some bugs and also adds some new actions and filters.

Security Fix:
* Updated JS dependencies. Props [@hats00n](https://github.com/hats00n) and [@felipeelia](https://github.com/felipeelia)

Bug Fixes:
* Fixed document indexing when running index command with nobulk option. Props [@Rahmon](https://github.com/Rahmon)
* Added an extra check in the iteration over the aggregations. Props [@felipeelia](https://github.com/felipeelia)
* Fixed no mapping found for [name.sortable] for Elasticsearch version 5. Props [@Rahmon](https://github.com/Rahmon)
* Fixed uninstall process to remove all options and transients. Props [@Rahmon](https://github.com/Rahmon)

Enhancements:
* Added missing inline JS documentation. Props [@JakePT](https://github.com/JakePT)
* Added the filter `ep_autosuggest_http_headers`. Props [@Rahmon](https://github.com/Rahmon)
* Added terms indexes to the status and stats WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* The Protected Content feature isn't auto-activated when using ElasticPress.io anymore. Props [@felipeelia](https://github.com/felipeelia)
* Added the new filter `ep_highlight_should_add_clause` to let developers decide where the highlight clause should be added to the ES query. Props [@felipeelia](https://github.com/felipeelia)
* Added the new filter `epwr_weight` and changed the default way scores are applied based on post date. Props [@Rahmon](https://github.com/Rahmon)

## [3.5.5] - 2021-02-25
This release fixes some bugs and also adds some new actions and filters.

Bug Fixes:
* Fix a problem in autosuggest when highlighting is not active. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon)
* Fix Facet Term Search for more than one Widget. Props [@goaround](https://github.com/goaround)
* Fix a Warning that was triggered while using PHP 8. Props [@Rahmon](https://github.com/Rahmon)
* Fix a wrong phrase in the Indexables documentation. Props [@jpowersdev](https://github.com/jpowersdev)

Enhancements:
* Add an `is-loading` class to the search form while autosuggestions are loading. Props [@JakePT](https://github.com/JakePT)
* Add the new `set-algorithm-version` and `get-algorithm-version` WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* Add a new `ep_query_weighting_fields` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott)
* Add two parameters to the `ep_formatted_args_query` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott)
* Add the new `set-algorithm-version` and `get-algorithm-version` WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* Create a new section in documentation called `Theme Integration`. Props [@JakePT](https://github.com/JakePT)
* Improvements to contributing documentation and tests. Props [@jeffpaul](https://github.com/jeffpaul) and [@felipeelia](https://github.com/felipeelia)
* Add the following new actions: `ep_wp_cli_after_index`, `ep_after_dashboard_index`, `ep_cli_before_set_search_algorithm_version`, `ep_cli_after_set_search_algorithm_version`, `ep_cli_before_clear_index`, `ep_after_update_feature`, and `ep_cli_after_clear_index`. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon)

## [3.5.4] - 2021-02-11
This is primarily a security and bug fix release. PLEASE NOTE that versions 3.5.2 and 3.5.3 contain a vulnerability that allows a user to bypass the nonce check associated with re-sending the unaltered default search query to ElasticPress.io that is used for providing Autosuggest queries. If you are running version 3.5.2. or 3.5.3 please upgrade to 3.5.4 immediately.

Security Fix:
* Fixed a nonce check associated with updating the default Autosuggest search query in ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia)

Bug Fixes:
* Fix broken click on highlighted element in Autosuggest results. Props [@felipeelia](https://github.com/felipeelia)
* Properly cast `from` parameter in `$formatted_args` to an integer to prevent errors if empty. Props [@CyberCyclone](https://github.com/CyberCyclone)

Enhancements:
* Add an `ep_is_facetable` filter to enable custom control over where to show or hide Facets. Props [@moraleida]
* Improvements to contributing documentation and tests. Props [@jeffpaul](https://github.com/jeffpaul) and [@felipeelia](https://github.com/felipeelia)

## [3.5.3] - 2021-01-28
This is a bug fix release.

Bug Fixes:
* Fixed a bug where the `ep-synonym` post type is updated to a regular post, which can cause it to be accidentally deleted. Props [@Rahmon](https://github.com/Rahmon)
* Fixed CSS formatting issues in the Settings and Features menus. Props [@Rahmon](https://github.com/Rahmon)

## [3.5.2] - 2021-01-18
This is a bug fix release.

Bug Fixes:
* Fixed a typo in elasticpress.pot. Props [@alexwoollam](https://github.com/alexwoollam)
* Don’t use timestamps that cause 5 digit years. Props [@brandon-m-skinner](https://github.com/brandon-m-skinner)
* Fix admin notice on the Synonyms page. Props [@Rahmon](https://github.com/Rahmon)
* Properly update slider numbers while sliding. Props [@Rahmon](https://github.com/Rahmon)
* Properly handle error from `get_terms()`. Props [@ciprianimike](https://github.com/ciprianimike)
* Fix incorrect titles page. Props [@Rahmon](https://github.com/Rahmon)
* Fix linting tests. Props [@felipeelia](https://github.com/felipeelia)
* Fix issue with price filter unsetting previous query. Props [@oscarssanchez](https://github.com/oscarssanchez)

Enhancements:
* Added actions that fire after bulk indexing (`ep_after_bulk_index`), in event of an invalid Elasticsearch response (`ep_invalid_response`), and before object deletion (`ep_delete_{indexable slug}`); added filters `ep_skip_post_meta_sync`, `pre_ep_index_sync_queue`, `ep_facet_taxonomies_size`, `epwr_decay_function`, `and epwr_score_mode`. Props [@brandon-m-skinner](https://github.com/brandon-m-skinner)
* Added `ep_filesystem_args` filter. Props [@pjohanneson](https://github.com/pjohanneson)
* Add SKU field to Weighting Engine if WooCommerce is active and fix issue with overriding `search_fields`. Props [@felipeelia](https://github.com/felipeelia)
* Support `author__in` and `author__not_in` queries. Props [@dinhtungdu](https://github.com/dinhtungdu)
* Update multiple unit tests. Props [@petenelson](https://github.com/petenelson)
* Show CLI indexing status in EP dashboard. Props [@Rahmon](https://github.com/Rahmon)
* Add `ep_query_send_ep_search_term_header` filter and don’t send `EP-Search-Term` header if not using ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia)

## [3.5.1] - 2020-10-29
A bug fix release.

Bug fixes:
* Fixes highlighting so that full content is returned instead of only snippets.
* Fix empty synonym bug.
* Only highlight post content, excerpt, and title.

Enhancements:
* Track CLI index in a headless fashion

## [3.5.0] - 2020-10-20
Version 3.5 is a very exciting release as it contains two major new features: a synonym dashboard and search term result highlighting. The synonym dashboard empowerers users to create synonym lists for searches. For example. searching "New York City" would return contain with "NYC". Search term highlighting will underline and add a CSS class to keywords within content that matches the current search.

The new version also includes a revamp of the search algorithm. This is a backwards compatibility break. If you'd like to revert to the old search algorithm, you can use the following code: `add_filter( 'ep_search_algorithm_version', function() { return '3.4'; } );`. The new algorithm offers much more relevant search results and removes fuzziness which results in mostly unwanted results for most people. If you are hooking in and modifying the search query directly, it's possible this code might break and you might need to tweak it.

Bug fixes:
* Fix default autosuggest selector.
* Fix facet feature $_GET parameter naming collision.
* Autosuggest a11y fixes. Props [amesplant](https://github.com/amesplant).
* Check $feature type before calling Feature methods. Props [pdewouters](https://github.com/pdewouters).
* Ensure trashed post is removed from Elasticsearch. Props [edwinsiebel](https://github.com/edwinsiebel).
* Add default permission callback to REST routes. Props [dkotter](https://github.com/dkotter).
* Hide links to weighting and synonym dashboard if network activated. Props [felipeelia](https://github.com/felipeelia).
* Only sync post on allow listed post meta. Props [felipeelia](https://github.com/felipeelia).
* Check if site is indexable before syncing in network activated mode.
* Fix facet widget and 3-level deep hierarchical taxonomy. Props [dinhtungdu](https://github.com/dinhtungdu).
* Make sure AJAX sync is disabled if EP_DASHBOARD is set to false.. Props [turtlepod](https://github.com/turtlepod).

Enhancements:
* Synonym dashboard. Props [christianc1](https://github.com/christianc1).
* Search term highlighting. Props [oscarsanchez](https://github.com/oscarsanchez).
* Search algorithm improvements.
* Improved WP Acceptance tests. Props [asharirfan](https://github.com/asharirfan).
* Rename ElasticPress submenu to "Features". Props [helen](https://github.com/helen).
* Add functionality for skipping ElasticPress install.
* Increase max supported Elasticsearch version to 7.9.
* Add filter to prepared user meta data. Props [g-kanoufi](https://github.com/g-kanoufi).
* Improve Terms Feature terminology to remove confusion.
* Add filter to facet terms query arguments. Props [felipeelia](https://github.com/felipeelia).

## [3.4.3] - 2020-07-21
Enhancements:
* Remove jQuery from front end JavaScript dependencies.

Bug Fixes:
* Fix accessibility bug on autosuggest.
* Fix broken facet search.

## [3.4.2] - 2020-06-17
Bug fixes:
* uninstall.php: Change the EP_FILE const to its value. Props [felipeelia](https://github.com/felipeelia).
* Fix list features WP CLI command. Props [felipeelia](https://github.com/felipeelia).
* Add `rel="nofollow"` to facet links. Props [mlaroy](https://github.com/mlaroy).
* Facets widget: Move `<div>` outside `ob_start()`. Props [kallehauge](https://github.com/kallehauge).
* Load facet scripts and styles only when they are really necessary. Props [goaround](https://github.com/goaround).
* Index attachments with Protected Content and query for them in media search. Props [oscarsanchez](https://github.com/oscarsanchez).
* Fixed `Deprecated field [include] used, expected [includes] instead.`. Props [dinhtungdu](https://github.com/dinhtungdu).

Enhancements:
* Add filter for enabling sticky posts.  Props [shadyvb](https://github.com/shadyvb).
* Add sync kill filter. Props [barryceelen](https://github.com/barryceelen).
* Add timeout filters for bulk_index and index_document. Props [@oscarsanchez](https://github.com/oscarsanchez).

## [3.4.1] - 2020-3-31
* Make weighting dashboard flex containers to prevent the slider from changing size. Props [@mlaroy](https://github.com/mlaroy).
* Fix issue where weightings wouldn't save properly for certain post types. Props [mustafauysal](https://github.com/mustafauysal).
* Fix bug where terms wouldn't finish syncing in certain scenarios.
* Properly order WooCommerce products using double to account for decimals. Props [@oscarsanchez](https://github.com/oscarsanchez).
* Show current indices in index health dashboard. Props [moraleida](https://github.com/moraleida).

## [3.4.0] - 2020-03-03
* Addition of Terms Indexable and Feature. ElasticPress can now integrate with `WP_Term_Query`. Props [dkotter](https://github.com/dkotter).
* Fixes for `WP_User_Query` 'fields' parameter. Props [petenelson](https://github.com/petenelson).
* Support all taxonomies in root of `WP_Query`
* Readd `ep_retrieve_aggregations` hook for backwards compatibility
* Move indexable posts class registration into a function that runs in the `plugins_loaded` action. Props [petenelson](https://github.com/petenelson).
* Fix author name in weighting and use post_author.display_name for weighted author field search. Props [petenelson](https://github.com/petenelson).
* Add `ep_prepared_*_meta` filters
* Refactor CLI indexing code for simplicity.
* Limit indexed Protected Content post types removing `revision`, `oembed_cache`, `custom_css`, `user_request`, `customize_changeset`, and `wp_block`.
* Cast taxonomy slug to array in case it's already an array in `WP_Query`.
* Remove unnecessary usage of `--network-wide` CLI parameter.
* Add name, nickname, and display name to fields used for user search.
* Add `clear-transient` WP CLI command.
* Don't make product categories facetable when WooCommerce feature is not active. Props [mustafauysal](https://github.com/mustafauysal).

## [3.3.0] - 2018-12-18
* Officially support Elasticsearch 7.5
* Add optional Google Analytics Autosuggest tracking Event
* Fix single node warning before sync has occurred.
* When `ep_integrate` is set to false, do not apply faceting.
* Fix search ordering error when there are no pointers.
* Add filter `ep_get_hits_from_query` to modify retrieved Elasticsearch hits.
* Make sure `post_type` array does not include keys. Fixes a bbPress issue.
* Pass query object to EP response so we can check for main query. This fixes a faceting bug.
* Add EP-Search-Term header to autosuggest requests to EP.io
* Clean up indexing transient on sigkill

## [3.2.6] - 2019-11-27
* Fix Under some edge conditions content for autosuggest can be large - don't cache it

## [3.2.5] - 2019-11-20
* Fix WP <5.0 fatal error on register_block_type.

## [3.2.4] - 2019-11-19
* Fix Gutenberg block initialization
* Fix Autosuggest: remove filter with proper priority in query generation. Props [Maxdw](https://github.com/Maxdw).
* Fix Autosuggest: returning WP_Error for non object cache autosuggest queries causes issue. Fallback to transient

## [3.2.3] - 2019-11-13
* Ensure query building for Autosuggest does not fallback to WPDB.

## [3.2.2] - 2019-11-05
* Fix PHPCS errors. Props [mmcachran](https://github.com/mmcachran)
* Fix ensuring stats are built prior to requesting information
* Fix related post block enqueue block assets on the frontend
* Fix custom order results change webpack config for externals:lodash
* Fix don't overwrite search fields
* Autosuggest queries generated though PHP instead of JavaScript
* Add WP Acceptance tests
* Add new WP-CLI commands: get_indexes and get_cluster_indexes

## [3.2.1] - 2019-10-14
* Fix Gutenberg breaking issue with Related Posts and image blocks. Props [adamsilverstein](https://github.com/adamsilverstein)

## [3.2.0] - 2019-10-08
* Improve block asset enqueueing: hook on `enqueue_block_editor_assets`. Props [adamsilverstein](https://github.com/adamsilverstein).
* Handle empty search weighting fields bug.
* Integrate WooCommerce default filter by price widget with ES range query.
* Improve messaging for custom result post type.
* Index health page.
* Add tag_in and tag__and support.
* Related posts Gutenberg block.
* Facet widget ordering option. Props [psorensen](https://github.com/psorensen).
* Control Index-ability of individual sites in multisite.
* Integrate WooCommerce default filter by price widget with ES range query.

See https://github.com/10up/ElasticPress/pulls?utf8=%E2%9C%93&q=is%3Apr+milestone%3A3.2.0+is%3Aclosed+

## [3.1.4] - 2019-08-28
Version 3.1.4 is a bug fix release.

See fixes:
https://github.com/10up/ElasticPress/pulls?q=is%3Apr+milestone%3A3.1.4+is%3Aclosed

## [3.1.3] - 2019-08-22
This is a bug fix release.
* Check wpcli transient before integrating with queries.
* Fix version comparison bug when comparing Elasticsearch versions.
* Use proper taxonomy name for WooCommerce attributes.
* Increase Elasticsearch minimum supported version to 5.0.
* Fix product attribute archives.

## [3.1.2] - 2019-08-16
This is a bug fix release with some filter additions.

- Add ep_es_query_results filter.
- Add option to sync prior to shutdown.
- Readme update around WPCLI post syncing. Props [@mmcachran](https://github.com/mmcachran).
- Ignore sticky posts in `find_related`. Props [@columbian-chris](https://github.com/columbian-chris).
- Weighting dashboard fixes around saving. [@oscarsanchez](https://github.com/oscarsanchez).
- Weighting UI improvements. Props [@mlaroy](https://github.com/mlaroy).

## [3.1.1] - 2019-07-25
### Fixed
- Ensure taxonomies that are shared among multiple post types show up on the weighting screen

## [3.1.0] - 2019-07-22
### Added
- Support for nested tax queries. Props [@dkotter](https://github.com/dkotter)
- `ep_bulk_index_action_args` filter. Props [@fabianmarz](https://github.com/fabianmarz)
- Add filters to control MLT related posts params.
- `ep_allow_post_content_filtered_index` filter to bypass filtered post content on indexing.
- Weighting dashboard to control weights of specific fields on a per post type basis
- Search ordering feature. Enables custom results for specific search queries.

### Changed
- Refactor admin notice, admin screen "resolver", and install path logic
- WordPress.org profile
- New EP settings interface. Props [@dkoo](https://github.com/dkoo)
- Delete pagination from facet URL.
- allows WooCommerce product attributes to be facetable in 3.0
- Autosuggest queries now match the search queries performed by WordPress, including weighting and any custom results

### Fixed
- Fix data escaping in WP 4.8.x
- Support order by "type"/"post_type" in EP queries
- Properly redirect after network sync
- User mapping for pre 5.0 Props [@mustafauysal](https://github.com/mustafauysal)
- Avoid multiple reflows in autosuggest. Props [@fabianmarz](https://github.com/fabianmarz)
- 400 error when popularity is default sorting.
- Fixed Facet widget not rendering WC product attribute options. Props [@fabianmarz](https://github.com/fabianmarz)
- Delete wpcli sync option/transient when an error occurs
- Create index/network alias when adding a new site on a network activated installation. Props [@elliott-stocks](https://github.com/elliott-stocks)
- Fix WooCommerce order search when WooCommerce module activated but protected content turned off.

## [3.0.3] - 2019-06-04
### Added
- Pass $post_id twice in ep_post_sync_kill for backwards compatibility. Props [@aaemnnosttv](https://github.com/aaemnnosttv)
- `ep_search_request_path` filter for backwards compant.
- `ep_query_request_path` filter for modifying the query path.

### Fixed
- Missing action name in post query integration.
- Properly add date filter to WP_Query.

## [3.0.2] - 2019-05-23
### Fixed
- Date query errors
- Re-add ep_retrieve_the_{type} filter. Props [@gassan](https://github.com/gassan)
- Empty autosuggest selector notice

## [3.0.1] - 2019-05-20
### Changed
- Changed max int to use core predefined constant. Props [@fabianmarz](https://github.com/fabianmarz)

### Fixed
- `wp elasticpress stats` and `wp elasticpress status` commands fatal error.
- Add autosuggest selector field default to fix notice.
- Re-add `ep_find_related` as deprecated function.
- Properly support legacy feature registration callbacks per #1329.
- Properly disable settings as needed on dashboard.
- Don't force document search on REST requests.

## [3.0] - 2019-05-13
### Notice
- Requires re-index

### Overview
- 3.0 is a refactor of ElasticPress for modern coding standards (PHP 5.4 required) as well as the introduction to indexables. Indexables abstracts out content types so data types other than post can be indexed and searched. 3.0 includes user indexing and search (integration with WP_User_Query). User features require at least WordPress version 5.1.
- The refactor changes a lot of ElasticPress internals. The biggest change is the feature registration API has completely changed. Now, new features should extend the `ElasticPress\Feature` class rather than calling `ep_register_feature`. Older features should be backwards compatible.

### Added
- Elasticsearch language setting in admin

### Changed
- `ep_post_sync_kill` filter removed `$post_args` argument.
- `posts-per-page` changed to `per-page` for WP-CLI index command.

### Removed
- `ep_feature_setup` action

## [2.8.2] - 2019-03-26
### Added
- WooCommerce product attributes as facets.
- Performance Boost for document indexing.

### Fixed
- Issue on WP REST API searches.
- Case-sensitivity issue with facet search.

## [2.8.1] - 2019-02-13
### Fixed
- Homepage out of chronological order.
- Missing meta key. (Props [@turtlepod](https://github.com/turtlepod))
- Bulk indexing default value on settings page.

## [2.8.0] - 2019-02-08
### Added
- Sticky posts support.
- WooCommerce searches with custom fields.
- Elasticsearch version in settings. (Props [@turtlepod](https://github.com/turtlepod))
- Allow user to set number of posts during bulk indexing cycle.
- Facet query string customization (Props [@ray-lee](https://github.com/ray-lee))
- Protected content: filtering of filtered post types.
- Implemented --post-ids CLI option to index only specific posts. (Props [@dotancohen](https://github.com/dotancohen))

### Changed
- Meta LIKE query adjustment.
- Autosuggest to abide by plugin settings.
- Adjustment to `wp elasticpress status`

### Removed
- Logic that determines if blog is public / indexable. (Resolves sync issue.)
- Date weighting for protected content admin queries.

### Fixed
- Autosuggest bugfix.
- Auto activating sync notices. (Props [@petenelson](https://github.com/petenelson))

## [2.7.0] - 2018-12-06
### Notice
- Requires re-index

### Added
- Prevent indexing when blog is deleted or not public.
- Do not apply absint to comment_status.
- Random WooCommerce ordering allowed.
- Better error notices. (Props [@petenelson](https://github.com/petenelson))

### Changed
- Query only post IDs when indexing. (Props [@elliott-stocks](https://github.com/elliott-stocks))

### Fixed
- ElasticPress.io credentials bugfix.
- Related posts bugfix.

## [2.6.1] - 2018-08-24
### Fixed
- Missing file for wp-cli.

## [2.6] - 2018-08-22
### Added
- Ability to set autosuggest endpoint by a constant (EP_AUTOSUGGEST_ENDPOINT).
- Enable WooCommerce products to be included in autosuggest results.
- Support for tax_query operators EXISTS and NOT EXISTS.
- Filter to change default orderby/sort (ep_set_default_sort).

### Changed
- Do not search for author_name when searching products in WooCommerce.

## [2.5.2] - 2018-05-09
### Notice
- Requires re-index

### Removed
- Unnecessary facet JavaScript

### Fixed
- Facet aggregations warning

## [2.5.1] - 2018-05-02
### Notice
- Requires re-index as we change the way data is being sent to Elasticsearch.
- It's also worth noting for ElasticPress version 2.5+, the Facets feature, which is on by default, will run post type archive and search page main queries through Elasticsearch. If Elasticsearch is out of sync with your content (possible in rare edge cases), this could result in incorrect content being shown. Turning off Facets would fix the problem.

### Fixed
- Don't pre-strip HTML before sending it to Elasticsearch.
- Support PHP 5.2 backwards compat.
- Don't show faceting widget if post type doesn't support taxonomy.

## [2.5] - 2018-04-23
### Overview
- ElasticPress 2.5 includes a new Facets feature that makes it easy to add high performance content filtering controls to a website.
- A new Facets widget enables site administrators to add taxonomy facets to a sidebar (or any widgetized area). When viewing a content list on the front end of the website, the widget will display the name of the taxonomy – e.g. “Categories” – and a checklist with all of its terms. Visitors can narrow down content by selecting terms they are interested in. The Facets feature can be globally configured to narrow results to content that is tagged with any or all of the selected terms. The widget’s front end output contains carefully named CSS classes, so that designers and developers can apply unique styling.

### Added
- Official support for Elasticsearch 6.2
- Increased functional parity with the WP_Query API
- Facets feature
- `--post-ids` CLI option to index only specific posts. Props [@dotancohen](https://github.com/dotancohen).
- Filter for hiding host setting in dashboard. Props [@tomdxw](https://github.com/tomdxw).
- Support `WP_Query` meta query `not between` comparator.

### Fixed
- Disallow duplicated Elasticsearch requests on WooCommerce orders page. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Taxonomy sync object warning. Props [@eugene-manuilov](https://github.com/eugene-manuilov)
- `true` in `is_empty_query` terminates ep_query process when it shouldn't. Props [@yaronuliel](https://github.com/yaronuliel)

## [2.4.2] - 2018-01-31
### Fixed
- Related posts not showing up bug.

## [2.4.1] - 2018-01-30
### Added
- Increase maximum Elasticsearch compatibility to 6.1

### Fixed
- Support Elasticsearch 6.1 and properly send Content-Type header with application/json. Props [@eugene-manuilov](https://github.com/eugene-manuilov).
- Autosuggest event target issue bug. Props [@eugene-manuilov](https://github.com/eugene-manuilov).
- Widget init bug. Props [@eugene-manuilov](https://github.com/eugene-manuilov).
- Taxonomy sync parameter warning. Props [@eugene-manuilov](https://github.com/eugene-manuilov).

## [2.4] - 2017-11-01
### Overview
- Version 2.4 introduces the Autosuggest feature. When enabled, input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As text is entered into the search field, suggested content will appear below it, based on top search results for the text. Suggestions link directly to the content.
- We also added hooks and filters to ElasticPress that make query logging possible. The [Debug Bar ElasticPress](https://github.com/10up/debug-bar-elasticpress) plugin now adds a Query Log screen to the ElasticPress admin menu. The Query Log is an extremely powerful tool for diagnosing search and indexing issues.

### Added
- Autosuggest feature
- Hooks for query log functionality in [Debug Bar ElasticPress](https://github.com/10up/debug-bar-elasticpress)
- Support `WP_Query` `fields` parameter. Props [@kallehauge](https://github.com/kallehauge).
- Setting for enabling/disabling date weighting in search. Props [@lukaspawlik](https://github.com/kallehauge).
- Shipping class as indexed WooCommerce taxonomy. Props [@kallehauge](https://github.com/kallehauge).
- Allow WooCommerce orders to be searched by items. Props [@kallehauge](https://github.com/kallehauge).
- Support Elasticsearch 5.6
- Filter to granularly control admin notices. Props [@mattonomics](https://github.com/mattonomics).
- Support ES 5.5+ strict content type checking. Props [@sc0ttclark](https://github.com/sc0ttclark)

### Removed
- Extra post meta storage key from Elasticsearch

### Fixed
- `author_name` search field. Props [@ivankristianto](https://github.com/ivankristianto).
- Unavailable taxonomy issue in WooCommerce. Props [@ivankristianto](https://github.com/ivankristianto).
- Index all publicly queryable taxonomies. Props [@allan23](https://github.com/allan23).
- Resolve case insensitive sorting issues. Props [@allan23](https://github.com/allan23).
- Escaping per VIP standards. Props [@jasonbahl](https://github.com/jasonbahl).
- WooCommerce post type warnings.

## [2.3.2] - 2017-06-30
### Fixed
- Broken upgrade sync notification.
- Cache ES plugins request. **This is super important.** Instead of checking the status of ES on every page load, do it every 5 minutes. If ES isn't available, show admin notification that allows you to retry the host.

## [2.3.1] - 2017-06-29
### Notice
- This requires a re-index if you are using the WooCommerce feature.

### Fixed
- Properly respect WC product visibility. Props [@ivankristianto](https://github.com/ivankristianto).

## [2.3] - 2017-05-26
### Overview
- Version 2.3 introduces the Documents feature which indexes text inside of popular file types, and adds those files types to search results. We've also officially added support for Elasticsearch 5.3.

### Added
- Documents feature
- Enable multiple feature status messages
- Disable dashboard sync via constant: `define( 'EP_DASHBOARD_SYNC', false );`. Props [@rveitch](https://github.com/rveitch).
- Filter for custom WooCommerce taxonomies. Props [@kallehauge](https://github.com/kallehauge).
- Support WooCommerce `product_type` taxonomy. Props [@kallehauge](https://github.com/kallehauge).

### Fixed
- WP-CLI `--no-bulk` number of posts indexed message. Props [i@vankristianto](https://github.com/ivankristianto).
- Honor `ep_integrate` in WooCommerce queries. Props [@ivankristianto](https://github.com/ivankristianto).
- Properly check when ES results are empty. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Incorrect `found_posts` set in query when ES is unavailable. Props [@lukaspawlik](https://github.com/lukaspawlik)

## [2.2.1] - 2017-03-30
### Added
- `EP_INDEX_PREFIX` constant. If set, index names will be prefixed with the constant. Props [@allan23](https://github.com/allan23).
- Increase total field limit to 5000 and add filter. Props [@ssorathia](https://github.com/ssorathia).
- Increase max result window size to 1000000 and add filter.

### Removed
- operator=>AND unneed execution code.

### Fixed
- Dashboard syncing delayed start issues.
- If plugins endpoint errors, try root endpoint to get the ES version.
- Make sure orderby is correct for default WooCommerce sorting. Props [@ivankristianto](https://github.com/ivankristianto).
- Stop dashboard sync if error occurs in the middle. Props [@ivankristianto](https://github.com/ivankristianto).
- Prevent EP from auto-activating a feature that was force deactivated
- Prevent massive field Elasticsearch error when indexing large strings

## [2.2] - 2017-02-28
### Notice
- Requires re-index

### Overview
- Version 2.2 rethinks the module process to make ElasticPress a more complete query engine solution. Modules are now auto-on and really just features. Why would anyone want to not use amazing functionality that improves speed and relevancy on their website? Features (previously modules) can of course be overridden and disabled. Features that don't have their minimum requirements met, such as a missing plugin dependency, are auto-disabled.
- We've bumped the minimum Elasticsearch version to 1.7 (although we strongly recommend 2+). The maximum tested version of Elasticsearch is version 5.2. If you are running Elasticsearch outside this version range, you will see a warning in the dashboard.

### Added
- __(Breaking change)__ Module registration API changed. See `register_module` in `classes/class-ep-modules.php`.
- __(Breaking change)__ Related posts are now in a widget instead of automatically being appending to content.
- __(Breaking change)__ Admin module renamed to Protected Content.
- Admin warning if current Elasticsearch version is not between the min/max supported version. Version 2.2 supports versions 1.3 - 5.1.
- Auto-reindex on versions requiring reindex.
- User friendly admin notifications for ElasticPress not set up, first sync needed, and feature auto activation.
- Protected Content feature applies to all features. This means if Protected Content isn't active, search or WooCommerce integration won't happen in the admin.
- Support for post_mime_type. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- 'number' param to satisfy WP v4.6+ fixing get_sites call. Props [@rveitch](https://github.com/rveitch)

### Fixed
- Back compat with old `ep_search` function.
- Respect indexable post types in WooCommerce feature
- New product drafts not showing in WooCommerce admin list
- WooCommerce feature breaking image search in media library. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- WooCommerce order search broken
- Stop the insansity made private. Props [@sc0ttclark](https://github.com/sc0ttclark)
- Multidimensional meta queries. Props [@Ritesh-patel](https://github.com/Ritesh-patel).
- Properly show bulk index errors in WP-CLI
- Update ep_delete_post, include $post_type argument. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- post_type product getting set in any WP_Query if tax_query is provided in WooCommerce feature. Props [@Ritesh-patel](https://github.com/Ritesh-patel)
- Order by proper relevancy in WooCommerce product search. Props [@ivankristianto](https://github.com/ivankristianto)
- Recursion fatal error due to oembed discovery during syncing. Props [@ivankristianto](https://github.com/ivankristianto)

## [2.1.2] - 2016-11-11
### Notice
- Requires re-index

### Changed
- Separate mapping for ES 5.0+

### Fixed
- Unit tests

## [2.1.1] - 2016-09-29
### Fixed
- PHP 5.3 errors
- Properly show syncing button module placeholder during sync

## [2.1] - 2016-09-20
### Backcompat breaks
- Move ep_admin_wp_query_integration to search integration only. EP integration by default is available everywhere.
- Remove `keep alive` setting
- Remove setting to integrate with search (just activate the module instead)
- Back up hosts code removed
- Remove active/inactive state. Rather just check if an index is going on our not.

### Added
- Support `meta_key` and `meta_value`
- Order by `meta_value_num`
- Search scope file. Props [@rveitch](https://github.com/rveitch)
- Support WP_Query `post_status`. Props [@sc0ttclark](https://github.com/sc0ttkclark)

### Changed
- Redo UI
- Make plugin modular
- Bundle existing modules into plugin

### Removed
- Remove unnecessary back up hosts code

### Fixed
- Properly support `post_parent = 0`. Props [@tuanmh](https://github.com/tuanmh)
- `post__in` support
- `paged` overwriting `offset`
- Integer and comma separated string `sites` WP_Query processing. Props [@jaisgit](https://github.com/jaisgit).

## [2.0.1] - 2016-08-02
### Fixed
- Don't load settings on front end. This fixes a critical bug causing ElasticPress to check the Elasticsearch connection on the front end.

## [2.0] - 2016-06-01
### Overview
- 10up ships ElasticPress 2.0 with __radical search algorithm improvements__ and a __more comprehensive integration of WP_Query__. ElasticPress is now even closer to supporting the complete WP_Query API. This version also improves upon post syncing ensuring that post meta updates are synced to Elasticsearch, adds a number of important hooks, and, of course, fixes some pesky bugs.
- A special thanks goes out to [Tuan Minh Huynh](https://github.com/tuanmh) and everyone else for contributions to version 2.0.

### Added
- Radical search algorithm improvements for more relevant results (see [#508](https://github.com/10up/ElasticPress/pull/508) for details)
- Support meta `BETWEEN` queries.
- Support `OR` relation for tax queries.
- Sync post to Elasticsearch when meta is added/updated.
- Support all taxonomies as root WP_Query arguments. Props [@tuanmh](https://github.com/tuanmh)
- `ID` field to Elasticsearch mapping
- Support `post_parent` WP_Query arguments. Props [@tuanmh](https://github.com/tuanmh)
- Filter to disable printing of post index status. Props [@tuanmh](https://github.com/tuanmh)
- Useful CLI hooks
- Filter to bypass permission checking on sync (critical for front end updates)

### Changed
- Improve GUI by disabling index status meta box text and improving instructions. Props [@ivanlopez](https://github.com/ivanlopez)

### Fixed
- Consider all remote request 20x responses as successful. Props [@tuanmh](https://github.com/tuanmh)
- Plugin localization. Props [@mustafauysal](https://github.com/mustafauysal)
- Do query logging by default. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Cannot redeclare class issue. Props [@tuanmh](https://github.com/tuanmh)
- Double querying Elasticsearch by ignoring `category_name` when `tax_query` is present.
- Post deletion endpoint URL. Props [@lukaspawlik](https://github.com/lukaspawlik)

## [1.9.1] - 2016-05-23
### Fixed
- GUI not working properly when plugin is not network enabled within multisite. Props [@ivanlopez](https://github.com/ivanlopez)

## [1.9] - 2016-05-17
### Overview
- ElasticPress 1.9 adds in an admin UI, where you can set your Elasticsearch Host and run your index command, without needing to us WP-CLI. Version 1.9 also adds in some performance improvements to reduce memory consumption during indexing. Full list of enhancements and bug fixes:

### Added
- Admin GUI to handle indexing. Props [@ChrisWiegman](https://github.com/ChrisWiegman).
- Option to not disable ElasticPress while indexing. Props [@lukaspawlik](https://github.com/lukaspawlik).
- Allow filtering of which post types we want to search for. Props [@rossluebe](https://github.com/rossluebe).
- Ensure both PHPUnit and WP-CLI are available in the development environment. Props [@ChrisWiegman](https://github.com/ChrisWiegman).
- User lower-case for our composer name, so packagist can find us. Props [@johnpbloch](https://github.com/johnpbloch).
- Check query_vars, not query to determine status. Props [@ChrisWiegman](https://github.com/ChrisWiegman).
- Further reduce memory usage during indexing. Props [@lukaspawlik](https://github.com/lukaspawlik).
- post__in and post__not_in documentation. Props [@mgibbs189](https://github.com/mgibbs189).
- Elasticsearch Shield authentication headers if constant is set. Props [@rveitch](https://github.com/rveitch).

### Changed
- Improve memory usage during indexing and fix unnecessary cache flushes. Props [@cmmarslender](https://github.com/cmmarslender).

### Removed
- composer.lock from the repo. Props [@ChrisWiegman](https://github.com/ChrisWiegman).

### Fixed
- --no-bulk indexing option. Props [@lukaspawlik](https://github.com/lukaspawlik).
- Error that occurs if no Elasticsearch host is running. Props [@petenelson](https://github.com/petenelson).
- Exception error. Props [@dkotter](https://github.com/dkotter).
- WP-CLI status command. Props [@dkotter](https://github.com/dkotter).

## [1.8] (Mapping change, requires reindex) - 2016-01-19
### Overview
- ElasticPress 1.8 adds a bunch of mapping changes for accomplishing more complex WP_Query functions such as filtering by term id and sorting by any Elasticsearch property. Version 1.8 also speeds up post syncing dramatically through non-blocking queries. Full list of enhancements and bug fixes:

### Added
- Filter around the search fuzziness argument. Props [@dkotter](https://github.com/dkotter).
- Make post indexing a non-blocking query. Props [@cmmarslender](https://github.com/cmmarslender).
- Log queries for debugging. Makes [ElasticPress Debug Bar](https://github.com/10up/debug-bar-elasticpress) plugin possible.
- Make `posts_per_page = -1` possible.
- Support term id and name tax queries.
- raw/sortable to property to term mapping. Props [@sc0ttkclark](https://github.com/sc0ttkclark)
- raw/sortable property to meta mapping. Props [@sc0ttkclark](https://github.com/sc0ttkclark)
- raw/sortable to author display name and login

### Fixed
- Post deletion. Props [@lukaspawlik](https://github.com/lukaspawlik).
- Properly flush cache with `wp_cache_flush`. Props [@jstensved](https://github.com/jstensved)
- When directly comparing meta values in a meta query, use the `raw` property instead of `value`.
- Support arbitrary document paths in orderby. Props [@sc0ttkclark](https://github.com/sc0ttkclark).

## [1.7] (Mapping change, requires reindex) - 2015-12-11
### Overview
- ElasticPress 1.7 restructures meta mapping for posts for much more flexible meta queries. The `post_meta` Elasticsearch post property has been left for backwards compatibility. As of this version, post meta will be stored in the `meta` Elasticsearch property. `meta` is structured as follows:
- When querying posts, you will get back `meta.value`. However, if you plan to mess with the new post mapping, it's important to understand the intricacies.
- The real implications of this is in `meta_query`. You can now effectively search by meta types. See the new section in README.md for details on this.

### Added
- `meta.value` (string)
- `meta.raw` (unanalyzed string)
- `meta.long` (unanalyzed number)
- `meta.double` (unanalyzed number)
- `meta.boolean` (unanalyzed number)
- `meta.date` (unanalyzed yyyy-MM-dd date)
- `meta.datetime` (unanalyzed yyyy-MM-dd HH:mm:ss datetime)
- `time` (unanalyzed HH:mm:ss time)
- Index posts according to post type. Props [@sc0ttkclark](https://github.com/sc0ttkclark)

### Fixed
- Prevent missed post indexing when duplicate post dates. Props [@lukaspawlik](https://github.com/lukaspawlik)
- Complex meta types are automatically serialized upon storage.

## [1.6.2] - 2015-11-12
### Notice
- Mapping change, requires reindex

### Overview
- ElasticPress 1.6.2 fixes ALL backwards compatibility issues with Elasticsearch 2.0

### Changed
- Uses string instead of array for post type term when there is only one term.

### Removed
- `fuzzy_like_this` query and uses `multi_match` instead.

## [1.6.1] - 2015-11-09
### Notice
- Mapping change, requires reindex

### Overview
- ElasticPress 1.6.1 fixes mapping backwards compatibility issues with Elasticsearch 2.0:

### Removed
- Fields field type from object typed fields as they should be called properties.
- Path from object field types.

## [1.6] - 2015-08-31
### Overview
- ElasticPress 1.6 contains a number of important enhancements and bug fixes. Most notably, we now support Elasticsearch fallback hosts and the indexing of attachments.

### Added
- Blog id to `ep_index_name` filter. Props [@kovshenin](https://github.com/kovshenin)
- Support post caching in search
- Recursive term indexing for heirarchal taxonomies. Props [@tuanmh](https://github.com/tuanmh)
- Enable indexing of attachments
- Support fallback hosts in case main EP host is unavailable. Props [@chriswiegman](https://github.com/chriswiegman)
- `ep_retrieve_the_post` filter to support relevancy score manipulation. Props [@matthewspencer](https://github.com/matthewspencer)
- Make search results filterable. Props [@chriswiegman](https://github.com/chriswiegman)

### Fixed
- Clean up PHP Code Sniffer errors. Props [@chriswiegman](https://github.com/chriswiegman)
- Properly document Elasticsearch version
- Abide by `exclude_from_search` instead of `public` when indexing post types. Props [@allan23](https://github.com/allan23) and [@ghosttoast](https://github.com/ghosttoast).
- Allow posts to be indexed with invalid date values. Props [@tuanmh](https://github.com/tuanmh)
- Support `ep_post_sync_kill` filter in bulk indexing. Props [@Stayallive](https://github.com/Stayallive)

## [1.5.1] - 2015-07-06
### Fixed
- Prevent notices from being thrown when non-existent index properties are accessed. This was happening for people how upgraded to 1.5 without doing a re-index. Props [@allan23](https://github.com/allan23)

## [1.5] - 2015-06-25
### Added
- Support for category_name WP_Query parameter. Props [@ocean90](https://github.com/ocean90)
- Support limiting sites in network wide commands. Props [@bordoni](https://github.com/bordoni)
- Support for method to un-integrate WP_Query. Props [@kingkool68](https://github.com/kingkool68)
- Support `cache_results` in WP_Query
- Action prior to starting WP-CLI index command
- Missing headers to WP_CLI commands. Props [@chriswiegman](https://github.com/chriswiegman)
- Improve error reporting in bulk indexing during bad ES requests.
- Filter to modify request headers. Props [@tuanmh](https://github.com/tuanmh)
- Prevent bulk index from sending useless error emails. Props [@cmmarslender](https://github.com/cmmarslender)
- --offset parameter to cli indexing command. [Stayallive](https://github.com/stayallive)
- Support like query in post meta. Props [@tuanmh](https://github.com/tuanmh)
- Sanitization fixes for PHPCS. Props [@mphillips](https://github.com/mphillips)
- Filter to set default sort order. Props [@HKandulla](https://github.com/HKandulla)
- MySQL DB completely removed from integrated ElasticPress WP Query. Props [@EduardMaghakyan](https://github.com/EduardMaghakyan) and [@crebacz](https://github.com/crebacz)

### Changed
- Syncing hook to play better with plugins. Props [@jonathanbardo](https://github.com/jonathanbardo)

### Fixed
- is_search check notice. Props [@allenmoore](https://github.com/allenmoore) and [@allan23](https://github.com/allan23)
- Prevent direct access to any PHP files. Props [@joelgarciajr84](https://github.com/joelgarciajr84)
- Fields not being loaded from ES. Props [@stayallive](https://github.com/stayallive)
- Inclusive check in date_query integration. Props [@EduardMaghakyan](https://github.com/EduardMaghakyan)

## [1.4] - 2015-05-18
### Added
- `date_query` and date parameters now supported in WP_Query. Props [@joeyblake](https://github.com/joeyblake) and [@eduardmaghakyan](https://github.com/eduardmaghakyan)
- Make all request headers filterable
- EP API key to all requests as a header if a constant is defined. Props [@zamoose](https://github.com/zamoose)
- Index exists function; remove indexes on blog deletion/deactivation. Props [@joeyblake](https://github.com/joeyblake)
- Refactor wp-cli stats for multisite. Props [@jaace](https://github.com/jaace)
- Index mappings array moved to separate file. Props [@mikaelmattsson](https://github.com/mikaelmattsson)
- Support meta inequality comparisons. Props [@psorensen](https://github.com/psorensen)

### Removed
- Default shard and indices configuration numbers but maintain backwards compatibility. Props [@zamoose](https://github.com/zamoose)

### Fixed
- Duplicate sync post hooks separated. Props [@superdummy](https://github.com/superdummy)
- Don't send empty index error emails. Props [@cmmarslender](https://github.com/cmmarslender)
- Wrong author ID in post data. Props [@eduardmaghakyan](https://github.com/eduardmaghakyan)

## [1.3.1] - 2015-04-09
### Added
- Support `date` in WP_Query `orderby`. Props [@psorensen](https://github.com/psorensen)

## [1.3] - 2015-02-03
### Added
- Support `meta_query` in WP_Query integration
- Improved documentation. Each WP-CLI command has been documented
- `elasticsearch` property to global post object to assist in debugging
- `ep_integrate` param added to allow for WP_Query integration without search. (Formally called ep_match_all)
- Filter added for post statuses (defaults to `publish`). Change the sync mechanism to make sure it takes all post statuses into account. Props [@jonathanbardo](https://github.com/jonathanbardo)

### Fixed
- Check if failed post exists in indexing. Props [@elliot-stocks](https://github.com/elliott-stocks)
- Properly check if setup is defined in indexing. Props [@elliot-stocks](https://github.com/elliott-stocks)
- Add WP_Query integration on init rather than plugins loaded. Props [@adamsilverstein](https://github.com/adamsilverstein)
- Properly set global post object post type in loop. Props [@tott](https://github.com/tott)
- Do not check if index exists on every page load. Refactor so we can revert to MySQL after failed ES ping.
- Make sure we check `is_multisite()` if `--network-wide` is provided. Props [@ivankruchkoff](https://github.com/ivankruchkoff)
- Abide by the `exclude_from_search` flag from post type when running search queries. Props [@ryanboswell](https://github.com/ryanboswell)
- Correct mapping of `post_status` to `not_analyzed` to allow for filtering of the search query (will require a re-index). Props [@jonathanbardo](https://github.com/jonathanbardo)

## [1.2] - 2014-12-05
### Added
- Allow number of shards and replicas to be configurable.
- Filter and disable query integration on a per query basis.
- Support orderby` parameter in `WP_Query

### Changed
- Improved searching algorithm. Favor exact matches over fuzzy matches.
- Query stack implementation to allow for query nesting.
- Delete action to action_delete_post instead of action_trash_post
- Improve unit testing for query ordering.

### Removed
- _boost from mapping. _boost is deprecated by Elasticsearch.

### Fixed
- We don't want to add the like_text query unless we have a non empty search string. This mimcs the behavior of MySQL or WP which will return everything if s is empty.

## [1.1] - 2014-10-27
### Added
- Add support for post_title and post_name orderby parameters in `WP_Query` integration. Add support for order parameters.

### Changed
- Refactored `is_alive`, `is_activated`, and `is_activated_and_alive`. We now have functions `is_activated`, `elasticsearch_alive`, `index_exists`, and `is_activated`. This refactoring helped us fix #150.

## [1.0] - 2014-10-20
### Added
- Support `search_fields` parameter. Support author, title, excerpt, content, taxonomy, and meta within this parameter.
- Check for valid blog ids in index names
- `sites` WP_Query parameter to allow for search only on specific blogs

### Changed
- Move all management functionality to WP-CLI commands
- Disable sync during import
- Improved bulk error handling
- Improved unit test coverage

### Removed
- Remove ES_Query and support everything through WP_Query
- `ep_last_synced` meta
- Syncing taxonomy

## [0.9.3] - 2014-09-26
### Added
- Better documentation surrounding `WP_Query` parameters (props @tlovett1)
- Option to allow for using `match_all` (props @colegeissinger for suggestion)
- Better tests for some `WP_Query` parameters (props @tlovett1)
- Allow for manual control over search integration
- Support for passing an array of sites to search against (props @tlovett1)
- Filter for controlling whether or not ElasticPress is enabled during a `wp_query` request
- Filter to allow adjusting which fields are searched (`ep_search_fields`)

### Changed
- Prevented filtering `WP_Query` in admin (props @cmmarslender)
- Updated tests to better conform to WordPress repo 5.2 compatibility (props @tlovett1)
- Made running re-indexing commands simpler and easier by adding support for a new `--setup` flag on the `index` command
- Disable search integration during syncing

### Fixed
- Bug that would cause a post to stay in the index when a post was unpublished
- Bug that would cause site to be improperly switched after a `wp_reset_postdata` while not in the loop
- Bug that would cause EP to individually sync each post during an import - disabled syncing during import - requires a full re-index after import

## [0.9.2] - 2014-09-11
### Added
- Wrapper method for wp_get_sites, added filter
- Ability to change scope of search to other sites in network
- tax_query support.

### Changed
- Aggregation filter update

## [0.9.1] - 2014-09-05
### Added
- Action to allow for retrieval of raw response
- Filter to retrieve aggregations
- Pagination tests
- ep_min_similarity and ep_formatted_args filters
- ep_search_fields filter for adding custom search fields
- Filter to allow for specific site selection on multisite indexing

### Changed
- Adjust default fuzziness to .75 instead of .5

### Removed
- Main query check on ep wp query integration

## [0.9] - 2014-09-03
### Added
- Make labels clickable in admin
- Setup plugin textdomain; POT file for translation; localize stray string in cron
- Tests for is_alive function
- search_meta key param support to ES_Query
- Test WP Query integration on multisite setup
- Flush and re-put mapping on admin sync request
- WP Query integration

### Changed
- Simplify sync
- do_scheduled_syncs into do_syncs
- Make config files static

### Removed
- EP hidden taxonomy

### Fixed
- Cron stuff
- Statii
- Type coercion in equality checks

## [0.1.2] - 2014-06-27
### Added
- Support ES_Query parameter that designates post meta entries to be searched
- Escape post ID and site ID in API calls
- Additional tests
- Translation support
- is_alive function for checking health status of Elasticsearch server

### Changed
- Only index public taxonomies
- Renamed `statii` to `status`

### Fixed
- Escaping issues

## 0.1.0 - Unknown
- Initial plugin release

[Unreleased]: https://github.com/10up/ElasticPress/compare/trunk...develop
[5.0.1]: https://github.com/10up/ElasticPress/compare/5.0.0...5.0.1
[5.0.0]: https://github.com/10up/ElasticPress/compare/4.7.2...5.0.0
[4.7.2]: https://github.com/10up/ElasticPress/compare/4.7.1...4.7.2
[4.7.1]: https://github.com/10up/ElasticPress/compare/4.7.0...4.7.1
[4.7.0]: https://github.com/10up/ElasticPress/compare/4.6.1...4.7.0
[4.6.1]: https://github.com/10up/ElasticPress/compare/4.6.0...4.6.1
[4.6.0]: https://github.com/10up/ElasticPress/compare/4.5.2...4.6.0
[4.5.2]: https://github.com/10up/ElasticPress/compare/4.5.1...4.5.2
[4.5.1]: https://github.com/10up/ElasticPress/compare/4.5.0...4.5.1
[4.5.0]: https://github.com/10up/ElasticPress/compare/4.4.1...4.5.0
[4.4.1]: https://github.com/10up/ElasticPress/compare/4.4.0...4.4.1
[4.4.0]: https://github.com/10up/ElasticPress/compare/4.3.1...4.4.0
[4.3.1]: https://github.com/10up/ElasticPress/compare/4.3.0...4.3.1
[4.3.0]: https://github.com/10up/ElasticPress/compare/4.2.2...4.3.0
[4.2.2]: https://github.com/10up/ElasticPress/compare/4.2.1...4.2.2
[4.2.1]: https://github.com/10up/ElasticPress/compare/4.2.0...4.2.1
[4.2.0]: https://github.com/10up/ElasticPress/compare/4.1.0...4.2.0
[4.1.0]: https://github.com/10up/ElasticPress/compare/4.0.1...4.1.0
[4.0.1]: https://github.com/10up/ElasticPress/compare/4.0.0...4.0.1
[4.0.0]: https://github.com/10up/ElasticPress/compare/3.6.6...4.0.0
[3.6.6]: https://github.com/10up/ElasticPress/compare/3.6.5...3.6.6
[4.0.0-beta.1]: https://github.com/10up/ElasticPress/compare/3.6.5...4.0.0-beta.1
[3.6.5]: https://github.com/10up/ElasticPress/compare/3.6.4...3.6.5
[3.6.4]: https://github.com/10up/ElasticPress/compare/3.6.3...3.6.4
[3.6.3]: https://github.com/10up/ElasticPress/compare/3.6.2...3.6.3
[3.6.2]: https://github.com/10up/ElasticPress/compare/3.6.1...3.6.2
[3.6.1]: https://github.com/10up/ElasticPress/compare/3.6.0...3.6.1
[3.6.0]: https://github.com/10up/ElasticPress/compare/3.5.6...3.6.0
[3.5.6]: https://github.com/10up/ElasticPress/compare/3.5.5...3.5.6
[3.5.5]: https://github.com/10up/ElasticPress/compare/3.5.4...3.5.5
[3.5.4]: https://github.com/10up/ElasticPress/compare/3.5.3...3.5.4
[3.5.3]: https://github.com/10up/ElasticPress/compare/3.5.2...3.5.3
[3.5.2]: https://github.com/10up/ElasticPress/compare/3.5.1...3.5.2
[3.5.1]: https://github.com/10up/ElasticPress/compare/3.5...3.5.1
[3.5.0]: https://github.com/10up/ElasticPress/compare/3.4.3...3.5
[3.4.3]: https://github.com/10up/ElasticPress/compare/3.4.2...3.4.3
[3.4.2]: https://github.com/10up/ElasticPress/compare/3.4.1...3.4.2
[3.4.1]: https://github.com/10up/ElasticPress/compare/3.4...3.4.1
[3.4.0]: https://github.com/10up/ElasticPress/compare/3.3...3.4
[3.3.0]: https://github.com/10up/ElasticPress/compare/3.2.6...3.3
[3.2.6]: https://github.com/10up/ElasticPress/compare/3.2.5...3.2.6
[3.2.5]: https://github.com/10up/ElasticPress/compare/3.2.4...3.2.5
[3.2.4]: https://github.com/10up/ElasticPress/compare/3.2.3...3.2.4
[3.2.3]: https://github.com/10up/ElasticPress/compare/3.2.2...3.2.3
[3.2.2]: https://github.com/10up/ElasticPress/compare/3.2.1...3.2.2
[3.2.1]: https://github.com/10up/ElasticPress/compare/3.2...3.2.1
[3.2.0]: https://github.com/10up/ElasticPress/compare/3.1.1...3.2
[3.1.1]: https://github.com/10up/ElasticPress/compare/3.1...3.1.1
[3.1.0]: https://github.com/10up/ElasticPress/compare/3.0.3...3.1
[3.0.3]: https://github.com/10up/ElasticPress/compare/3.0.2...3.0.3
[3.0.2]: https://github.com/10up/ElasticPress/compare/3.0.1...3.0.2
[3.0.1]: https://github.com/10up/ElasticPress/compare/3.0...3.0.1
[3.0]: https://github.com/10up/ElasticPress/compare/2.8.2...3.0
[2.8.2]: https://github.com/10up/ElasticPress/compare/2.8.1...2.8.2
[2.8.1]: https://github.com/10up/ElasticPress/compare/2.8.0...2.8.1
[2.8.0]: https://github.com/10up/ElasticPress/compare/2.7.0...2.8.0
[2.7.0]: https://github.com/10up/ElasticPress/releases/tag/2.7.0
[2.6.1]: https://plugins.trac.wordpress.org/changeset/1929875/elasticpress
[2.6]: https://github.com/10up/ElasticPress/compare/2.5.2...2.6
[2.5.2]: https://github.com/10up/ElasticPress/compare/2.5.1...2.5.2
[2.5.1]: https://github.com/10up/ElasticPress/compare/2.5...2.5.1
[2.5]: https://github.com/10up/ElasticPress/compare/2.4.2...2.5
[2.4.2]: https://github.com/10up/ElasticPress/compare/2.4.1...2.4.2
[2.4.1]: https://github.com/10up/ElasticPress/compare/2.4...2.4.1
[2.4]: https://github.com/10up/ElasticPress/compare/2.3.2...2.4
[2.3.2]: https://github.com/10up/ElasticPress/compare/2.3.1...2.3.2
[2.3.1]: https://github.com/10up/ElasticPress/compare/2.3...2.3.1
[2.3]: https://github.com/10up/ElasticPress/compare/2.2.1...2.3
[2.2.1]: https://github.com/10up/ElasticPress/compare/2.2...2.2.1
[2.2]: https://github.com/10up/ElasticPress/compare/2.1.2...2.2
[2.1.2]: https://github.com/10up/ElasticPress/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/10up/ElasticPress/compare/2.1...2.1.1
[2.1]: https://github.com/10up/ElasticPress/compare/2.0.1...2.1
[2.0.1]: https://github.com/10up/ElasticPress/compare/2.0...2.0.1
[2.0]: https://github.com/10up/ElasticPress/compare/1.9.1...2.0
[1.9.1]: https://github.com/10up/ElasticPress/compare/1.9...1.9.1
[1.9]: https://github.com/10up/ElasticPress/compare/1.8...1.9
[1.8]: https://github.com/10up/ElasticPress/compare/1.7...1.8
[1.7]: https://github.com/10up/ElasticPress/compare/1.6.2...1.7
[1.6.2]: https://github.com/10up/ElasticPress/compare/1.6.1...1.6.2
[1.6.1]: https://github.com/10up/ElasticPress/compare/1.6...1.6.1
[1.6]: https://github.com/10up/ElasticPress/compare/1.5...1.6
[1.5.1]: https://github.com/10up/ElasticPress/compare/1.5...1.5.1
[1.5]: https://github.com/10up/ElasticPress/compare/1.4...1.5
[1.4]: https://github.com/10up/ElasticPress/compare/v1.3.1...1.4
[1.3.1]: https://github.com/10up/ElasticPress/compare/v1.3...v1.3.1
[1.3]: https://github.com/10up/ElasticPress/compare/v1.2...v1.3
[1.2]: https://github.com/10up/ElasticPress/compare/v1.1...v1.2
[1.1]: https://github.com/10up/ElasticPress/compare/v1.0...v1.1
[1.0]: https://github.com/10up/ElasticPress/compare/v0.9.3...v1.0
[0.9.3]: https://github.com/10up/ElasticPress/compare/0.9.2...v0.9.3
[0.9.2]: https://github.com/10up/ElasticPress/compare/0.9.1...0.9.2
[0.9.1]: https://github.com/10up/ElasticPress/compare/0.9...0.9.1
[0.9]: https://github.com/10up/ElasticPress/compare/0.1.2...0.9
[0.1.2]: https://github.com/10up/ElasticPress/releases/tag/0.1.2
