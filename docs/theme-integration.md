## Autosuggest

### Connecting Autosuggest to Your Theme's Search Bar

When enabled, ElasticPress Autosuggest will automatically add itself to any `input[type="search"]` elments on the page, as well as any elements with the `.ep-autosuggest` or  `.search-field` classes. You can add Autosuggest to additional elements yourself by adding [selectors](https://developer.mozilla.org/en-US/docs/Learn/CSS/Building_blocks/Selectors) as a comma-separated list to the _Autosuggest Selector_ setting under _ElasticPress > Features > Autosuggest > Settings_.

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

The HTML for individual Autosuggest suggestions are filtered by the `ep.Autosuggest.itemHTML` [JavaScript hook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/). You can add or remove fields on suggestions by adding a filter to this hook from your theme (or a plugin, if appropriate).

Filters for the `ep.Autosuggest.itemHTML` hook receive the original HTML as a string and 3 additional values related to the suggestion as parameters:

| Name         | Type   | Description
| -------------|--------|------------------------------------------------
| `itemHTML`   | string | The suggestion HTML as a string.
| `option`     | object | The Elasticsearch record for the suggestion.
| `index`      | number | The index of the suggestion in the results set.
| `searchText` | string | The search term.

Filters must return HTML for the suggestion as a string.

This example uses the hook to add the post date to the suggestion:

```
const autosuggestItemHTML = (itemHTML, option, index, searchText) => {
	const text = option._source.post_title;
	const url = option._source.permalink;
	const postDate = new Date(option._source.post_date).toLocaleString('en', { dateStyle: 'medium' })

	return `<li class="autosuggest-item" role="option" aria-selected="false" id="autosuggest-option-${index}">
		<a href="${url}" class="autosuggest-link" data-url="${url}" tabindex="-1">
			${text} (${postDate})
		</a>
	</li>`;
};

wp.hooks.addFilter('ep.Autosuggest.itemHTML', 'myTheme/autosuggestItemHTML', autosuggestItemHTML);
```

Note that the `class`, `id`, `role`, `aria-selected`, `data-url`, and `tabindex` attributes in the returned markup must match the default values for those attributes, as they do in the example, to ensure that Autosuggest functions as normal.

### Customize Suggestions List Markup

The HTML for the entire list of suggestions is filtered by the `ep.Autosuggest.listHTML` [JavaScript hook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/). You can append or prepend items to the suggestions list, or otherwise make edits to the entire list (rather than individual suggestions), by adding a filter to this hook from your theme (or a plugin, if appropriate).

Filters for the `ep.Autosuggest.listHTML` hook receive the original HTML as a string and 2 additional values related to the suggestion as parameters:

| Name         | Type    | Description
| -------------|---------|-----------------------------------------------
| `listHTML`   | string  | The list HTML as a string.
| `options`    | array   | The Elasticsearch records for the suggestions.
| `input`      | Element | The input element that triggered Autosuggest.

Filters must return HTML for the suggestion list as a string.

This example uses the hook to add a "View All Results" link to the bottom of the suggestions list.

```
const autosuggestListHTMLFilter = (listHTML, options, input) => {
	const allUrl = new URL(input.form.action);
	const formData = new FormData(input.form);
	const urlParams = new URLSearchParams(formData);

	allUrl.search = urlParams.toString();

	const url = allUrl.toString();

	listHTML += `<li class="autosuggest-item" role="option" aria-selected="false" id="autosuggest-option-all">
		<a href="${url}" class="autosuggest-link" data-url="${url}" tabindex="-1">
			View All Results
		</a>
	</li>`;

	return listHTML;
};

wp.hooks.addFilter('ep.Autosuggest.listHTML', 'myTheme/autosuggestListHTMLFilter', autosuggestListHTMLFilter);
```

Note that the `class`, `role`, `aria-selected`, and `tabindex` attributes in any new items must match the default values for those attributes, as they do in the example, to ensure that Autosuggest functions as normal. Items must also contain a link with the `href` and `data-url` attributes set to the URL that the item should link to.

### Customize the Suggestions Container

When ElasticPress inserts the markup for Autosuggest into the search form the element to be added is filtered by the  `ep.Autosuggest.element` [JavaScript hook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/). You can modify the markup of the Autosuggest container by adding a filter to this hook from your theme (or a plugin, if appropriate).

Filters for the `ep.Autosuggest.element` hook receive the DOM element that will be inserted and the element it will be inserted after as parameters:

| Name                   | Type    | Description
| -----------------------|---------|-----------------------------------------------
| `element`              | Element | The autosuggest container element.
| `inpreviousElementput` | Element | The element the container will be inserted after.

Filters must return a DOM element for the Autosuggest container.

This example uses the hook to add a "Powered by ElasticPress" message to the Autosuggest dropdown.

```
const autosuggestElementFilter = (element, previousElementput) => {
	const poweredBy = document.createElement('div');

	poweredBy.textContent = 'Powered by ElasticPress';

	element.appendChild(poweredBy);

	return element;
};

wp.hooks.addFilter('ep.Autosuggest.element', 'myTheme/autosuggestElementFilter', autosuggestElementFilter);
```

### Customize the Autosuggest Query

To get suggestions for Autosuggest, ElasticPress sends an AJAX request containing an Elasticsearch query to your Autosuggest endpoint. Before this request is sent the Elasticsearch query is filtered by the `ep.Autosuggest.query` [JavaScript hook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/). You can modify the query by adding a filter to this hook from your theme (or a plugin, if appropriate).

Filters for the `ep.Autosuggest.query` hook receive the query as a JavaScript object and 2 additional values related to the request as parameters:

| Name         | Type    | Description
| -------------|---------|------------------------------------------------
| `query`      | object  | The Elasticsearch query as a JavaScript object.
| `searchText` | string  | The search term.
| `input`      | Element | The input element that triggered Autosuggest.

Filters must return an Elasticsearch query as an object.

This example uses the function to add the value of a `wp_dropdown_categories()` field as a filter to the search query:

```
const autosuggestQueryFilter = (query, searchText, input) => {
	const formData = new FormData(input.form);
	const category = formData.get('cat');

	if (category) {
		query.post_filter.bool.must.push({
			term: {
				'terms.category.term_id': parseInt(category, 10),
			},
		});
	}

	return query;
};

wp.hooks.addFilter('ep.Autosuggest.query', 'myTheme/autosuggestQueryFilter', autosuggestQueryFilter);
```
## Instant Results

### Customize the Template Used for Results

When ElasticPress Instant Results renders search results it does so using a [React component](https://reactjs.org/docs/components-and-props.html). You can replace this component with your own from within a theme or plugin using the `ep.InstantResults.Result` [JavaScript hook](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-hooks/).

The result component receives the following props that your component can use to render the result:

| prop            | type   | description
| --------------- | ------ | -----------------------------------------------
| `averageRating` | number | Average review rating for WooCommerce products.
| `date`          | string | Localized date.
| `hit`           | object | Full result from Elasticsearch.
| `excerpt`       | string | Highlighted excerpt.
| `id`            | string | Post ID.
| `priceHtml`     | string | Price HTML for a WooCommerce product.
| `thumbnail`     | object | Thumbnail image attributes.
| `title`         | string | Highlighted title.
| `type`          | string | Post type label.
| `url`           | string | Post permalink.

This example replaces the result component with a component that renders results as just a simple linked title and date in a div:

**JSX**
```js
const CustomResult = ({ date, title, url }) => {
	return (
		<div className="my-custom-result">
			<strong><a href={url}>{title}</a></strong> {date}
		</div>
	)
};

wp.hooks.addFilter('ep.InstantResults.Result', 'myTheme/customResult', () => CustomResult);
```
**Plain**
```js
const el = wp.element.createElement;

const CustomResult = ({ date, title, url }) => {
	return el(
		'div',
		{
			className: 'my-custom-result',
		},
		el(
			'strong',
			{},
			el(
				'a',
				{ href: url },
				title
			),
		),
		' ',
		date
	);
};

wp.hooks.addFilter('ep.InstantResults.Result', 'myTheme/customResult', () => CustomResult);
```

To conditionally replace the component based on each result you can pass a simple component that checks the result before either rendering the original component or a new custom component. This example renders the custom component from above but only for results with the `post` post type:

**JSX**
```js
wp.hooks.addFilter('ep.InstantResults.Result', 'myTheme/customResultForPosts', (Result) => {
	return (props) => {
		if (props.hit._source.post_type === 'post') {
			return <CustomResult {...props} />;
		}

		return <Result {...props} />;
	};
});
```
**Plain**
```js
const el = wp.element.createElement;

wp.hooks.addFilter('ep.InstantResults.Result', 'myTheme/customResultForPosts', (Result) => {
	return (props) => {
		if (props.hit._source.post_type === 'post') {
			return el(CustomResult, props);
		}

		return el(Result, props);
	};
});
```

By returning a new component that wraps the original component you can customize the props that are passed to it. This example uses this approach to remove the post type label from results with the `page` post type:

**JSX**
```js
wp.hooks.addFilter('ep.InstantResults.Result', 'myTheme/noTypeLabelsForPages', (Result) => {
	return (props) => {
		if (props.hit._source.post_type === 'page') {
			return <Result {...props} type={null} />;
		}

		return <Result {...props} />;
	};
});
```
**Plain**
```js
const el = wp.element.createElement;

wp.hooks.addFilter('ep.InstantResults.Result', 'myTheme/noTypeLabelsForPages', (Result) => {
	return (props) => {
		if (props.hit._source.post_type === 'page') {
			return el(Result, {...props, type: null});
		}

		return el(Result, props);
	};
});
```

**Notes:**
 - To take advantage of JavaScript hooks, make sure to set `wp-hooks` as a [dependency](https://developer.wordpress.org/reference/functions/wp_enqueue_script/#parameters) of your script.
 - These examples use [JSX](https://reactjs.org/docs/introducing-jsx.html) to render for readability. Using JSX will require a build tool such as [@wordpress/scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/) to compile it into a format that can be understood by the browser. To create a component without a build process you will need to use the more verbose `createElement` method of [@wordpress/element](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-element/).

### Styling Instant Results

The default styles for Instant Results are as minimal as they can be to allow Instant Results to reflect the active theme's design and styles as much as possible while maintaining the expected layout. However, you may still wish to add styles to your theme specifically for Instant Results. To help with styling, Instant Results supports several [custom CSS properties](https://developer.mozilla.org/en-US/docs/Web/CSS/Using_CSS_custom_properties) that can be used to update certain recurring styles without needing to target multiple selectors. For other elements Instant Results uses [BEM syntax](https://css-tricks.com/bem-101/) to allow easier styling of recurring components.

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

### Open Instant Results on Button Press

By default the Instant Results modal will only be opened by submitting a WordPress search form, but you can manually open Instant Results from your theme or plugin by calling the `window.epInstantResults.openModal()` function. This allows you to open Instant Results in response to other events, such as clicking a button. Additionally, by passing certain parameters to the function you can set the initial search term, sorting and filters.

The following parameters are supported by passing them to the `openModal()` function in an object:

| Parameter                | Type                        | Description
| ------------------------ | --------------------------- | ---------------------------------------------------------------------
| search                   | string                      | Search term. Default `''`.
| per_page                 | integer                     | Maximum number of items to be returned in result set.
| tax-{$taxonomy}          | number[]                    | Filter results by taxonomy terms. Array of term IDs.
| order                    | `asc\|desc`                 | Order results in ascending or descending order. Default `desc`.
| orderby                  | `date\|relevance\|price`    | Order results by attribute. Default `relevance`.
| post_type                | string                      | Filter results by post type. Comma separated list of post type slugs.

This example uses the function to open the modal with a search for a specific search term when a button is clicked:

```html
<button id="search-blocks" type="button">Search for "Blocks"</button>

<script>
document.getElementById('search-blocks').addEventListener('click', () => {
	window.epInstantResults.openModal({ search: "blocks" });
});
</script>
```
