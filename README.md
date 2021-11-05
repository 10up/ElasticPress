# ElasticPress

> A fast and flexible search and query engine for WordPress.

[![Support Level](https://img.shields.io/badge/support-active-green.svg)](#support-level) [![Tests Status](https://github.com/10up/ElasticPress/actions/workflows/test.yml/badge.svg?branch=develop)](https://github.com/10up/ElasticPress) [![Release Version](https://img.shields.io/github/release/10up/ElasticPress.svg)](https://github.com/10up/ElasticPress/releases/latest) ![WordPress tested up to version](https://img.shields.io/badge/WordPress-v5.8%20tested-success.svg) [![MIT License](https://img.shields.io/github/license/10up/ElasticPress.svg)](https://github.com/10up/ElasticPress/blob/develop/LICENSE.md)

* Check out the [ElasticPress Docs](http://10up.github.io/ElasticPress/)

**Please note:** master is the stable branch.

## Planned changes for 4.0.0

**Note that the upcoming ElasticPress 4.0.0 release will remove built assets from the `develop` branch, will replace `master` with `trunk`, ~~will build a stable release version including built assets into a `stable` branch,~~ will add a zip with the plugin and its built assets in the GitHub release page, and will include a build script should you want to build assets from a branch.**  As such, please plan to update any references you have from `master` to ~~either `stable` or~~ `trunk` or to GitHub releases depending on whether you require built assets or not.

Minimum supported versions:

||Current (3.6.4)|4.0.0|
|---|:---:|:---:|
|Elasticsearch|5.0+|5.2+|
|WordPress|3.7.1+|5.6+|
|PHP|5.6+|7.0+|

## Overview

ElasticPress, a fast and flexible search and query engine for WordPress, enables WordPress to find or “query” relevant content extremely fast through a variety of highly customizable features. WordPress out-of-the-box struggles to analyze content relevancy and can be very slow. ElasticPress supercharges your WordPress website making for happier users and administrators. The plugin even contains features for popular plugins.

## Documentation

ElasticPress has an in depth documentation site. [Visit the docs ☞](http://10up.github.io/ElasticPress/)

ElasticPress FAQs and tutorials can be found on our support site. [Visit the support site ☞](https://elasticpress.zendesk.com/hc/en-us)

## Requirements

* [Elasticsearch](https://www.elastic.co) 5.0+ **ElasticSearch max version supported: 7.9**
* [WordPress](http://wordpress.org) 3.7.1+
* [PHP](https://php.net/) 5.6+

## React Components

Interested in integrating ElasticPress in your headless WordPress website? Check out [ElasticPress React](https://github.com/10up/elasticpress-react).

## Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/10up/ElasticPress/issues?state=open). We're excited to see what the community thinks of this project, and we would love your input!

## Support Level

**Active:** 10up is actively working on this, and we expect to continue work for the foreseeable future including keeping tested up to the most recent version of WordPress.  Bug reports, feature requests, questions, and pull requests are welcome.

## Changelog

A complete listing of all notable changes to ElasticPress are documented in [CHANGELOG.md](https://github.com/10up/elasticpress/blob/develop/CHANGELOG.md).

## Upgrade notices

### 3.5

**Search Algorithm Upgrade Notice:** Version 3.5 includes a revamp of the search algorithm. This is a backwards compatibility break. If you'd like to revert to the old search algorithm, you can use the following code: `add_filter( 'ep_search_algorithm_version', function() { return '3.4'; } );`. The new algorithm offers much more relevant search results and removes fuzziness which results in mostly unwanted results for most people. If you are hooking in and modifying the search query directly, it's possible this code might break and you might need to tweak it.

## Contributing

Please read [CODE_OF_CONDUCT.md](https://github.com/10up/elasticpress/blob/develop/CODE_OF_CONDUCT.md) for details on our code of conduct, [CONTRIBUTING.md](https://github.com/10up/elasticpress/blob/develop/CONTRIBUTING.md) for details on the process for submitting pull requests to us, and [CREDITS.md](https://github.com/10up/elasticpress/blob/develop/CREDITS.md) for a listing of maintainers of, contributors to, and libraries used by ElasticPress.

## Like what you see?

<p align="center">
<a href="http://10up.com/contact/"><img src="https://10up.com/uploads/2016/10/10up-Github-Banner.png" width="850"></a>
</p>
