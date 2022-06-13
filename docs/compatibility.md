ElasticPress requirements can be found in the [Requirements section](https://github.com/10up/ElasticPress#requirements) in our README. If your solution relies on a different server or version, you may find additional information in this document.

## Elasticsearch (Different Versions)

The ElasticPress team changes minimum and maximum required versions as often as possible. If you are using a more recent version of Elasticsearch, please share with us your findings via a GitHub issue.

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
