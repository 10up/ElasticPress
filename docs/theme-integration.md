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

### Customize Suggestion Markup

When ElasticPress Autosuggest renders the suggestion list each item is run through a `window,epAutosuggestItemHTMLFilter()` function, if such a function exists. Therefore you can provide your own markup for suggestions by defining this function from your theme or plugin. This can be used to include other fields in the suggestion.

The `epAutosuggestItemHTMLFilter()` function should return the HTML for the suggestion as a string, and accept 4 parameters:

1. `itemHTML` _(string)_ The suggestion HTML as a string.
2. `option` _(object)_ The Elasticsearch record for the suggestion.
3. `i` _(int)_ The index of the suggestion in the results set.
4. `searchText` _(string)_ The search term.

This example uses the function to add the post date to the suggestion:

```
window.epAutosuggestItemHTMLFilter = (itemHTML, option, i, searchText) => {
	const text = option._source.post_title;
	const url = option._source.permalink;
	const postDate = new Date(option._source.post_date).toLocaleString('en', { dateStyle: 'medium' })

	return `<li class="autosuggest-item" role="option" aria-selected="false" id="autosuggest-option-${i}">
		<a href="${url}" class="autosuggest-link" data-url="${url}" tabindex="-1">
			${text} (${postDate})
		</a>
	</li>`;
}
```

Note that the `class`, `id`, `role`, `aria-selected`, `data-url`, and `tabindex` attributes in the returned markup should match the default values for those attributes, as they do in the example, to ensure that Autosuggest functions as normal.
