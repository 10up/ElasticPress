## OpenSearch

Currently, if you want to run ElasticPress with any version of OpenSearch, you need to use a snippet like the following to use the compatible ES mapping version.

```
add_filter(
	'ep_elasticsearch_version',
	function() {
		return '7.10';
	}
);
```
