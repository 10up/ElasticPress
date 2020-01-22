If you’re hosted with ElasticPress.io, simply add your Subscription ID and Token into the ElasticPress settings page to secure your ElasticPress installation.

ElasticPress can be used with the [Elasticsearch Shield](https://www.elastic.co/products/shield) plugin

Define the constant ```ES_SHIELD``` in your ```wp-config.php``` file with the username and password of your Elasticsearch Shield user. For example:

```php
define( 'ES_SHIELD', 'username:password' );
```