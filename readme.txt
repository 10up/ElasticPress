=== ElasticPress ===
Contributors: 10up, tlovett1, vhauri, tott, oscarssanchez, cmmarslender
Tags:         performance, slow, search, elasticsearch, fuzzy, facet, aggregation, searching, autosuggest, suggest, elastic, advanced search, woocommerce, related posts, woocommerce
Tested up to: 6.3
Stable tag:   5.0.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html

A fast and flexible search and query engine for WordPress.

== Description ==
ElasticPress, a fast and flexible search and query engine for WordPress, enables WordPress to find or “query” relevant content extremely fast through a variety of highly customizable features. WordPress out-of-the-box struggles to analyze content relevancy and can be very slow. ElasticPress supercharges your WordPress website making for happier users and administrators. The plugin even contains features for popular plugins.

Here is a list of the amazing ElasticPress features included in the plugin:

__Search__: Instantly find the content you’re looking for. The first time.

__Instant Results__: A built for WordPress search experience that bypasses WordPress for optimal performance. Instant Results routes search queries through a dedicated API, separate from WordPress, returning results up to 10x faster than previous versions of ElasticPress.

__WooCommerce__: With ElasticPress, filtering WooCommerce product results is fast and easy. Your customers can find and buy exactly what they're looking for, even if you have a large or complex product catalog.

__Related Posts__: ElasticPress understands data in real time, so it can instantly deliver engaging and precise related content with no impact on site performance.

__Protected Content__: Optionally index all of your content, including private and unpublished content, to speed up searches and queries in places like the administrative dashboard.

__Documents__: Indexes text inside of popular file types, and adds those files types to search results.

__Autosuggest__: Suggest relevant content as text is entered into the search field.

__Filters__: Add controls to your website to filter content by one or more taxonomies.

__Comments__: Indexes your comments and provides a widget with type-ahead search functionality. It works with WooCommerce product reviews out-of-the-box.

== Frequently Asked Questions ==

= How does ElasticPress work? =

The ElasticPress plugin enables you to connect your WordPress site to the ElasticPress.io service, a SaaS solution that provides an enhanced search experience while reducing load on your WordPress site. For advanced users familiar with both WordPress and Elasticsearch hosting and management, ElasticPress also offers support for plugin functionality using an Elasticsearch instance. Please keep in mind that there are multiple security, performance, and configuration considerations to take into account if you take this approach.

= I have to use an in-house or custom Elasticsearch solution due to policy or institutional requirements. Can you still help? =

If circumstances prevent the use of a SaaS solution like ElasticPress.io, we can also provide [consulting](https://www.elasticpress.io/elasticpress-consulting/) around installation and configuration of custom Elasticsearch instances.

= Where can I find ElasticPress documentation and user guides? =

Please refer to [GitHub](https://github.com/10up/ElasticPress) for detailed usage instructions and documentation. FAQs and tutorials can be also found on our [support site](https://elasticpress.zendesk.com/hc/en-us).

= I have a problem with the plugin. Where can I get help? =

If you have identified a bug or would like to suggest an enhancement, please refer to our [GitHub repo](https://github.com/10up/ElasticPress). We do not provide support here at WordPress.org forums.

If you are an ElasticPress.io customer, please open a ticket in your account dashboard. If you need a custom solution, we also offer [consulting](https://www.elasticpress.io/elasticpress-consulting/).

= Where do I report security bugs? =

You can report any security bugs found in the source code of ElasticPress through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/elasticpress). The Patchstack team will assist you with verification, CVE assignment and take care of notifying the developers of this plugin.

= Is ElasticPress compatible with OpenSearch or Elasticsearch X.Y? =

ElasticPress requirements can be found in the [Requirements section](https://github.com/10up/ElasticPress#requirements) of our GitHub repository. If your solution relies on a different server or version, you may find additional information on our [Compatibility documentation page](https://10up.github.io/ElasticPress/tutorial-compatibility.html).

= I really like ElasticPress! Can I contribute? =

For sure! Feel free to submit ideas or feedback in general to our [GitHub repo](https://github.com/10up/ElasticPress). If you can, also consider sending us [a review](https://wordpress.org/support/plugin/elasticpress/reviews/#new-post).

== Installation ==
1. First, you will need to properly [install and configure](https://www.elastic.co/guide/en/elasticsearch/reference/current/setup.html) Elasticsearch.
2. Activate the plugin in WordPress.
3. In the ElasticPress settings page, input your Elasticsearch host.
4. Sync your content by clicking the sync icon.
5. Enjoy!

== Screenshots ==
1. Features Page
2. Search Fields & Weighting Dashboard
3. Sync Page
4. Synonyms Dashboard
5. Instant Results modal

== Changelog ==

= 5.0.0 - 2023-11-01 =

**ElasticPress 5.0.0 contains some important changes. Make sure to read these highlights before upgrading:**

* This version does not require a full reindex but it is recommended, especially for websites using synonyms containing spaces.
* Meta keys are not indexed by default anymore. The new Weighting Dashboard allows admin users to mark meta fields as indexables. The new `ep_prepare_meta_allowed_keys` filter allows to add meta keys programmatically.
* Features now have their fields declared in JSON. Custom features may need to implement the `set_settings_schema()` method to work.
* The `Users` feature was moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin and is no longer available in this plugin. If you use this feature, make sure to install and configure EP Labs before upgrading.
* The `Terms` and `Comments` features are now hidden by default for sites that do not have them active yet. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.
* New minimum versions are:
	* Elasticsearch: 5.2
	* WordPress: 6.0
	* PHP: 7.4

__Added__:

* New Sync page. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@apurvrdx1](https://github.com/apurvrdx1), [@brandwaffle](https://github.com/brandwaffle), [@anjulahettige](https://github.com/anjulahettige), [@burhandodhy](https://github.com/burhandodhy), and [@MARQAS](https://github.com/MARQAS).
* New feature settings screen. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@apurvrdx1](https://github.com/apurvrdx1), [@brandwaffle](https://github.com/brandwaffle), and [@anjulahettige](https://github.com/anjulahettige).
* New weighting dashboard with support for making meta fields searchable. Props [@JakePT](https://github.com/JakePT), [@mehidi258](https://github.com/mehidi258), and [@felipeelia](https://github.com/felipeelia).
* New Date Filter Block. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia).
* Sync history to the Sync page. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@apurvrdx1](https://github.com/apurvrdx1), [@brandwaffle](https://github.com/brandwaffle), and [@anjulahettige](https://github.com/anjulahettige).
* Final status of syncs (success, with errors, failed, or aborted.) Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* REST API endpoint at `elasticpress/v1/features` for updating feature settings. Props [@JakePT](https://github.com/JakePT).
* New `ElasticsearchErrorInterpreter` class. Props [@felipeelia](https://github.com/felipeelia).
* New `default_search` analyzer to differentiate what is applied during sync and search time. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS).
* The sync page now describes what triggered the current sync, and previous syncs. Props [@JakePT](https://github.com/JakePT).
* Weighting and Synonyms Dashboards to multisites. Props [@felipeelia](https://github.com/felipeelia).
* No-cache headers to sync calls. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Abstracted Sync page logic into a provider pattern. Props [@JakePT](https://github.com/JakePT).
* Moved syncing from an `admin-ajax.php` callback to a custom REST API endpoint with support for additional arguments. Props [@JakePT](https://github.com/JakePT).
* Store previous syncs info, changed option name from `ep_last_index` to `ep_sync_history`. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* Features settings declared as JSON. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* Tweaked layout and notifications style on the Status Report screen for consistency with the updated Sync page. Props [@JakePT](https://github.com/JakePT).
* Moved REST API endpoint definitions to controller classes. Props [@JakePT](https://github.com/JakePT).
* SyncManager array queues are now indexed by the blog ID. Props [@sathyapulse](https://github.com/sathyapulse) and [@felipeelia](https://github.com/felipeelia).
* Comments and Terms are now hidden by default. Props [@felipeelia](https://github.com/felipeelia).
* WooCommerce-related hooks are now removed when switching to a site that does not have WC active. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS).
* Run e2e tests against the minimum supported WordPress version. Props [@felipeelia](https://github.com/felipeelia).
* Several tweaks in the Features settings API. Props [@JakePT](https://github.com/JakePT) via [#3708](https://github.com/10up/ElasticPress/pull/3708).
* EP Settings are now reverted if it is not possible to connect to the new ES Server. Props [@felipeelia](https://github.com/felipeelia), [@burhandodhy](https://github.com/burhandodhy), and [@scottbuscemi](https://github.com/scottbuscemi).
* Node packages updated. Props [@felipeelia](https://github.com/felipeelia).
* Updated the labels of feature settings and options for consistency and clarity. Props [@JakePT](https://github.com/JakePT).
* Depending on the requirements, some feature settings are now saved to be applied after a full sync. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* Minimum requirements. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).
* Old features will have their settings displayed based on their default setting values. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* Radio and checkbox settings were changed from booleans to strings. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* The troubleshooting article link was updated. Props [@felipeelia](https://github.com/felipeelia) and [@anjulahettige](https://github.com/anjulahettige).

__Deprecated:__

* The `IndexHelper::get_last_index` method was replaced by `IndexHelper::get_last_sync`.
* The `FailedQueries::maybe_suggest_solution_for_es` method was replaced by `ElasticsearchErrorInterpreter::maybe_suggest_solution_for_es`.
* `Weighting::render_settings_section`, `Weighting::handle_save`, `Weighting::redirect`, and `Weighting::save_weighting_configuration` were deprecated in favor of React components.

__Removed:__

* Users-related files from the main plugin. Props [@felipeelia](https://github.com/felipeelia).
* Removed mapping files related to older versions of Elasticsearch. Props [@MARQAS](https://github.com/MARQAS).

__Fixed:__

* Docblock for the `ep_facet_renderer_class` filter. Props [@misfist](https://github.com/misfist).
* Instant Results console warning. Props [@burhandodhy](https://github.com/burhandodhy).
* Total fields limit message interpretation. Props [@felipeelia](https://github.com/felipeelia) [@JakePT](https://github.com/JakePT).
* End to end tests intermittent failures. Props [@felipeelia](https://github.com/felipeelia).
* React warning on Sync page. Props [@burhandodhy](https://github.com/burhandodhy).
* Content was not showing properly on the tooltop on install page. Props [@burhandodhy](https://github.com/burhandodhy).
* Redirect to correct sync url after enabling feature that requires a new sync. Props [@burhandodhy](https://github.com/burhandodhy).
* Post type setting wasn't respected during sync. Props [@burhandodhy](https://github.com/burhandodhy).
* Fix a JS error appearing when sync requests are intentionally stopped. Props [@burhandodhy](https://github.com/burhandodhy).
* Features description copy. Props [@felipeelia](https://github.com/felipeelia), [@burhandodhy](https://github.com/burhandodhy), and [@MARQAS](https://github.com/MARQAS).
* Endpoint URL field is not a URL type field. Props [@burhandodhy](https://github.com/burhandodhy).
* WooCommerce feature not autoactivating. Props [@felipeelia](https://github.com/felipeelia).
* Elasticsearch errors interpretation. Props [@felipeelia](https://github.com/felipeelia).
* Deactivating a feature via WP-CLI also takes into account draft states. Props [@felipeelia](https://github.com/felipeelia).

= 4.7.2 - 2023-10-10 =

__Added:__

* New `ep_highlight_number_of_fragments` filter. Props [@dgnorrod](https://github.com/dgnorrod) and [@felipeelia](https://github.com/felipeelia).
* >=PHP 7.0 version check. Props [@bmarshall511](https://github.com/bmarshall511) and [@felipeelia](https://github.com/felipeelia).
* GitHub action to automatically open a new issue when a new version of WordPress is released. Props [@felipeelia](https://github.com/felipeelia).

__Removed:__

* Unnecessary aliases in use statements. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* Calls to `ep_woocommerce_default_supported_post_types` were ignored. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS).
* WooCommerce Orders search field disappearing when Orders Autosuggest receives an unexpected response from ElasticPress.io. Props [@JakePT](https://github.com/JakePT) and [@anjulahettige](https://github.com/anjulahettige).
* Call composer while building docs. Props [@felipeelia](https://github.com/felipeelia).
* Make sure `post__not_in` and `post_status` are translated into arrays, not objects. Props [@felipeelia](https://github.com/felipeelia).
* Updated phpDoc entries. Props [@renatonascalves](https://github.com/renatonascalves).
* Docblock for `Utils\get_option` return type. Props [@felipeelia](https://github.com/felipeelia).
* Docblock for `ep_capability` and `ep_network_capability` filters. Props [@burhandodhy](https://github.com/burhandodhy).
* PHP warning related to the Autosuggest template generation. Props [@felipeelia](https://github.com/felipeelia).
* WooCommerce unit tests running multiple times. Props [@felipeelia](https://github.com/felipeelia).
* Display the meta range facet block in versions prior to WP 6.1. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS).
* Number of expected arguments for `add_attachment` and `edit_attachment`. Props [@burhandodhy](https://github.com/burhandodhy).
* Error while running `composer install` on PHP 8. Props [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `composer/composer` from 2.5.8 to 2.6.4. Props [@dependabot](https://github.com/dependabot).


= 4.7.1 - 2023-08-31 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

__Added:__

* Synonyms and weighting settings added to the status report. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Composer packages are namespaced by Strauss. Props [@felipeelia](https://github.com/felipeelia) and [@junaidbhura](https://github.com/junaidbhura).
* E2e tests now log the formatted query info from Debug Bar ElasticPress. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* WooCommerce products sorted by popularity are now always sorted in a descending order. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* E2e tests with WordPress 6.3. Props [@felipeelia](https://github.com/felipeelia).

= 4.7.0 - 2023-08-10 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

__Added:__

* Exclude Media Attachments from search results. Props [@burhandodhy](https://github.com/burhandodhy).
* New `Default to Site Language` option in the language dropdown in ElasticPress' settings page. Props [@felipeelia](https://github.com/felipeelia).
* Compatibility with block themes for the Facet meta blocks. Props [@felipeelia](https://github.com/felipeelia).
* Integrate Did You Mean feature in the Instant Results. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* All blocks now support styling features in themes that support them. Props [@JakePT](https://github.com/JakePT).
* Descriptions and keywords have been added to all blocks. Props [@JakePT](https://github.com/JakePT).
* New `ep_stop` filter, that changes the stop words used according to the language set. Props [@felipeelia](https://github.com/felipeelia).
* New `get-index-settings` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia).
* New `ep_facet_tax_special_slug_taxonomies` filter. Props [@oscarssanchez](https://github.com/oscarssanchez).
* New `--stop-on-error` flag to the `sync` command. Props [@oscarssanchez](https://github.com/oscarssanchez).
* New `get` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia).
* Transient utility functions. Props [@felipeelia](https://github.com/felipeelia).
* Indices' language settings in status reports. Props [@felipeelia](https://github.com/felipeelia).
* Initial changes to implement a DI Container. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott).
* New `$only_indexable` parameter to the `Utils\get_sites()` function. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* WooCommerce feature only integrates with queries that are the main query, a search, or have ep_integrate set as true. Props [@felipeelia](https://github.com/felipeelia).
* Miscellaneous changes to all blocks, including their category, names, and code structure. Props [@JakePT](https://github.com/JakePT), [@oscarssanchez](https://github.com/oscarssanchez), and [@felipeelia](https://github.com/felipeelia).
* The Facets feature was renamed to Filters. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* The WooCommerce feature was refactored, separating code related to products and orders. Props [@felipeelia](https://github.com/felipeelia).
* Transients deletion during uninstall. Props [@felipeelia](https://github.com/felipeelia).
* Bump Elasticsearch version to 7.10.2 for E2E tests. Props [@burhandodhy](https://github.com/burhandodhy).
* Refactor `get_settings()` usage inside ElasticPress features. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* In a multisite, if a site is indexable or not is now stored in site meta, instead of a blog option. Props [@felipeelia](https://github.com/felipeelia).
* Autosuggest authenticated requests are not cached anymore and are only sent during the sync process or when the weighting dashboard is saved. Props [@felipeelia](https://github.com/felipeelia) and [@kovshenin](https://github.com/kovshenin).
* Use `createRoot` instead of `render` to render elements. Props [@oscarssanchez](https://github.com/oscarssanchez), [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia).
* Moved methods to abstract Facet classes. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* Only display available languages in the Settings screen. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* WooCommerce feature description. Props [@brandwaffle](https://github.com/brandwaffle), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT).

__Deprecated:__

* `Autosuggest::delete_cached_query()` was deprecated without a replacement. See [#3566](https://github.com/10up/ElasticPress/pull/3566).
* `EP_Uninstaller::delete_related_posts_transients()` and `EP_Uninstaller::delete_total_fields_limit_transients()` was merged into `EP_Uninstaller::delete_transients_by_name`. See [#3548](https://github.com/10up/ElasticPress/pull/3548).
* The `ep_woocommerce_default_supported_post_types` filter was split into `ep_woocommerce_orders_supported_post_types` and `ep_woocommerce_products_supported_post_types`. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* The `ep_woocommerce_supported_taxonomies` filter is now `ep_woocommerce_products_supported_taxonomies`. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* All old `WooCommerce\Orders` methods were migrated to the new `WooCommerce\OrdersAutosuggest` class. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* The `Elasticsearch::clear_total_fields_limit_cache()` method was replaced by `Elasticsearch::clear_index_settings_cache()`. See [#3552](https://github.com/10up/ElasticPress/pull/3552).
* Several methods that were previously part of the `WooCommerce\WooCommerce` class were moved to the new `WooCommerce\Product` class. See [#3502](https://github.com/10up/ElasticPress/pull/3502).
* Several methods that were specific to Facet types were moved to the new `Block` and `Renderer` abstract classes. See [#3499](https://github.com/10up/ElasticPress/pull/3499).

__Fixed:__

* Same error message being displayed more than once on the Dashboard sync. Props [@felipeelia](https://github.com/felipeelia), [@MARQAS](https://github.com/MARQAS), [@tott](https://github.com/tott), and [@wildberrylillet](https://github.com/wildberrylillet).
* Sync media item when attaching or detaching media. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* Display "Loading results" instead of "0 results" on first search using Instant Results. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@JakePT](https://github.com/JakePT).
* Highlighting returning inaccurate post title when partial/no term match on Instant Results. Props [@oscarssanchez](https://github.com/oscarssanchez), [@JakePT](https://github.com/JakePT), and [@tomi10up](https://github.com/tomi10up).
* Warning in Orders Autosuggest: `"Creation of dynamic property $search_template is deprecated"`. Props [@burhandodhy](https://github.com/burhandodhy).
* Warning while using PHP 8.1+: `Deprecated: version_compare(): Passing null to parameter #1 ($version1) of type string is deprecated`. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Warning in the metadata range facet renderer: `Undefined array key "is_preview"`. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `word-wrap` from 1.2.3 to 1.2.4. Props [@dependabot](https://github.com/dependabot).
* Bumped `tough-cookie` from 4.1.2 to 4.1.3 and `@cypress/request` from 2.88.10 to 2.88.12. Props [@dependabot](https://github.com/dependabot).

= 4.6.1 - 2023-07-05 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

__Added:__

* Add doc url for "Did You Mean" feature. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Use `wp_cache_supports` over `wp_cache_supports_group_flush`. Props [@spacedmonkey](https://github.com/spacedmonkey).
* Update the `ep_exclude_from_search` post meta only if it is set or has some value. Props [@MARQAS](https://github.com/MARQAS) and [@columbian-chris](https://github.com/columbian-chris).

__Fixed:__

* Deprecation notice in `ElasticPress\Feature\WooCommerce\Orders`. Props [@mwidmann](https://github.com/mwidmann).
* Don't apply a facet filter to the query if the filter value is empty. Props [@burhandodhy](https://github.com/burhandodhy).
* Syncing a post with empty post meta key. Props [@MARQAS](https://github.com/MARQAS) and [@oscarssanchez](https://github.com/oscarssanchez).
* Order by clauses with Elasticsearch field formats are not changed anymore. Props [@felipeelia](https://github.com/felipeelia) and [@tlovett1](https://github.com/tlovett1).
* Failed Query logs are automatically cleared on refreshing the "Status Report" page. Props [@burhandodhy](https://github.com/burhandodhy).
* Warning message on Health page when Last Sync information is not available. Props [@burhandodhy](https://github.com/burhandodhy).
* Deprecation notice: json_encode(): Passing null to parameter #2. Props [@burhandodhy](https://github.com/burhandodhy).
* Documentation of the `ep_facet_search_get_terms_args` filter. Props [@burhandodhy](https://github.com/burhandodhy).

= 4.6.0 - 2023-06-13 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

__Added:__

* 'Did you mean' feature. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), and [@tott](https://github.com/tott).
* Facet by Post type. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@burhandodhy](https://github.com/burhandodhy).
* Two new options to disable weighting results by date in WooCommerce products related queries. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* Sort meta queries by named clauses and sort by different meta types. Props [@felipeelia](https://github.com/felipeelia) and [@selim13](https://github.com/selim13).
* New `--force` flag in the sync WP-CLI command, to stop any other ongoing syncs. Props [@felipeelia](https://github.com/felipeelia) and [@tomjn](https://github.com/tomjn).
* Installers added to composer.json, so `installer-paths` works without any additional requirement. Props [@felipeelia](https://github.com/felipeelia) and [@tomjn](https://github.com/tomjn).
* Links to Patchstack Vulnerability Disclosure Program. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).
* E2E tests for Password Protected Post. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).

__Changed:__

* If no index is found, the "failed queries" message will be replaced with a prompt to sync. Props [@felipeelia](https://github.com/felipeelia).
* Bumped Cypress version to v12. Props [@felipeelia](https://github.com/felipeelia).
* Documentation partially moved to Zendesk. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).

__Fixed:__

* Fatal error related to the `maybe_process_error_limit` function. Props [@burhandodhy](https://github.com/burhandodhy).
* Display the error message returned by Elasticsearch if a mapping operation fails. Props [@felipeelia](https://github.com/felipeelia) via [#3464](https://github.com/10up/ElasticPress/pull/3464).
* Negative `menu_order` values being transformed into positive numbers. Props [@felipeelia](https://github.com/felipeelia) and [@navidabdi](https://github.com/navidabdi).
* Password protected content being indexed upon save when Protected Content is not active. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Error message when the Elasticsearch server is not available during the put mapping operation. Props [@felipeelia](https://github.com/felipeelia).

= 4.5.2 - 2023-04-19 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

__Added:__

* New `ep_enable_query_integration_during_indexing` filter. Props [@rebeccahum](https://github.com/rebeccahum).

__Changed:__

* Automated message sent in GitHub issues after 3 days of inactivity. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).

__Fixed:__

* Authenticated requests for autosuggest were not being properly cached while using external object cache. Props [@felipeelia](https://github.com/felipeelia).

= 4.5.1 - 2023-04-11 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

__Added:__

* New `ep_instant_results_args_schema` filter for filtering Instant Results arguments schema. Props [@JakePT](https://github.com/JakePT).
* New `ep.Autosuggest.navigateCallback` JS filter for changing the behavior of a clicked element on Autosuggest. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT).
* New `ep.Autosuggest.fetchOptions` JS filter for filtering Elasticsearch fetch configuration of Autosuggest. Props [@tlovett1](https://github.com/,tlovett1), [@MARQAS](https://github.com/MARQAS), and [@felipeelia](https://github.com/felipeelia).
* Code linting before pushing to the repository. Props [@felipeelia](https://github.com/felipeelia).
* Unit tests for the Status Reports feature. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Meta field facets now only filter based on fields selected on blocks. The new `ep_facet_should_check_if_allowed` filter reverts this behavior. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).

__Fixed:__

* Instant Results crashing when using taxonomies as facets that are attached to both searchable and non-searchable post types. Props [@JakePT](https://github.com/JakePT).
* Fatal error during plugin uninstall. Props [@felipeelia](https://github.com/felipeelia).
* Compatibility issue which prevented Instant Results from working in WordPress 6.2. Props [@JakePT](https://github.com/JakePT).
* Fatal error while syncing on older versions of WordPress. Props [@felipeelia](https://github.com/felipeelia), [@TorlockC](https://github.com/TorlockC).
* Facets removing taxonomy parameters in the URL when not using pretty permalinks. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* JS errors when creating Facet blocks in WP 6.2. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* `ep_index_meta` option blowing up on an indexing process with many errors. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* Typo in `index_output` WP-CLI command help text. Props [@bratvanov](https://github.com/bratvanov).
* React warning messages for the comments block. Props [@burhandodhy](https://github.com/burhandodhy).
* Fixed `action_edited_term` to call `kill_sync` in SyncManager for post Indexable. Props [@rebeccahum](https://github.com/rebeccahum).
* Undefined array key `'index'` during sync. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Meta Range Facet Block e2e tests. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* Users e2e tests using WP 6.2. Props [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumped `webpack` from 5.75.0 to 5.76.3. Props [@dependabot](https://github.com/dependabot).

= 4.5.0 - 2023-03-09 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code. Check [our blog post](https://www.elasticpress.io/blog/2023/03/enabling-comments-and-terms-in-elasticpress-5-0) for more info.**

ElasticPress 4.5.0 release highlights:

* Autosuggest for WooCommerce Orders ([#3175](https://github.com/10up/ElasticPress/pull/3175), [#3308](https://github.com/10up/ElasticPress/pull/3308), [#3321](https://github.com/10up/ElasticPress/pull/3321), [#3324](https://github.com/10up/ElasticPress/pull/3324), [#3323](https://github.com/10up/ElasticPress/pull/3323), [#3310](https://github.com/10up/ElasticPress/pull/3310), [#3349](https://github.com/10up/ElasticPress/pull/3349), [#3339](https://github.com/10up/ElasticPress/pull/3339), and [#3363](https://github.com/10up/ElasticPress/pull/3363))
* New Facet by Meta Range block ([#3289](https://github.com/10up/ElasticPress/pull/3289), [#3342](https://github.com/10up/ElasticPress/pull/3342), [#3337](https://github.com/10up/ElasticPress/pull/3337), [#3361](https://github.com/10up/ElasticPress/pull/3361), [#3364](https://github.com/10up/ElasticPress/pull/3364), [#3368](https://github.com/10up/ElasticPress/pull/3368), and [#3365](https://github.com/10up/ElasticPress/pull/3365))
* ElasticPress.io messages system ([#3162](https://github.com/10up/ElasticPress/pull/3162) and [#3376](https://github.com/10up/ElasticPress/pull/3376))
* Indices of disabled features will be deleted during a full sync ([#3261](https://github.com/10up/ElasticPress/pull/3261))
* WooCommerce Queries ([#3259](https://github.com/10up/ElasticPress/pull/3259) and [#3362](https://github.com/10up/ElasticPress/pull/3362))

__Added:__

* Autosuggest for WooCommerce Orders. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* New Facet by Meta Range block (currently in Beta.) Props [@felipeelia](https://github.com/felipeelia).
* Option to display term counts in Facets blocks. Props [@felipeelia](https://github.com/felipeelia).
* New capability for managing ElasticPress. Props [@tlovett1](https://github.com/tlovett1), [@tott](https://github.com/tott), and [@felipeelia](https://github.com/felipeelia).
* New "Download report" button in the Status Report page. Props [@felipeelia](https://github.com/felipeelia).
* ElasticPress.io messages system. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* WP CLI commands `get-search-template`, `put-search-template`, and `delete-search-template`. Props [@oscarssanchez](https://github.com/oscarssanchez).
* New `--status` parameter to the `get-indices` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia).
* New `ep_instant_results_per_page` filter for changing the number of results per page in Instant Results. Props [@JakePT](https://github.com/JakePT).
* Support for `post_parent__in` and `post_parent__not_in`. Props [@MARQAS](https://github.com/MARQAS).
* New `ep_sync_args` filter. Props [@felipeelia](https://github.com/felipeelia) and [@nickchomey](https://github.com/nickchomey).
* "Full Sync" (Yes/No) to the Last Sync section in Status Report. Props [@felipeelia](https://github.com/felipeelia).
* New `ep_user_register_feature` and `ep_feature_is_visible` filters. Props [@felipeelia](https://github.com/felipeelia).
* Requests now have a new header called `X-ElasticPress-Request-ID` to help with debugging. Props [@felipeelia](https://github.com/felipeelia).
* Compatibility with `'orderby' => 'none'` in WP_Query. Props [@felipeelia](https://github.com/felipeelia).
* Unit tests related to the `ep_weighting_configuration_for_search` filter. Props [@felipeelia](https://github.com/felipeelia).
* New Unit tests for the WooCoomerce feature. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Description for the `--network-wide` flag in WP-CLI commands. Props [@MARQAS](https://github.com/MARQAS).
* New `is_available()` helper method in the Feature class. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Indices of disabled features will be deleted during a full sync. Mappings of needed but non-existent indices will be added even during a regular sync. Props [@felipeelia](https://github.com/felipeelia).
* Reduced number of WooCommerce product queries automatically integrated with ElasticPress. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* The number of results per page in Instant Results now matches the site's posts per page setting. Props [@JakePT](https://github.com/JakePT).
* Under the hood improvements to the structure of Instant Results. Props [@JakePT](https://github.com/JakePT).
* Apply the "Exclude from Search" filter directly on ES Query. Props [@burhandodhy](https://github.com/burhandodhy).
* Avoid using Elasticsearch if query has an unsupported orderby clause. Props [@burhandodhy](https://github.com/burhandodhy).
* E2e tests split into 2 groups to be executed in parallel. Props [@iamchughmayank](https://github.com/iamchughmayank), [@burhandodhy](https://github.com/burhandodhy), and [@felipeelia](https://github.com/felipeelia).
* Filter command flags using `get_flag_value()`. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* Code Standards are now applied to the test suite as well. Props [@felipeelia](https://github.com/felipeelia).
* Text displayed when a feature that requires a sync is about to be enabled. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).

__Removed:__

* Remove legacy filters `woocommerce_layered_nav_query_post_ids`, `woocommerce_unfiltered_product_ids`, and `ep_wp_query_search_cached_posts`. Props [@burhandodhy](https://github.com/burhandodhy).

__Fixed:__

* API requests for Instant Results sent on page load before the modal has been opened. Props [@JakePT](https://github.com/JakePT).
* Prevent search queries for coupons from using Elasticsearch. Props [@burhandodhy](https://github.com/burhandodhy).
* Thumbnails are not removed from indexed WooCommerce Products when the attachments are deleted. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* Auto sync posts associated with a child term when the term parent is changed. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* Status Report page firing requests to ES twice. Props [@felipeelia](https://github.com/felipeelia).
* Sanitization of Meta Queries. Props [@MARQAS](https://github.com/MARQAS).
* Facets styles not enqueued more than once. Props [@felipeelia](https://github.com/felipeelia) and [@MediaMaquina](https://github.com/MediaMaquina).
* Duplicate terms listed in Instant Results facets. Props [@felipeelia](https://github.com/felipeelia).
* Not setting the post context when indexing a post. Props [@tomjn](https://github.com/tomjn).
* Some utilitary methods in the Command class treated as WP-CLI Commands. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Make the "Failed Queries" notice dismissible. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* Undefined index `'elasticpress'` in the Status Report page. Props [@MARQAS](https://github.com/MARQAS).
* Undefined array key `'displayCount'` error for facet. Props [@burhandodhy](https://github.com/burhandodhy).
* Warnings on the feature setup page. Props [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `http-cache-semantics` from 4.1.0 to 4.1.1. Props [@dependabot](https://github.com/dependabot).
* Bumped `got` from 9.6.0 to 11.8.5 and `simple-bin-help` from 1.7.7 to 1.8.0. Props [@dependabot](https://github.com/dependabot).
* Bumped `simple-git` from 3.15.1 to 3.16.0. Props [@dependabot](https://github.com/dependabot).
* Bumped `json5` from 1.0.1 to 1.0.2. Props [@dependabot](https://github.com/dependabot).

= 4.4.1 - 2023-01-10 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code.**

This is a bug fix release.

__Added:__

* Node 18 support. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Unit tests for WP-CLI Commands. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Unit tests for the `HealthCheckElasticsearch` class, Protected Feature, and #3106. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Detection of indexable meta fields when visiting the sync and status report pages. Props [@felipeelia](https://github.com/felipeelia), [@paoloburzacca](https://github.com/paoloburzacca), and [@burhandodhy](https://github.com/burhandodhy).
* `put-mapping` WP-CLI command returns an error message if mapping failed. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia).
* Last Sync subsection title in the Status Report page. Props [@MARQAS](https://github.com/MARQAS), [@felipeelia](https://github.com/felipeelia), and [@tomioflagos](https://github.com/tomioflagos).
* Title for Autosuggest and Instant results features, if connected to an ElasticPress.io account. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), and [@NV607FOX](https://github.com/NV607FOX).
* "Exclude from search" checkbox text. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), and [@anjulahettige](https://github.com/anjulahettige).
* Visibility of the `analyze_log` method of the `FailedQueries` class. Props [@MARQAS](https://github.com/MARQAS).
* Text of the notice under the Documents feature. Props [@MARQAS](https://github.com/MARQAS) and [@NV607FOX](https://github.com/NV607FOX).
* Usage of `get_index_default_per_page` instead of a direct call to `Utils\get_option`. Props [@burhandodhy](https://github.com/burhandodhy).

__Removed:__

* Unnecessary `remove_filters` from the unit tests. Props [@burhandodhy](https://github.com/burhandodhy).

__Fixed:__

* Sync is stopped if put mapping throws an error. Props [@burhandodhy](https://github.com/burhandodhy), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia).
* Layout issue in Instant Results that would occur with small result sets. Props [@JakePT](https://github.com/JakePT).
* Issue where keyboard focus on a facet option was lost upon selection. Props [@JakePT](https://github.com/JakePT).
* JS error on Status Report page. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Hooks documentation reference. Props [@burhandodhy](https://github.com/burhandodhy).
* `'current'` as value for the `'sites'` parameter. Props [@burhandodhy](https://github.com/burhandodhy), [@oscarssanchez](https://github.com/oscarssanchez), and [@anders-naslund](https://github.com/anders-naslund).
* `Uncaught ArgumentCountError: Too few arguments to function WP_CLI::halt()` message. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* Queries with `post_parent` set to `0` not working correctly. Props [@JiveDig](https://github.com/JiveDig).
* Sync command exits without any error message if mapping fails. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Evaluate the WP-CLI `--pretty` flag as real boolean. Props [@oscarssanchez](https://github.com/oscarssanchez).
* Remove deprecated command from the error message. Props [@burhandodhy](https://github.com/burhandodhy).
* CLI command `delete-index --network-wide` throws error when EP is not network activated. Props [@burhandodhy](https://github.com/burhandodhy).
* E2E tests for PHP 8. Props [@burhandodhy](https://github.com/burhandodhy).
* Feature title issue on the report page and notices. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* Autosuggest Site Health Info containing incorrect information unrelated to Autosuggest. Props [@JakePT](https://github.com/JakePT).
* Styling of the Instant Results Facets field. Props [@JakePT](https://github.com/JakePT).

__Security:__

* Bumped `simple-git` from 3.6.0 to 3.15.1. Props [@dependabot](https://github.com/dependabot).

= 4.4.0 - 2022-11-29 =

**Note that starting from the ElasticPress 5.0.0 release the `Users` feature will be moved to the [ElasticPress Labs](https://github.com/10up/ElasticPressLabs) plugin. The `Terms` and `Comments` features will remain in ElasticPress but will be available only if enabled via code.**

ElasticPress 4.4.0 release highlights:

* New Status Report page and failed queries logs ([#3130](https://github.com/10up/ElasticPress/pull/3130), [#3148](https://github.com/10up/ElasticPress/pull/3148), and [#3136](https://github.com/10up/ElasticPress/pull/3136))
* Instant Results template customization ([#2959](https://github.com/10up/ElasticPress/pull/2959))
* Facets by Meta available by default. Users should delete the 1-file plugin released with 4.3.0 ([#3071](https://github.com/10up/ElasticPress/pull/3071))
* New option to exclude posts from search ([#3100](https://github.com/10up/ElasticPress/pull/3100))
* Renamed some WP-CLI commands and added deprecation notices for the old versions (see table below)

__Added:__

* New Status Report page. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), [@tott](https://github.com/tott), and [@brandwaffle](https://github.com/brandwaffle).
* New Query Logger to display admin notices about failed queries and the list in the new Status Report page. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@brandwaffle](https://github.com/brandwaffle).
* New option to exclude posts from search. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT).
* Search Comments block. Replaces the Comments widget in the block editor. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* [Instant Results] Notice when ElasticPress is network activated warning that Instant Results will not work on all sites without additional steps. Props [@JakePT](https://github.com/JakePT).
* Extra debugging information in the browser console when syncing fails and more useful error messages with a troubleshooting URL. Props [@JakePT](https://github.com/JakePT).
* New `elasticpress.InstantResults.Result` JavaScript filter for filtering the component used for Instant Results search results. Props [@JakePT](https://github.com/JakePT).
* New `window.epInstantResults.openModal()` method for developers to manually open Instant Results. Props [@JakePT](https://github.com/JakePT).
* Support for `stock_status` filter on the WooCommerce Admin Product List. Props [@felipeelia](https://github.com/felipeelia) and [@jakgsl](https://github.com/jakgsl).
* Option to toggle the term count in Instant results. Props [@MARQAS](https://github.com/MARQAS) and [@JakePT](https://github.com/JakePT).
* New `ep_autosuggest_query_args` filter, to change WP Query args of the autosuggest query template. Props [@felipeelia](https://github.com/felipeelia).
* New `ep_post_filters` filter and refactor of the `Post::format_args` method. Props [@felipeelia](https://github.com/felipeelia).
* New `get_index_settings()` method to retrieve index settings. Props [@rebeccahum](https://github.com/rebeccahum).
* New `ep_woocommerce_default_supported_post_types` and `ep_woocommerce_admin_searchable_post_types` filters. Props [@ecaron](https://github.com/ecaron).
* Add test factories for Post, User and Term. Props [@burhandodhy](https://github.com/burhandodhy).
* Unit tests to check access to custom results endpoints. Props [@burhandodhy](https://github.com/burhandodhy).
* New unit tests for the user feature. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Facets by Meta available by default. Props [@burhandodhy](https://github.com/burhandodhy).
* If an Elasticsearch index is missing, force a full sync.  Props [@MARQAS](https://github.com/MARQAS), [@felipeelia](https://github.com/felipeelia), and [@JakePT](https://github.com/JakePT).
* ElasticPress.io clients only need to enter the Subscription ID now. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* `Renderer::order_by_selected` visibility. Props [@burhandodhy](https://github.com/burhandodhy).
* After editing a term, only sync posts if the term is associated with fewer posts than the Content Items per Index Cycle number. Props [@felipeelia](https://github.com/felipeelia), [@cmcandrew](https://github.com/cmcandrew), [@DenisFlorin](https://github.com/DenisFlorin), and [@burhandodhy](https://github.com/burhandodhy).
* The `meta_query` clause when using the `meta_key` parameter. Props [@felipeelia](https://github.com/felipeelia), [@MARQAS](https://github.com/MARQAS), and [@Greygooo](https://github.com/Greygooo).
* Facets filters are not applied in the WP Query level anymore. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* To be compatible with WordPress 6.1, when passing `'all'` as the `fields` parameter of `WP_User_Query` only user IDs will be returned. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* `update_term_meta_cache` parameter set as false while getting terms for Facets. Props [@mae829](https://github.com/mae829).
* Small refactor of Indexables' `parse_orderby` to make it easier to read. Props [@felipeelia](https://github.com/felipeelia).
* Search algorithms descriptions. Props [@felipeelia](https://github.com/felipeelia).
* Hide taxonomies from facet block whose `show_ui` is set to false. Props [@burhandodhy](https://github.com/burhandodhy).
* Use `Utils\*_option()` when possible. Props [@rebeccahum](https://github.com/rebeccahum).
* Remove unnecessary check from `allow_excerpt_html`. Props [@burhandodhy](https://github.com/burhandodhy).
* Updated Cypress (version 9 to 10). Props [@felipeelia](https://github.com/felipeelia).
* Use factory to create comments for tests. Props [@burhandodhy](https://github.com/burhandodhy).
* Improved e2e tests performance. Props [@felipeelia](https://github.com/felipeelia).
* GitHub Action used by PHPCS. Props [@felipeelia](https://github.com/felipeelia).

__Deprecated:__

* The following WP-CLI commands were deprecated. They will still work but with a warning.
	* `wp elasticpress index` in favor of `wp elasticpress sync`
	* `wp elasticpress get-cluster-indexes` in favor of `wp elasticpress get-cluster-indices`
	* `wp elasticpress get-indexes` in favor of `wp elasticpress get-indices`
	* `wp elasticpress clear-index` in favor of `wp elasticpress clear-sync`
	* `wp elasticpress get-indexing-status` in favor of `wp elasticpress get-ongoing-sync-status`
	* `wp elasticpress get-last-cli-index` in favor of `wp elasticpress get-last-cli-sync`
	* `wp elasticpress stop-indexing` in favor of `wp elasticpress stop-sync`

Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).

* The `sites` parameter for WP_Query, WP_Term_Query and WP_Comment_Query was deprecated in favor of the new `site__in` and `site__not_in`. Props [@burhandodhy](https://github.com/burhandodhy).

__Removed:__

* Compatibility code for WP < 4.6 in the Post Search feature. Props [@burhandodhy](https://github.com/burhandodhy).
* Legacy hook from unit tests. Props [@burhandodhy](https://github.com/burhandodhy).
* Time average box in the Index Health page. Props [@felipeelia](https://github.com/felipeelia) and [@alaa-alshamy](https://github.com/alaa-alshamy).
* [Protected Content] Removed post types to be indexed by default: ep-synonym, ep-pointer, wp_global_styles, wp_navigation, wp_template, and wp_template_part. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* Clicking on the Facet Term redirect to Homepage. Props [@burhandodhy](https://github.com/burhandodhy).
* Custom results are not highlighted. Props [@burhandodhy](https://github.com/burhandodhy).
* Error when passing an array of post types to WP_Comment_Query. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@MARQAS](https://github.com/MARQAS).
* Deprecated filters for search algorithms do not overwrite values set with the newer filters. Props [@felipeelia](https://github.com/felipeelia) and [@marc-tt](https://github.com/marc-tt).
* No posts returned when an invalid value was passed to the tax_query parameter. Props [@burhandodhy](https://github.com/burhandodhy).
* Incorrect excerpt when `get_the_excerpt` is called outside the Loop and Excerpt highlighting option is enabled. Props [@burhandodhy](https://github.com/burhandodhy).
* Facet returns no result for a term having accent characters. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* An issue where some characters in taxonomy terms would appear encoded when displayed in Instant Results. Props [@JakePT](https://github.com/JakePT).
* An issue that caused Autosuggest filter functions to no longer work. Props [@JakePT](https://github.com/JakePT).
* An issue that prevented clicking Autosuggest suggestions if they had been customized with additional markup. Props [@JakePT](https://github.com/JakePT).
* WooCommerce custom product sort order. Props [@felipeelia](https://github.com/felipeelia) and [@MARQAS](https://github.com/MARQAS).
* Network alias creation failed warning when one of the sites is deactivated. Props [@burhandodhy](https://github.com/burhandodhy).
* JS Error on widget screen. Props [@burhandodhy](https://github.com/burhandodhy).
* PHP Warning when a post has no comments. Props [@felipeelia](https://github.com/felipeelia) and [@JiveDig](https://github.com/JiveDig).
* `put-mapping --network-wide` throws error when plugin is not activated on network. Props [@burhandodhy](https://github.com/burhandodhy).
* Internationalization of strings in JavaScript files. Props [@felipeelia](https://github.com/felipeelia).
* Documentation of the `ep_woocommerce_admin_products_list_search_fields` filter. Props [@felipeelia](https://github.com/felipeelia).
* Warning if `_source` is not returned in query hit. Props [@pschoffer](https://github.com/pschoffer).
* Undefined variable `$update` on synonyms page. Props [@burhandodhy](https://github.com/burhandodhy).
* PHP 8 deprecation warning related to `uasort()` usage. Props [@burhandodhy](https://github.com/burhandodhy).
* Cypress intermittent tests failures. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Fix PHP Unit Tests for PHP 8. Props [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `loader-utils` from 1.4.0 to 1.4.2. Props [@dependabot](https://github.com/dependabot).

= 4.3.1 - 2022-09-27 =

This release fixes some bugs and also adds some new filters.

__Added:__

* New `ep_facet_taxonomy_terms` filter to filter the Facet terms. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Added `ep.Autosuggest.itemHTML`, `ep.Autosuggest.listHTML`, `ep.Autosuggest.query`, and `ep.Autosuggest.element` JavaScript hooks to Autosuggest and migrated filter functions to hook callbacks for backwards compatibility. Props [@JakePT](https://github.com/JakePT).
* E2E tests for the Comments Feature. Props [@burhandodhy](https://github.com/burhandodhy).
* E2E tests for the Instant Results feature. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* More E2E tests for the WooCommerce Feature. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* REST API endpoints used for managing custom results are no longer publicly accessible. Props [@JakePT](https://github.com/JakePT) and [@PypWalters](https://github.com/PypWalters).

__Fixed:__

* WooCommerce data privacy eraser query deleting all orders if EP is enabled for admin and Ajax requests. Props [@sun](https://github.com/sun) and [@bogdanarizancu](https://github.com/bogdanarizancu).
* Facets removing WooCommerce sorting. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Facets triggering the ElasticPress integration in feed pages. Props [@felipeelia](https://github.com/felipeelia) and [@rafaucau](https://github.com/rafaucau).
* Taxonomy Facet tree issue when child category is selected. Props [@burhandodhy](https://github.com/burhandodhy).
* Term search in the admin panel for non-public taxonomies returning nothing. Props [@burhandodhy](https://github.com/burhandodhy).
* Clicking a Related Posts link while in the editor no longer follows the link. Props [@JakePT](https://github.com/JakePT).
* Visual alignment of elements in the Settings page. Props [@MARQAS](https://github.com/MARQAS) and [@felipeelia](https://github.com/felipeelia).
* Intermittent tests failures. Props [@burhandodhy](https://github.com/burhandodhy).

= 4.3.0 - 2022-08-31 =

ElasticPress 4.3.0 fixes some bugs and introduces some new and exciting changes.

WooCommerce Product Variations SKUs are now a publicly searchable field. Site administrators wanting to allow users to search for their product variations SKUs can enable it in the _Search Fields & Weighting_ Dashboard, under Products. If a user searches for a variation SKU, the parent product will be displayed in the search results.

The last ElasticPress sync information is now available in WordPress's Site Health. If you want to check information like the date of the last full sync, time spent, number of indexed content, or errors go to Tools -> Site Health, open the Info tab and click on _ElasticPress - Last Sync_.

Facets received some further improvements in this version. In addition to some refactoring related to WordPress Block Editor, ElasticPress 4.3.0 ships with an experimental version of a _Facet By Meta_ block. With that, users will be able to filter content based on post meta fields. If you want to try it, download and activate [this plugin](https://raw.githubusercontent.com/10up/ElasticPress/develop/tests/cypress/wordpress-files/test-plugins/elasticpress-facet-by-meta.php). Do you have an idea of an enhancement? Search in our [`facets`](https://github.com/10up/ElasticPress/labels/module%3Afacets) label in GitHub and if it is not there yet, feel free to open a new issue. We would love to hear new ideas!

__Added:__

* Search products by their variations' SKUs. Props [@burhandodhy](https://github.com/burhandodhy).
* New block to Facet by Meta fields. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/@tott).
* Display last sync info in site health screen. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* New `epwr_decay_field` filter to set the decay field for date weighting. Props [@MARQAS](https://github.com/MARQAS) and [@HypeAU](https://github.com/HypeAU).
* Autosuggest: filter the autosuggest ElasticSearch query by defining a `window.epAutosuggestQueryFilter()` function in JavaScript. Props [@johnwatkins0](https://github.com/johnwatkins0).
* Autosuggest: filter the HTML of all results by defining a `window.epAutosuggestListItemsHTMLFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT).
* Autosuggest: filter the container element by defining a `window.epAutosuggestElementFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT).
* Documentation for Autosuggest JavaScript filters. Props [@JakePT](https://github.com/JakePT) and [@brandwaffle](https://github.com/brandwaffle).
* Documentation for styling Instant Results. Props [@JakePT](https://github.com/JakePT).
* Use `wp_cache_flush_group()` for autosuggest when available. Props [@tillkruss](https://github.com/tillkruss).
* The public search API is automatically deactivated when the Instant Results feature is deactivated. Props [@JakePT](https://github.com/JakePT).
* Support for transforming instances of the legacy Facet and Related Posts widgets into blocks. Props [@JakePT](https://github.com/JakePT).
* Use `wp_cache_flush_runtime()` when available. Props [@tillkruss](https://github.com/tillkruss), [@felipeelia](https://github.com/felipeelia), and [@tott](https://github.com/@tott).
* E2E tests for the Custom Results feature. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* E2E tests for the Terms feature. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Improved performance in `get_term_tree()`. Props [@rebeccahum](https://github.com/rebeccahum).
* Migrated Related Posts block definitions to `block.json`. Props [@JakePT](https://github.com/JakePT).
* Total comment count made during sync process to be a proper count call. Props [@felipeelia](https://github.com/felipeelia) and [@bsabalaskey](https://github.com/bsabalaskey).
* Search algorithms moved to separate classes. Props [@felipeelia](https://github.com/felipeelia).
* The legacy Facet and Related Posts widgets are now hidden when using the block editor. Props [@JakePT](https://github.com/JakePT).
* Facets are now divided by types and received their own class. Props [@felipeelia](https://github.com/felipeelia).
* PHP compatibility check merged to regular lint. Props [@felipeelia](https://github.com/felipeelia).
* E2e tests to run WP-CLI commands in an existent docker container. Props [@felipeelia](https://github.com/felipeelia).
* Increased E2e tests coverage for WP-CLI commands. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).

__Deprecated:__

* The following filters were deprecated. They will still work but add a notice in the error logs.
	* Deprecated `ep_formatted_args_query` in favour of `ep_post_formatted_args_query`
	* Deprecated `ep_match_phrase_boost` in favour of `ep_post_match_phrase_boost`
	* Deprecated `ep_match_boost` in favour of `ep_post_match_boost`
	* Deprecated `ep_fuzziness_arg` in favour of `ep_post_fuzziness_arg`
	* Deprecated `ep_match_fuzziness` in favour of `ep_post_match_fuzziness`
	* Deprecated `ep_match_cross_fields_boost` in favour of `ep_post_match_cross_fields_boost`

__Fixed:__

* Error returned by the `recreate-network-alias` CLI command when called on single site. Props [@burhandodhy](https://github.com/burhandodhy).
* Term objects in `format_hits_as_terms` to use `WP_Term` instead of `stdClass` to match WordPress expectations. Props [@jonathanstegall](https://github.com/jonathanstegall).
* Post reindex on meta deletion. Props [@pschoffer](https://github.com/pschoffer).
* Autosaved drafts not showing up in draft post listing when using the Protected Content feature. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* Display fatal error messages in the Sync Dashboard. Props [@felipeelia](https://github.com/felipeelia) and [@orasik](https://github.com/orasik).
* An issue where syncing after skipping setup, instead of deleting and syncing, resulted in an error. Props [@JakePT](https://github.com/JakePT).
* Stuck progress bar when no post is found. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Infinite loop during sync if the site has just password protected posts and no other content. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* JS error on Custom Results edit page. Props [@burhandodhy](https://github.com/burhandodhy).
* Horizontal scroll in ElasticPress Quick Setup Screen. Props [@MARQAS](https://github.com/MARQAS) and [@JakePT](https://github.com/JakePT).
* Allows to replace `post_excerpt` with highlighted results from within AJAX and other integrated contexts. Props [@nickchomey](https://github.com/nickchomey).
* Empty results for taxonomy terms that have non ASCII characters. Props [@alaa-alshamy](https://github.com/alaa-alshamy).
* Format of highlight tags quotation mark. Props [@nickchomey](https://github.com/nickchomey).
* Intermittent error with sticky posts in the tests suite. Props [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumped `terser` from 5.12.0 to 5.14.2. Props [@dependabot](https://github.com/dependabot).
* Bumped `@wordpress/env` from 4.5.0 to 5.0.0. Props [@felipeelia](https://github.com/felipeelia).

= 4.2.2 - 2022-07-14 =

This is a bug fix release.

__Added:__

* New `ep_enable_do_weighting` filter and re-factor with new function `apply_weighting`. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* New `ep_default_analyzer_char_filters` filter. Props [@rebeccahum](https://github.com/rebeccahum).
* E2E test to prevent saving feature settings during a sync. Props [@burhandodhy](https://github.com/burhandodhy).
* Full compatibility with Composer v2. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* `update_index_settings()` now accounts for the index closing action timing out and re-opens index if closed. Props [@rebeccahum](https://github.com/rebeccahum).

__Fixed:__

* Wrong post types being displayed on the homepage while having the Facets feature enabled. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez).
* Wrong notice about unsupported server software. Props [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumped `moment` from 2.29.2 to 2.29.4. Props [@dependabot](https://github.com/dependabot).

= 4.2.1 - 2022-06-28 =

This is a bug fix release.

__Added:__

* Server type/software detection and warning. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).
* Coverage of E2E tests for the activate-feature command. Props [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Sync button `title` attribute. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* `npm run build:zip` to use `git archive`. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* Fatal error related to WP-CLI timers on long-running syncs. Props [@felipeelia](https://github.com/felipeelia) and [@przestrzal](https://github.com/przestrzal).
* Uncaught TypeError on the Settings Page. Props [@burhandodhy](https://github.com/burhandodhy).
* Meta values that are not dates converted into date format. Props [@burhandodhy](https://github.com/burhandodhy), [@oscarssanchez](https://github.com/oscarssanchez), [@tott](https://github.com/@tott), and [@felipeelia](https://github.com/felipeelia).
* An issue where feature settings could be saved during a sync. Props [@JakePT](https://github.com/JakePT).
* Admin menu bar items are not clickable when instant results popup modal is activated. Props [@MARQAS](https://github.com/MARQAS) and [@JakePT](https://github.com/JakePT).
* Facet block wrongly available in the post editor. Props [@oscarssanchez](https://github.com/oscarssanchez).
* Show Facet widgets on taxonomy archives. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Meta queries with 'exists' as compare operator and empty meta values handling. Props [@burhandodhy](https://github.com/burhandodhy).
* Sync interruption message always mentioning ElasticPress.io. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* An issue where the Related Posts block would display the wrong posts in the preview when added inside a Query Loop block. Props [@JakePT](https://github.com/JakePT).
* E2e tests for the Facets feature. Props [@felipeelia](https://github.com/felipeelia).
* Intermittent error on GitHub Actions using the latest node 16 version. Props [@felipeelia](https://github.com/felipeelia).

= 4.2.0 - 2022-05-26 =

ElasticPress 4.2.0 fixes some bugs and introduces some new and exciting changes.

The sync functionality had its JavaScript refactored. Timeouts, memory limits, and general errors are now properly handled and do not make the sync get stuck when performed via the WP-CLI `index` command. There is also a new `get-last-sync` WP-CLI command to check the errors and numbers from the last sync.

We've improved the admin search experience for sites using both WooCommerce and Protected Content. Previously, WooCommerce was processing that screen with two different queries, and EP was used only in one of them. Now, it will be only one query, fully handled by ElasticPress. Users wanting to keep the previous behavior can do so by adding `add_filter( 'ep_woocommerce_integrate_admin_products_list', '__return_false' );` to their website's codebase.

Facets are now available through a WordPress block. If you are using the Full Site Editing feature, you can now add ElasticPress Facets to your theme with just a few clicks! This block has been introduced with a simplified user interface to enable compatibility with Full Site Editing and will continue to be iterated and improved in future versions of the plugin.

__Added:__

* E2e tests for the Facets feature. Props [@felipeelia](https://github.com/felipeelia).
* `$post_args` and `$post_id` to the `ep_pc_skip_post_content_cleanup` filter. Props [@felipeelia](https://github.com/felipeelia) and [@ecaron](https://github.com/ecaron).
* New filter `ep_integrate_search_queries`. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* New `get-last-sync` WP-CLI command. Props [@felipeelia](https://github.com/felipeelia).
* Facet block (previously only available as a widget.) Props [@felipeelia](https://github.com/felipeelia).
* New `_variations_skus` field to WooCommerce products. Props [@felipeelia](https://github.com/felipeelia), [@kallehauge](https://github.com/kallehauge), and [@lukaspawlik](https://github.com/lukaspawlik).
* Support for ordering Users by `user_registered` and lowercase `id`. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* New filter `ep_sync_number_of_errors_stored`. Props [@felipeelia](https://github.com/felipeelia), [@tott](https://github.com/tott) and [@JakePT](https://github.com/JakePT).

__Changed:__

* Facets widgets rendered by a separate class. Props [@felipeelia](https://github.com/felipeelia).
* Deprecated `ElasticPress\Feature\Facets\Widget::get_facet_term_html()` in favor of `ElasticPress\Feature\Facets\Renderer::get_facet_term_html()`. Props [@felipeelia](https://github.com/felipeelia).
* Log errors and remove indexing status on failed syncs. Props [@felipeelia](https://github.com/felipeelia).
* Refactored Sync page JavaScript. Props [@JakePT](https://github.com/JakePT).
* Updated admin scripts to use WordPress's version of React. Props [@JakePT](https://github.com/JakePT).
* WooCommerce products list in the Dashboard now properly leverages ElasticPress. Props [@felipeelia](https://github.com/felipeelia).
* Removed Instant Results' dependency on `@wordpress/components` and `@wordpress/date`. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* (Protected Content) Password-protected posts are only hidden on searches. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@burhandodhy](https://github.com/burhandodhy).
* The plugin is now available via Composer without any additional steps required. Props [@felipeelia](https://github.com/felipeelia), [@jeffpaul](https://github.com/jeffpaul), and [@johnbillion](https://github.com/johnbillion).

__Fixed:__

* WP-CLI parameters documentation. Props [@felipeelia](https://github.com/felipeelia).
* Full indices removal after e2e tests. Props [@felipeelia](https://github.com/felipeelia) and [@dustinrue](https://github.com/dustinrue).
* Usage of the `$return` parameter in `Feature\RelatedPosts::find_related()`. Props [@felipeelia](https://github.com/felipeelia) and [@altendorfme](https://github.com/altendorfme).
* Link to API Functions under the Related Posts feature -> Learn more. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Sync of WooCommerce Orders fields when WooCommerce and Protected Content features are enabled. Props [@felipeelia](https://github.com/felipeelia) and [@ecaron](https://github.com/ecaron).
* An issue where selecting no features during install would just cause the install page to reload without any feedback. Props [@JakePT](https://github.com/JakePT) and [@tlovett1](https://github.com/tlovett1).
* An issue where deselecting a feature during install would not stop that feature from being activated. Props [@JakePT](https://github.com/JakePT).
* Add the missing text domain for the Related Posts block. Props [@burhandodhy](https://github.com/burhandodhy).
* Console error when hitting enter on search inputs with autosuggest. Props [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), and [@MarijnvSprundel](https://github.com/MarijnvSprundel).
* An issue where attribute selectors could not be used for the Autosuggest Selector. Props [@JakePT](https://github.com/JakePT) and [@oscarssanchez](https://github.com/oscarssanchez).
* "Time elapsed" and "Total time elapsed" in WP-CLI index command. Props [@felipeelia](https://github.com/felipeelia) and [@archon810](https://github.com/archon810).
* Sync process with skipped objects. Props [@juliecampbell](https://github.com/juliecampbell) and [@felipeelia](https://github.com/felipeelia).
* Typo in the sync page. Props [@JakePT](https://github.com/JakePT) and [@davidegreenwald](https://github.com/davidegreenwald).
* WP-CLI index command without the `--network-wide` only syncs the main site. Props [@felipeelia](https://github.com/felipeelia) and [@colegeissinger](https://github.com/colegeissinger).
* WP-CLI commands `get-mapping`, `get-indexes`, `status`, and `stats` only uses all sites' indices name when network activated. Props [@felipeelia](https://github.com/felipeelia) and [@colegeissinger](https://github.com/colegeissinger).
* A bug where URL search parameters could be cleared when using Instant Results. Props [@JakePT](https://github.com/JakePT) and [@yashumitsu](https://github.com/yashumitsu).
* Undefined index notice in Facets renderer. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Prevent an unnecessary call when the ES server is not set yet. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* An incompatibility with the way WP 6.0 handles WP_User_Query using fields. Props [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumped `moment` from 2.29.1 to 2.29.2. Props [@dependabot](https://github.com/dependabot).
* Bumped `@wordpress/env` from 4.4.0 to 4.5.0. Props [@felipeelia](https://github.com/felipeelia).

= 4.1.0 - 2022-04-05 =

__Added:__

* Utility command to create zip packages: `npm run build:zip`. Props [@felipeelia](https://github.com/felipeelia).
* E2e tests for the Synonyms feature. Props [@felipeelia](https://github.com/felipeelia).
* `generate_mapping()` to post and comment indexables. Props [@rebeccahum](https://github.com/rebeccahum).
* `get_related_query()` to the `RelatedPosts` class. Props [@ayebare](https://github.com/ayebare).
* New `--pretty` flag to the WP-CLI commands that output a JSON. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez).
* Support for an array of aggregations in the `aggs` parameter of `WP_Query`. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez).

__Changed:__

* Refactored remaining admin scripts to remove jQuery as a dependency. Props [@JakePT](https://github.com/JakePT).
* Generate Instant Results' search template as an anonymous user by default. Props [@JakePT](https://github.com/JakePT).

__Fixed:__

* PHP warning Trying to access array offset on value of type int in `get_index_names()`. Props [@sun](https://github.com/sun).
* Searches by WooCommerce Order ID. Props [@felipeelia](https://github.com/felipeelia).
* Display and error message if syncing failed due to invalid JSON response from the server. Props [@dsawardekar](https://github.com/dsawardekar).
* Better compatibility with PHP 8.1 by replacing the deprecated `FILTER_SANITIZE_STRING`. Props [@sjinks](https://github.com/sjinks).
* `get_term_tree()` no longer infinite loops when parent ID is non-existent. Props [@rebeccahum](https://github.com/rebeccahum).
* User search results include users who do not exist in the current site. Props [@tfrommen](https://github.com/tfrommen) and [@felipeelia](https://github.com/felipeelia).
* Pagination while syncing Indexables other than Posts. Props [@felipeelia](https://github.com/felipeelia) and [@derringer](https://github.com/derringer).
* Handle the output of an array of messages in sync processes. Props [@felipeelia](https://github.com/felipeelia).
* Truthy values for the `'enabled'` field's attribute while using the `ep_weighting_configuration_for_search` filter. Props [@felipeelia](https://github.com/felipeelia) and [@moritzlang](https://github.com/moritzlang).
* "Learn More" link on the Sync Page. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), and [@brandwaffle](https://github.com/brandwaffle).
* Icons alignment in the WP Dashboard. Props [@jakemgold](https://github.com/jakemgold), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), and [@tlovett1](https://github.com/tlovett1).

__Security:__

* Bumped `node-forge` from 1.2.1 to 1.3.0. Props [@dependabot](https://github.com/dependabot).
* Bumped` @wordpress/env` from 4.2.2 to 4.4.0, and `minimist` from 1.2.5 to 1.2.6. Props [@felipeelia](https://github.com/felipeelia).

= 4.0.1 - 2022-03-16 =

**This is a security release affecting users running ElasticPress 4.0 with both the WooCommerce and Protected Content Features activated. Please update to the latest version of ElasticPress if the WooCommerce and Protected Content features are activated and you're using ElasticPress 4.0.**

__Security:__

* Orders belonging to all users loaded in the My Account WooCommerce page. Props [@tomburtless](https://github.com/tomburtless) and [@oscarssanchez](https://github.com/oscarssanchez).

= 4.0.0 - 2022-03-08 =

**ElasticPress 4.0 contains some important changes. Make sure to read these highlights before upgrading:**

* This version requires a full reindex.
* It introduces a new search algorithm that may change the search results displayed on your site.
* A new feature called "Instant Results" is available. As it requires a full reindex, if you plan to use it, we recommend you enable it first and reindex only once.
* Users upgrading from Beta 1 need to re-save the Instant Results feature settings.
* New minimum versions are:

	||Min|Max|
	|---|:---:|:---:|
	|Elasticsearch|5.2|7.10|
	|WordPress|5.6+|latest|
	|PHP|7.0+|latest|

**Note that ElasticPress 4.0.0 release removes built assets from the `develop` branch, replaced `master` with `trunk`, added a ZIP with the plugin and its built assets in the [GitHub Releases page](https://github.com/10up/ElasticPress/releases), and included a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub Releases depending on whether you require built assets or not. (See changes in [#2622](https://github.com/10up/ElasticPress/pull/2622).)

The Facets widget is not currently available within Full Site Editing mode.

__Added:__

* Instant Results. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), and [Pratheep Chandrasekhar](https://www.linkedin.com/in/pratheepch/).
* New default search algorithm prioritizing exact matches, matches in the same field, then matches across different fields. Props [@brandwaffle](https://github.com/brandwaffle) and [@felipeelia](https://github.com/felipeelia).
* Filter `ep_load_search_weighting` to disable search weighting engine. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* Post types to facet labels when needed to to differentiate facets with duplicate labels. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* Support for search form post type fields to Instant Results. Props [@JakePT](https://github.com/JakePT).
* Alternative way to count total posts on larger DBs during indexing. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* Do not count posts in `get_total_objects_for_query_from_db()` if any object limit IDs are passed in. Props [@rebeccahum](https://github.com/rebeccahum).
* Show WP-CLI progress on the new Sync page. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia).
* Display results counts for facet options in Instant Results. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia).
* ARIA attributes to Facet widget links to improve accessibility. Props [@JakePT](https://github.com/JakePT).
* Support for shareable URLs to Instant Results. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* Dynamic bulk requests limits. Instead of sending only one request per document batch, send several adjusting their sizes based on the Elasticsearch response. Props [@felipeelia](https://github.com/felipeelia), [@dinhtungdu](https://github.com/dinhtungdu), [@brandwaffle](https://github.com/brandwaffle), and [@Rahmon](https://github.com/Rahmon).
* New step in the installation process: users can now select features before the initial sync. Props [@felipeelia](https://github.com/felipeelia), [@JakePT](https://github.com/JakePT), [Jonathan Netek](https://www.linkedin.com/in/jonathan-netek/), and [@brandwaffle](https://github.com/brandwaffle).

__Changed:__

* Sync page and code responsible for indexing. Props [@helen](https://github.com/helen), [@felipeelia](https://github.com/felipeelia), [@Rahmon](https://github.com/Rahmon), [@mckdemps](https://github.com/mckdemps), [@tott](https://github.com/tott), and [Pratheep Chandrasekhar](https://www.linkedin.com/in/pratheepch/).
* When Protected Content is enabled, ElasticPress will have a more similar behavior to WordPress core but the post content and meta will not be indexed (the new `ep_pc_skip_post_content_cleanup` can be used to skip that removal.) Props [@rebeccahum](https://github.com/rebeccahum), [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), [@dinhtungdu](https://github.com/dinhtungdu), [@cristianuibar](https://github.com/cristianuibar), and [@allan23](https://github.com/allan23), [@mallorydxw](https://github.com/mallorydxw).
* Bump minimum required versions of Elasticsearch from 5.0 to 5.2 and WordPress from 3.7.1 to 5.6. Props [@felipeelia](https://github.com/felipeelia).
* Bump minimum required PHP version from 5.6 to 7.0. Props [@felipeelia](https://github.com/felipeelia), [@ActuallyConnor](https://github.com/ActuallyConnor), and [@brandwaffle](https://github.com/brandwaffle).
* Internationalize start and end datetimes of sync. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia).
* `ep_integrate` argument in WP_Query to accept `0` and `'false'` as valid negative values. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).
* To comply with modern WooCommerce behavior, ElasticPress no longer changes the `orderby` parameter. Props [@felipeelia](https://github.com/felipeelia) and [@beazuadmin](https://github.com/beazuadmin).
* Query parameters for facets to start with `ep_filter`, changeable via the new `ep_facet_filter_name` filter. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@slaxxarn](https://github.com/slaxxarn).
* Output of sync processes using offset to display the number of documents skipped. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), [@cbratschi](https://github.com/cbratschi), and [@brandwaffle](https://github.com/brandwaffle).
* Switched from WP Acceptance to Cypress for end to end tests. Props [@felipeelia](https://github.com/felipeelia), [@Sidsector9](https://github.com/Sidsector9), and [@dustinrue](https://github.com/dustinrue).
* CSS vars usage in the new Sync page. Props [@Rahmon](https://github.com/Rahmon), [@JakePT](https://github.com/JakePT), [@mehidi258](https://github.com/mehidi258), and [@felipeelia](https://github.com/felipeelia).
* Features screen: improved accessibility and jQuery dependency removal. Props [@JakePT](https://github.com/JakePT).
* Taxonomy parameters now reflect the WordPress parsed `tax_query` value. Props [@felipeelia](https://github.com/felipeelia) and [@sathyapulse](https://github.com/sathyapulse).
* Features order in the Features screen. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).
* WooCommerce's `search` parameter also to be used by ElasticPress queries. Props [@felipeelia](https://github.com/felipeelia), [@dianfishekqi](https://github.com/dianfishekqi), and [@oscarssanchez](https://github.com/oscarssanchez).
* Posts are now reindexed when a new term is associated with them and also when an associated term is updated or deleted. Props [@nickdaugherty](https://github.com/nickdaugherty), [@felipeelia](https://github.com/felipeelia), [@brandon-m-skinner](https://github.com/brandon-m-skinner), [@mckdemps](https://github.com/mckdemps), [@rebeccahum](https://github.com/rebeccahum).
* Complement to the resync message related to Instant Results. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).
* Updated `jsdoc` from 3.6.9 to 3.6.10 and fixed the documentation of the `ep_thumbnail_image_size` filter. Props [@felipeelia](https://github.com/felipeelia).
* Instant Results: type and initial value of search template and move save to the end of sync. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez).

__Removed:__

* Built assets (minified JavaScript and CSS files) from the repository. Props [@felipeelia](https://github.com/felipeelia) and [@jeffpaul](https://github.com/jeffpaul).
* Duplicate `case 'description':` from `ElasticPress\Indexable\Term\Term::parse_orderby`. Props [@sjinks](https://github.com/sjinks).

__Fixed:__

* CSS issues on Features page. Props [@JakePT](https://github.com/JakePT).
* AJAX URL on subsites. Props [@Rahmon](https://github.com/Rahmon).
* PHP Notice while monitoring a WP-CLI sync in the dashboard. Props [@felipeelia](https://github.com/felipeelia) and [@ParhamG](https://github.com/ParhamG).
* Sync page when WooCommerce's "hide out of stock items" and Instant Results are both enabled. Props [@felipeelia](https://github.com/felipeelia).
* PHPUnit Tests and WordPress 5.9 compatibility. Props [@felipeelia](https://github.com/felipeelia).
* WooCommerce Orders Search when searching for an order ID. Props [@felipeelia](https://github.com/felipeelia).
* Code standards. Props [@felipeelia](https://github.com/felipeelia).
* Posts insertion and deletion in the same thread. Props [@felipeelia](https://github.com/felipeelia) and [@tcrsavage](https://github.com/tcrsavage).
* Invalid values in `tax_query` terms resulting in a query failure. Props [@rinatkhaziev](https://github.com/rinatkhaziev) and [@felipeelia](https://github.com/felipeelia).
* New Sync Page to display a message when an indexing is stopped by the WP-CLI `stop-indexing` command. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@brandwaffle](https://github.com/brandwaffle).
* Nested queries are no longer deleted. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@christianc1](https://github.com/christianc1).
* Type hints for `epwr_decay` and `epwr_weight` hooks. Props [@edwinsiebel](https://github.com/edwinsiebel).
* Errors count in the new Sync page. Props [@felipeelia](https://github.com/felipeelia).
* Multisite could index posts from a disabled indexing site. Props [@oscarssanchez](https://github.com/oscarssanchez), [@chrisvanpatten](https://github.com/chrisvanpatten), [@felipeelia](https://github.com/felipeelia).
* New sync code and the `upper-limit-object-id` and `lower-limit-object-id` parameters in WP-CLI command. Props [@felipeelia](https://github.com/felipeelia).
* Sync link on index health page. Props [@JakePT](https://github.com/JakePT).
* Logic checking if it is a full sync and if search should go or not through ElasticPress. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).

__Security:__

* Use most recent external GitHub Actions versions. Props [@felipeelia](https://github.com/felipeelia) and [@qazaqstan2025](https://github.com/qazaqstan2025).
* Updated `10up-toolkit` from 1.0.13 to 3.0.1, `jsdoc` from 3.6.7 to 3.6.9, `terser-webpack-plugin` from 5.2.4 to 5.3.0, `@wordpress/env` from 4.1.1 to 4.2.2, and `promise-polyfill` from 8.2.0 to 8.2.1. Props [@felipeelia](https://github.com/felipeelia).
* Bumped `follow-redirects` from 1.14.7 to 1.14.9. Props [@dependabot](https://github.com/dependabot).

= 3.6.6 - 2021-12-20 =

ElasticPress 4.0 Beta 1 is [now available](https://github.com/10up/ElasticPress/releases/tag/4.0.0-beta.1) for non-production testing.

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub releases depending on whether you require built assets or not.

Supported versions changes planned for ElasticPress 4.0.0:

* Elasticsearch: from 5.0 - 7.9 to 5.2 - 7.10.
* WordPress: from 3.7.1+ to 5.6+.
* PHP: from 5.6+ to 7.0+.

__Added:__

* Ensure array query parameters do not contain empty items. Props [@roborourke](https://github.com/roborourke).
* WP-CLI `request` subcommand. Props [@joehoyle](https://github.com/joehoyle) and [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Enabling features that require a reindex will now ask for confirmation. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@Rahmon](https://github.com/Rahmon), [@columbian-chris](https://github.com/columbian-chris), and [@brandwaffle](https://github.com/brandwaffle).

__Fixed:__

* Broken search pagination on hierarchical post types. Props [@tfrommen](https://github.com/tfrommen).
* Synonyms erased when syncing via WP-CLI. Props [@felipeelia](https://github.com/felipeelia) and [@oscarssanchez](https://github.com/oscarssanchez).
* Deleting a metadata without passing an object id now updates all associated posts. Props [@oscarssanchez](https://github.com/oscarssanchez), [@felipeelia](https://github.com/felipeelia), and [@Shrimpstronaut](https://github.com/Shrimpstronaut).
* Not indexable sites added to indexes list in WP-CLI commands. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).

= 3.6.5 - 2021-11-30 =

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub releases depending on whether you require built assets or not.

Supported versions changes planned for ElasticPress 4.0.0:

* Elasticsearch: from 5.0 - 7.9 to 5.2 - 7.10.
* WordPress: from 3.7.1+ to 5.6+.
* PHP: from 5.6+ to 7.0+.

__Added:__

* Docs: Link to the support page in README.md. Props [@brandwaffle](https://github.com/brandwaffle).
* New `ep_weighting_default_enabled_taxonomies` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott).
* `$blog_id` and `$indexable_slug` parameters to the `ep_keep_index` filter. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).

__Changed:__

* Add `$type` parameter to `ep_do_intercept_request` filter. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* Cache the detected Posts mapping version, avoiding `get_mapping` calls in all admin requests. Props [@felipeelia](https://github.com/felipeelia).
* Docs: Required ES and WP versions planned for ElasticPress 4.0.0. Props [@felipeelia](https://github.com/felipeelia).
* The `admin.min.js` file was split in `notice.min.js` and `weighting.min.js`, being loaded accordingly. Props [@felipeelia](https://github.com/felipeelia) and [@barryceelen](https://github.com/barryceelen).

__Fixed:__

* Force fetching `ep_wpcli_sync_interrupted` transient from remote to allow for more reliable remote interruption. Props [@rinatkhaziev](https://github.com/rinatkhaziev) and [@rebeccahum](https://github.com/rebeccahum).
* Duplicate orderby statement in Users query. Props [@brettshumaker](https://github.com/brettshumaker), [@pschoffer](https://github.com/pschoffer), and [@rebeccahum](https://github.com/rebeccahum).
* When using offset and default maximum result window value for size, subtract offset from size. Props [@rebeccahum](https://github.com/rebeccahum).
* Order for Custom Search Results in autosuggest. Props [@felipeelia](https://github.com/felipeelia) and [@johnwatkins0](https://github.com/johnwatkins0).
* WP-CLI stats and status to output all indices related to ElasticPress. Props [@felipeelia](https://github.com/felipeelia).
* Tests: Ensure that Posts related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon).
* Tests: PHPUnit and yoast/phpunit-polyfills. Props [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumped `path-parse` from 1.0.6 to 1.0.7. Props [@dependabot](https://github.com/dependabot).
* Bumped `10up-toolkit` from 1.0.12 to 1.0.13. Props [@felipeelia](https://github.com/felipeelia).

= 3.6.4 - 2021-10-26 =

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, ~~will build a stable release version including built assets into a `stable` branch,~~ will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to ~~either `stable` or~~ `trunk` or to GitHub releases depending on whether you require built assets or not.

Supported versions changes planned for ElasticPress 4.0.0:

* Elasticsearch: from 5.0 - 7.9 to 5.2 - 7.10.
* WordPress: from 3.7.1+ to 5.6+.
* PHP: from 5.6+ to 7.0+.

__Added:__

* WP-CLI: New `get-mapping` command. Props [@tfrommen](https://github.com/tfrommen), [@felipeelia](https://github.com/felipeelia), and [@Rahmon](https://github.com/Rahmon).
* New filters: `ep_query_request_args` and `ep_pre_request_args`. Props [@felipeelia](https://github.com/felipeelia).
* Support for Autosuggest to dynamically inserted search inputs. Props [@JakePT](https://github.com/JakePT), [@rdimascio](https://github.com/rdimascio), [@brandwaffle](https://github.com/brandwaffle), and [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Automatically generated WP-CLI docs. Props [@felipeelia](https://github.com/felipeelia).
* Verification of active features requirement. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@WPprodigy](https://github.com/WPprodigy).
* `ewp_word_delimiter` base filter: changed from `word_delimiter` to `word_delimiter_graph`. Props [@pschoffer](https://github.com/pschoffer), [@Rahmon](https://github.com/Rahmon) and [@yolih](https://github.com/yolih).
* Terms search query in admin will not be fuzzy. Props [@rebeccahum](https://github.com/rebeccahum).

__Fixed:__

* Elapsed time beyond 1000 seconds in WP-CLI index command. Props [@felipeelia](https://github.com/felipeelia) and [@dustinrue](https://github.com/dustinrue).
* Layout of Index Health totals on small displays. Props [@JakePT](https://github.com/JakePT) and [@oscarssanchez](https://github.com/oscarssanchez).
* Deprecated URL for multiple documents get from ElasticSearch. Props [@pschoffer](https://github.com/pschoffer).
* Add new lines and edit terms in the Advanced Synonym Editor. Props [@JakePT](https://github.com/JakePT) and [@johnwatkins0](https://github.com/johnwatkins0).
* Terms: Avoid falling back to MySQL when results are empty. Props [@felipeelia](https://github.com/felipeelia).
* Terms: Usage of several parameters for searching and ordering. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon).
* Attachment indexing on Elasticsearch 7. Props [@Rahmon](https://github.com/Rahmon).
* Tests: Ensure that Documents related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon).
* Tests: Ensure that WooCommerce related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia).
* Tests: Ensure that Comments related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia).
* Tests: Ensure that Multisite related queries use ElasticPress. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia).
* Tests: Ensure that Terms related queries use ElasticPress. Props [@felipeelia](https://github.com/felipeelia).

= 3.6.3 - 2021-09-29 =

**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

Official PHP support is currently 5.6+. Minimum PHP version for ElasticPress 3.7.0 will be 7.0+.

__Added:__

* New `ep_facet_widget_term_html` and `ep_facet_widget_term_label` filters to the Facet widget for filtering the HTML and label of individual facet terms. Props [@JakePT](https://github.com/JakePT), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).
* New `ep_set_sort` filter for changing the `sort` clause of the ES query if `orderby` is not set in WP_Query. Props [@rebeccahum](https://github.com/rebeccahum) and [@felipeelia](https://github.com/felipeelia).
* WP-CLI documentation for some commands and parameters. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* In addition to post titles, now autosuggest also partially matches taxonomy terms. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon).
* Date parsing change to avoid `E_WARNING`s. Props [@pschoffer](https://github.com/pschoffer).

__Fixed:__

* Comments created by anonymous users (but approved by default) are now indexed. Props [@tomjn](https://github.com/tomjn) and [@Rahmon](https://github.com/Rahmon).
* Deleted terms are now properly removed from the Elasticsearch index. Props [@felipeelia](https://github.com/felipeelia).
* Children of deleted terms are now properly removed from the Elasticsearch index. Props [@pschoffer](https://github.com/pschoffer).
* Post tag duplicated in the Elasticsearch query. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).
* Infinite loading state of ElasticPress Related Posts block in the Widgets Edit Screen. Props [@felipeelia](https://github.com/felipeelia).
* Return of `Search::integrate_search_queries()` when `is_integrated_request`. Props [@adiloztaser](https://github.com/adiloztaser).
* Mapping determination based on existing info. Props [@felipeelia](https://github.com/felipeelia).
* `WP_Term_Query` and `parent = 0`. Props [@felipeelia](https://github.com/felipeelia) and [@juansanchezfernandes](https://github.com/juansanchezfernandes).
* WP Acceptance Tests. Props [@felipeelia](https://github.com/felipeelia).
* Typos in the output of some WP-CLI Commands. Props [@rebeccahum](https://github.com/rebeccahum).

__Security:__

* Bumped `10up-toolkit` from 1.0.11 to 1.0.12, `terser-webpack-plugin` from 5.1.4 to 5.2.4, `@wordpress/api-fetch` from 3.21.5 to 3.23.1, and `@wordpress/i18n` from 3.18.0 to 3.20.0. Props [@felipeelia](https://github.com/felipeelia).

= 3.6.2 - 2021-08-26 =
**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version bumps official PHP support from 5.3+ to 5.6+. Minimum PHP version for ElasticPress 3.7.0 will be 7.0+.

__Added:__

* GitHub Action to test compatibility with different PHP versions. Props [@felipeelia](https://github.com/felipeelia).
* Validate mapping currently in index against expected version. Props [@tott](https://github.com/tott), [@tlovett1](https://github.com/tlovett1), [@asharirfan](https://github.com/asharirfan), [@oscarssanchez](https://github.com/oscarssanchez), and [@felipeelia](https://github.com/felipeelia).
* `ep_default_analyzer_filters` filter to adjust default analyzer filters. Props [@pschoffer](https://github.com/pschoffer) and [@felipeelia](https://github.com/felipeelia).
* `title` and `aria-labels` attributes to each icon hyperlink in the header toolbar. Props [@claytoncollie](https://github.com/claytoncollie) and [@felipeelia](https://github.com/felipeelia).
* `Utils\is_integrated_request()` function to centralize checks for admin, AJAX, and REST API requests. Props [@JakePT](https://github.com/JakePT), [@felipeelia](https://github.com/felipeelia), [@brandwaffle](https://github.com/brandwaffle), [@moritzlang](https://github.com/moritzlang), and [@lkraav](https://github.com/lkraav).

__Changed:__

* Use `10up-toolkit` to build assets. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@nicholasio](https://github.com/nicholasio).
* Official PHP supported version bumped to 5.6. Props [@felipeelia](https://github.com/felipeelia).
* Lint React rules using `10up/eslint-config/react`. Props [@Rahmon](https://github.com/Rahmon).
* For ES 7.0+ mappings, change `edgeNGram` to `edge_ngram`. Props [@pschoffer](https://github.com/pschoffer) and [@rinatkhaziev](https://github.com/rinatkhaziev).

__Removed:__

* Remove duplicate category_name, cat and tag_id from ES query when tax_query set. Props [@rebeccahum](https://github.com/rebeccahum) and [@oscarssanchez](https://github.com/oscarssanchez).
* Remove unused `path` from `dynamic_templates`. Props [@pschoffer](https://github.com/pschoffer).

__Fixed:__

* Remove data from Elasticsearch on a multisite network when a site is archived, deleted or marked as spam. Props [@dustinrue](https://github.com/dustinrue) and [@felipeelia](https://github.com/felipeelia).
* `stats` and `status` commands in a multisite scenario. Props [@Rahmon](https://github.com/Rahmon), [@felipeelia](https://github.com/felipeelia), and [@dustinrue](https://github.com/dustinrue).
* Multiple words synonyms. Props [@scooterlord](https://github.com/scooterlord), [@jonasstrandqvist](https://github.com/jonasstrandqvist), and [@felipeelia](https://github.com/felipeelia).
* Category slug used when doing cat Tax Query with ID. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@karols0](https://github.com/karols0).
* Restore current blog when the_post triggers outside the loop in multisite environment and the whole network is searched if the first result is from another blog. Props [@gonzomir](https://github.com/gonzomir) and [@felipeelia](https://github.com/felipeelia).
* Prevents a post from being attempted to delete twice. Props [@pauarge](https://github.com/pauarge).
* Indexing button on Health screen. Props [@Rahmon](https://github.com/Rahmon) and [@oscarssanchez](https://github.com/oscarssanchez).
* WP Acceptance tests and Page Crashed errors. Props [@felipeelia](https://github.com/felipeelia) and [@jeffpaul](https://github.com/jeffpaul).
* Facets: Children of selected terms ordered by count. Props [@oscarssanchez](https://github.com/oscarssanchez), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumps `path-parse` from 1.0.6 to 1.0.7. Props [@dependabot](https://github.com/dependabot).

= 3.6.1 - 2021-07-15 =
**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version requires a full reindex. The new `facet` field introduced in `3.6.0` requires a change in the mapping, otherwise, all content sync related to posts will silently fail. If you've upgraded to 3.6.0 and didn't resync your content yet (via Dashboard or with WP-CLI `wp elasticpress index --setup`) make sure to do so.

__Added:__

* Filter `ep_remote_request_add_ep_user_agent`. Passing `true` to that, the ElasticPress version will be added to the User-Agent header in the request. Props [@felipeelia](https://github.com/felipeelia).
* Flagged `3.6.0` as version that needs a full reindex. Props [@adiloztaser](https://github.com/adiloztaser) and [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Notice when a sync is needed is now an error. Props [@felipeelia](https://github.com/felipeelia) and [@brandwaffle](https://github.com/brandwaffle).

__Fixed:__

* Encode the Search Term header before sending it to ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia).

= 3.6.0 - 2021-07-07 =
**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.

This version requires a full reindex.

__Breaking Changes:__

* Autosuggest will now respect the `[name="post_type"]` input in the same form. Before it would bring all post types. Props [@mustafauysal](https://github.com/mustafauysal) and [@JakePT](https://github.com/JakePT).
* Facets Widget presentation, replacing the `<input type="checkbox">` elements in option links with a custom `.ep-checkbox presentational` div. Props [@MediaMaquina](https://github.com/MediaMaquina), [@amesplant](https://github.com/amesplant), [@JakePT](https://github.com/JakePT), and [@oscarssanchez](https://github.com/oscarssanchez).
* Confirmation for destructive WP-CLI commands. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@Rahmon](https://github.com/Rahmon).

__Added:__

* Comments Indexable. Props [@dkotter](https://github.com/dkotter), [@dinhtungdu](https://github.com/dinhtungdu), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).
* "ElasticPress - Comments", a search form for comments. Props [@Rahmon](https://github.com/Rahmon) and [@felipeelia](https://github.com/felipeelia).
* Facets: new `ep_facet_allowed_query_args` filter. Props [@mustafauysal](https://github.com/mustafauysal), [@JakePT](https://github.com/JakePT),[@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).
* Facets: new `ep_facet_use_field` filter. Props [@moraleida](https://github.com/moraleida).
* GitHub Action to auto-close non-responsive reporter feedback issues after 3 days. Props [@jeffpaul](https://github.com/jeffpaul).
* Autosuggest: new `ep_autosuggest_default_selectors` filter. Props [@JakePT](https://github.com/JakePT) and [@johnbillion](https://github.com/johnbillion).
* WP-CLI: Index by ID ranges with `--upper-limit-object-id` and `--lower-limit-object-id`. Props [@WPprodigy](https://github.com/WPprodigy), [@Rahmon](https://github.com/Rahmon), and [@felipeelia](https://github.com/felipeelia).
* `Elasticsearch::get_documents()` and `Indexable::multi_get()`. Props [@nickdaugherty](https://github.com/nickdaugherty), [@felipeelia](https://github.com/felipeelia), and [@Rahmon](https://github.com/Rahmon).
* Custom sorting to features on the Features page. Props [@Rahmon](https://github.com/Rahmon).
* Terms: add a new `facet` field to hold the entire term object in json format. Props [@moraleida](https://github.com/moraleida).
* Elasticsearch connection check to Site Health page. Props [@spacedmonkey](https://github.com/spacedmonkey) and [@Rahmon](https://github.com/Rahmon).
* Support for NOT LIKE operator for meta_query. Props [@Thalvik)](https://github.com/Thalvik) and [@Rahmon](https://github.com/Rahmon).
* Support for `category__not_in` and `tag__not_in`. Props [@oscarssanchez](https://github.com/oscarssanchez) and [@felipeelia](https://github.com/felipeelia).
* Support for `post__name_in`. Props [@jayhill90](https://github.com/jayhill90) and [@oscarssanchez](https://github.com/oscarssanchez).
* `$indexable_slug` property to `ElasticPress\Indexable\Post\SyncManager`. Props [@edwinsiebel](https://github.com/edwinsiebel).
* Permission check bypass for indexing / deleting for cron and WP CLI. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@felipeelia](https://github.com/felipeelia).
* Check if term exists before a capabilities check is done. Props [@msawicki](https://github.com/msawicki).
* New `ep_show_indexing_option_on_multisite` filter. Props [@johnbillion](https://github.com/johnbillion) and [@Rahmon](https://github.com/Rahmon).
* Documentation updates related to upcoming changes in 3.7.0. Props [@jeffpaul](https://github.com/jeffpaul).
* Documentation about how to search using rendered content (shortcodes and reusable blocks). Props [@johnbillion](https://github.com/johnbillion) and [@felipeelia](https://github.com/felipeelia).
* Autosuggest: filter results HTML by defining a `window.epAutosuggestItemHTMLFilter()` function in JavaScript. Props [@JakePT](https://github.com/JakePT).

__Changed:__

* Facets Widget presentation, replacing the `<input type="checkbox">` elements in option links with a custom `.ep-checkbox presentational` div. Props [@MediaMaquina](https://github.com/MediaMaquina), [@amesplant](https://github.com/amesplant), [@JakePT](https://github.com/JakePT), and [@oscarssanchez](https://github.com/oscarssanchez).
* Autosuggest: JavaScript is not loaded anymore when ElasticPress is indexing. Props [@fagiani](https://github.com/fagiani) and [@felipeelia](https://github.com/felipeelia).
* `Indexable\Post\Post::prepare_date_terms()` to only call `date_i18n()` once. Props [@WPprodigy](https://github.com/WPprodigy) and [@Rahmon](https://github.com/Rahmon).

__Removed:__

* Assets source mappings. Props [@Rahmon](https://github.com/Rahmon) and [@MadalinWR](https://github.com/MadalinWR).
* References to `posts_by_query` property and `spl_object_hash` calls. Props [@danielbachhuber](https://github.com/danielbachhuber) and [@Rahmon](https://github.com/Rahmon).

__Fixed:__

* GitHub issue templates. Props [@jeffpaul](https://github.com/jeffpaul).
* Facets: error in filters where terms wouldn't match if the user types a space. Props [@felipeelia](https://github.com/felipeelia).
* Facets: pagination parameters in links are now removed when clicking on filters. Props [@shmaltz](https://github.com/shmaltz), [@oscarssanchez](https://github.com/oscarssanchez), and [@Rahmon](https://github.com/Rahmon).
* Output of WP-CLI index errors. Props [@notjustcode-sp](https://github.com/notjustcode-sp) and [@felipeelia](https://github.com/felipeelia).
* `index_name` is transformed in lowercase before the index creation in Elasticsearch. Props [@teoteo](https://github.com/teoteo) and [@felipeelia](https://github.com/felipeelia).
* Validate that a meta_value is a recognizable date value before storing. Props [@jschultze](https://github.com/jschultze), [@moraleida](https://github.com/moraleida) and [@Rahmon](https://github.com/Rahmon).
* Array with a MIME type without the subtype in `post_mime_type` argument. Props [@ethanclevenger91](https://github.com/ethanclevenger91) and [@Rahmon](https://github.com/Rahmon).
* Sort for WP_User_Query. Props [@Rahmon](https://github.com/Rahmon).
* WP Acceptance Tests. Props [@felipeelia](https://github.com/felipeelia).
* Styling issue of Autosuggest and search block (WP 5.8). Props [@dinhtungdu](https://github.com/dinhtungdu).
* `Undefined variable: closed` notice in `Elasticsearch::update_index_settings()`. Props [@nickdaugherty](https://github.com/nickdaugherty) and [@pschoffer](https://github.com/pschoffer).
* Documentation for WP-CLI `*-feature` commands. Props [@felipeelia](https://github.com/felipeelia).
* Custom Results: a `current_user_can()` call now receives the post ID instead of the whole object. Props [@Sysix](https://github.com/Sysix).
* Autosuggest: adjust debounce to avoid sending unnecessary requests to the server. Props [@Rahmon](https://github.com/Rahmon).

__Security:__

* Updated browserslist and jsdoc versions. Props [@felipeelia](https://github.com/felipeelia).
* Updated lodash, hosted-git-info, ssri, rmccue/requests, and y18n versions. Props [@dependabot](https://github.com/dependabot).

= 3.5.6 - 2021-03-18 =
This release fixes some bugs and also adds some new actions and filters.

__Security Fix:__

* Updated JS dependencies. Props [@hats00n](https://github.com/hats00n) and [@felipeelia](https://github.com/felipeelia)

__Bug Fixes:__

* Fixed document indexing when running index command with nobulk option. Props [@Rahmon](https://github.com/Rahmon)
* Added an extra check in the iteration over the aggregations. Props [@felipeelia](https://github.com/felipeelia)
* Fixed no mapping found for [name.sortable] for Elasticsearch version 5. Props [@Rahmon](https://github.com/Rahmon)
* Fixed uninstall process to remove all options and transients. Props [@Rahmon](https://github.com/Rahmon)

__Enhancements:__

* Added missing inline JS documentation. Props [@JakePT](https://github.com/JakePT)
* Added the filter `ep_autosuggest_http_headers`. Props [@Rahmon](https://github.com/Rahmon)
* Added terms indexes to the status and stats WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* The Protected Content feature isn't auto-activated when using ElasticPress.io anymore. Props [@felipeelia](https://github.com/felipeelia)
* Added the new filter `ep_highlight_should_add_clause` to let developers decide where the highlight clause should be added to the ES query. Props [@felipeelia](https://github.com/felipeelia)
* Added the new filter `epwr_weight` and changed the default way scores are applied based on post date. Props [@Rahmon](https://github.com/Rahmon)

= 3.5.5 - 2021-02-25 =
This release fixes some bugs and also adds some new actions and filters.

__Bug Fixes:__

* Fix a problem in autosuggest when highlighting is not active. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon)
* Fix a wrong phrase in the Indexables documentation. Props [@jpowersdev](https://github.com/jpowersdev)
* Fix Facet Term Search for more than one Widget. Props [@goaround](https://github.com/goaround)
* Fix a Warning that was triggered while using PHP 8. Props [@Rahmon](https://github.com/Rahmon)

__Enhancements:__

* Add an `is-loading` class to the search form while autosuggestions are loading. Props [@JakePT](https://github.com/JakePT)
* Add the new `set-algorithm-version` and `get-algorithm-version` WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* Add a new `ep_query_weighting_fields` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott)
* Add two parameters to the `ep_formatted_args_query` filter. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott)
* Add the new `set-algorithm-version` and `get-algorithm-version` WP-CLI commands. Props [@felipeelia](https://github.com/felipeelia)
* Create a new section in documentation called `Theme Integration`. Props [@JakePT](https://github.com/JakePT)
* Improvements to contributing documentation and tests. Props [@jeffpaul](https://github.com/jeffpaul) and [@felipeelia](https://github.com/felipeelia)
* Add the following new actions: `ep_wp_cli_after_index`, `ep_after_dashboard_index`, `ep_cli_before_set_search_algorithm_version`, `ep_cli_after_set_search_algorithm_version`, `ep_after_update_feature`, `ep_cli_before_clear_index`, and `ep_cli_after_clear_index`. Props [@felipeelia](https://github.com/felipeelia) and [@Rahmon](https://github.com/Rahmon)

= 3.5.4 - 2021-02-11 =
This is primarily a security and bug fix release. PLEASE NOTE that versions 3.5.2 and 3.5.3 contain a vulnerability that allows a userto bypass the nonce check associated with re-sending the unaltered default search query to ElasticPress.io that is used for providing Autosuggest queries. If you are running version 3.5.2. or 3.5.3 please upgrade to 3.5.4 immediately.

__Security Fix:__

* Fixed a nonce check associated with updating the default Autosuggest search query in ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia)

__Bug Fixes:__

* Fix broken click on highlighted element in Autosuggest results. Props [@felipeelia](https://github.com/felipeelia)
* Properly cast `from` parameter in `$formatted_args` to an integer to prevent errors if empty. Props [@CyberCyclone](https://github.com/CyberCyclone)

__Enhancements:__

* Add an `ep_is_facetable` filter to enable custom control over where to show or hide Facets. Props [@moraleida]
* Improvements to contributing documentation and tests. Props [@jeffpaul](https://github.com/jeffpaul) and [@felipeelia](https://github.com/felipeelia)

= 3.5.3 - 2021-01-28 =
This is a bug fix release.

__Bug Fixes:__

* Fixed a bug where the `ep-synonym` post type is updated to a regular post, which can cause it to be accidentally deleted. Props [@Rahmon](https://github.com/Rahmon)
* Fixed CSS formatting issues in the Settings and Features menus. Props [@Rahmon](https://github.com/Rahmon)

= 3.5.2 - 2021-01-18 =
This is a bug fix release.

__Bug Fixes:__

* Fixed a typo in elasticpress.pot. Props [@alexwoollam](https://github.com/alexwoollam)
* Don’t use timestamps that cause 5 digit years. Props [@brandon-m-skinner](https://github.com/brandon-m-skinner)
* Fix admin notice on the Synonyms page. Props [@Rahmon](https://github.com/Rahmon)
* Properly update slider numbers while sliding. Props [@Rahmon](https://github.com/Rahmon)
* Properly handle error from `get_terms()`. Props [@ciprianimike](https://github.com/ciprianimike)
* Fix incorrect titles page. Props [@Rahmon](https://github.com/Rahmon)
* Fix linting tests. Props [@felipeelia](https://github.com/felipeelia)
* Fix issue with price filter unsetting previous query. Props [@oscarssanchez](https://github.com/oscarssanchez)

__Enhancements:__

* Added actions that fire after bulk indexing (`ep_after_bulk_index`), in event of an invalid Elasticsearch response (`ep_invalid_response`), and before object deletion (`ep_delete_{indexable slug}`); added filters `ep_skip_post_meta_sync`, `pre_ep_index_sync_queue`, `ep_facet_taxonomies_size`, `epwr_decay_function`, `and epwr_score_mode`. Props [@brandon-m-skinner](https://github.com/brandon-m-skinner)
* Added `ep_filesystem_args` filter. Props [@pjohanneson](https://github.com/pjohanneson)
* Add SKU field to Weighting Engine if WooCommerce is active and fix issue with overriding `search_fields`. Props [@felipeelia](https://github.com/felipeelia)
* Support `author__in` and `author__not_in` queries. Props [@dinhtungdu](https://github.com/dinhtungdu)
* Update multiple unit tests. Props [@petenelson](https://github.com/petenelson)
* Show CLI indexing status in EP dashboard. Props [@Rahmon](https://github.com/Rahmon)
* Add `ep_query_send_ep_search_term_header` filter and don’t send `EP-Search-Term` header if not using ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia)

= 3.5.1 - 2020-10-29 =
A bug fix release.

__Bug fixes:__

* Fixes highlighting so that full content is returned instead of only snippets.
* Fix empty synonym bug.
* Only highlight post content, excerpt, and title.

__Enhancements:__

* Track CLI index in a headless fashion

= 3.5.0 - 2020-10-20 =
Version 3.5 is a very exciting release as it contains two major new features: a synonym dashboard and search term result highlighting. The synonym dashboard empowerers users to create synonym lists for searches. For example. searching "New York City" would return contain with "NYC". Search term highlighting will underline and add a CSS class to keywords within content that matches the current search.

The new version also includes a revamp of the search algorithm. This is a backwards compatibility break. If you'd like to revert to the old search algorithm, you can use the following code: `add_filter( 'ep_search_algorithm_version', function() { return '3.4'; } );`. The new algorithm offers much more relevant search results and removes fuzziness which results in mostly unwanted results for most people. If you are hooking in and modifying the search query directly, it's possible this code might break and you might need to tweak it.

__Bug fixes:__

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

__Enhancements:__

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

= 3.4.3 - 2020-07-21 =
__Enhancements:__

* Remove jQuery from front end JavaScript dependencies.

__Bug Fixes:__

* Fix accessibility bug on autosuggest.
* Fix broken facet search.

= 3.4.2 - 2020-06-17 =
__Bug fixes:__

* uninstall.php: Change the EP_FILE const to its value. Props [felipeelia](https://github.com/felipeelia).
* Fix list features WP CLI command. Props [felipeelia](https://github.com/felipeelia).
* Add `rel="nofollow"` to facet links. Props [mlaroy](https://github.com/mlaroy).
* Facets widget: Move <div> outside ob_start(). Props [kallehauge](https://github.com/kallehauge).
* Load facet scripts and styles only when they are really necessary. Props [goaround](https://github.com/goaround).
* Index attachments with Protected Content and query for them in media search. Props [oscarsanchez](https://github.com/oscarsanchez).
* Fixed `Deprecated field [include] used, expected [includes] instead.`. Props [dinhtungdu](https://github.com/dinhtungdu).

__Enhancements:__

* Add filter for enabling sticky posts.  Props [shadyvb](https://github.com/shadyvb).
* Add sync kill filter. Props [barryceelen](https://github.com/barryceelen).
* Add timeout filters for bulk_index and index_document. Props [@oscarsanchez](https://github.com/oscarsanchez).

= 3.4.1 - 2020-3-31 =
* Make weighting dashboard flex containers to prevent the slider from changing size. Props [@mlaroy](https://github.com/mlaroy).
* Fix issue where weightings wouldn't save properly for certain post types. Props [mustafauysal](https://github.com/mustafauysal).
* Fix bug where terms wouldn't finish syncing in certain scenarios.
* Properly order WooCommerce products using double to account for decimals. Props [@oscarsanchez](https://github.com/oscarsanchez).
* Show current indices in index health dashboard. Props [moraleida](https://github.com/moraleida).

= 3.4.0 - 2020-03-03 =
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

= 3.3.0 - 2018-12-18 =
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

= 3.2.6 - 2019-11-27 =
This is a bugfix release

* Under some edge conditions content for autosuggest can be large - don't cache it

= 3.2.5 - 2019-11-20 =
This is a bug fix version.

* Fix WP <5.0 fatal error on register_block_type.

= 3.2.4 - 2019-11-19 =
This is a bug fix version.

* Fix Gutenberg block initialization
* Fix Autosuggest: remove filter with proper priority in query generation. Props [Maxdw](https://github.com/Maxdw).
* Fix Autosuggest: returning WP_Error for non object cache autosuggest queries causes issue. Fallback to transient

= 3.2.3 - 2019-11-13 =
This is a bug fix version.

* Ensure query building for Autosuggest does not fallback to WPDB.

= 3.2.2 - 2019-11-05 =
This is a bug fix version with some feature additions.

* Fix PHPCS errors. Props [mmcachran](https://github.com/mmcachran)
* Fix ensuring stats are built prior to requesting information
* Fix related post block enqueue block assets on the frontend
* Fix custom order results change webpack config for externals:lodash
* Fix don't overwrite search fields
* Autosuggest queries generated though PHP instead of JavaScript
* Add WP Acceptance tests
* Add new WP-CLI commands: get_indexes and get_cluster_indexes

= 3.2.1 - 2019-10-14 =
This is a bug fix version.

* Fix Gutenberg breaking issue with Related Posts and image blocks. Props [adamsilverstein](https://github.com/adamsilverstein)

= 3.2.0 - 2019-10-08 =
ElasticPress 3.2 is a feature release. We've added quite a few useful features including an index health page, the ability to control which sites are indexed in a network activated multisite setup, a related posts Gutenberg block, and more.

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

= 3.1.4 - 2019-08-28 =
Version 3.1.4 is a bug fix release.

See fixes: https://github.com/10up/ElasticPress/pulls?q=is%3Apr+milestone%3A3.1.4+is%3Aclosed

= 3.1.3 - 2019-08-22 =
This is a bug fix release.

* Check wpcli transient before integrating with queries
* Fix version comparison bug when comparing Elasticsearch versions
* Use proper taxonomy name for WooCommerce attributes.
* Increase Elasticsearch minimum supported version to 5.0
* Fix product attribute archives

= 3.1.2 - 2019-08-16 =
This is a bug fix release with some filter additions.

* Add ep_es_query_results filter.
* Add option to sync prior to shutdown.
* Readme update around WPCLI post syncing. Props [@mmcachran](https://github.com/mmcachran)
* Ignore sticky posts in `find_related`. Props [@columbian-chris](https://github.com/columbian-chris)
* Weighting dashboard fixes around saving. [@oscarsanchez](https://github.com/oscarsanchez)
* Weighting UI improvements. Props [@mlaroy](https://github.com/mlaroy)

= 3.1.1 - 2019-07-25 =
* Ensure taxonomies that are shared among multiple post types show up on the weighting screen

= 3.1.0 - 2019-07-22 =
* Support for nested tax queries. Props [@dkotter](https://github.com/dkotter)
* `ep_bulk_index_action_args` filter. Props [@fabianmarz](https://github.com/fabianmarz)
* Add filters to control MLT related posts params.
* `ep_allow_post_content_filtered_index` filter to bypass filtered post content on indexing.
* Weighting dashboard to control weights of specific fields on a per post type basis
* Search ordering feature. Enables custom results for specific search queries.
* Refactor admin notice, admin screen "resolver", and install path logic
* WordPress.org profile
* New EP settings interface. Props [@dkoo](https://github.com/dkoo)
* Delete pagination from facet URL.
* allows WooCommerce product attributes to be facetable in 3.0
* Autosuggest queries now match the search queries performed by WordPress, including weighting and any custom results
* Fix data escaping in WP 4.8.x
* Support order by "type"/"post_type" in EP queries
* Properly redirect after network sync
* User mapping for pre 5.0 Props [@mustafauysal](https://github.com/mustafauysal)
* Avoid multiple reflows in autosuggest. Props [@fabianmarz](https://github.com/fabianmarz)
* 400 error when popularity is default sorting.
* Fixed Facet widget not rendering WC product attribute options. Props [@fabianmarz](https://github.com/fabianmarz)
* Delete wpcli sync option/transient when an error occurs
* Create index/network alias when adding a new site on a network activated installation. Props [@elliott-stocks](https://github.com/elliott-stocks)
* Fix WooCommerce order search when WooCommerce module activated but protected content turned off.

= 3.0.3 - 2019-06-04 =
* Pass $post_id twice in ep_post_sync_kill for backwards compatibility. Props [aaemnnosttv](https://github.com/aaemnnosttv)
* Add `ep_search_request_path` filter for backwards compant.
* Add `ep_query_request_path` filter for modifying the query path.
* Fix missing action name in post query integration.
* Properly add date filter to WP_Query.

= 3.0.2 - 2019-05-23 =
3.0.2 is a minor bug release version. Here is a list of fixes:

* Fix date query errors
* Readd ep_retrieve_the_{type} filter. Props [gassan](https://github.com/gassan)
* Fix empty autosuggest selector notice

= 3.0.1 - 2019-05-20 =
3.0.1 is a minor bug release version. Here is a list of fixes:

* `wp elasticpress stats` and `wp elasticpress status` commands fatal error fixed.
* Add autosuggest selector field default to fix notice.
* Re-add `ep_find_related` as deprecated function.
* Changed max int to use core predefined constant. Props [@fabianmarz](https://github.com/fabianmarz)
* Properly support legacy feature registration callbacks per #1329.
* Properly disable settings as needed on dashboard.
* Don't force document search on REST requests.

= 3.0 - 2019-05-13 =
NOTICE: Requires re-index.

3.0 is a refactor of ElasticPress for modern coding standards (PHP 5.4 required) as well as the introduction to indexables. Indexables abstracts out content types so data types other than post can be indexed and searched. 3.0 includes user indexing and search (integration with WP_User_Query). User features require at least WordPress version 5.1.

The refactor changes a lot of ElasticPress internals. The biggest change is the feature registration API has completely changed. Now, new features should extend the `ElasticPress\Feature` class rather than calling `ep_register_feature`. Older features should be backwards compatible.

Other Features:
* Elasticsearch language setting in admin

Here are a list of filters/actions removed or changed:

### Actions Removed:

* `ep_feature_setup`

### Filters changed:

* `ep_post_sync_kill` - Removed `$post_args` argument.

### Other changes:

* `posts-per-page` changed to `per-page` for WP-CLI index command.

= 2.8.2 - 2019-03-26 =
* Enhancement: WooCommerce product attributes as facets.
* Enhancement: Performance Boost for document indexing.
* Bugfix for issue on WP REST API searches.
* Bugfix for case-sensitivity issue with facet search.

= 2.8.1 - 2019-02-13 =
* Bugfix for homepage out of chronological order.
* Bugfix for missing meta key. (Props [turtlepod](https://github.com/turtlepod))
* Bugfix for bulk indexing default value on settings page.

= 2.8.0 - 2019-02-08 =
ElasticPress 2.8 provides some new enhancements and bug fixes.

* Sticky posts support.
* Meta LIKE query adjustment.
* Autosuggest bugfix.
* Autosuggest to abide by plugin settings.
* WooCommerce searches with custom fields.
* Adjustment to `wp elasticpress status`
* Add Elasticsearch version in settings. (Props [turtlepod](https://github.com/turtlepod))
* Allow user to set number of posts during bulk indexing cycle.
* Facet query string customization (Props [ray-lee](https://github.com/ray-lee))
* Removal of logic that determines if blog is public / indexable. (Resolves sync issue.)
* Fix for auto activating sync notices. (Props [petenelson](https://github.com/petenelson))
* Removal of date weighting for protected content admin queries.
* Protected content: filtering of filtered post types.
* Implemented --post-ids CLI option to index only specific posts. (Props [dotancohen](https://github.com/dotancohen))

= 2.7.0 - 2018-12-06 =
NOTICE: Requires re-index

ElasticPress 2.7 provides some new enhancements and bug fixes.

* Prevent indexing when blog is deleted or not public.
* Do not apply absint to comment_status.
* ElasticPress.io credentials bugfix.
* Related posts bugfix.
* Random WooCommerce ordering allowed.
* Query only post IDs when indexing. (Props [elliott-stocks](https://github.com/elliott-stocks))
* Better error notices. (Props [petenelson](https://github.com/petenelson))

= 2.6.1 - 2018-08-24 =
* Resolves issue of missing file for wp-cli.

= 2.6.0 - 2018-08-22 =
ElasticPress 2.6 provides some new enhancements and bug fixes.

* Ability to set autosuggest endpoint by a constant (EP_AUTOSUGGEST_ENDPOINT).
* Enable WooCommerce products to be included in autosuggest results.
* Support for tax_query operators EXISTS and NOT EXISTS.
* Addition of new filter to change default orderby/sort (ep_set_default_sort).
* Do not search for author_name when searching products in WooCommerce.

= 2.5.2 - 2018-05-09 =
NOTICE: Requires re-index.

This is a small bug fix release.

* Removed unnecessary facet JavaScript
* Fix facet aggregations warning

= 2.5.1 - 2018-05-02 =
NOTICE: Requires re-index.

This if a bug fix release. This version requires a re-index as we change the way data is being sent to Elasticsearch.

It's also worth noting for ElasticPress version 2.5+, the Facets feature, which is on by default, will run post type archive and search page main queries through Elasticsearch. If Elasticsearch is out of sync with your content (possible in rare edge cases), this could result in incorrect content being shown. Turning off Facets would fix the problem.

### Bug Fixes

* Don't pre-strip HTML before sending it to Elasticsearch.
* Support PHP 5.2 backwards compat.
* Don't show faceting widget if post type doesn't support taxonomy.

= 2.5 - 2018-04-23 =
ElasticPress 2.5 includes a new Facets feature that makes it easy to add high performance content filtering controls to a website.

A new Facets widget enables site administrators to add taxonomy facets to a sidebar (or any widgetized area). When viewing a content list on the front end of the website, the widget will display the name of the taxonomy – e.g. “Categories” – and a checklist with all of its terms. Visitors can narrow down content by selecting terms they are interested in. The Facets feature can be globally configured to narrow results to content that is tagged with any or all of the selected terms. The widget’s front end output contains carefully named CSS classes, so that designers and developers can apply unique styling.

Version 2.5 also includes a number of smaller enhancements and fixes, including official support for Elasticsearch 6.2, and increased functional parity with the WP_Query API.

Here is a detailed list of what's been included in the release:

### Enhancements
* Facets feature
* `--post-ids` CLI option to index only specific posts. Props [dotancohen](https://github.com/dotancohen).
* Filter for hiding host setting in dashboard. Props [tomdxw](https://github.com/tomdxw).
* Support `WP_Query` meta query `not between` comparator.

### Bugs
* Disallow duplicated Elasticsearch requests on WooCommerce orders page. Props [lukaspawlik](https://github.com/lukaspawlik)
* Fix taxonomy sync object warning. Props [eugene-manuilov](https://github.com/eugene-manuilov)
* `true` in `is_empty_query` terminates ep_query process when it shouldn't. Props [yaronuliel](https://github.com/yaronuliel)

= 2.4.2 - 2018-01-31 =
Version 2.4.2 is a bug fix version.

* Fix related posts not showing up bug.

= 2.4.1 - 2018-01-30 =
Version 2.4.1 is a bug fix and maintenance release. Here are a listed of issues that have been resolved:

* Support Elasticsearch 6.1 and properly send Content-Type header with application/json. Props [eugene-manuilov](https://github.com/eugene-manuilov).
* Fix autosuggest event target issue bug. Props [eugene-manuilov](https://github.com/eugene-manuilov).
* Fix widget init bug. Props [eugene-manuilov](https://github.com/eugene-manuilov).
* Fix taxonomy sync parameter warning. Props [eugene-manuilov](https://github.com/eugene-manuilov).
* Increase maximum Elasticsearch compatibility to 6.1

= 2.4 - 2017-11-01 =
Version 2.4 introduces the Autosuggest feature. When enabled, input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As text is entered into the search field, suggested content will appear below it, based on top search results for the text. Suggestions link directly to the content.

We also added hooks and filters to ElasticPress that make query logging possible. The [Debug Bar ElasticPress](https://github.com/10up/debug-bar-elasticpress) plugin now adds a Query Log screen to the ElasticPress admin menu. The Query Log is an extremely powerful tool for diagnosing search and indexing issues.

Here is a comphrensive list of changes:

### Enhancements
* Autosuggest feature
* Hooks for query log functionality in [Debug Bar ElasticPress](https://github.com/10up/debug-bar-elasticpress)
* Support `WP_Query` `fields` parameter. Props [kallehauge](https://github.com/kallehauge).
* Add setting for enabling/disabling date weighting in search. Props [lukaspawlik](https://github.com/kallehauge).
* Remove extra post meta storage key from Elasticsearch
* Add shipping class as indexed WooCommerce taxonomy. Props [kallehauge](https://github.com/kallehauge).
* Allow WooCommerce orders to be searched by items. Props [kallehauge](https://github.com/kallehauge).
* Support Elasticsearch 5.6
* Add filter to granularly control admin notices. Props [mattonomics](https://github.com/mattonomics).
* Support ES 5.5+ strict content type checking. Props [sc0ttclark](https://github.com/sc0ttclark)

### Bug Fixes
* Fix `author_name` search field. Props [ivankristianto](https://github.com/ivankristianto).
* Fix unavailable taxonomy issue in WooCommerce. Props [ivankristianto](https://github.com/ivankristianto).
* Index all publicly queryable taxonomies. Props [allan23](https://github.com/allan23).
* Resolve case insensitive sorting issues. Props [allan23](https://github.com/allan23).
* Add escaping per VIP standards. Props [jasonbahl](https://github.com/jasonbahl).
* Fix WooCommerce post type warnings.

= 2.3.1, 2.3.2 - 2017-06-30=
Version 2.3.1-2.3.2 is a bug fix release. Here are a listed of issues that have been resolved:

* Cache ES plugins request. This is super important. Instead of checking the status of ES on every page load, do it every 5 minutes. If ES isn't available, show admin notification that allows you to retry the host.
* Fix broken upgrade sync notification.
* Properly respect WC product visibility. Props [ivankristianto](https://github.com/ivankristianto). This requires a re-index if you are using the WooCommerce feature.

= 2.3 - 2017-05-26 =
Version 2.3 introduces the Documents feature which indexes text inside of popular file types, and adds those files types to search results. We've also officially added support for Elasticsearch 5.3.

### Enhancements
* Documents feature
* Enable multiple feature status messages
* Disable dashboard sync via constant: `define( 'EP_DASHBOARD_SYNC', false );`. Props [rveitch](https://github.com/rveitch).
* Add filter for custom WooCommerce taxonomies. Props [kallehauge](https://github.com/kallehauge).
* Support WooCommerce `product_type` taxonomy. Props [kallehauge](https://github.com/kallehauge).

### Bug Fixes
* Fix WP-CLI `--no-bulk` number of posts indexed message. Props [ivankristianto](https://github.com/ivankristianto).
* Honor `ep_integrate` in WooCommerce queries. Props [ivankristianto](https://github.com/ivankristianto).
* Properly check when ES results are empty. Props [lukaspawlik](https://github.com/lukaspawlik)
* Incorrect `found_posts` set in query when ES is unavailable. Props [lukaspawlik](https://github.com/lukaspawlik)

= 2.2.1 - 2017-03-30 =
Version 2.2.1 is a bug fix release. Here are a listed of issues that have been resolved:

* Fix dashboard syncing delayed start issues.
* If plugins endpoint errors, try root endpoint to get the ES version.
* Make sure orderby is correct for default WooCommerce sorting. Props [ivankristianto](https://github.com/ivankristianto).
* Remove operator=>AND unneed execution code.
* Stop dashboard sync if error occurs in the middle. Props [ivankristianto](https://github.com/ivankristianto).
* Add `EP_INDEX_PREFIX` constant. If set, index names will be prefixed with the constant. Props [allan23](https://github.com/allan23).
* Increase total field limit to 5000 and add filter. Props [ssorathia](https://github.com/ssorathia).
* Prevent EP from auto-activating a feature that was force deactivated
* Prevent massive field Elasticsearch error when indexing large strings
* Increase max result window size to 1000000 and add filter.

= 2.2 - 2017-02-28 =
NOTICE: Requires re-index.

Version 2.2 rethinks the module process to make ElasticPress a more complete query engine solution. Modules are now auto-on and really just features. Why would anyone want to not use amazing functionality that improves speed and relevancy on their website? Features (previously modules) can of course be overridden and disabled. Features that don't have their minimum requirements met, such as a missing plugin dependency, are auto-disabled.

We've bumped the minimum Elasticsearch version to 1.7 (although we strongly recommend 2+). The maximum tested version of Elasticsearch is version 5.2. If you are running Elasticsearch outside this version range, you will see a warning in the dashboard.

### Enhancements
* __(Breaking change)__ Module registration API changed. See `register_module` in `classes/class-ep-modules.php`.
* __(Breaking change)__ Related posts are now in a widget instead of automatically being appending to content.
* __(Breaking change)__ Admin module renamed to Protected Content.
* Admin warning if current Elasticsearch version is not between the min/max supported version. Version 2.2 supports versions 1.3 - 5.1.
* Auto-reindex on versions requiring reindex.
* User friendly admin notifications for ElasticPress not set up, first sync needed, and feature auto activation.
* Protected Content feature applies to all features. This means if Protected Content isn't active, search or WooCommerce integration won't happen in the admin.
* Add support for post_mime_type. Props [Ritesh-patel](https://github.com/Ritesh-patel)

### Bug Fixes
* Back compat with old `ep_search` function.
* Respect indexable post types in WooCommerce feature
* New product drafts not showing in WooCommerce admin list
* WooCommerce feature breaking image search in media library. Props [Ritesh-patel](https://github.com/Ritesh-patel)
* WooCommerce order search broken
* Stop the insansity made private. Props [sc0ttclark](https://github.com/sc0ttclark)
* Fix multidimensional meta queries. Props [Ritesh-patel](https://github.com/Ritesh-patel).
* Properly show bulk index errors in WP-CLI
* Update ep_delete_post, include $post_type argument. Props [Ritesh-patel](https://github.com/Ritesh-patel)
* Fix post_type product getting set in any WP_Query if tax_query is provided in WooCommerce feature. Props [Ritesh-patel](https://github.com/Ritesh-patel)
* Adds 'number' param to satisfy WP v4.6+ fixing get_sites call. Props [rveitch](https://github.com/rveitch)
* Order by proper relevancy in WooCommerce product search. Props [ivankristianto](https://github.com/ivankristianto)
* Fix recursion fatal error due to oembed discovery during syncing. Props [ivankristianto](https://github.com/ivankristianto)

= 2.1.2 - 2016-11-11 =
NOTICE: Requires re-index.

* Separate mapping for ES 5.0+
* Fix some unit tests

= 2.1.1 - 2016-09-29 =
* Fix PHP 5.3 errors
* Properly show syncing button module placeholder during sync

= 2.1 - 2016-09-20 =
* Redo UI
* Make plugin modular
* Remove unnecessary back up hosts code
* Bundle existing modules into plugin
* Support `meta_key` and `meta_value`
* Order by `meta_value_num`
* Properly support `post_parent = 0`. Props [tuanmh](https://github.com/tuanmh)
* Add search scope file. Props [rveitch](https://github.com/rveitch)
* Support WP_Query `post_status`. Props [sc0ttclark](https://github.com/sc0ttkclark)

### Backward compat breaks:
* Move ep_admin_wp_query_integration to search integration only. EP integration by default is available everywhere.
* Remove `keep alive` setting
* Remove setting to integrate with search (just activate the module instead)
* Back up hosts code removed
* Remove active/inactive state. Rather just check if an index is going on our not.

### Bug fixes
* Fix `post__in` support
* Fix `paged` overwriting `offset`
* Fix integer and comma separated string `sites` WP_Query processing. Props [jaisgit](https://github.com/jaisgit).

= 2.0.1 - 2016-08-02 =
### Bug fixes
* Don't load settings on front end. This fixes a critical bug causing ElasticPress to check the Elasticsearch connection on the front end.

= 2.0 - 2016-06-01 =
10up ships ElasticPress 2.0 with __radical search algorithm improvements__ and a __more comprehensive integration of WP_Query__. ElasticPress is now even closer to supporting the complete WP_Query API. This version also improves upon post syncing ensuring that post meta updates are synced to Elasticsearch, adds a number of important hooks, and, of course, fixes some pesky bugs.

### Enhancements
* Radical search algorithm improvements for more relevant results (see [#508](https://github.com/10up/ElasticPress/pull/508) for details)
* Support meta `BETWEEN` queries.
* Improve GUI by disabling index status meta box text and improving instructions. Props [ivanlopez](https://github.com/ivanlopez)
* Support `OR` relation for tax queries.
* Sync post to Elasticsearch when meta is added/updated.
* Support all taxonomies as root WP_Query arguments. Props [tuanmh](https://github.com/tuanmh)
* Add `ID` field to Elasticsearch mapping
* Support `post_parent` WP_Query arguments. Props [tuanmh](https://github.com/tuanmh)
* Add filter to disable printing of post index status. Props [tuanmh](https://github.com/tuanmh)
* Add useful CLI hooks
* Add a filter to bypass permission checking on sync (critical for front end updates)

### Bugs
* Consider all remote request 20x responses as successful. Props [tuanmh](https://github.com/tuanmh)
* Fix plugin localization. Props [mustafauysal](https://github.com/mustafauysal)
* Do query logging by default. Props [lukaspawlik](https://github.com/lukaspawlik)
* Fix cannot redeclare class issue. Props [tuanmh](https://github.com/tuanmh)
* Fix double querying Elasticsearch by ignoring `category_name` when `tax_query` is present.
* Fix post deletion endpoint URL. Props [lukaspawlik](https://github.com/lukaspawlik)

A special thanks goes out to [Tuan Minh Huynh](https://github.com/tuanmh) and everyone else for contributions to version 2.0.

= 1.9.1 - 2016-05-23 =
Quick bug fix version to address the GUI not working properly when plugin is not network enabled within multisite. Props to [Ivan Lopez](https://github.com/ivanlopez)

= 1.9 - 2016-05-17 =
ElasticPress 1.9 adds in an admin UI, where you can set your Elasticsearch Host and run your index command, without needing to us WP-CLI. Version 1.9 also adds in some performance improvements to reduce memory consumption during indexing. Full list of enhancements and bug fixes:

### Enhancements:
* Add in an Admin GUI to handle indexing. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* Add option to not disable ElasticPress while indexing. Props [lukaspawlik](https://github.com/lukaspawlik).
* Allow filtering of which post types we want to search for. Props [rossluebe](https://github.com/rossluebe).
* Remove composer.lock from the repo. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* Ensure both PHPUnit and WP-CLI are available in the development environment. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* User lower-case for our composer name, so packagist can find us. Props [johnpbloch](https://github.com/johnpbloch).
* Check query_vars, not query to determine status. Props [ChrisWiegman](https://github.com/ChrisWiegman).
* Improve memory usage during indexing and fix unnecessary cache flushes. Props [cmmarslender](https://github.com/cmmarslender).
* Further reduce memory usage during indexing. Props [lukaspawlik](https://github.com/lukaspawlik).
* Add post__in and post__not_in documentation. Props [mgibbs189](https://github.com/mgibbs189).
* Add Elasticsearch Shield authentication headers if constant is set. Props [rveitch](https://github.com/rveitch).

### Bugs:
* Fix the --no-bulk indexing option. Props [lukaspawlik](https://github.com/lukaspawlik).
* Fixed an error that occurs if no Elasticsearch host is running. Props [petenelson](https://github.com/petenelson).
* Fixed an exception error. Props [dkotter](https://github.com/dkotter).
* Fixed the WP-CLI status command. Props [dkotter](https://github.com/dkotter).

= 1.8 - 2016-01-19 =
NOTICE: Mapping change, requires re-index.

ElasticPress 1.8 adds a bunch of mapping changes for accomplishing more complex WP_Query functions such as filtering by term id and sorting by any Elasticsearch property. Version 1.8 also speeds up post syncing dramatically through non-blocking queries. Full list of enhancements and bug fixes:

### Enhancements:
* Add a filter around the search fuzziness argument. Props [dkotter](https://github.com/dkotter).
* Make post indexing a non-blocking query. Props [cmmarslender](https://github.com/cmmarslender).
* Log queries for debugging. Makes [ElasticPress Debug Bar](https://github.com/10up/debug-bar-elasticpress) plugin possible.
* Make `posts_per_page = -1` possible.
* Support term id and name tax queries.
* Add raw/sortable to property to term mapping. Props [sc0ttkclark](https://github.com/sc0ttkclark)
* Add raw/sortable property to meta mapping. Props [sc0ttkclark](https://github.com/sc0ttkclark)
* Add raw/sortable to author display name and login

### Bugs:
* Fix post deletion. Props [lukaspawlik](https://github.com/lukaspawlik).
* Properly flush cache with `wp_cache_flush`. Props [jstensved](https://github.com/jstensved)
* When directly comparing meta values in a meta query, use the `raw` property instead of `value`.
* Support arbitrary document paths in orderby. Props [sc0ttkclark](https://github.com/sc0ttkclark).

= 1.7 - 2015-12-11 =
NOTICE: Mapping change, requires re-index.

ElasticPress 1.7 restructures meta mapping for posts for much more flexible meta queries. The `post_meta` Elasticsearch post property has been left for backwards compatibility. As of this version, post meta will be stored in the `meta` Elasticsearch property. `meta` is structured as follows:

* `meta.value` (string)
* `meta.raw` (unanalyzed string)
* `meta.long` (unanalyzed number)
* `meta.double` (unanalyzed number)
* `meta.boolean` (unanalyzed number)
* `meta.date` (unanalyzed yyyy-MM-dd date)
* `meta.datetime` (unanalyzed yyyy-MM-dd HH:mm:ss datetime)
* `time` (unanalyzed HH:mm:ss time)

When querying posts, you will get back `meta.value`. However, if you plan to mess with the new post mapping, it's important to understand the intricacies.

The real implications of this is in `meta_query`. You can now effectively search by meta types. See the new section in README.md for details on this.

1.7 also contains the following bugs/enhancements:

* (Bug) Prevent missed post indexing when duplicate post dates. Props [lukaspawlik](https://github.com/lukaspawlik)
* (Bug) Complex meta types are automatically serialized upon storage.
* (Enhancement) Index posts according to post type. Props [sc0ttkclark](https://github.com/sc0ttkclark)

= 1.6.2 - 2015-11-12 =
NOTICE: Mapping change, requires re-index.

ElasticPress 1.6.2 fixes ALL backwards compatibility issues with Elasticsearch 2.0:

* Removes `fuzzy_like_this` query and uses `multi_match` instead.
* Uses string instead of array for post type term when there is only one term.

= 1.6.1 - 2015-11-09 =
NOTICE: Mapping change, requires re-index.

ElasticPress 1.6.1 fixes mapping backwards compatibility issues with Elasticsearch 2.0:

* Removes the fields field type from object typed fields as they should be called properties.
* Remove path from object field types.

= 1.6 - 2015-08-31 =
ElasticPress 1.6 contains a number of important enhancements and bug fixes. Most notably, we now support Elasticsearch fallback hosts and the indexing of attachments.

### Bug Fixes:
* Clean up PHP Code Sniffer errors. Props [chriswiegman](https://github.com/chriswiegman)
* Properly document Elasticsearch version
* Abide by `exclude_from_search` instead of `public` when indexing post types. Props [allan23](https://github.com/allan23) and [ghosttoast](https://github.com/ghosttoast).
* Allow posts to be indexed with invalid date values. Props [tuanmh](https://github.com/tuanmh)
* Support `ep_post_sync_kill` filter in bulk indexing. Props [Stayallive](https://github.com/Stayallive)

### Enhancements:
* Add blog id to `ep_index_name` filter. Props [kovshenin](https://github.com/kovshenin)
* Support post caching in search
* Add recursive term indexing for heirarchal taxonomies. Props [tuanmh](https://github.com/tuanmh)
* Enable indexing of attachments
* Support fallback hosts in case main EP host is unavailable. Props [chriswiegman](https://github.com/chriswiegman)
* Add `ep_retrieve_the_post` filter to support relevancy score manipulation. Props [matthewspencer](https://github.com/matthewspencer)
* Make search results filterable. Props [chriswiegman](https://github.com/chriswiegman)

= 1.5.1 - 2015-07-06 =
### Bug Fixes:
* Prevent notices from being thrown when non-existent index properties are accessed. This was happening for people how upgraded to 1.5 without doing a re-index. Props [allan23](https://github.com/allan23)

= 1.5 - 2015-06-25 =
### Bug Fixes:
* Prevent direct access to any PHP files. Props [joelgarciajr84](https://github.com/joelgarciajr84)
* Fixed fields not being loaded from ES. Props [stayallive](https://github.com/stayallive)
* Fixed inclusive check in date_query integration. Props [EduardMaghakyan](https://github.com/EduardMaghakyan)

### Enhancements:
* Add support for category_name WP_Query parameter. Props [ocean90](https://github.com/ocean90)
* Support limiting sites in network wide commands. Props [bordoni](https://github.com/bordoni)
* Add support for method to un-integrate WP_Query. Props [kingkool68](https://github.com/kingkool68)
* Support `cache_results` in WP_Query
* Add action prior to starting WP-CLI index command
* Add missing headers to WP_CLI commands. Props [chriswiegman](https://github.com/chriswiegman)
* Improve error reporting in bulk indexing during bad ES requests.
* Fix is_search check notice. Props [allenmoore](https://github.com/allenmoore) and [allan23](https://github.com/allan23)
* Added a filter to modify request headers. Props [tuanmh](https://github.com/tuanmh)
* Prevent bulk index from sending useless error emails. Props [cmmarslender](https://github.com/cmmarslender)
* Add --offset parameter to cli indexing command. [Stayallive](https://github.com/stayallive)
* Change the syncing hook to play better with plugins. Props [jonathanbardo](https://github.com/jonathanbardo)
* Support like query in post meta. Props [tuanmh](https://github.com/tuanmh)
* Sanitization fixes for PHPCS. Props [mphillips](https://github.com/mphillips)
* Added filter to set default sort order. Props [HKandulla](https://github.com/HKandulla)
* MySQL DB completely removed from integrated ElasticPress WP Query. Props [EduardMaghakyan](https://github.com/EduardMaghakyan) and [crebacz](https://github.com/crebacz)

= 1.4 - 2015-05-18 =
### Bug Fixes:
* Duplicate sync post hooks separated. Props [superdummy](https://github.com/superdummy)
* Don't send empty index error emails. Props [cmmarslender](https://github.com/cmmarslender)
* Remove default shard and indices configuration numbers but maintain backwards compatibility. Props [zamoose](https://github.com/zamoose)
* Fix wrong author ID in post data. Props [eduardmaghakyan](https://github.com/eduardmaghakyan)

### Enhancements:
* `date_query` and date parameters now supported in WP_Query. Props [joeyblake](https://github.com/joeyblake) and [eduardmaghakyan](https://github.com/eduardmaghakyan)
* Make all request headers filterable
* Add EP API key to all requests as a header if a constant is defined. Props [zamoose](https://github.com/zamoose)
* Add index exists function; remove indexes on blog deletion/deactivation. Props [joeyblake](https://github.com/joeyblake)
* Refactor wp-cli stats for multisite. Props [jaace](https://github.com/jaace)
* Index mappings array moved to separate file. Props [mikaelmattsson](https://github.com/mikaelmattsson)
* Support meta inequality comparisons. Props [psorensen](https://github.com/psorensen)

= 1.3.1 - 2015-04-09 =
* Support `date` in WP_Query `orderby`. Props [psorensen](https://github.com/psorensen)

= 1.3 - 2015-02-03 =
* Support `meta_query` in WP_Query integration
* Improved documentation. Each WP-CLI command has been documented
* Add `elasticsearch` property to global post object to assist in debugging
* `ep_integrate` param added to allow for WP_Query integration without search. (Formally called ep_match_all)
* Filter added for post statuses (defaults to `publish`). Change the sync mechanism to make sure it takes all post statuses into account. Props [jonathanbardo](https://github.com/jonathanbardo)
* Bug fix: check if failed post exists in indexing. Props [elliot-stocks](https://github.com/elliott-stocks)
* Bug fix: properly check if setup is defined in indexing. Props [elliot-stocks](https://github.com/elliott-stocks)
* Bug fix: add WP_Query integration on init rather than plugins loaded. Props [adamsilverstein](https://github.com/adamsilverstein)
* Bug fix: Properly set global post object post type in loop. Props [tott](https://github.com/tott)
* Bug fix: Do not check if index exists on every page load. Refactor so we can revert to MySQL after failed ES ping.
* Bug fix: Make sure we check `is_multisite()` if `--network-wide` is provided. Props [ivankruchkoff](https://github.com/ivankruchkoff)
* Bug fix: Abide by the `exclude_from_search` flag from post type when running search queries. Props [ryanboswell](https://github.com/ryanboswell)
* Bug fix: Correct mapping of `post_status` to `not_analyzed` to allow for filtering of the search query (will require a re-index). Props [jonathanbardo](https://github.com/jonathanbardo)

= 1.2 - 2014-12-05 =
* Allow number of shards and replicas to be configurable.
* Improved searching algorithm. Favor exact matches over fuzzy matches.
* Query stack implementation to allow for query nesting.
* Filter and disable query integration on a per query basis.
* Support orderby` parameter in `WP_Query
* (Bug) We don't want to add the like_text query unless we have a non empty search string. This mimcs the behavior of MySQL or WP which will return everything if s is empty.
* (Bug) Change delete action to action_delete_post instead of action_trash_post
* (Bug) Remove _boost from mapping. _boost is deprecated by Elasticsearch.
* Improve unit testing for query ordering.

= 1.1 - 2014-10-27 =
* Refactored `is_alive`, `is_activated`, and `is_activated_and_alive`. We now have functions `is_activated`, `elasticsearch_alive`, `index_exists`, and `is_activated`. This refactoring helped us fix #150.
* Add support for post_title and post_name orderby parameters in `WP_Query` integration. Add support for order parameters.

= 1.0 - 2014-10-20 =
* Support `search_fields` parameter. Support author, title, excerpt, content, taxonomy, and meta within this parameter.
* Move all management functionality to WP-CLI commands
* Remove ES_Query and support everything through WP_Query
* Disable sync during import
* Check for valid blog ids in index names
* Improved bulk error handling
* No need for `ep_last_synced` meta
* No need for syncing taxonomy
* Improved unit test coverage
* `sites` WP_Query parameter to allow for search only on specific blogs

= 0.9.3 - 2014-09-26 =
Added:
* Better documentation surrounding `WP_Query` parameters (props @tlovett1).
* Option to allow for using `match_all` (props @colegeissinger for suggestion).
* Better tests for some `WP_Query` parameters (props @tlovett1).
* Allow for manual control over search integration.
* Support for passing an array of sites to search against (props @tlovett1).
* Filter for controlling whether or not ElasticPress is enabled during a `wp_query` request.
* Filter to allow adjusting which fields are searched (`ep_search_fields`).

Changed:
* Prevented filtering `WP_Query` in admin (props @cmmarslender).
* Updated tests to better conform to WordPress repo 5.2 compatibility (props @tlovett1).
* Made running re-indexing commands simpler and easier by adding support for a new `--setup` flag on the `index` command.
* Disable search integration during syncing.

Fixed:
* Bug that would cause a post to stay in the index when a post was unpublished.
* Bug that would cause site to be improperly switched after a `wp_reset_postdata` while not in the loop.
* Bug that would cause EP to individually sync each post during an import - disabled syncing during import - requires a full re-index after import.

= 0.9.2 - 2014-09-11 =
Added:
* Wrapper method for wp_get_sites, added filter.
* Ability to change scope of search to other sites in network.
* tax_query support.

Changed:
* Aggregation filter update.

= 0.9.1 - 2014-09-05 =
Added:
* Action to allow for retrieval of raw response.
* Filter to retrieve aggregations.
* Pagination tests.
* ep_min_similarity and ep_formatted_args filters.
* ep_search_fields filter for adding custom search fields.
* Filter to allow for specific site selection on multisite indexing.

Changed:
* Adjust default fuzziness to .75 instead of .5.

Removed:
* Main query check on ep wp query integration.

= 0.9 - 2014-09-03 =
Added:
* Make labels clickable in admin.
* Setup plugin textdomain; POT file for translation; localize stray string in cron.
* Tests for is_alive function.
* search_meta key param support to ES_Query.
* Test WP Query integration on multisite setup.
* Flush and re-put mapping on admin sync request.
* WP Query integration.

Changed:
* Simplify sync.
* do_scheduled_syncs into do_syncs.
* Make config files static.

Removed:
* EP hidden taxonomy.

Fixed:
* Cron stuff.
* Statii.
* Type coercion in equality checks.

= 0.1.2 - 2014-06-27 =
* Only index public taxonomies
* Support ES_Query parameter that designates post meta entries to be searched
* Escape post ID and site ID in API calls
* Fix escaping issues
* Additional tests
* Translation support
* Added is_alive function for checking health status of Elasticsearch server
* Renamed `statii` to `status`

= 0.1.0 - Unknown =
* Initial plugin

== Upgrade Notice ==

= 4.0.0 =
**Note that ElasticPress 4.0.0 release removes built assets from the `develop` branch, replaced `master` with `trunk`, added a ZIP with the plugin and its built assets in the [GitHub Releases page](https://github.com/10up/ElasticPress/releases), and included a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to `trunk` or to GitHub Releases depending on whether you require built assets or not.

= 3.6.0 =
**Note that the upcoming ElasticPress 3.7.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, will build a stable release version including built assets into a `stable` branch, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to either `stable` or `trunk` depending on whether you require built assets or not.
