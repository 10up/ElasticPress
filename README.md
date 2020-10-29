# ElasticPress

> A fast and flexible search and query engine for WordPress.

[![Support Level](https://img.shields.io/badge/support-active-green.svg)](#support-level) [![Build Status](https://travis-ci.org/10up/ElasticPress.svg?branch=develop)](https://travis-ci.org/10up/ElasticPress) [![Release Version](https://img.shields.io/github/release/10up/ElasticPress.svg)](https://github.com/10up/ElasticPress/releases/latest) ![WordPress tested up to version](https://img.shields.io/badge/WordPress-v5.4%20tested-success.svg) [![MIT License](https://img.shields.io/github/license/10up/ElasticPress.svg)](https://github.com/10up/ElasticPress/blob/develop/LICENSE.md)

**Please note:** master is the stable branch

**Search Algorithm Upgrade Notice:** Version 3.5 includes a revamp of the search algorithm. This is a backwards compatibility break. If you'd like to revert to the old search algorithm, you can use the following code: `add_filter( 'ep_search_algorithm_version', function() { return '3.4'; } );`. The new algorithm offers much more relevant search results and removes fuzziness which results in mostly unwanted results for most people. If you are hooking in and modifying the search query directly, it's possible this code might break and you might need to tweak it.

* Check out the [ElasticPress Docs](http://10up.github.io/ElasticPress/)

## Overview

ElasticPress, a fast and flexible search and query engine for WordPress, enables WordPress to find or “query” relevant content extremely fast through a variety of highly customizable features. WordPress out-of-the-box struggles to analyze content relevancy and can be very slow. ElasticPress supercharges your WordPress website making for happier users and administrators. The plugin even contains features for popular plugins.

## Documentation

ElasticPress has an in depth documentation site. [Visit the docs ☞](http://10up.github.io/ElasticPress/)

## Requirements

* [Elasticsearch](https://www.elastic.co) 5.0+ **ElasticSearch max version supported: 7.9**
* [WordPress](http://wordpress.org) 3.7.1+
* [PHP](https://php.net/) 5.4+

## React Components

Interested in integrating ElasticPress in your headless WordPress website? Check out [ElasticPress React](https://github.com/10up/elasticpress-react).

## Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/10up/ElasticPress/issues?state=open). We're excited to see what the community thinks of this project, and we would love your input!

## Support Level

**Active:** 10up is actively working on this, and we expect to continue work for the foreseeable future including keeping tested up to the most recent version of WordPress.  Bug reports, feature requests, questions, and pull requests are welcome.

## Like what you see?

<p align="center">
<a href="http://10up.com/contact/"><img src="https://10updotcom-wpengine.s3.amazonaws.com/uploads/2016/10/10up-Github-Banner.png" width="850"></a>
</p>