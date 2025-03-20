=== ElasticPress ===
Contributors: 10up, tlovett1, vhauri, tott, felipeelia, oscarssanchez, cmmarslender
Tags:         performance, search, elasticsearch, fuzzy, related posts
Tested up to: 6.7
Stable tag:   5.1.4
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

Please refer to [GitHub](https://github.com/10up/ElasticPress) for detailed usage instructions and documentation. FAQs and tutorials can be also found on our [support site](https://www.elasticpress.io/documentation/).

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

= 5.1.4 - 2024-12-12 =

__Added:__

* New filter `ep_facet_selected_filters`. Props [@burhandodhy](https://github.com/burhandodhy).
* New filter `ep_disable_query_logging` to disable query logging. Props [@davidsword](https://github.com/davidsword) and [@rebeccahum](https://github.com/rebeccahum).
* New setting to Protect Content to use WP default order in admin. Props [@felipeelia](https://github.com/felipeelia) and [@realrellek](https://github.com/realrellek).

__Changed:__

* Apply ElasticPress filters to the requests in status and stats CLI commands. Props [@edpittol](https://github.com/edpittol).
* Autosuggest Endpoint field explanation. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Alignment of custom search results action icons. Props [@felipeelia](https://github.com/felipeelia) and [@anjulahettige](https://github.com/anjulahettige).
* Update all of our blocks apiVersion from 2 to 3, to indicate support for working in an iframed editor. Props [@dkotter](https://github.com/dkotter) and [@JakePT](https://github.com/JakePT).
* If using the new way to index meta, avoid querying distinct meta fields in the sync page. Props [@felipeelia](https://github.com/felipeelia) and [@majiix](https://github.com/majiix).
* Updated several composer and node packages. Node 20 is now the default version. Props [@felipeelia](https://github.com/felipeelia).
* Improve readability of sync output (MB/GB) and number formatting on the Health Status page. Props [@columbian-chris](https://github.com/columbian-chris).

__Fixed:__

* Hardcoded `tmp` path replaced with a dynamic value. Props [@burhandodhy](https://github.com/burhandodhy).
* Variable names and descriptions in the docblocks for `ep_formatted_args` and `ep_post_formatted_args`. Props [@barryceelen](https://github.com/barryceelen).
* Remove 'None' from Highlight tag list. Props [@burhandodhy](https://github.com/burhandodhy).
* [Facets] Incorrect link on description when not using a block theme. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Deprecation warning in `strtotime()` call. Props [@felipeelia](https://github.com/felipeelia) and [@barryceelen](https://github.com/barryceelen).
* Special characters like `\` in search terms for both Autosuggest and Instant Results. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* [WooCommerce] Incompatibility when "Enable table usage" was enabled to filter the product catalog. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Deprecation warning related to PluginPostStatusInfo. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* [Custom Results] Inconsistent Reordering Issue. Props [@felipeelia](https://github.com/felipeelia), [@anjulahettige](https://github.com/anjulahettige), [@burhandodhy](https://github.com/burhandodhy).
* Update supported document file types in Documents feature summary. Props [@burhandodhy](https://github.com/burhandodhy).
* "Exclude from search results" to work in AJAX contexts. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Retain CR & RD Labels Upon Saving Custom Search Result Posts. Props [@felipeelia](https://github.com/felipeelia) and [@anjulahettige](https://github.com/anjulahettige).
* Typo in "All filters" text domain. Props [@felipeelia](https://github.com/felipeelia) and [@arturomonge](https://github.com/arturomonge).
* Autosuggest GA tracking to work when ad blocks are enabled. The dataLayer.push() call now pushes a custom event called ep_autosuggest_click with ep_autosuggest_search_term and ep_autosuggest_clicked_url as custom parameters. Props [@felipeelia](https://github.com/felipeelia) and [@anjulahettige](https://github.com/anjulahettige).
* Delay `load_plugin_textdomain` to `init` and set a Domain Path. Props [@felipeelia](https://github.com/felipeelia).
* Only display the Exclude From Search checkbox if the post type supports `custom-fields`. Props [@felipeelia](https://github.com/felipeelia) and [@maartenhunink](https://github.com/maartenhunink).
* JS error when submit button is clicked without selecting a date. Props [@burhandodhy](https://github.com/burhandodhy).
* Deprecated warnings for margin style. Props [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `composer/composer` from 2.7.0 to 2.7.8. Props [@dependabot](https://github.com/dependabot).
* Bumped `symfony/process` from 6.4.8 to 6.4.14. Props [@dependabot](https://github.com/dependabot).

__Developer:__

* Tests use ES 8 by default. Props [@felipeelia](https://github.com/felipeelia).
* Update E2E tests to work properly with the iframed block editor. Props [@dkotter](https://github.com/dkotter).
* E2e tests for WP 6.6. Props [@felipeelia](https://github.com/felipeelia).
* E2e tests for WP 6.7. Props [@felipeelia](https://github.com/felipeelia).
* Unit Tests: Fail faster on requests we know will fail. Props [@felipeelia](https://github.com/felipeelia).
* E2e tests: Fix the debug-bar-elasticpress dependency of ElasticPress. Props [@felipeelia](https://github.com/felipeelia).


= 5.1.3 - 2024-06-11 =

__Fixed:__

* Missing nonces on some sync trigger URLs, making them require a manual interaction from the user. Props [@felipeelia](https://github.com/felipeelia).

= 5.1.2 - 2024-06-11 =

**This is a security release affecting all previous versions of ElasticPress.**

__Security:__

* Missing nonce verification for the sync triggered during activation of some features. Props [@felipeelia](https://github.com/felipeelia) and [@dhakalananda](https://github.com/dhakalananda).
* Missing nonce verification for retrying the EP connection and fixed PHPCS linting rules. Props [@felipeelia](https://github.com/felipeelia).

= 5.1.1 - 2024-05-27 =

__Changed:__

* Update Support Article URLs. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* Status report page when indexable post types is an empty array. Props [@furai](https://github.com/furai).

= 5.1.0 - 2024-04-29 =

__Added:__

* [Filters] New `ep_facet_enabled_in_editor` filter to enabled facet blocks in the post editor. Props [@JiveDig](https://github.com/JiveDig) and [@felipeelia](https://github.com/felipeelia).
* Official support to Elasticsearch 8.x. Props [@felipeelia](https://github.com/felipeelia).
* A new Sync errors tab, with errors grouped by type and links to support documentation when available. Props [@JakePT](https://github.com/JakePT) and [@apurvrdx1](https://github.com/apurvrdx1).
* [WooCommerce] HPOS compatibility notice for WooCommerce Orders. Props [@felipeelia](https://github.com/felipeelia).
* [Synonyms] A new settings screen with the the ability to bulk delete synonyms, support for many-to-many replacements, and a new type of synonym for terms with a hierarchical relationship, called hyponyms. Props [@JakePT](https://github.com/JakePT) and [@apurvrdx1](https://github.com/apurvrdx1).
* Infinite loop when using excerpt highlighting with posts that use blocks that print an excerpt. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* Context parameter to the `get_capability()` function. Props [@felipeelia](https://github.com/felipeelia) and [@selim13](https://github.com/selim13).
* A tooltip for meta keys to the weighting screen to allow seeing the full key if it has been truncated. Props [@JakePT](https://github.com/JakePT).
* New `ep_weighting_options` filter to modify the weighting dashboard options. Props [@burhandodhy](https://github.com/burhandodhy).
* New `ep_post_test_meta_value` filter. Props [@felipeelia](https://github.com/felipeelia).
* New message related to indices limits on ElasticPress.io. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Acknowledge all Elasticsearch modules, making the Documents feature available in ES 8 installations by default. Props [@felipeelia](https://github.com/felipeelia), [@Serverfox](https://github.com/Serverfox), and [@jerasokcm](https://github.com/jerasokcm).
* [Documents] Index CSV and TXT file contents. Props [@felipeelia](https://github.com/felipeelia).
* [Documents] Only set documents-related parameters if no post type was set or if the list already contains attachments. Props [@felipeelia](https://github.com/felipeelia).
* Automatically open the error log when a sync completes with errors. Props [@JakePT](https://github.com/JakePT) and [@felipeelia](https://github.com/felipeelia).
* Aggregations created with the 'aggs' WP_Query parameter, are now retrievable using `$query->query_vars['ep_aggregations']`. Props [@felipeelia](https://github.com/felipeelia).
* Major refactor of the `Term::format_args()` method and conditionally set search fields for term queries in REST API requests. Props [@felipeelia](https://github.com/felipeelia) and [@mgurtzweiler](https://github.com/mgurtzweiler).
* Replaced `lee-dohm/no-response` with `actions/stale` to help with closing no-response/stale issues. Props [@jeffpaul](https://github.com/jeffpaul).
* Bumped actions/upload-artifact from v3 to v4. Props [@iamdharmesh](https://github.com/iamdharmesh).
* Required node version. Props [@oscarssanchez](https://github.com/oscarssanchez).

__Fixed:__

* [Autosuggest] Hide the Autosuggest Endpoint URL field for EP.io users. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* [Autosuggest] Google Analytics integration gtag call. Props [@felipeelia](https://github.com/felipeelia) and [@JakePT](https://github.com/JakePT).
* [Autosuggest] Link click when using a touchpad. Props [@romanberdnikov](https://github.com/romanberdnikov).
* [Autosuggest] Pressing Enter to select an Autosuggest suggestion would instead open Instant Results. Props [@JakePT](https://github.com/JakePT).
* [Synonyms] Fatal error when saving synonyms if an index does not exist. Props [@felipeelia](https://github.com/felipeelia), [@MARQAS](https://github.com/MARQAS), [@randallhedglin](https://github.com/randallhedglin), and [@bispldeveloper](https://github.com/bispldeveloper).
* [Synonyms] Fix Synonyms case sensitive issue. Props [@burhandodhy](https://github.com/burhandodhy).
* [Documents] Media search returns no result in admin dashboard. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy) via [#3837](https://github.com/10up/ElasticPress/pull/3837).
* [WooCommerce] E2e tests. Props [@felipeelia](https://github.com/felipeelia).
* [Instant Results] A default post type filter set by a field in the search form was cleared if a new search term was entered. Props [@JakePT](https://github.com/JakePT) and [@burhandodhy](https://github.com/burhandodhy).
* Inconsistent search results when calling the same function via PHP and Ajax. Props [@burhandodhy](https://github.com/burhandodhy).
* Unit test related to blog creation. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Correct PHPdoc return type for `Elasticsearch::index_document` and related methods. Props [@ictbeheer](https://github.com/ictbeheer).
* Unnecessary horizontal scroll for the `<pre>` tag on the status report page. Props [@burhandodhy](https://github.com/burhandodhy) via [#3894](https://github.com/10up/ElasticPress/pull/3894).

__Security:__

* Bumped `composer/composer` from 2.6.5 to 2.7.0. Props [@dependabot](https://github.com/dependabot).

= 5.0.2 - 2024-01-16 =

__Changed:__

* [Terms] Counts are now calculated with `wp_count_terms()` in `query_db`. Props [@rebeccahum](https://github.com/rebeccahum).
* Composer and npm files are now part of the final package. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* [WooCommerce] Not use a hard-coded list of order post types. Props [@felipeelia](https://github.com/felipeelia).
* [Autosuggest] Stop calling the get`-autosuggest-allowed` endpoint to build the REST API schema. Props [@felipeelia](https://github.com/felipeelia).

__Security:__

* Bumped `follow-redirects` from 1.15.3 to 1.15.4. Props [@dependabot](https://github.com/dependabot).

= 5.0.1 - 2023-12-12 =

__Added:__

* Failed queries in the Index Health page will now be outputted with their error messages. Props [@felipeelia](https://github.com/felipeelia) and [@pvnanini](https://github.com/pvnanini).

__Fixed:__

* Queries failing due to a "request body is required" error. Props [@felipeelia](https://github.com/felipeelia).
* Fatal error when site has a bad cookie. Props [@burhandodhy](https://github.com/burhandodhy).
* Broken i18n of some strings. Props [@felipeelia](https://github.com/felipeelia) and [@iazema](https://github.com/iazema).
* PHP Warning on term archive pages when the term was not found. Props [@felipeelia](https://github.com/felipeelia) and [@Igor-Yavych](https://github.com/Igor-Yavych).
* PHP warning when using block themes. Props [@felipeelia](https://github.com/felipeelia) and [@tropicandid](https://github.com/tropicandid).
* Several typos. Props [@szepeviktor](https://github.com/szepeviktor).
* Index cleanup process - offset being zeroed too late. Props [@pknap](https://github.com/pknap).
* PHP warning in site health page. Props [@turtlepod](https://github.com/turtlepod).
* ReactDOM.render is no longer supported in React 18. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* E2e tests with WordPress 6.4. Props [@felipeelia](https://github.com/felipeelia).
* PHP Setup in GitHub Actions. Props [@felipeelia](https://github.com/felipeelia).

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

[View historical changelog details here](https://github.com/10up/ElasticPress/blob/develop/CHANGELOG.md).