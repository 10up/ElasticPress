Index, Reindex, and Sync are some of the names used by ElasticPress to refer to the process of synchronizing the content stored in the MySQL/MariaDB database with the Elasticsearch server. As of 4.0, the IndexHelper class is the central point of the process for all methods available (WP Dashboard and WP-CLI, so far.)

The process consists of these steps:

1. Build the index meta: this is an array that stores all information related to the steps that will be performed during the sync process. It will contain which indexables will be synced, if a mapping will be sent or not, the method used (dashboard or cli), range of IDs, etc. It is stored in an option called `ep_index_meta`.

2. Per Indexable,  i.e., a WordPress entity like Posts, Terms, Users, or Comments:
    1. (optional) Put a mapping. In general terms, this will set up the necessary fields for the Indexable. If this step is required, data in Elasticsearch will be completely removed.
    2. Index content. During this step, content in WordPress will be sent and stored into Elasticsearch.

3. (optional) Create a network alias. If activated in a multisite network, ElasticPress will create an alias of all post indices, making searches across all sites easier.

4. Finalizing. During this step, the index meta array is cleaned up and timers are stopped, consolidating overall time used, for example.

The index meta array is constantly updated throughout the process. It means that looking at that array in any part of the sync execution, the IndexHelper class (and also developers and debug tools) is able to identify exactly what still needs to be done, i.e., what is needed to do the finish the sync.

For the WP Dashboard sync, where the entire process happens via AJAX, the plugin needs to handle the process through several small requests, so messages can be sent to the frontend. In order to have that happening, each of those small requests is the IndexHelper class processing a small part of the list of posts, updating the index meta array, and returning back the result. The frontend then fires a new request, and IndexHelper gets the updated index meta array and continues from where it stopped.

Also worth noting is how fallbacks work:

- If sending a mapping
If a mapping is sent, that means result searches will be empty at the beginning of the process. Due to that, ElasticPress will fall back to MySQL until the sync of that indexable is done.

- If not sending a mapping
If the content is being synced without being completely erased, Elasticsearch will be used. Although with possibly slightly different results, it won't fall back to MySQL.

- If working on different Indexables
If resyncing sending mappings for Posts and Users, for example, searches for Posts will fall back to MySQL but searches for Users will keep working as normal (through EP.) When the posts sync is done, searches for Posts will start going through EP but Users searches will start falling back to MySQL.