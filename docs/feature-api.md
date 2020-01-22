ElasticPress has a robust API for registering your own features. Refer to the code within each feature for detailed examples. To register a feature, you will need to call the `ep_register_feature()` function like so:

```php
add_action( 'plugins_loaded', function() {
    ep_register_feature( 'slug', array(
        'title' => 'Pretty Title',
        'setup_cb' => 'setup_callback_function',
        'feature_box_summary_cb' => 'summary_callback_function',
        'feature_box_long_cb' => 'long_summary_callback_function',
        'requires_install_reindex' => true,
        'requirements_status_cb' => 'requirements_status_callback_function',
        'post_activation_cb' => 'post_activation_callback_function',
    ) );
} );
```

The only arguments that are really required are the `slug` and `title` of the associative arguments array. Here are descriptions of each of the associative arguments:

* `title` (string) - Pretty title for feature
* `requires_install_reindex` (boolean) - Setting to true will force a reindex after the feature is activated.
* `setup_cb` (callback) - Callback to a function to be called on each page load when the feature is activated.
* `post_activation_cb` (callback) - Callback to a function to be called after a feature is first activated.
* `feature_box_summary_cb` (callback) - Callback to a function that outputs HTML feature box summary (short description of feature).
* `feature_box_long_cb` (callback) - Callback to a function that outputs HTML feature box full description.
* `requirements_status_cb` (callback) - Callback to a function that determines if the features requirements are met. This function needs to return a `EP_Feature_Requirements_Status` object. `EP_Feature_Requirements_Status` is a simple class with a `code` and a `message` property. Code 0 means there are no requirement issues; code 1 means there are issues with warnings; code 2 means the feature does not have it's requirements met and cannot be used. The message property of the object can be used to store warnings.

If you build an open source custom feature, let us know! We'd be happy to list the feature within ElasticPress documentation.