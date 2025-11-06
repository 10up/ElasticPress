=== ElasticPress ===
Contributors: 10up, tlovett1, vhauri, tott, felipeelia, oscarssanchez, cmmarslender
Tags:         performance, search, elasticsearch, fuzzy, related posts
Tested up to: 6.8
Stable tag:   5.3.1
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

ElasticPress requirements can be found in the [Requirements section](https://github.com/10up/ElasticPress#requirements) of our GitHub repository. If your solution relies on a different server or version, you may find additional information on our [Compatibility documentation page](https://www.elasticpress.io/resources/articles/compatibility/).

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

= 5.3.1 - 2025-11-06 =

__Fixed:__

* Compatibility with PHP 7.4. Props [@realrellek](https://github.com/realrellek) and [@felipeelia](https://github.com/felipeelia).

= 5.3.0 - 2025-11-05 =

Highlights of this version:

* Improved compatibility with Elementor
* Better UX/UI in the Features Screen: Grouped features and conditional display of fields 
* New indicator in the WordPress Admin Bar: See if your content is powered by Elasticsearch and how many queries were fired and failed in the current page.

__Added:__

* Grouped features. Props [@ZacharyRener](https://github.com/ZacharyRener) and [@felipeelia](https://github.com/felipeelia).
* Selected features and groups now persist across reload. Props [@ZacharyRener](https://github.com/ZacharyRener), [@burhandodhy](https://github.com/burhandodhy), and [@felipeelia](https://github.com/felipeelia).
* Feature fields can be dependent on other fields. Props [@ZacharyRener](https://github.com/ZacharyRener) and [@felipeelia](https://github.com/felipeelia).
* Ability to create groups of fields. Props [@ZacharyRener](https://github.com/ZacharyRener) and [@felipeelia](https://github.com/felipeelia).
* Ability for a feature to require multiple other features, instead of just one. Props [@ZacharyRener](https://github.com/ZacharyRener) and [@felipeelia](https://github.com/felipeelia).
* Add new widgets for Date, Meta, and Meta Range Filters. Props [@burhandodhy](https://github.com/burhandodhy).
* Status indicator in the WordPress Admin Bar. Props [@felipeelia](https://github.com/felipeelia), [@burhandodhy](https://github.com/burhandodhy), and [@tott](https://github.com/tott).
* Add Elasticsearch 9 support. Props [@burhandodhy](https://github.com/burhandodhy).
* Support for rand with a seed in the orderby clause. Props [@asharirfan](https://github.com/asharirfan), [@asharirfan](https://github.com/asharirfan), [@burhandodhy](https://github.com/burhandodhy), [@felipeelia](https://github.com/felipeelia), [@tlovett1](https://github.com/tlovett1), [@mustafauysal](https://github.com/mustafauysal), and [@jgmedialtd](https://github.com/jgmedialtd).
* Implement "OR" filter relationship in DateQuery. Props [@burhandodhy](https://github.com/burhandodhy) and [@eartahhj](https://github.com/eartahhj).
* Add support for new WP_Query argument `ep_intercept_request`. Props [@burhandodhy](https://github.com/burhandodhy).
* New ElasticPressIoTemplateManager class. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* Support for painless scripts in the ES queries. Props [@burhandodhy](https://github.com/burhandodhy) and [@scottbuckel](https://github.com/scottbuckel).
* Support to set an array value in the `orderby_meta_mapping` filters. Props [@burhandodhy](https://github.com/burhandodhy) and [@jzzaj](https://github.com/jzzaj).
* New `ep_skip_search_exclusions` WP_Query argument. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* [Autosuggest] Added link in Status Report to send allowed parameters directly. Props [@felipeelia](https://github.com/felipeelia), [@burhandodhy](https://github.com/burhandodhy), and [@tott](https://github.com/tott).
* New `ep_get_query_log` filter. Props [@felipeelia](https://github.com/felipeelia).

__Changed:__

* Index `srcset` for post thumbnails. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* Menu SVG icon with default WP color. Props [@LenVan](https://github.com/LenVan).
* Aggregation data stored at query level instead of a global variable. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).
* Text explaining when a manual sync may be required. Props [@felipeelia](https://github.com/felipeelia) and [@anjulahettige](https://github.com/anjulahettige).
* ElasticPress.io endpoint to fetch messages and available services. Props [@felipeelia](https://github.com/felipeelia).
* [Autosuggest] WP_Query arguments are now passed to Elasticsearch->query() when setting allowed parameters. Props [@felipeelia](https://github.com/felipeelia).
* Exceptions thrown during a post sync will now become error messages in sync processes. Props [@felipeelia](https://github.com/felipeelia).
* Points the taxonomy filter URL to the facet section. Props [@burhandodhy](https://github.com/burhandodhy).
* Bumped `react-router-dom` from 6.14.3 to 7.9.4. Props [@burhandodhy](https://github.com/burhandodhy).

__Deprecated:__

* Remove deprecated side param from edge_ngram filter for ES 8.16.x compatibility. Props [@rebeccahum](https://github.com/rebeccahum).
* The `ep_bypass_exclusion_from_search` filter (replaced by the new `ep_skip_search_exclusions` WP_Query argument). Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).

__Removed:__

* Old feature settings code. Props [@burhandodhy](https://github.com/burhandodhy) and [@JakePT](https://github.com/JakePT).

__Fixed:__

* Autosuggest doesn't work if the placeholder is changed via a `ep_autosuggest_query_placeholder` filter. Props [@fabianmarz](https://github.com/fabianmarz) and [@burhandodhy](https://github.com/burhandodhy).
* Link to compatibility documentation in admin notices. Props [@dilipbheda](https://github.com/dilipbheda).
* [Metadata Range filter] Warning `Undefined array key "is_preview"`. Props [@burhandodhy](https://github.com/burhandodhy).
* 'Filter by Taxonomy' widget does not appear in Elementor. Props [@burhandodhy](https://github.com/burhandodhy).
* Add support to handle values in array format when the comparison operator was 'IN' or 'NOT IN'. Props [@burhandodhy](https://github.com/burhandodhy).
* Users could see other authors' private posts. Props [@burhandodhy](https://github.com/burhandodhy).
* Undefined array key warning related to media mime types. Props [@burhandodhy](https://github.com/burhandodhy) and [@DarioBF](https://github.com/DarioBF).
* Warning: value prop on "input" should not be null. Props [@burhandodhy](https://github.com/burhandodhy).
* Comment query when orderby set to none. Props [@burhandodhy](https://github.com/burhandodhy).
* JS warnings on Status Report page. Props [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `http-proxy-middleware` from 2.0.7 to 2.0.9. Props [@dependabot](https://github.com/dependabot).
* Bumped `tar-fs` from 3.0.8 to 3.1.1. Props [@dependabot](https://github.com/dependabot).
* Bumped `compression` from 1.7.5 to 1.8.1. Props [@dependabot](https://github.com/dependabot).
* Bumped `form-data` from 4.0.1 to 4.0.4. Props [@dependabot](https://github.com/dependabot).
* Overwrite package `@babel/runtime` coming from core packages due to a vulnerability. Props [@hugosolar](https://github.com/hugosolar).

### Developer
* Fixed e2e tests on WP 6.8. Props [@felipeelia](https://github.com/felipeelia).
* Migrated e2e tests from Cypress to Playwright. Props [@felipeelia](https://github.com/felipeelia).

= 5.2.0 - 2025-04-10 =

This version bumps the minimum WordPress version to 6.2+.

__Added:__

* New ACF Repeater Field Compatibility feature. Props [@felipeelia](https://github.com/felipeelia).
* Add new filter `ep.InstantResults.filter.taxonomy.terms`. Props [@burhandodhy](https://github.com/burhandodhy) and [@syedc](https://github.com/syedc).
* Support to "number" fields in the Features Settings API. Props [@felipeelia](https://github.com/felipeelia).
* Add `include`, `exclude`, `upper-limit-object-id`, and `lower-limit-object-id` support for the term and comment indexable. Props [@burhandodhy](https://github.com/burhandodhy).
* Ability to display results on focus back + cached autosuggest results on same query. Props [@oscarssanchezz](https://github.com/oscarssanchezz), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia).
* Document status displayed in the admin bar. Props [@felipeelia](https://github.com/felipeelia), [@tott](https://github.com/tott), and [@burhandodhy](https://github.com/burhandodhy).

__Changed:__

* Minimum required version of WordPress (from 6.0 to 6.2). Props [@felipeelia](https://github.com/felipeelia).
* `ElasticPress\Feature\RelatedPosts::get_related_query()`, `ElasticPress\Feature\RelatedPosts::find_related()` parameter name change to `$post_return`. Props [@oscarssanchez](https://github.com/oscarssanchezz) and [@felipeelia](https://github.com/felipeelia).
* The `ep_remote_request` action to also run on non-blocking requests. Props [@felipeelia](https://github.com/felipeelia).
* Potentially resource intensive status reports are loaded on demand with AJAX. Props [@oscarssanchezz](https://github.com/oscarssanchezz), [@felipeelia](https://github.com/felipeelia), and [@archon810](https://github.com/archon810).
* If a feature doesn't have all its requirements fulfilled, prevent it to run its setup method. Props [@felipeelia](https://github.com/felipeelia).

__Fixed:__

* PHP warning: Attempt to read property "base" on null. Props [@burhandodhy](https://github.com/burhandodhy) and [@yarovikov](https://github.com/yarovikov).
* Notice not displayed while updating a term. Props [@burhandodhy](https://github.com/burhandodhy) and [@MARQAS](https://github.com/MARQAS).
* PHP Notice: Function _load_textdomain_just_in_time was called incorrectly. Props [@burhandodhy](https://github.com/burhandodhy).
* PHP 8.4: Implicitly marking parameter $woocommerce as nullable is deprecated. Props [@BrookeDot](https://github.com/BrookeDot).
* WP-CLI sync timer resetting after 16 minutes. Props [@felipeelia](https://github.com/felipeelia) and [@columbian-chris](https://github.com/columbian-chris).
* Cannot get outside of autosuggest list pressing up on first item. Props [@oscarssanchezz](https://github.com/oscarssanchezz), [@JakePT](https://github.com/JakePT), and [@felipeelia](https://github.com/felipeelia).
* Prevent other code from modifying the ORDERBY clause in Post and Term indexable queries. Props [@burhandodhy](https://github.com/burhandodhy) and [@felipeelia](https://github.com/felipeelia).
* Date Query returns no result when the before and after range is the same and inclusive is set to true. Props [@burhandodhy](https://github.com/burhandodhy) and [@ErikBrendel](https://github.com/ErikBrendel).
* Warning for undefined "post_type" array key. Props [@econscript](https://github.com/econscript).
* Delete a post from the index if it had a password added. Props [@felipeelia](https://github.com/felipeelia) and [@dtakken](https://github.com/dtakken).
* [Synonyms] Linebreaks being wrongly replaced in Windows Systems. Props [@nymwo](https://github.com/nymwo).
* Deprecated `36px default size is deprecated` warnings. Props [@burhandodhy](https://github.com/burhandodhy).
* The Sync Complete message being displayed when the log is cleared. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Selecting a value in the date filter not redirecting users back to page 1. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* Discard Changes button coming back when saving the feature twice. Props [@felipeelia](https://github.com/felipeelia) and [@burhandodhy](https://github.com/burhandodhy).
* WooCommerce Orders Incompatibility not appearing when plugin is activated network wide. Props [@burhandodhy](https://github.com/burhandodhy).

__Security:__

* Bumped `serialize-javascript` from 6.0.1 to 6.0.2, `jsdoc` from 3.6.11 to 4.0.4, and `taffydb`. Props [@dependabot](https://github.com/dependabot).
* Bumped `tar-fs` from from 3.0.6 to 3.0.8. Props [@dependabot](https://github.com/dependabot).

__Developer:__

* Trufflehog GitHub Action to detect secrets leak. Props [@felipeelia](https://github.com/felipeelia) and [@tott](https://github.com/tott).
* Install Unit Tests without relying on SVN. Props [@felipeelia](https://github.com/felipeelia).
* Remove the setup step from the `build-with-vendor-prefixed.yml` and standardize the use of Node Version. Props [@burhandodhy](https://github.com/burhandodhy).
* Fixed E2E tests. Props [@burhandodhy](https://github.com/burhandodhy).
* PHP, JS and Style lint fixes. Props [@oscarssanchez](https://github.com/oscarssanchezz) and [@felipeelia](https://github.com/felipeelia).
* Several node and composer packages updated. Props [@felipeelia](https://github.com/felipeelia).
* Updated the chart.js library. Props [@felipeelia](https://github.com/felipeelia).

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

[View historical changelog details here](https://github.com/10up/ElasticPress/blob/develop/CHANGELOG.md).