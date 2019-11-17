ElasticPress uses third party software (Elasticsearch) and touches critical WordPress functionality that many other plugins interact with e.g. WP_Query. As such, instances arise where ElasticPress conflicts with another plugin, Elasticsearch setups aren't compatible with ElasticPress, and bugs are discovered. Use this guide to debug ElasticPress issues.

## ElasticPress Debug Bar Plugin

The first thing to do when any ElasticPress issue occurs, is install the [ElasticPress Debug Bar Plugin](https://github.com/10up/debug-bar-elasticpress). This tool allows you to examine all the ElasticPress queries on each page load. It extends the standard [Debug Bar Plugin](https://wordpress.org/plugins/debug-bar/) so you'll need that too.

Navigate to the page that isn't behaving as expected. For example, if a search page is returning no results, go to that page and open the debug bar. It will show the ElasticPress query.

To examine indexing, enable querying logging in the ElasticPress Debug Bar admin settings page. This will keep a log of all queries that result in an error. Make sure not to leave this on too long as it can cause performance issues.

For a more in depth guide on debugging, check out this [blog post](https://www.elasticpress.io/blog/2017/05/debugging/).