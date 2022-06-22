ElasticPress requirements can be found in the [Requirements section](https://github.com/10up/ElasticPress#requirements) in our README. If your solution relies on a different server or version, you may find additional information in this document.

## Elasticsearch (Unsupported Versions)

The ElasticPress team updates minimum and maximum required versions for Elasticsearch when required to implement new features and ensure security. ElasticPress will warn you if the version detected is newer than the latest tested version, but all plugin functionality will continue to work. If you are succesfully using a more recent version of Elasticsearch than ElasticPress currently supports, please share your findings with us via a [Github issue](https://github.com/10up/ElasticPress/issues).

## OpenSearch

ElasticPress does not officially support OpenSearch in this version of the plugin, but our initial testing indicates basic ElasticPress functionality may be possible using OpenSearch. We do not recommend using OpenSearch in production at this time. Please provide any feedback about compability issues via a [Github issue](https://github.com/10up/ElasticPress/issues) and we will include it in a future OpenSearch compatibility release, should one occur. 

Currently, if you want to run ElasticPress with any version of OpenSearch, you need to use a snippet like the following to set the compatible Elasticsearch mapping version for the version of OpenSearch you're running. Otherwise, ElasticPress will detect the version of OpenSearch and attempt to set the oldest possible Elasticsearch mapping, due to version number differences between Elasticsearch and OpenSearch.

```
add_filter(
	'ep_elasticsearch_version',
	function() {
		return '7.10';
	}
);
```
