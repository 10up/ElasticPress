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

## Instant Results

### Styling Instant Results

The default styles for Instant Results are as minimal as they can be to allow Instant Results to reflect the active theme's design and styles as much as possible while maintaining the expected layout. However you may still wish to add styles to your theme specifically for Instant Results. To help with styling Instant Results supports several [custom CSS properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties) that can be used to update certain recurring styles without needing to target multiple selectors. For other elements Instant Results uses [BEM syntax](https://css-tricks.com/bem-101/) to allow easier styling of recurring components.

#### Custom Properties

The following is a list of CSS custom properties you can set from your own theme to modify the appearance of Instant Results. This list may expand as more features and customization options are added to Instant Results.

- `--ep-search-background-color` Setting this property will set the background color of the Instant Results modal, as well as the colors of any elements that should match the background color, such as parts of the Price facet's slider.
- `--ep-search-alternate-background-color` This property is used for the background colour for elements that should appear offset from the regular background. This includes the post type labels for results, as well as the track for the Price facet's slider.
- `--ep-search-border-color` This property is used for any lines used as borders or separators such as the borders around facets.
- `--ep-search-range-thumb-size` This property controls the size of the 'thumbs' or 'handles' of the range slider used for the Price facet. By default this has different values for desktop and mobile so that on mobile they are more touch friendly. This is something you may want to replicate in your own theme.
- `--ep-search-range-track-size` This property controls the size of the track that the range slider handles travel along in the Price facet. By default this has different values for desktop and mobile so that on mobile it is more touch friendly. This also is something you may want to replicate in your own theme.

The values for custom properties can be set within a stylesheet by declaring them as you would any other CSS property:

```
:root {
	--ep-search-border-color: #1a1a1a;
}
```

All other colours used in Instant Results are inherited from the default styles of your theme.

#### Components

The HTML classes used by Instant Results adhere to the BEM syntax and therefore the Instant Results UI is made from an assortment of components or "blocks". The following is a list of the top-level **B**lock classes that represent the components that you will most likely want to style, as they are the most likely to be affected by your theme's default styles.

- `.ep-search-input` This is the main search input inside the Instant Results modal. By default it will use the theme's default styling for `<input>` elements but full-width and with an increased font size.
- `.ep-search-result` This is the component used for individual search results and comprises the image, post type label, title, excerpt and date. The default styles for the post title and excerpt will be inherited from the theme's default styles for `<h2>` and `<p>` elements respectively.
- `.ep-search-panel` This is the component used to contain each facet. It comprises a heading with a button for expanding and collapsing the facet, and the collapsible content of the panel which contains the facet itself. By default panels have a border whose color is controlled by a custom CSS property outlined above, and the button should inherit the theme's default styles for `<h3>` elements.
  - Note that for WooCommerce Product results the price and star ratings use the default WooCommerce markup, rather than Instant Results' own markup, so their styles will be inherited from the theme's WooCommerce styles, if they exist, or WooCommerce's own styles.
- `.ep-search-small-button` This is the component used for the 'chips' for active filters and the clear filters button. By default it will use the theme's default styling for `<button>` elements but with a small font size and little padding.
- `.ep-search-sidebar-toggle` This is the component used for the button that appears on mobile for displaying the list of filters. By default it will use the theme's default styling for `<button>` elements but full width.
- `.ep-search-pagination-button` This is the component used for the Next and Previous page buttons. By default it will use the theme's default styling for `<button>` elements.

All other components that can be styled, such as those used for layout, can be found by inspecting the markup of Instant Results with a browser's developer tools. New components may be added as more features and customization options are added to Instant Results.
