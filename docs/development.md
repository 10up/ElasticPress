Developing for ElasticPress is easy. We provide tools that give you everything you need to test ElasticPress features and create Pull Requests. First thing you'll want to do is setup a local development environment.

## Local Development Environment
We highly recommend using [WP Local Docker V2](https://github.com/10up/wp-local-docker-v2) to develop for ElasticPress. This docker-based environment can be installed and setup via NPM. It comes with Elasticsearch out of the box.

After installing WP Local Docker V2, just run `10updocker create`. Use `elasticpress.test` as the hostname. Make sure to answer yes when it asks if you need Elasticsearch. After the site is created, follow the plugin install instructions.

In your `wp-config.php` file, insert the following to tell ElasticPress where Elasticsearch is located:

```
define( 'EP_HOST', 'http://elasticpress.test/__elasticsearch' );
```

Finally, run `composer install` in the root of the plugin.

Unit Testing
ElasticPress uses unit tests via the WordPress core unit testing library as well as acceptance tests with [WP Acceptance](https://github.com/10up/wpacceptance).

To run unit tests, assuming you are using WP Local Docker V2 and Elasticsearch is running, SSH into your docker container by running `10updocker shell`. Navigate to the root of the ElasticPress directory, first setup the test database:

```
composer run-script setup-local-tests
```

Now run the tests:

```
EP_HOST="http://elasticsearch:9200" phpunit
```

To run WP Acceptance, navigate to the root of the plugin and run:

```
./vendor/bin/wpacceptance
```