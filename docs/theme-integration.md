## Autosuggest

### Connecting Autosuggest to Your Theme's Search Bar

When enabled the ElasticPress Autosuggest will automatically be added to any `input[type="search"]` elments on the page, as well as any elements with the `.ep-autosuggest` or  `.search-field` classes. You can add autosuggest to additional elements yourself by adding [selectors](https://developer.mozilla.org/en-US/docs/Learn/CSS/Building_blocks/Selectors) as a comma-separated list to the _Autosuggest Selector_ setting under _ElasticPress > Features > Autosuggest > Settings_.

You can change or remove the default selectors used by the plugin with the `ep_autosuggest_default_selectors` filter:
```
add_filter( 'ep_autosuggest_default_selectors', '__return_empty_string' );
```
This example uses the [`__return_empty_string()`](https://developer.wordpress.org/reference/functions/__return_empty_string/) function to remove the default selectors so that only the selectors entered into the settings are used.
### Adding a Loading Indicator

While new suggestions are being fetched as the user types, an `.is-loading` class will be added to the parent `<form>` element. This class can be used to style the search form differently while suggestions are being loaded. One possible use case is to display a loading indicator. For example, if you have a loading gif in your theme's search form:
```
<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label>
		<span class="screen-reader-text">Search for:</span>
		<input type="search" value="<?php echo get_search_query(); ?>" name="s">
	</label>
	<input type="submit" value="Search">
	<img src="<?php echo esc_url( get_theme_file_uri( 'images/loading.gif' ) ); ?>" width="32" height="32" class="loading-indicator">
</form>
```

You could display the loading gif while suggestions are being fetched with this CSS:
```
.loading-indicator {
	display: none;
}

.is-loading .loading-indicator {
	display: block;
}
```